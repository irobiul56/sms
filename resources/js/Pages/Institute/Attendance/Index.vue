<script setup>
import InstituteLayout from '@/Layouts/InstituteLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    attendance: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    academicYear: {
        type: Object,
        default: null,
    },
    classes: {
        type: Array,
        default: () => [],
    },
    sections: {
        type: Array,
        default: () => [],
    },
    teachers: {
        type: Array,
        default: () => [],
    },
    currentTeacherId: {
        type: Number,
        default: null,
    },
    manageableClassIds: {
        type: Array,
        default: () => [],
    },
    isTeacher: {
        type: Boolean,
        default: false,
    },
});

const localDate = () => {
    const now = new Date();
    const offset = now.getTimezoneOffset() * 60000;
    return new Date(now.getTime() - offset).toISOString().slice(0, 10);
};

const form = useForm({
    class_id: '',
    section_id: '',
    date: localDate(),
    teacher_id: props.currentTeacherId ?? '',
    reviewed_unmatched: false,
    records: [],
});

const students = ref([]);
const rosterLoading = ref(false);
const rosterError = ref('');
const video = ref(null);
const cameraStream = ref(null);
const cameraError = ref('');
const scanError = ref('');
const scanning = ref(false);
const scanSummary = ref('');
const faceMatchIds = ref(new Set());
const modelLoading = ref(false);
const modelsReady = ref(false);
const cameraAspectRatio = ref('3 / 4');
let rosterRequest = 0;
let faceApiPromise;
const referenceDescriptorCache = new Map();

const availableSections = computed(() => props.sections.filter(
    (section) => String(section.institute_class_id) === String(form.class_id),
));
const manageableClasses = computed(() => props.classes.filter(
    (item) => props.manageableClassIds.some((id) => String(id) === String(item.id)),
));
const selectedClass = computed(() => props.classes.find(
    (item) => String(item.id) === String(form.class_id),
));
const presentCount = computed(() => form.records.filter((record) => record.status === 'present').length);
const absentCount = computed(() => form.records.filter((record) => record.status === 'absent').length);
const leaveCount = computed(() => form.records.filter((record) => record.status === 'leave').length);
const studentStatus = (studentId) => form.records.find((record) => record.student_id === studentId)?.status ?? 'absent';

function updateStatus(studentId, status) {
    const record = form.records.find((entry) => entry.student_id === studentId);
    if (record) {
        record.status = status;
        form.reviewed_unmatched = false;
    }
}

async function loadRoster() {
    const requestId = ++rosterRequest;
    rosterLoading.value = false;
    students.value = [];
    form.records = [];
    form.reviewed_unmatched = false;
    faceMatchIds.value = new Set();
    referenceDescriptorCache.clear();
    scanSummary.value = '';
    rosterError.value = '';

    if (!form.class_id || !form.section_id || !form.date || !props.academicYear) {
        return;
    }

    rosterLoading.value = true;

    try {
        const response = await axios.get(route('attendance.roster'), {
            params: {
                class_id: form.class_id,
                section_id: form.section_id,
                date: form.date,
            },
        });

        if (requestId !== rosterRequest) {
            return;
        }

        students.value = response.data.data;
        form.records = students.value.map((student) => ({
            student_id: student.id,
            status: ['present', 'leave'].includes(student.status) ? student.status : 'absent',
        }));
    } catch (error) {
        if (requestId === rosterRequest) {
            rosterError.value = error.response?.data?.message
                ?? 'The student list could not be loaded. Please try again.';
        }
    } finally {
        if (requestId === rosterRequest) {
            rosterLoading.value = false;
        }
    }
}

watch(() => form.class_id, () => {
    form.section_id = '';
    loadRoster();
});
watch(() => [form.section_id, form.date], loadRoster);

async function startCamera() {
    cameraError.value = '';

    if (!navigator.mediaDevices?.getUserMedia) {
        cameraError.value = 'This browser cannot access a camera. Use a current browser over HTTPS or localhost.';
        return;
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: { facingMode: 'environment' },
        });
        cameraStream.value = stream;

        if (video.value) {
            video.value.srcObject = stream;
            await video.value.play();
            updateCameraAspectRatio();
        }
        void warmFaceApi();
    } catch (error) {
        stopCamera();
        cameraError.value = error.name === 'NotAllowedError'
            ? 'Camera permission was denied. Allow camera access in your browser and try again.'
            : 'The camera could not be opened. Check that it is connected and not being used by another app.';
    }
}

