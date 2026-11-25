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
const form = useForm({
    institute_class_id: '',
    section_id: '',
    institute_subject_id: '',
    teacher_id: '',
    day_of_week: '6',
    start_time: '09:00',
    end_time: '09:45',
    classroom: '',
});

const selectedClass = computed(() => props.classes.find(item => Number(item.id) === Number(form.institute_class_id)));
const availableSubjects = computed(() => props.subjects.filter(item =>
    item.class_ids.some(classId => Number(classId) === Number(form.institute_class_id))
));

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.day_of_week = '6';
    form.start_time = '09:00';
    form.end_time = '09:45';
};

const editRoutine = item => {
    editingId.value = item.id;
    form.institute_class_id = String(item.institute_class_id);
    form.section_id = item.section_id ? String(item.section_id) : '';
    form.institute_subject_id = String(item.institute_subject_id);
    form.teacher_id = item.teacher_id ? String(item.teacher_id) : '';
    form.day_of_week = String(item.day_of_week);
    form.start_time = item.start_time;
    form.end_time = item.end_time;
    form.classroom = item.classroom || '';
};

const submit = () => {
    const options = { preserveScroll: true, onSuccess: resetForm };
    if (editingId.value) {
        form.put(route('class-routines.update', editingId.value), options);
    } else {
        form.post(route('class-routines.store'), options);
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
    items: props.routines.filter(item => Number(item.day_of_week) === day),
})).filter(group => group.items.length));
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
                <button v-if="editingId" type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700" @click="resetForm">
                    নতুন রুটিন
                </button>
            </header>

            <div v-if="!academicYear" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                ক্লাস রুটিন যোগ করতে আগে একটি চলতি শিক্ষাবর্ষ নির্ধারণ করুন।
            </div>

            <section class="rounded-xl border border-gray-200 bg-white p-4 sm:p-5">
                <h2 class="mb-4 text-lg font-semibold text-gray-900">{{ editingId ? 'রুটিন সম্পাদনা' : 'নতুন ক্লাস যোগ করুন' }}</h2>
                <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="submit">
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
                    <label class="text-sm font-medium text-gray-700">
                        শিক্ষক
                        <select v-model="form.teacher_id" class="mt-1 w-full rounded-lg border-gray-300">
                            <option value="">শিক্ষক নির্বাচন</option>
                            <option v-for="item in teachers" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
                        </select>
                    </label>
                    <label class="text-sm font-medium text-gray-700">
                        বার *
                        <select v-model="form.day_of_week" required class="mt-1 w-full rounded-lg border-gray-300">
                            <option v-for="(day, index) in weekdays" :key="day" :value="String(index)">{{ day }}</option>
                        </select>
                    </label>
                    <label class="text-sm font-medium text-gray-700">
                        শুরুর সময় *
                        <input v-model="form.start_time" type="time" required class="mt-1 w-full rounded-lg border-gray-300">
                        <span v-if="form.errors.start_time" class="mt-1 block text-red-600">{{ form.errors.start_time }}</span>
                    </label>
                    <label class="text-sm font-medium text-gray-700">
                        শেষের সময় *
                        <input v-model="form.end_time" type="time" required class="mt-1 w-full rounded-lg border-gray-300">
                        <span v-if="form.errors.end_time" class="mt-1 block text-red-600">{{ form.errors.end_time }}</span>
                    </label>
                    <label class="text-sm font-medium text-gray-700">
                        কক্ষ
                        <input v-model="form.classroom" type="text" maxlength="100" class="mt-1 w-full rounded-lg border-gray-300" placeholder="যেমন: ১০১">
                    </label>
                    <div class="flex items-end sm:col-span-2 lg:col-span-4">
                        <button type="submit" :disabled="form.processing || !academicYear" class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">
                            {{ form.processing ? 'সংরক্ষণ হচ্ছে…' : editingId ? 'পরিবর্তন সংরক্ষণ' : 'রুটিনে যোগ করুন' }}
                        </button>
                    </div>
                </form>
                <p v-if="form.errors.academic_year" class="mt-3 text-sm text-red-600">{{ form.errors.academic_year }}</p>
            </section>

            <section v-for="group in routinesByDay" :key="group.day" class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <h2 class="border-b border-gray-100 bg-gray-50 px-4 py-3 font-semibold text-gray-900">{{ group.name }}</h2>
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
            </section>

            <div v-if="!routines.length" class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">
                এখনো কোনো ক্লাস রুটিন যোগ করা হয়নি।
            </div>
        </div>
    </main>
</template>
