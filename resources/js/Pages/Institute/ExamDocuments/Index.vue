<script setup>
import InstituteLayout from '@/Layouts/InstituteLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    classes: { type: Array, default: () => [] },
    exams: { type: Array, default: () => [] },
    selectedExam: { type: Object, default: null },
    seatPlanClasses: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
    students: { type: Array, default: () => [] },
    classStudentCount: { type: Number, default: 0 },
    classHasAllResults: { type: Boolean, default: false },
    classHasAllPublishedResults: { type: Boolean, default: false },
    documentPermissions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({ class_id: '', exam_id: '', section_id: '', roll_number: '', document_type: '' }) },
});

const showSeatPlanModal = ref(false);
const form = reactive({
    class_id: props.filters.class_id ?? '',
    exam_id: props.filters.exam_id ?? '',
    section_id: props.filters.section_id ?? '',
    roll_number: props.filters.roll_number ?? '',
    document_type: props.filters.document_type ?? '',
    seat_plan_class_ids: props.filters.class_id ? [String(props.filters.class_id)] : [],
    room_name: 'Main Exam Hall',
    seats_per_row: 5,
    rooms: [{ room_name: 'Room 1', bench_rows: 3, seats_per_bench: 10 }],
});

const filteredExams = computed(() => form.class_id
    ? props.exams.filter((exam) => exam.class_ids?.some((classId) => String(classId) === String(form.class_id)))
    : props.exams);
const filteredStudents = computed(() => props.students);
const availableSections = computed(() => props.sections.filter(
    section => !form.class_id || String(section.institute_class_id) === String(form.class_id)
));
const getClassLabel = (cls) => cls.group
    ? `${cls.name} (${cls.group_label || cls.group})`
    : cls.name;
const totalRoomCapacity = computed(() => form.rooms.reduce(
    (capacity, room) => capacity + (Number(room.bench_rows) || 0) * (Number(room.seats_per_bench) || 0),
    0,
));
const selectedSeatPlanClasses = computed(() => props.seatPlanClasses.filter(
    (classItem) => form.seat_plan_class_ids.includes(String(classItem.id)),
));
const seatPlanStudentCount = computed(() => selectedSeatPlanClasses.value.reduce(
    (total, classItem) => total + Number(classItem.student_count || 0),
    0,
));
const selectedExamIsCurrent = computed(() => props.selectedExam
    && String(props.selectedExam.id) === String(form.exam_id)
    && String(props.selectedExam.class_id) === String(form.class_id)
    && String(props.filters.section_id ?? '') === String(form.section_id)
    && String(props.filters.roll_number ?? '') === String(form.roll_number)
    && String(props.filters.document_type ?? '') === String(form.document_type));
const loadedListHasAllPublishedResults = computed(() => filteredStudents.value.length > 0
    && filteredStudents.value.every((student) => student.has_published_result));

watch(() => props.filters, (filters) => {
    form.class_id = filters.class_id ?? '';
    form.exam_id = filters.exam_id ?? '';
    form.section_id = filters.section_id ?? '';
    form.roll_number = filters.roll_number ?? '';
    form.document_type = filters.document_type ?? '';
    form.seat_plan_class_ids = filters.class_id ? [String(filters.class_id)] : [];
});

