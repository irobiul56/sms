<script setup>
import { ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import InstituteLayout from '@/Layouts/InstituteLayout.vue';

const props = defineProps({
    grades: {
        type: Array,
        default: () => [],
    },
    canSeedDefaults: {
        type: Boolean,
        default: false,
    },
});

const showForm = ref(false);
const editingGrade = ref(null);
const page = usePage();
const gradeForm = useForm({
    name: '',
    point: 0,
    marks_from: 0,
    marks_to: 0,
    remarks: '',
});

const openCreateForm = () => {
    editingGrade.value = null;
    gradeForm.reset();
    gradeForm.clearErrors();
    showForm.value = true;
};

const openEditForm = (grade) => {
    editingGrade.value = grade;
    gradeForm.name = grade.name;
    gradeForm.point = Number(grade.point);
    gradeForm.marks_from = Number(grade.marks_from);
    gradeForm.marks_to = Number(grade.marks_to);
    gradeForm.remarks = grade.remarks ?? '';
    gradeForm.clearErrors();
    showForm.value = true;
};

const closeForm = () => {
    showForm.value = false;
    editingGrade.value = null;
    gradeForm.reset();
    gradeForm.clearErrors();
};

const saveGrade = () => {
    const options = {
        preserveScroll: true,
        onSuccess: closeForm,
    };

    if (editingGrade.value) {
        gradeForm.put(route('grades.update', editingGrade.value.id), options);
        return;
    }

    gradeForm.post(route('grades.store'), options);
};

const addDefaultGrades = () => {
    router.post(route('grades.defaults'), {}, { preserveScroll: true });
};

const deleteGrade = (grade) => {
    if (window.confirm(`Delete grade ${grade.name}?`)) {
        router.delete(route('grades.destroy', grade.id), { preserveScroll: true });
    }
};
</script>

<template>
    <InstituteLayout>
        <Head title="Grade System" />
        <main class="min-w-0 flex-1 p-4 sm:p-6">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Grade System</h1>
                    <p class="mt-1 text-sm text-gray-600">Configure grade ranges and grade points for this institute.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="canSeedDefaults"
                        type="button"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        @click="addDefaultGrades"
                    >
                        Use standard grades
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                        @click="openCreateForm"
                    >
                        Add grade
                    </button>
                </div>
            </div>

            <p v-if="page.props.errors?.grade || page.props.errors?.grades" class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ page.props.errors.grade || page.props.errors.grades }}
            </p>

            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">Grade</th>
                            <th class="px-4 py-3 font-medium">Marks range</th>
                            <th class="px-4 py-3 font-medium">Grade point</th>
                            <th class="px-4 py-3 font-medium">Remarks</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="grade in grades" :key="grade.id">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ grade.name }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ grade.marks_from }} – {{ grade.marks_to }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ grade.point }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ grade.remarks || '—' }}</td>
                            <td class="space-x-3 whitespace-nowrap px-4 py-3 text-right">
                                <button type="button" class="font-medium text-blue-600 hover:text-blue-800" @click="openEditForm(grade)">
                                    Edit
                                </button>
                                <button type="button" class="font-medium text-red-600 hover:text-red-800" @click="deleteGrade(grade)">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!grades.length">
                            <td colspan="5" class="px-4 py-10 text-center text-gray-500">
                                No grade ranges have been configured for this institute. Add grades or use the standard grading scale.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4" @click.self="closeForm">
            <section class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="grade-form-title">
                <h2 id="grade-form-title" class="text-lg font-semibold text-gray-900">
                    {{ editingGrade ? 'Edit grade' : 'Add grade' }}
                </h2>
                <form class="mt-5 space-y-4" @submit.prevent="saveGrade">
                    <div>
                        <label for="grade-name" class="block text-sm font-medium text-gray-700">Grade name</label>
                        <input id="grade-name" v-model="gradeForm.name" type="text" required maxlength="255"
                            class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <p v-if="gradeForm.errors.name" class="mt-1 text-sm text-red-600">{{ gradeForm.errors.name }}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label for="grade-point" class="block text-sm font-medium text-gray-700">Grade point</label>
                            <input id="grade-point" v-model.number="gradeForm.point" type="number" min="0" max="5" step="0.01" required
                                class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <p v-if="gradeForm.errors.point" class="mt-1 text-sm text-red-600">{{ gradeForm.errors.point }}</p>
                        </div>
                        <div>
                            <label for="marks-from" class="block text-sm font-medium text-gray-700">Marks from</label>
                            <input id="marks-from" v-model.number="gradeForm.marks_from" type="number" min="0" max="100" step="0.01" required
                                class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <p v-if="gradeForm.errors.marks_from" class="mt-1 text-sm text-red-600">{{ gradeForm.errors.marks_from }}</p>
                        </div>
                        <div>
                            <label for="marks-to" class="block text-sm font-medium text-gray-700">Marks to</label>
                            <input id="marks-to" v-model.number="gradeForm.marks_to" type="number" min="0" max="100" step="0.01" required
                                class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <p v-if="gradeForm.errors.marks_to" class="mt-1 text-sm text-red-600">{{ gradeForm.errors.marks_to }}</p>
                        </div>
                    </div>

                    <div>
                        <label for="grade-remarks" class="block text-sm font-medium text-gray-700">Remarks <span class="font-normal text-gray-400">(optional)</span></label>
                        <input id="grade-remarks" v-model="gradeForm.remarks" type="text" maxlength="255"
                            class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <p v-if="gradeForm.errors.remarks" class="mt-1 text-sm text-red-600">{{ gradeForm.errors.remarks }}</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="closeForm">
                            Cancel
                        </button>
                        <button type="submit" :disabled="gradeForm.processing"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                            {{ gradeForm.processing ? 'Saving…' : 'Save grade' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </InstituteLayout>
</template>
