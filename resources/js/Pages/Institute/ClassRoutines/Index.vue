<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import InstituteLayout from '@/Layouts/InstituteLayout.vue';

defineOptions({ layout: InstituteLayout });

const props = defineProps({
    academicYear: { type: Object, default: null },
    classes: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] },
    teachers: { type: Array, default: () => [] },
    routines: { type: Array, default: () => [] },
});

const weekdays = ['রবিবার', 'সোমবার', 'মঙ্গলবার', 'বুধবার', 'বৃহস্পতিবার', 'শুক্রবার', 'শনিবার'];
const editingId = ref(null);
const isModalOpen = ref(false);
const isModalMinimized = ref(false);
const isModalMaximized = ref(false);
const filterClassId = ref('');
const newRow = () => ({
    day_of_week: '6',
    start_time: '09:00',
    end_time: '09:45',
    classroom: '',
    teacher_id: '',
});
const scheduleRows = ref([newRow()]);
const form = useForm({
    institute_class_id: '',
    section_id: '',
    institute_subject_id: '',
});

const selectedClass = computed(() => props.classes.find(item => Number(item.id) === Number(form.institute_class_id)));
const availableSubjects = computed(() => props.subjects.filter(item =>
    item.class_ids.some(classId => Number(classId) === Number(form.institute_class_id))
));

const resetForm = () => {
    editingId.value = null;
    scheduleRows.value = [newRow()];
    form.reset();
    closeModal();
};

const editRoutine = item => {
    isModalOpen.value = true;
    isModalMinimized.value = false;
    isModalMaximized.value = false;
    editingId.value = item.id;
    form.institute_class_id = String(item.institute_class_id);
    form.section_id = item.section_id ? String(item.section_id) : '';
    form.institute_subject_id = String(item.institute_subject_id);
    scheduleRows.value = [{
        day_of_week: String(item.day_of_week),
        start_time: item.start_time,
        end_time: item.end_time,
        classroom: item.classroom || '',
        teacher_id: item.teacher_id ? String(item.teacher_id) : '',
    }];
};

const openCreateModal = () => {
    editingId.value = null;
    scheduleRows.value = [newRow()];
    form.reset();
    isModalOpen.value = true;
    isModalMinimized.value = false;
    isModalMaximized.value = false;
};

const closeModal = () => {
    isModalOpen.value = false;
    isModalMinimized.value = false;
};

const toggleModalMinimize = () => {
    isModalMinimized.value = !isModalMinimized.value;
    if (isModalMinimized.value) isModalMaximized.value = false;
};

const toggleModalMaximize = () => {
    isModalMaximized.value = !isModalMaximized.value;
};

const addRow = () => scheduleRows.value.push(newRow());
const removeRow = index => {
    if (scheduleRows.value.length > 1) scheduleRows.value.splice(index, 1);
};

const submit = () => {
    const options = { preserveScroll: true, onSuccess: resetForm };
    if (editingId.value) {
        const row = scheduleRows.value[0];
        form.transform(() => ({ ...form.data(), ...row })).put(route('class-routines.update', editingId.value), options);
    } else {
        form.transform(() => ({ ...form.data(), routines: scheduleRows.value })).post(route('class-routines.store'), options);
    }
};

const deleteRoutine = item => {
    if (window.confirm(`${item.subject_name} ক্লাসটি মুছে ফেলবেন?`)) {
        router.delete(route('class-routines.destroy', item.id), { preserveScroll: true });
    }
};

const routinesByDay = computed(() => weekdays.map((name, day) => ({
    day,
    name,
    items: props.routines.filter(item =>
        Number(item.day_of_week) === day
        && (!filterClassId.value || String(item.institute_class_id) === String(filterClassId.value))
    ),
})).filter(group => group.items.length));

const filteredRoutines = computed(() =>
    filterClassId.value
        ? props.routines.filter(item => String(item.institute_class_id) === String(filterClassId.value))
        : props.routines
);

const printRoutine = () => {
    const url = new URL(route('class-routines.print'), window.location.origin);
    url.searchParams.set('class_id', filterClassId.value || 'all');
    window.open(url.toString(), '_blank', 'noopener,noreferrer');
};
</script>

