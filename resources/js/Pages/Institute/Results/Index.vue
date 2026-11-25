<script setup>
import InstituteLayout from '@/Layouts/InstituteLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    results: {
        type: Array,
        default: () => [],
    },
    showUnpublishedOnly: {
        type: Boolean,
        default: false,
    },
    filters: {
        type: Object,
        default: () => ({ exam_id: '', class_id: '', section_id: '', status: 'all' }),
    },
    classes: {
        type: Array,
        default: () => [],
    },
    sections: {
        type: Array,
        default: () => [],
    },
    exams: {
        type: Array,
        default: () => [],
    },
    subjects: {
        type: Array,
        default: () => [],
    },
    tabulationRows: {
        type: Array,
        default: () => [],
    },
    canGenerateResults: { type: Boolean, default: true },
    canPrintTabulation: { type: Boolean, default: true },
    canViewMarks: { type: Boolean, default: true },
    isTeacher: { type: Boolean, default: false },
});

const form = reactive({
    exam_id: props.filters.exam_id ?? '',
    class_id: props.filters.class_id ?? '',
    section_id: props.filters.section_id ?? '',
    subject_id: props.filters.subject_id ?? '',
    status: props.filters.status ?? 'all',
});
const generatingResults = ref(false);
const page = usePage();
const selectedExam = computed(() => props.exams.find((exam) => String(exam.id) === String(form.exam_id)));
const visibleSubjects = computed(() => form.subject_id
    ? props.subjects.filter((subject) => (subject.subject_ids || [subject.id])
        .some((id) => String(id) === String(form.subject_id)))
    : props.subjects);

watch(() => props.filters, (filters) => {
    form.exam_id = filters.exam_id ?? '';
    form.class_id = filters.class_id ?? '';
    form.section_id = filters.section_id ?? '';
    form.subject_id = filters.subject_id ?? '';
    form.status = filters.status ?? 'all';
});

const filteredSections = () => form.class_id
    ? props.sections.filter((section) => String(section.institute_class_id) === String(form.class_id))
    : props.sections;