const filterRoster = () => {
    router.get(route('exam-documents.index'), {
        class_id: form.class_id,
        exam_id: form.exam_id,
        section_id: form.section_id,
        roll_number: form.roll_number,
        document_type: form.document_type,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const documentUrl = (name, student, enabled = true) => {
    if (!enabled) return '#';
    if (!form.exam_id || !student) return '#';
    return route(name, { exam: form.exam_id, student: student.id, class_id: form.class_id });
};

const classDocumentUrl = (name, enabled = true) => {
    if (!form.exam_id || !enabled) return '#';
    const routeParams = {
        exam: form.exam_id,
        class_id: form.class_id,
        section_id: form.section_id || undefined,
        roll_number: form.roll_number || undefined,
    };
    const url = new URL(route(name, routeParams), window.location.origin);
    if (name === 'exam-documents.seat-plan') {
        form.seat_plan_class_ids.forEach((classId, index) => {
            url.searchParams.set(`class_ids[${index}]`, classId);
        });
        form.rooms.forEach((room, index) => {
            url.searchParams.set(`rooms[${index}][room_name]`, room.room_name);
            url.searchParams.set(`rooms[${index}][bench_rows]`, room.bench_rows);
            url.searchParams.set(`rooms[${index}][seats_per_bench]`, room.seats_per_bench);
        });
    }

    return url.toString();
};

const individualSeatPlanUrl = (student) => route('exam-documents.seat-plan', {
    exam: form.exam_id,
    class_id: form.class_id,
    student_id: student.id,
    section_id: student.section_id || undefined,
    room_name: form.room_name,
    seats_per_row: form.seats_per_row,
});

const printAllSeatPlans = () => {
    if (form.rooms.length < 1
        || !form.seat_plan_class_ids.length
        || form.rooms.some((room) => !room.room_name.trim() || room.bench_rows < 1 || room.seats_per_bench < 1)
        || totalRoomCapacity.value < seatPlanStudentCount.value) return;

    window.open(
        classDocumentUrl('exam-documents.seat-plan', filteredStudents.value.length > 0),
        '_blank',
        'noopener,noreferrer',
    );
    showSeatPlanModal.value = false;
};

const addRoom = () => {
    form.rooms.push({
        room_name: `Room ${form.rooms.length + 1}`,
        bench_rows: 3,
        seats_per_bench: 10,
    });
};

const removeRoom = (index) => {
    if (form.rooms.length > 1) form.rooms.splice(index, 1);
};
</script>

<template>
    <InstituteLayout>
        <Head title="Exam Documents" />
        <main class="min-w-0 flex-1 p-4 sm:p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-gray-900">Exam Documents</h1>
                <p class="mt-1 text-sm text-gray-600">Print individual or class-wide documents, including a room-door seating list and cut-out bench labels.</p>
            </div>

            <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
                <form @submit.prevent="filterRoster" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    <label class="text-sm font-medium text-gray-700">
                        Class
                        <select v-model="form.class_id" required
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm"
                        @change="form.exam_id = ''; form.section_id = ''; form.seat_plan_class_ids = form.class_id ? [String(form.class_id)] : []">
                            <option value="">Select class</option>
                            <option v-for="classItem in classes" :key="classItem.id" :value="String(classItem.id)">
                                {{ getClassLabel(classItem) }}
                            </option>
                        </select>
                    </label>
                    <label class="text-sm font-medium text-gray-700">
                        Exam
                        <select v-model="form.exam_id" required
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm"
                            :disabled="!form.class_id"
                            @change="form.section_id = ''; form.seat_plan_class_ids = form.class_id ? [String(form.class_id)] : []">
                            <option value="">Select exam</option>
                            <option v-for="exam in filteredExams" :key="exam.id" :value="String(exam.id)">
                                {{ exam.name }}{{ exam.academic_year }}
                            </option>
                        </select>
                    </label>
                    <label class="text-sm font-medium text-gray-700">
                        Section
                        <select v-model="form.section_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm"
                            :disabled="!form.exam_id">
                            <option value="">All sections</option>
                            <option v-for="section in availableSections" :key="section.id" :value="String(section.id)">
                                {{ section.name }}
                            </option>
                        </select>
                    </label>
                    <label class="text-sm font-medium text-gray-700">
                        Search by roll
                        <input v-model="form.roll_number" type="search" inputmode="numeric" placeholder="Enter roll number"
                            :disabled="!form.exam_id"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="text-sm font-medium text-gray-700">
                        Document type
                        <select v-model="form.document_type" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            <option value="">All documents</option>
                            <option value="marksheet">Marksheet</option>
                            <option value="admit-card">Admit card</option>
                            <option value="seat-plan">Seat plan</option>
                        </select>
                    </label>
                    <div class="flex items-end">
                        <button type="submit" :disabled="!form.exam_id"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                            Load students
                        </button>
                    </div>
                </form>

                <section v-if="selectedExamIsCurrent" class="mt-6 border-t border-gray-100 pt-6">
                    <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Students</h2>
                            <p class="text-sm text-gray-500">{{ students.length }} students loaded; all-print actions use the selected filters.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a v-if="documentPermissions['admit_cards.print']" :href="classDocumentUrl('exam-documents.class-admit-cards', students.length > 0)"
                                target="_blank" rel="noopener"
                                :class="{ 'pointer-events-none opacity-50': !students.length }"
                                class="rounded-lg bg-indigo-700 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-800">
                                All Admit Print
                            </a>
                            <button v-if="documentPermissions['seat_plan.print']" type="button" @click="showSeatPlanModal = true"
                                :disabled="!students.length"
                                :class="{ 'pointer-events-none opacity-50': !students.length }"
                                class="rounded-lg bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-900">
                                All Seat Plan Print
                            </button>
                            <a v-if="documentPermissions['marksheets.print']" :href="classDocumentUrl('exam-documents.class-marksheets', loadedListHasAllPublishedResults)"
                                target="_blank" rel="noopener"
                                :class="{ 'pointer-events-none opacity-50': !loadedListHasAllPublishedResults }"
                                class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800">
                                All Marksheet Print
                            </a>
                            <a v-if="documentPermissions['tabulation_sheet.print']" :href="classDocumentUrl('exam-documents.class-tabulation-sheet', students.length > 0)"
                                target="_blank" rel="noopener"
                                :class="{ 'pointer-events-none opacity-50': !students.length }"
                                class="rounded-lg bg-violet-700 px-3 py-2 text-sm font-medium text-white hover:bg-violet-800">
                                All Tabulation Sheet
                            </a>
                        </div>
                    </div>
                    <p v-if="!loadedListHasAllPublishedResults" class="mb-3 text-xs text-amber-700">
                        All Marksheet Print will be available after results are published for every student in the loaded list.
                    </p>
                    <div class="mt-5 overflow-x-auto rounded-lg border border-gray-200">
                        <table class="w-full min-w-[760px] divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Roll</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Student</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Student ID</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Section</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Result</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-for="student in filteredStudents" :key="student.id">
                                    <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">{{ student.roll_number }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ student.name }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ student.student_id }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ student.section_name || '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span v-if="student.has_published_result" class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">Published</span>
                                        <span v-else-if="student.has_result" class="rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-700">Unpublished</span>
                                        <span v-else class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600">Not generated</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <a v-if="documentPermissions['admit_cards.print'] && (!form.document_type || form.document_type === 'admit-card')"
                                                :href="documentUrl('exam-documents.admit-card', student)"
                                                target="_blank" rel="noopener"
                                                class="rounded border border-indigo-200 px-2 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-50">
                                                Individual Admit
                                            </a>
                                            <a v-if="documentPermissions['seat_plan.print'] && (!form.document_type || form.document_type === 'seat-plan')"
                                                :href="individualSeatPlanUrl(student)"
                                                target="_blank" rel="noopener"
                                                class="rounded border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                                Individual Seat Plan
                                            </a>
                                            <a v-if="documentPermissions['marksheets.print'] && (!form.document_type || form.document_type === 'marksheet')"
                                                :href="student.has_published_result ? route('exam-documents.marksheet', { exam: form.exam_id, student: student.id, class_id: form.class_id }) : '#'"
                                                :aria-disabled="!student.has_published_result"
                                                target="_blank" rel="noopener"
                                                :class="{ 'pointer-events-none opacity-40': !student.has_published_result }"
                                                class="rounded border border-emerald-200 px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-50">
                                                Individual Marksheet
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!filteredStudents.length">
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">No students match the selected filters.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <p v-else class="mt-6 text-sm text-amber-700">প্রথমে একটি exam নির্বাচন করুন।</p>
            </section>

            <div v-if="showSeatPlanModal" class="fixed inset-0 z-[70] flex items-center justify-center bg-gray-900/50 p-4"
                @click.self="showSeatPlanModal = false">
                <section role="dialog" aria-modal="true" aria-labelledby="seat-plan-modal-title"
                    class="w-full max-w-2xl rounded-xl bg-white p-5 shadow-2xl sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 id="seat-plan-modal-title" class="text-lg font-semibold text-gray-900">রুমভিত্তিক সিট প্ল্যান</h2>
                            <p class="mt-1 text-sm text-gray-600">প্রতি রুমে বেঞ্চ-রো এবং প্রতি বেঞ্চে আসন নির্ধারণ করুন। রোল নম্বর বেঞ্চ-রো ধরে উপর থেকে নিচে বসবে।</p>
                        </div>
                        <button type="button" @click="showSeatPlanModal = false" aria-label="Close dialog"
                            class="rounded-lg p-2 text-gray-500 hover:bg-gray-100">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <form class="mt-5 space-y-4" @submit.prevent="printAllSeatPlans">
                        <fieldset class="rounded-lg border border-gray-200 p-3">
                            <legend class="px-1 text-sm font-semibold text-gray-800">সিট প্ল্যানে ক্লাস নির্বাচন</legend>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label v-for="classItem in seatPlanClasses" :key="classItem.id"
                                    class="flex items-center gap-2 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                    <input v-model="form.seat_plan_class_ids" type="checkbox" :value="String(classItem.id)"
                                        class="rounded border-gray-300 text-blue-600">
                                    <span>{{ classItem.name }}{{ classItem.group ? ` — ${classItem.group}` : '' }} <span class="text-gray-500">({{ classItem.student_count }} জন)</span></span>
                                </label>
                            </div>
                            <p v-if="form.section_id" class="mt-2 text-xs text-amber-700">
                                নির্বাচিত সেকশন ফিল্টারটি শুধু মূল ক্লাসে প্রযোজ্য হবে; অন্য নির্বাচিত ক্লাসের সব সেকশন থাকবে।
                            </p>
                        </fieldset>
                        <div class="max-h-[55vh] space-y-3 overflow-y-auto">
                            <section v-for="(room, index) in form.rooms" :key="index"
                                class="grid gap-3 rounded-lg border border-gray-200 p-3 sm:grid-cols-[minmax(0,1.3fr)_1fr_1fr_auto]">
                                <label class="text-sm font-medium text-gray-700">
                                    রুমের নাম
                                    <input v-model.trim="room.room_name" type="text" maxlength="80" required
                                        class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                                </label>
                                <label class="text-sm font-medium text-gray-700">
                                    বেঞ্চ-রো
                                    <input v-model.number="room.bench_rows" type="number" min="1" max="12" required
                                        class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                                </label>
                                <label class="text-sm font-medium text-gray-700">
                                    প্রতি বেঞ্চে আসন
                                    <input v-model.number="room.seats_per_bench" type="number" min="1" max="30" required
                                        class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                                </label>
                                <button type="button" @click="removeRoom(index)" :disabled="form.rooms.length === 1"
                                    aria-label="রুম বাদ দিন"
                                    class="self-end rounded-lg border border-red-200 px-3 py-2 text-sm text-red-700 disabled:opacity-40">
                                    বাদ
                                </button>
                                <p class="text-xs text-gray-500 sm:col-span-4">
                                    ধারণক্ষমতা: {{ (Number(room.bench_rows) || 0) * (Number(room.seats_per_bench) || 0) }} জন
                                </p>
                            </section>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <button type="button" @click="addRoom" :disabled="form.rooms.length >= 20"
                                class="rounded-lg border border-blue-200 px-3 py-2 text-sm font-medium text-blue-700 disabled:opacity-40">
                                + রুম যোগ করুন
                            </button>
                            <p :class="totalRoomCapacity >= seatPlanStudentCount ? 'text-green-700' : 'text-red-700'"
                                class="text-sm font-medium">
                                মোট আসন {{ totalRoomCapacity }} / শিক্ষার্থী {{ seatPlanStudentCount }}
                            </p>
                        </div>
                        <p v-if="totalRoomCapacity < seatPlanStudentCount" class="text-sm text-red-700">
                            সব শিক্ষার্থীর জন্য আরও {{ seatPlanStudentCount - totalRoomCapacity }}টি আসন যোগ করুন।
                        </p>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="showSeatPlanModal = false"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" :disabled="!form.seat_plan_class_ids.length || totalRoomCapacity < seatPlanStudentCount"
                                class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900 disabled:cursor-not-allowed disabled:opacity-50">
                                সব রুমের সিট প্ল্যান প্রিন্ট
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        </main>
    </InstituteLayout>
</template>