<template>
    <Head title="Class Routine" />
    <main class="min-h-screen bg-gray-50 p-4 sm:p-6">
        <div class="mx-auto max-w-6xl space-y-5">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 sm:text-2xl">ক্লাস রুটিন</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ academicYear?.name || 'চলতি শিক্ষাবর্ষ নির্ধারিত নেই' }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700" @click="openCreateModal">
                        নতুন ক্লাস
                    </button>
                    <button type="button" :disabled="!academicYear" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" @click="printRoutine">
                        প্রিন্ট করুন
                    </button>
                </div>
            </header>

            <div v-if="!academicYear" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                ক্লাস রুটিন যোগ করতে আগে একটি চলতি শিক্ষাবর্ষ নির্ধারণ করুন।
            </div>

            <section class="rounded-xl border border-gray-200 bg-white p-4 sm:p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-gray-900">রুটিন তালিকা</h2>
                    <label class="text-sm font-medium text-gray-700">
                        শ্রেণি অনুযায়ী
                        <select v-model="filterClassId" class="ml-2 min-w-44 rounded-lg border-gray-300 text-sm">
                            <option value="">সব শ্রেণি</option>
                            <option v-for="item in classes" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
                        </select>
                    </label>
                </div>

                <div v-if="filteredRoutines.length === 0" class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500">
                    নির্বাচিত শ্রেণিতে কোনো ক্লাস রুটিন পাওয়া যায়নি।
                </div>

                <div v-for="group in routinesByDay" :key="group.day" class="mb-3 overflow-hidden rounded-xl border border-gray-200 last:mb-0">
                    <h3 class="border-b border-gray-100 bg-gray-50 px-4 py-3 font-semibold text-gray-900">{{ group.name }}</h3>
                    <div v-for="item in group.items" :key="item.id" class="flex flex-wrap items-center gap-3 border-b border-gray-100 px-4 py-3 last:border-0">
                        <div class="w-28 shrink-0 text-sm font-semibold text-blue-700">{{ item.start_time }} – {{ item.end_time }}</div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-900">{{ item.subject_name }}</p>
                            <p class="text-sm text-gray-500">{{ item.class_name }}{{ item.section_name ? ` · ${item.section_name}` : '' }}{{ item.teacher_name ? ` · ${item.teacher_name}` : '' }}{{ item.classroom ? ` · কক্ষ ${item.classroom}` : '' }}</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700" @click="editRoutine(item)">সম্পাদনা</button>
                            <button type="button" class="rounded-lg border border-red-200 px-3 py-1.5 text-sm text-red-700" @click="deleteRoutine(item)">মুছুন</button>
                        </div>
                    </div>
                </div>
            </section>

            <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4" :class="{ 'pointer-events-none bg-transparent': isModalMinimized }">
                <section
                    class="flex max-h-[calc(100vh-24px)] w-full flex-col overflow-hidden rounded-t-2xl border border-gray-200 bg-white shadow-2xl sm:rounded-2xl"
                    :class="isModalMinimized ? 'pointer-events-auto h-16 w-full max-w-md self-end sm:mb-4' : isModalMaximized ? 'h-[calc(100vh-24px)] max-w-[calc(100vw-24px)]' : 'h-[min(900px,calc(100vh-24px))] max-w-6xl'"
                >
                    <header class="flex shrink-0 items-center justify-between border-b border-gray-200 px-4 py-3 sm:px-5">
                        <h2 class="text-lg font-semibold text-gray-900">{{ editingId ? 'রুটিন সম্পাদনা' : 'নতুন ক্লাস যোগ করুন' }}</h2>
                        <div class="flex gap-1">
                            <button type="button" class="h-9 w-9 rounded-lg text-lg text-gray-600 hover:bg-gray-100" :title="isModalMinimized ? 'বড় করুন' : 'মিনিমাইজ'" @click="toggleModalMinimize">
                                {{ isModalMinimized ? '▢' : '—' }}
                            </button>
                            <button v-if="!isModalMinimized" type="button" class="h-9 w-9 rounded-lg text-lg text-gray-600 hover:bg-gray-100" :title="isModalMaximized ? 'স্বাভাবিক আকার' : 'পূর্ণস্ক্রিন'" @click="toggleModalMaximize">
                                {{ isModalMaximized ? '❐' : '▢' }}
                            </button>
                            <button type="button" class="h-9 w-9 rounded-lg text-xl text-gray-600 hover:bg-red-50 hover:text-red-600" title="বন্ধ করুন" @click="closeModal">×</button>
                        </div>
                    </header>
                    <div v-show="!isModalMinimized" class="overflow-y-auto p-4 sm:p-5">
                <form @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="text-sm font-medium text-gray-700">
                            শ্রেণি *
                            <select v-model="form.institute_class_id" required class="mt-1 w-full rounded-lg border-gray-300" @change="form.section_id = ''; form.institute_subject_id = ''">
                                <option value="">শ্রেণি নির্বাচন</option>
                                <option v-for="item in classes" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
                            </select>
                            <span v-if="form.errors.institute_class_id" class="mt-1 block text-red-600">{{ form.errors.institute_class_id }}</span>
                        </label>
                        <label class="text-sm font-medium text-gray-700">
                            শাখা
                            <select v-model="form.section_id" class="mt-1 w-full rounded-lg border-gray-300">
                                <option value="">সব শাখা</option>
                                <option v-for="item in selectedClass?.sections || []" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
                            </select>
                        </label>
                        <label class="text-sm font-medium text-gray-700">
                            বিষয় *
                            <select v-model="form.institute_subject_id" required class="mt-1 w-full rounded-lg border-gray-300">
                                <option value="">বিষয় নির্বাচন</option>
                                <option v-for="item in availableSubjects" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
                            </select>
                            <span v-if="form.errors.institute_subject_id" class="mt-1 block text-red-600">{{ form.errors.institute_subject_id }}</span>
                        </label>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div v-for="(row, index) in scheduleRows" :key="index" class="grid items-end gap-3 border-t border-dashed border-gray-200 pt-4 first:border-0 first:pt-0 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_1fr_1fr_96px]">
                            <label class="text-sm font-medium text-gray-700">
                                বার *
                                <select v-model="row.day_of_week" required class="mt-1 w-full rounded-lg border-gray-300">
                                    <option v-for="(day, dayIndex) in weekdays" :key="day" :value="String(dayIndex)">{{ day }}</option>
                                </select>
                            </label>
                            <label class="text-sm font-medium text-gray-700">
                                শুরুর সময় *
                                <input v-model="row.start_time" type="time" required class="mt-1 w-full rounded-lg border-gray-300">
                            </label>
                            <label class="text-sm font-medium text-gray-700">
                                শেষের সময় *
                                <input v-model="row.end_time" type="time" required class="mt-1 w-full rounded-lg border-gray-300">
                            </label>
                            <label class="text-sm font-medium text-gray-700">
                                কক্ষ
                                <input v-model="row.classroom" type="text" maxlength="100" class="mt-1 w-full rounded-lg border-gray-300" placeholder="যেমন: ১০১">
                            </label>
                            <label class="text-sm font-medium text-gray-700">
                                শিক্ষক
                                <select v-model="row.teacher_id" class="mt-1 w-full rounded-lg border-gray-300">
                                    <option value="">শিক্ষক নির্বাচন</option>
                                    <option v-for="item in teachers" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
                                </select>
                            </label>
                            <div class="flex justify-end gap-2">
                                <button v-if="!editingId" type="button" class="h-10 w-10 rounded-lg border border-green-200 bg-green-50 text-xl font-semibold text-green-700" title="যোগ করুন" @click="addRow">+</button>
                                <button v-if="!editingId" type="button" :disabled="scheduleRows.length === 1" class="h-10 w-10 rounded-lg border border-red-200 bg-red-50 text-xl font-semibold text-red-700 disabled:cursor-not-allowed disabled:opacity-40" title="মুছুন" @click="removeRow(index)">−</button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end">
                        <button type="submit" :disabled="form.processing || !academicYear" class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">
                            {{ form.processing ? 'সংরক্ষণ হচ্ছে…' : editingId ? 'পরিবর্তন সংরক্ষণ' : 'সংরক্ষণ করুন' }}
                        </button>
                    </div>
                </form>
                <p v-if="form.errors.academic_year" class="mt-3 text-sm text-red-600">{{ form.errors.academic_year }}</p>
                    </div>
                </section>
            </div>
        </div>
    </main>
</template>