const applyFilters = () => {
    const destination = props.showUnpublishedOnly ? 'results.publish' : 'results.index';
    router.get(route(destination), form, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    form.exam_id = '';
    form.class_id = '';
    form.section_id = '';
    form.subject_id = '';
    form.status = props.showUnpublishedOnly ? 'unpublished' : 'all';
    applyFilters();
};

const generateResults = () => {
    if (!form.exam_id) return;

    generatingResults.value = true;
    router.post(route('results.generate', form.exam_id), {}, {
        preserveScroll: true,
        onFinish: () => {
            generatingResults.value = false;
        },
    });
};

const togglePublished = (result) => {
    router.post(route('results.toggle-publish', result.id), {}, {
        preserveScroll: true,
    });
};

const printTabulation = () => window.print();
</script>

<template>
    <InstituteLayout>
        <Head :title="showUnpublishedOnly ? 'Publish Results' : 'Results'" />
        <main class="min-w-0 flex-1 p-4 sm:p-6">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3 no-print">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">
                        {{ showUnpublishedOnly ? 'Publish Results' : 'Results' }}
                    </h1>
                    <p class="mt-1 text-sm text-gray-600">Filter exam results and print a tabulation sheet.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link v-if="!isTeacher" :href="route('results.index')" class="rounded-lg border px-4 py-2 text-sm"
                        :class="!showUnpublishedOnly ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-gray-300 text-gray-700'">
                        All results
                    </Link>
                    <Link :href="route('results.publish')" class="rounded-lg border px-4 py-2 text-sm"
                        :class="showUnpublishedOnly ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-gray-300 text-gray-700'">
                        Publish Results
                    </Link>
                    <Link v-if="canViewMarks" :href="route('marks.index')" class="rounded-lg bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700">
                        Marks
                    </Link>
                    <button v-if="canGenerateResults" type="button" :disabled="!form.exam_id || generatingResults" @click="generateResults"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">
                        {{ generatingResults ? 'Generating…' : 'Generate / Refresh Results' }}
                    </button>
                </div>
            </div>

            <p v-if="page.props.errors?.exam" class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 no-print">
                {{ page.props.errors.exam }}
            </p>
            <p v-if="page.props.flash?.success" class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 no-print">
                {{ page.props.flash.success }}
            </p>

            <form @submit.prevent="applyFilters" class="mb-6 grid gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-6 no-print">
                <label class="block text-sm font-medium text-gray-700">
                    Exam
                    <select v-model="form.exam_id" name="exam_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm"
                        @change="form.subject_id = ''">
                        <option value="">All exams</option>
                        <option v-for="exam in exams" :key="exam.id" :value="String(exam.id)">
                            {{ exam.name }}{{ exam.academic_year ? ` (${exam.academic_year})` : '' }}
                        </option>
                    </select>
                </label>
                <label class="block text-sm font-medium text-gray-700">
                    Subject
                    <select v-model="form.subject_id" name="subject_id"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm" :disabled="!form.exam_id">
                        <option value="">All subjects</option>
                        <option v-for="subject in subjects" :key="subject.id" :value="String(subject.id)">
                            {{ subject.name }}
                        </option>
                    </select>
                </label>
                <label class="block text-sm font-medium text-gray-700">
                    Class
                    <select v-model="form.class_id" name="class_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm"
                        @change="form.section_id = ''">
                        <option value="">All classes</option>
                        <option v-for="schoolClass in classes" :key="schoolClass.id" :value="String(schoolClass.id)">
                            {{ schoolClass.name }}{{ schoolClass.group ? ` — ${schoolClass.group}` : '' }}
                        </option>
                    </select>
                </label>
                <label class="block text-sm font-medium text-gray-700">
                    Section
                    <select v-model="form.section_id" name="section_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        <option value="">All sections</option>
                        <option v-for="section in filteredSections()" :key="section.id" :value="String(section.id)">
                            {{ section.name }}
                        </option>
                    </select>
                </label>
                <label class="block text-sm font-medium text-gray-700">
                    Publication status
                    <select v-model="form.status" name="status" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        <option value="all">All statuses</option>
                        <option value="published">Published</option>
                        <option value="unpublished">Unpublished</option>
                    </select>
                </label>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        Filter
                    </button>
                    <button type="button" @click="clearFilters" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        Clear
                    </button>
                </div>
            </form>

            <div v-if="form.exam_id" class="mb-4 flex items-center justify-between gap-3 no-print">
                <p class="text-sm text-gray-600">
                    {{ visibleSubjects.length }} subject{{ visibleSubjects.length === 1 ? '' : 's' }}
                    · {{ form.subject_id ? tabulationRows.length : results.length }} students
                </p>
                <button v-if="canPrintTabulation" type="button" @click="printTabulation"
                    class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">
                    <i class="fa-solid fa-print mr-2"></i>Print Tabulation Sheet
                </button>
            </div>
            <p v-else class="mb-4 text-sm text-amber-700 no-print">
                Tabulation sheet প্রিন্ট করতে একটি exam নির্বাচন করে Filter চাপুন।
            </p>

            <section id="tabulation-print" class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="print-heading hidden p-4 text-center">
                    <h1 class="text-xl font-bold">Tabulation Sheet</h1>
                    <p>{{ selectedExam?.name ?? 'Exam Results' }}</p>
                    <p v-if="form.subject_id">{{ visibleSubjects[0]?.name ?? '' }}</p>
                    <p>
                        {{ selectedExam?.institute_class?.name ?? '' }}
                        {{ results[0]?.section?.name ? `— ${results[0].section.name}` : '' }}
                    </p>
                </div>
                <table v-if="form.exam_id && form.subject_id && visibleSubjects.length" class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-3 py-3 font-medium">Roll</th>
                            <th class="px-3 py-3 font-medium">Student ID</th>
                            <th class="px-3 py-3 font-medium">Student Name</th>
                            <th class="px-3 py-3 text-center font-medium">
                                {{ visibleSubjects[0].name }}<span v-if="visibleSubjects[0].full_marks"> / {{ visibleSubjects[0].full_marks }}</span>
                                <span v-if="visibleSubjects[0].pass_marks !== null && visibleSubjects[0].pass_marks !== undefined" class="block text-xs font-normal text-gray-500">
                                    Pass: {{ visibleSubjects[0].pass_marks }}
                                </span>
                            </th>
                            <th class="px-3 py-3 text-center font-medium">Grade Point</th>
                            <th class="px-3 py-3 text-center font-medium">Grade</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in tabulationRows" :key="row.student.id">
                            <td class="px-3 py-2">{{ row.roll_number ?? row.student?.roll_no ?? '—' }}</td>
                            <td class="px-3 py-2">{{ row.student?.student_id ?? '—' }}</td>
                            <td class="px-3 py-2 font-medium text-gray-900">{{ row.student?.name ?? '—' }}</td>
                            <td class="px-3 py-2 text-center">
                                {{ row.mark?.is_absent ? 'Absent' : (row.mark?.total_marks ?? '—') }}
                            </td>
                            <td class="px-3 py-2 text-center">{{ row.mark?.grade_point ?? '—' }}</td>
                            <td class="px-3 py-2 text-center">{{ row.mark?.grade ?? '—' }}</td>
                        </tr>
                        <tr v-if="!tabulationRows.length">
                            <td colspan="6" class="px-4 py-10 text-center text-gray-500">
                                এই subject-এর জন্য কোনো শিক্ষার্থী পাওয়া যায়নি।
                            </td>
                        </tr>
                    </tbody>
                </table>
                <table v-else-if="form.exam_id && visibleSubjects.length" class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-3 py-3 font-medium">Roll</th>
                            <th class="px-3 py-3 font-medium">Student ID</th>
                            <th class="px-3 py-3 font-medium">Student Name</th>
                            <th v-for="subject in visibleSubjects" :key="subject.id" class="px-3 py-3 text-center font-medium">
                                {{ subject.name }}<span v-if="subject.full_marks"> / {{ subject.full_marks }}</span>
                                <span v-if="subject.pass_marks !== null && subject.pass_marks !== undefined" class="block text-xs font-normal text-gray-500">
                                    Pass: {{ subject.pass_marks }}
                                </span>
                            </th>
                            <th class="px-3 py-3 text-right font-medium">Total</th>
                            <th class="px-3 py-3 text-center font-medium">GPA</th>
                            <th class="px-3 py-3 text-center font-medium">Grade</th>
                            <th class="px-3 py-3 text-center font-medium">Position</th>
                            <th class="px-3 py-3 font-medium no-print">Status / Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="result in results" :key="result.id">
                            <td class="px-3 py-2">{{ result.student?.roll_no ?? result.student?.id ?? '—' }}</td>
                            <td class="px-3 py-2">{{ result.student?.student_id ?? '—' }}</td>
                            <td class="px-3 py-2 font-medium text-gray-900">{{ result.student?.name ?? '—' }}</td>
                            <td v-for="subject in visibleSubjects" :key="subject.id" class="px-3 py-2 text-center">
                                {{ result.subject_marks?.[subject.key]?.is_absent ? 'Absent' : (result.subject_marks?.[subject.key]?.total_marks ?? '—') }}
                            </td>
                            <td class="px-3 py-2 text-right">{{ result.total_marks }} / {{ result.total_full_marks ?? '—' }}</td>
                            <td class="px-3 py-2 text-center">{{ result.gpa }}</td>
                            <td class="px-3 py-2 text-center">{{ result.final_grade }}</td>
                            <td class="px-3 py-2 text-center">{{ result.position ?? result.class_position ?? '—' }}</td>
                            <td class="px-3 py-2 no-print">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs">{{ result.is_published ? 'Published' : 'Unpublished' }}</span>
                                    <button type="button" @click="togglePublished(result)"
                                        class="rounded border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                        {{ result.is_published ? 'Unpublish' : 'Publish' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!results.length">
                            <td :colspan="visibleSubjects.length + 9" class="px-4 py-10 text-center text-gray-500">
                                এই ফিল্টারে কোনো ফলাফল পাওয়া যায়নি।
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-else-if="form.exam_id" class="p-8 text-center text-gray-500">
                    এই পরীক্ষার জন্য কোনো subject নির্ধারিত নেই।
                </div>
                <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">Student</th>
                            <th class="px-4 py-3 font-medium">Exam</th>
                            <th class="px-4 py-3 font-medium">Section</th>
                            <th class="px-4 py-3 font-medium">Total</th>
                            <th class="px-4 py-3 font-medium">GPA</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium no-print">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="result in results" :key="result.id">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">{{ result.student?.name ?? '—' }}</div>
                                <div class="text-xs text-gray-500">{{ result.student?.student_id ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ result.exam?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ result.section?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ result.total_marks }} / {{ result.total_full_marks ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ result.gpa }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="result.is_published ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'">
                                    {{ result.is_published ? 'Published' : 'Unpublished' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right no-print">
                                <button type="button" @click="togglePublished(result)"
                                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                    {{ result.is_published ? 'Unpublish' : 'Publish' }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!results.length">
                            <td colspan="7" class="px-4 py-10 text-center text-gray-500">
                                এই ফিল্টারে কোনো ফলাফল পাওয়া যায়নি।
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </main>
    </InstituteLayout>
</template>

<style>
@media print {
    @page {
        size: landscape;
        margin: 12mm;
    }

    body * {
        visibility: hidden !important;
    }

    #tabulation-print,
    #tabulation-print * {
        visibility: visible !important;
    }

    #tabulation-print {
        position: absolute;
        inset: 0;
        width: 100%;
        overflow: visible !important;
        border: 0 !important;
        box-shadow: none !important;
    }

    #tabulation-print .print-heading {
        display: block !important;
    }

    #tabulation-print .no-print {
        display: none !important;
    }

    #tabulation-print table {
        width: 100%;
        border-collapse: collapse;
        font-size: 9pt;
    }

    #tabulation-print th,
    #tabulation-print td {
        border: 1px solid #555 !important;
        padding: 4px !important;
        color: #000 !important;
    }

    #tabulation-print thead {
        display: table-header-group;
    }

    #tabulation-print tr {
        break-inside: avoid;
    }
}
</style>
