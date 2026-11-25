<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import InstituteLayout from '@/Layouts/InstituteLayout.vue';

defineOptions({ layout: InstituteLayout });

const props = defineProps({
    homework: { type: Array, default: () => [] },
    academicYear: { type: Object, default: null },
    classes: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
    isTeacher: { type: Boolean, default: false },
});

const editing = ref(null);
const form = useForm({
    academic_year_id: props.academicYear?.id ?? '',
    institute_class_id: '',
    section_id: '',
    institute_subject_id: '',
    title: '',
    description: '',
    due_date: '',
});

const filteredSections = computed(() => props.sections.filter(section =>
    !form.institute_class_id || Number(section.institute_class_id) === Number(form.institute_class_id)
));

const startCreate = () => {
    editing.value = null;
    form.reset();
    form.academic_year_id = props.academicYear?.id ?? '';
};

const startEdit = (item) => {
    editing.value = item.id;
    form.academic_year_id = item.academic_year_id;
    form.institute_class_id = item.institute_class_id;
    form.section_id = item.section_id ?? '';
    form.institute_subject_id = item.institute_subject_id;
    form.title = item.title;
    form.description = item.description;
    form.due_date = item.due_date?.substring(0, 10) ?? '';
};

const submit = () => {
    if (editing.value) {
        form.put(route('homework.update', editing.value), {
            preserveScroll: true,
            onSuccess: () => startCreate(),
        });
        return;
    }

    form.post(route('homework.store'), {
        preserveScroll: true,
        onSuccess: () => startCreate(),
    });
};

const destroyHomework = (item) => {
    if (!window.confirm(`"${item.title}" homework delete করবেন?`)) return;
    router.delete(route('homework.destroy', item.id), { preserveScroll: true });
};

const formatDate = (value) => value
    ? new Intl.DateTimeFormat('bn-BD', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(`${value.substring(0, 10)}T00:00:00`))
    : '';
</script>

<template>
    <Head title="Homework" />
    <main class="min-h-screen bg-gray-50 p-4 sm:p-6">
        <div class="mx-auto max-w-6xl space-y-5">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 sm:text-2xl">বাড়ির কাজ</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ academicYear?.session || academicYear?.name || 'Academic year' }}</p>
                </div>
                <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white" @click="startCreate">
                    নতুন Homework
                </button>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-5">
                <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                    <input v-model="form.academic_year_id" type="hidden">

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Class *</label>
                        <select v-model="form.institute_class_id" @change="form.section_id = ''" class="w-full rounded-lg border-gray-300" required>
                            <option value="">Select class</option>
                            <option v-for="item in classes" :key="item.id" :value="item.id">{{ item.name }}{{ item.group ? ` — ${item.group}` : '' }}</option>
                        </select>
                        <p v-if="form.errors.institute_class_id" class="mt-1 text-sm text-red-600">{{ form.errors.institute_class_id }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Section</label>
                        <select v-model="form.section_id" class="w-full rounded-lg border-gray-300">
                            <option value="">সকল সেকশন</option>
                            <option v-for="item in filteredSections" :key="item.id" :value="item.id">{{ item.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Subject *</label>
                        <select v-model="form.institute_subject_id" class="w-full rounded-lg border-gray-300" required>
                            <option value="">Select subject</option>
                            <option v-for="item in subjects" :key="item.id" :value="item.id">{{ item.bn_name || item.name }}</option>
                        </select>
                        <p v-if="form.errors.institute_subject_id" class="mt-1 text-sm text-red-600">{{ form.errors.institute_subject_id }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Due date *</label>
                        <input v-model="form.due_date" type="date" class="w-full rounded-lg border-gray-300" required>
                        <p v-if="form.errors.due_date" class="mt-1 text-sm text-red-600">{{ form.errors.due_date }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Title *</label>
                        <input v-model="form.title" type="text" maxlength="255" class="w-full rounded-lg border-gray-300" required>
                        <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Description *</label>
                        <textarea v-model="form.description" rows="4" maxlength="5000" class="w-full rounded-lg border-gray-300" required></textarea>
                        <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</p>
                    </div>

                    <div class="md:col-span-2 flex gap-2">
                        <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white disabled:opacity-60">
                            {{ editing ? 'Update Homework' : 'Publish Homework' }}
                        </button>
                        <button v-if="editing" type="button" class="rounded-lg border border-gray-300 px-5 py-2 text-sm" @click="startCreate">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <article v-for="item in homework" :key="item.id" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <span class="text-xs font-semibold text-blue-600">{{ item.institute_subject?.bn_name || item.institute_subject?.name }}</span>
                            <h2 class="mt-1 font-bold text-gray-900">{{ item.title }}</h2>
                        </div>
                        <span class="shrink-0 rounded-full bg-orange-50 px-2 py-1 text-xs text-orange-700">Due {{ formatDate(item.due_date) }}</span>
                    </div>
                    <p class="mt-3 whitespace-pre-line text-sm text-gray-600">{{ item.description }}</p>
                    <div class="mt-4 flex items-center justify-between border-t pt-3 text-xs text-gray-500">
                        <span>{{ item.institute_class?.name }} <template v-if="item.section">· {{ item.section.name }}</template></span>
                        <div v-if="!isTeacher || item.teacher?.id" class="flex gap-3">
                            <button type="button" class="font-medium text-blue-600" @click="startEdit(item)">Edit</button>
                            <button type="button" class="font-medium text-red-600" @click="destroyHomework(item)">Delete</button>
                        </div>
                    </div>
                </article>
                <div v-if="!homework.length" class="md:col-span-2 rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                    এখনো কোনো homework দেওয়া হয়নি।
                </div>
            </div>
        </div>
    </main>
</template>