function stopCamera() {
    cameraStream.value?.getTracks().forEach((track) => track.stop());
    cameraStream.value = null;
    cameraAspectRatio.value = '3 / 4';
    if (video.value) {
        video.value.srcObject = null;
    }
}

function updateCameraAspectRatio() {
    if (video.value?.videoWidth && video.value?.videoHeight) {
        cameraAspectRatio.value = `${video.value.videoWidth} / ${video.value.videoHeight}`;
    }
}

async function getFaceApi() {
    if (!faceApiPromise) {
        faceApiPromise = import('@vladmandic/face-api').then(async (module) => {
            const faceapi = module.default ?? module;
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri('/models'),
                faceapi.nets.faceLandmark68Net.loadFromUri('/models'),
                faceapi.nets.faceRecognitionNet.loadFromUri('/models'),
            ]);
            return faceapi;
        });
    }

    return faceApiPromise;
}

async function warmFaceApi() {
    if (modelsReady.value || modelLoading.value) {
        return;
    }

    modelLoading.value = true;
    try {
        await getFaceApi();
        modelsReady.value = true;
    } catch (error) {
        faceApiPromise = undefined;
        scanError.value = error.message || 'The recognition model could not be loaded.';
    } finally {
        modelLoading.value = false;
    }
}

function loadImage(url) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('A student photo could not be loaded.'));
        image.src = url;
    });
}

async function getReferenceDescriptor(student, faceapi, detectorOptions) {
    if (!referenceDescriptorCache.has(student.photo)) {
        const descriptorPromise = loadImage(student.photo).then(async (image) => {
            const result = await faceapi
                .detectSingleFace(image, detectorOptions)
                .withFaceLandmarks()
                .withFaceDescriptor();

            return result?.descriptor ?? null;
        });
        referenceDescriptorCache.set(student.photo, descriptorPromise);
    }

    try {
        return await referenceDescriptorCache.get(student.photo);
    } catch (error) {
        referenceDescriptorCache.delete(student.photo);
        throw error;
    }
}

function distanceBetween(first, second) {
    let sum = 0;
    for (let index = 0; index < first.length; index += 1) {
        const difference = first[index] - second[index];
        sum += difference * difference;
    }
    return Math.sqrt(sum);
}

async function scanFaces() {
    scanError.value = '';
    scanSummary.value = '';
    faceMatchIds.value = new Set();

    if (!cameraStream.value || !video.value) {
        scanError.value = 'Open the camera before scanning.';
        return;
    }
    if (!students.value.length) {
        scanError.value = 'Load a class roster before scanning.';
        return;
    }

    scanning.value = true;
    modelLoading.value = !modelsReady.value;

    try {
        const faceapi = await getFaceApi();
        modelsReady.value = true;
        modelLoading.value = false;

        const detectorOptions = new faceapi.TinyFaceDetectorOptions({
            inputSize: 416,
            scoreThreshold: 0.45,
        });
        const referenceDescriptors = [];
        const unusablePhotoNames = [];

        for (let index = 0; index < students.value.length; index += 4) {
            const batch = students.value.slice(index, index + 4);
            const results = await Promise.all(batch.map(async (student) => {
                if (!student.photo) {
                    return { student, descriptor: null };
                }

                try {
                    return {
                        student,
                        descriptor: await getReferenceDescriptor(student, faceapi, detectorOptions),
                    };
                } catch (error) {
                    return { student, error };
                }
            }));

            for (const { student, descriptor, error } of results) {
                if (descriptor) {
                    referenceDescriptors.push({ studentId: student.id, descriptor });
                } else if (error) {
                    unusablePhotoNames.push(`${student.name} (${error.message})`);
                } else {
                    unusablePhotoNames.push(student.name);
                }
            }
        }

        if (!referenceDescriptors.length) {
            throw new Error('No usable face photos were found. Add clear, front-facing student photos or mark attendance manually.');
        }

        const faces = await faceapi
            .detectAllFaces(video.value, detectorOptions)
            .withFaceLandmarks()
            .withFaceDescriptors();

        const candidates = [];
        for (let faceIndex = 0; faceIndex < faces.length; faceIndex += 1) {
            const distances = referenceDescriptors
                .map((reference) => ({
                    studentId: reference.studentId,
                    distance: distanceBetween(faces[faceIndex].descriptor, reference.descriptor),
                }))
                .sort((first, second) => first.distance - second.distance);
            const nearest = distances[0];
            const runnerUp = distances[1];

            if (nearest && nearest.distance < 0.5
                && (!runnerUp || runnerUp.distance - nearest.distance >= 0.04)) {
                candidates.push({ faceIndex, ...nearest });
            }
        }

        candidates.sort((first, second) => first.distance - second.distance);
        const matchedFaces = new Set();
        const matchedStudents = new Set();

        for (const candidate of candidates) {
            if (matchedFaces.has(candidate.faceIndex) || matchedStudents.has(candidate.studentId)) {
                continue;
            }
            matchedFaces.add(candidate.faceIndex);
            matchedStudents.add(candidate.studentId);
        }

        const recognizedIds = new Set(matchedStudents);
        for (const record of form.records) {
            record.status = recognizedIds.has(record.student_id) ? 'present' : 'absent';
        }
        faceMatchIds.value = recognizedIds;
        form.reviewed_unmatched = false;
        const unusablePhotoSummary = unusablePhotoNames.length
            ? ` Photos needing manual review: ${unusablePhotoNames.join(', ')}.`
            : '';
        scanSummary.value = `${recognizedIds.size} student(s) matched; ${faces.length - matchedFaces.size} face(s) need manual review.${unusablePhotoSummary}`;
    } catch (error) {
        if (!modelsReady.value) {
            faceApiPromise = undefined;
        }
        scanError.value = error.message || 'Face scanning failed. Please try again or mark attendance manually.';
    } finally {
        scanning.value = false;
        modelLoading.value = false;
    }
}

function saveAttendance() {
    form.post(route('attendance.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reviewed_unmatched = false;
            loadRoster();
        },
    });
}

function openAttendanceReport() {
    const url = route('attendance.report', {
        from_date: form.date,
        to_date: form.date,
        class_id: form.class_id || undefined,
        section_id: form.section_id || undefined,
    });
    window.open(url, '_blank', 'noopener');
}

onBeforeUnmount(stopCamera);
</script>

<template>
    <InstituteLayout>
        <Head title="Attendance" />
        <main class="min-w-0 w-full flex-1 p-3 sm:p-6">
            <section v-if="manageableClasses.length" class="mb-8 min-w-0 rounded-xl border border-gray-200 bg-white p-3 shadow-sm sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900">Take attendance</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Face matching runs in this browser. Camera frames and face descriptors are not uploaded or saved.
                </p>

                <div v-if="!academicYear" class="mt-4 rounded-lg bg-amber-50 p-4 text-sm text-amber-900">
                    Select a current academic year before taking attendance.
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <label class="block text-sm font-medium text-gray-700">
                        Class
                        <select v-model="form.class_id" @change="form.section_id = ''" :disabled="!academicYear" class="mt-1 w-full rounded-lg border-gray-300">
                            <option value="">Select class</option>
                            <option v-for="item in manageableClasses" :key="item.id" :value="item.id">{{ item.name }}{{ item.group ? ` — ${item.group}` : '' }}</option>
                        </select>
                        <span v-if="form.errors.class_id" class="mt-1 block text-xs text-red-600">{{ form.errors.class_id }}</span>
                    </label>

                    <label class="block text-sm font-medium text-gray-700">
                        Section
                        <select v-model="form.section_id" :disabled="!form.class_id" class="mt-1 w-full rounded-lg border-gray-300">
                            <option value="">Select section</option>
                            <option v-for="item in availableSections" :key="item.id" :value="item.id">{{ item.name }}</option>
                        </select>
                        <span v-if="form.errors.section_id" class="mt-1 block text-xs text-red-600">{{ form.errors.section_id }}</span>
                    </label>

                    <label class="block text-sm font-medium text-gray-700">
                        Date
                        <input v-model="form.date" type="date" :max="localDate()" class="mt-1 w-full rounded-lg border-gray-300">
                        <span v-if="form.errors.date" class="mt-1 block text-xs text-red-600">{{ form.errors.date }}</span>
                    </label>

                    <label class="block text-sm font-medium text-gray-700">
                        Marked by
                        <select v-model="form.teacher_id" class="mt-1 w-full rounded-lg border-gray-300">
                            <option value="">Select teacher</option>
                            <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">
                                {{ teacher.name }}{{ teacher.employee_id ? ` (${teacher.employee_id})` : '' }}
                            </option>
                        </select>
                        <span v-if="form.errors.teacher_id" class="mt-1 block text-xs text-red-600">{{ form.errors.teacher_id }}</span>
                    </label>
                </div>

                <div class="mt-5 grid min-w-0 gap-4 sm:gap-5 lg:grid-cols-2">
                    <div :class="cameraStream
                        ? 'fixed inset-0 z-[100] flex flex-col overflow-hidden bg-black p-0 sm:relative sm:inset-auto sm:z-auto sm:block sm:rounded-lg sm:p-2'
                        : 'min-w-0 rounded-lg bg-gray-950 p-2'">
                        <div :class="cameraStream ? 'relative min-h-0 flex-1 overflow-hidden sm:flex-none' : 'relative'">
                            <video ref="video" :style="cameraStream ? undefined : { aspectRatio: cameraAspectRatio }"
                                :class="cameraStream
                                    ? 'absolute inset-0 h-full w-full bg-black object-cover sm:static sm:max-h-[65svh] sm:w-full sm:rounded-md sm:object-contain sm:align-middle'
                                    : 'max-h-[65svh] w-full rounded-md bg-black object-contain sm:max-h-none'"
                                autoplay muted playsinline @loadedmetadata="updateCameraAspectRatio" />
                            <div v-if="cameraStream"
                                class="absolute inset-x-0 top-0 z-10 px-4 pt-5 sm:px-3 sm:pt-3"
                                :style="{ paddingTop: 'max(1.25rem, env(safe-area-inset-top))' }">
                                <div class="rounded-2xl border border-white/15 bg-slate-950/75 p-4 text-white shadow-xl backdrop-blur-md sm:max-w-lg">
                                    <div class="flex items-start gap-3">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-500/25 text-lg" aria-hidden="true">
                                            <i class="fa-solid fa-camera"></i>
                                        </span>
                                        <div>
                                            <p class="text-base font-semibold leading-6" style="font-family: 'Hind Siliguri', 'Noto Sans Bengali', sans-serif">
                                                শিক্ষার্থীদের ক্যামেরার সামনে দাঁড়াতে বলুন
                                            </p>
                                            <p class="mt-1 text-sm leading-5 text-slate-200" style="font-family: 'Hind Siliguri', 'Noto Sans Bengali', sans-serif">
                                                আলো ভালো রাখুন, মুখ স্পষ্ট ও ক্যামেরার দিকে রাখুন। স্ক্যান শেষে হাজিরা যাচাই করে সংরক্ষণ করুন।
                                            </p>
                                        </div>
                                    </div>
                                    <p v-if="scanSummary" class="mt-3 rounded-lg bg-blue-500/20 px-3 py-2 text-sm leading-5 text-blue-50">
                                        {{ scanSummary }}
                                    </p>
                                    <p v-if="scanError" class="mt-3 rounded-lg bg-red-500/20 px-3 py-2 text-sm leading-5 text-red-100">
                                        {{ scanError }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div :class="cameraStream
                            ? 'grid shrink-0 gap-2 bg-gradient-to-t from-black via-black/95 to-black/70 px-4 pb-5 pt-4 sm:flex sm:flex-wrap sm:bg-none sm:p-2'
                            : 'grid gap-2 p-2 sm:flex sm:flex-wrap'"
                            :style="cameraStream ? { paddingBottom: 'max(1.25rem, env(safe-area-inset-bottom))' } : undefined">
                            <button v-if="!cameraStream" type="button" @click="startCamera"
                                class="min-h-11 w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 sm:w-auto">
                                Open camera
                            </button>
                            <button v-else type="button" @click="stopCamera"
                                class="min-h-11 w-full rounded-lg bg-gray-700 px-4 py-2 text-sm font-medium text-white hover:bg-gray-600 sm:w-auto">
                                Stop camera
                            </button>
                            <button type="button" @click="scanFaces" :disabled="!cameraStream || scanning || modelLoading || rosterLoading"
                                class="min-h-12 w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white shadow-lg hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50 sm:min-h-11 sm:w-auto sm:rounded-lg sm:px-4 sm:py-2 sm:font-medium">
                                {{ modelLoading ? 'Loading recognition model…' : scanning ? 'Scanning…' : 'Scan and suggest attendance' }}
                            </button>
                            <p v-if="cameraStream" class="text-center text-xs text-slate-300 sm:hidden">
                                {{ selectedClass?.name ?? 'Class' }} · {{ students.length }} students
                            </p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            <strong>Teacher review required.</strong>
                            Recognition is only a suggestion; lighting, distance, occlusion, or poor photos can cause errors.
                            Check each student's status before saving. Students without a usable photo can be marked manually.
                        </div>
                        <p v-if="cameraError" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ cameraError }}</p>
                        <p v-if="scanError" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ scanError }}</p>
                        <p v-if="scanSummary" class="rounded-lg bg-blue-50 p-3 text-sm text-blue-800">{{ scanSummary }}</p>
                        <p v-if="rosterError" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ rosterError }}</p>
                        <p v-if="selectedClass && form.section_id && !rosterLoading" class="text-sm text-gray-600">
                            {{ selectedClass.name }} · {{ students.length }} active student(s) ·
                            {{ presentCount }} present · {{ absentCount }} absent · {{ leaveCount }} leave
                        </p>
                        <p class="text-xs text-gray-500">
                            Camera access requires HTTPS (or localhost). Use clear, front-facing profile photos and ensure students are visible.
                        </p>
                    </div>
                </div>

                <div v-if="rosterLoading" class="py-8 text-center text-sm text-gray-500">Loading students…</div>
                <div v-else-if="students.length" class="mt-6">
                    <div class="space-y-3 md:hidden">
                        <article v-for="student in students" :key="student.id" class="rounded-xl border border-gray-200 p-3 shadow-sm">
                            <div class="flex min-w-0 items-center gap-3">
                                <img v-if="student.photo" :src="student.photo" :alt="`${student.name} profile`"
                                    class="h-12 w-12 shrink-0 rounded-full object-cover">
                                <div v-else class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-amber-50 text-xs font-medium text-amber-800">
                                    No photo
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-medium text-gray-900">{{ student.name }}</div>
                                    <div class="truncate text-xs text-gray-500">Roll {{ student.roll_no }} · {{ student.student_id }}</div>
                                </div>
                                <span v-if="faceMatchIds.has(student.id)"
                                    class="shrink-0 rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800">Matched</span>
                                <span v-else
                                    class="shrink-0 rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700">Review</span>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <button type="button" :aria-pressed="studentStatus(student.id) === 'present'"
                                    @click="updateStatus(student.id, 'present')"
                                    :class="studentStatus(student.id) === 'present'
                                        ? 'border-green-700 bg-green-700 text-white'
                                        : 'border-green-200 bg-white text-green-800'"
                                    class="min-h-11 rounded-lg border px-3 py-2 text-sm font-semibold">
                                    Present
                                </button>
                                <button type="button" :aria-pressed="studentStatus(student.id) === 'absent'"
                                    @click="updateStatus(student.id, 'absent')"
                                    :class="studentStatus(student.id) === 'absent'
                                        ? 'border-red-700 bg-red-700 text-white'
                                        : 'border-red-200 bg-white text-red-800'"
                                    class="min-h-11 rounded-lg border px-3 py-2 text-sm font-semibold">
                                    Absent
                                </button>
                                <button type="button" :aria-pressed="studentStatus(student.id) === 'leave'"
                                    @click="updateStatus(student.id, 'leave')"
                                    :class="studentStatus(student.id) === 'leave'
                                        ? 'border-amber-700 bg-amber-600 text-white'
                                        : 'border-amber-200 bg-white text-amber-800'"
                                    class="col-span-2 min-h-11 rounded-lg border px-3 py-2 text-sm font-semibold">
                                    Leave
                                </button>
                            </div>
                        </article>
                    </div>

                    <div class="hidden overflow-x-auto rounded-lg border border-gray-200 md:block">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-600">
                            <tr>
                                <th class="px-4 py-3 font-medium">Student</th>
                                <th class="px-4 py-3 font-medium">Photo</th>
                                <th class="px-4 py-3 font-medium">Recognition</th>
                                <th class="px-4 py-3 font-medium">Attendance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="student in students" :key="student.id">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ student.name }}</div>
                                    <div class="text-xs text-gray-500">Roll {{ student.roll_no }} · {{ student.student_id }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <img v-if="student.photo" :src="student.photo" :alt="`${student.name} profile`"
                                        class="h-10 w-10 rounded-full object-cover">
                                    <span v-else class="text-xs text-amber-700">No photo</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="faceMatchIds.has(student.id)" class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-800">Matched</span>
                                    <span v-else class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">Not matched / not scanned</span>
                                </td>
                                <td class="px-4 py-3">
                                    <select :value="studentStatus(student.id)" @change="updateStatus(student.id, $event.target.value)"
                                        class="rounded-lg border-gray-300 text-sm">
                                        <option value="present">Present</option>
                                        <option value="absent">Absent</option>
                                        <option value="leave">Leave</option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                </div>
                <p v-else-if="form.section_id && !rosterLoading" class="mt-6 rounded-lg bg-gray-50 p-6 text-center text-sm text-gray-600">
                    No active students are enrolled in this class and section for the current academic year.
                </p>

                <div v-if="students.length" class="sticky bottom-0 z-10 -mx-3 -mb-3 mt-5 flex flex-col gap-3 border-t border-gray-200 bg-white/95 p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] shadow-[0_-8px_20px_-16px_rgba(0,0,0,0.4)] backdrop-blur sm:static sm:mx-0 sm:mb-0 sm:flex-row sm:items-center sm:justify-between sm:border-0 sm:bg-transparent sm:p-0 sm:pt-4 sm:shadow-none sm:backdrop-blur-none">
                    <label class="flex min-h-11 items-start gap-2 text-sm text-gray-700">
                        <input v-model="form.reviewed_unmatched" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 rounded border-gray-300 text-blue-600">
                        I reviewed the unmatched students and confirmed their attendance statuses.
                    </label>
                    <div class="w-full sm:w-auto">
                        <button type="button" @click="saveAttendance"
                            :disabled="form.processing || !form.teacher_id || !form.reviewed_unmatched"
                            class="min-h-11 w-full rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">
                            {{ form.processing ? 'Saving…' : 'Confirm and save attendance' }}
                        </button>
                        <p v-if="form.errors.reviewed_unmatched || form.errors.records" class="mt-1 text-xs text-red-600">
                            {{ form.errors.reviewed_unmatched || form.errors.records }}
                        </p>
                    </div>
                </div>
            </section>
            <p v-else-if="isTeacher" class="mb-8 rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-600 shadow-sm">
                You have view-only attendance access for your assigned classes.
            </p>

            <div class="mb-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-gray-900">Recent attendance</h2>
                    <button type="button" @click="openAttendanceReport"
                        class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        <i class="fa-solid fa-print" aria-hidden="true"></i>
                        Attendance report / Print
                    </button>
                </div>
            </div>
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="divide-y divide-gray-100 md:hidden">
                    <article v-for="record in attendance.data" :key="record.id" class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate font-medium text-gray-900">{{ record.student?.name ?? '—' }}</div>
                                <div class="text-xs text-gray-500">{{ record.student?.student_id ?? '' }}</div>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium capitalize"
                                :class="record.status === 'present' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                {{ record.status }}
                            </span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-600">
                            <span>{{ record.date }}</span>
                            <span>{{ record.institute_class?.name ?? '—' }} / {{ record.section?.name ?? '—' }}</span>
                            <span>{{ record.teacher?.name ?? '—' }}</span>
                        </div>
                        <p v-if="record.remarks" class="mt-2 break-words text-xs text-gray-600">{{ record.remarks }}</p>
                    </article>
                    <p v-if="!attendance.data?.length" class="px-4 py-10 text-center text-sm text-gray-500">
                        No attendance records have been added yet.
                    </p>
                </div>
                <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">Date</th>
                            <th class="px-4 py-3 font-medium">Student</th>
                            <th class="px-4 py-3 font-medium">Class / Section</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Marked by</th>
                            <th class="px-4 py-3 font-medium">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="record in attendance.data" :key="record.id">
                            <td class="px-4 py-3 text-gray-700">{{ record.date }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">{{ record.student?.name ?? '—' }}</div>
                                <div class="text-xs text-gray-500">{{ record.student?.student_id ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ record.institute_class?.name ?? '—' }} / {{ record.section?.name ?? '—' }}</td>
                            <td class="px-4 py-3 capitalize text-gray-700">{{ record.status }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ record.teacher?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ record.remarks || '—' }}</td>
                        </tr>
                        <tr v-if="!attendance.data?.length">
                            <td colspan="6" class="px-4 py-10 text-center text-gray-500">No attendance records have been added yet.</td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>

            <nav v-if="attendance.links?.length > 3" class="mt-4 flex flex-wrap gap-2" aria-label="Attendance pagination">
                <Link v-for="link in attendance.links" :key="`${link.label}-${link.url}`"
                    :href="link.url || '#'" :class="[
                        'rounded border px-3 py-1.5 text-sm',
                        link.active ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-gray-300 text-gray-700',
                        !link.url ? 'pointer-events-none opacity-50' : '',
                    ]" v-html="link.label" />
            </nav>
        </main>
    </InstituteLayout>
</template>
