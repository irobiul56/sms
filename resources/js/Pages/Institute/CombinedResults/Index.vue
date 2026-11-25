<script setup>
import InstituteLayout from '@/Layouts/InstituteLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    classes: { type: Array, default: () => [] },
    academicYears: { type: Array, default: () => [] },
    exams: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    report: { type: Object, default: null },
    institute: { type: Object, default: () => ({}) },
});

const page = usePage();
const form = reactive({
    class_id: props.filters.class_id ?? '',
    academic_year_id: props.filters.academic_year_id ?? '',
    section_id: props.filters.section_id ?? '',
    exam_ids: props.filters.exam_ids ?? [],
    mode: props.filters.mode ?? 'percentage',
    allocations: { ...(props.filters.allocations ?? {}) },
});
const availableSections = computed(() => props.sections.filter(
    section => !form.class_id || String(section.institute_class_id) === String(form.class_id)
));
const getClassLabel = (cls) => cls.group
    ? `${cls.bn_name || cls.name} (${cls.group_label || cls.group})`
    : (cls.bn_name || cls.name);
const viewMode = ref('tabulation');
const selectedStudentId = ref('');
const language = ref('bn');
const fontFamily = ref('"Noto Sans Bengali", "SolaimanLipi", sans-serif');
const fontSize = ref(13);
const pageOrientation = ref('landscape');
const visibleSubjectIds = ref([]);
const visibleExamIds = ref([]);
const visibleColumns = reactive({
    total: true,
    gpa: true,
    grade: true,
    point: true,
    merit_rank: true,
});
const selectedSummaryColumnKeys = reactive({
    tabulation: ['total', 'gpa', 'grade', 'merit_rank'],
    marksheet: ['total', 'grade', 'point', 'gpa'],
});
const summaryColumnGroup = computed(() => viewMode.value === 'tabulation' ? 'tabulation' : 'marksheet');
const activeSummaryColumnKeys = computed(() => selectedSummaryColumnKeys[summaryColumnGroup.value]);
const combinedQrCode = ref('');
const isFinalizing = ref(false);
const finalizationMessage = ref('');
const reportStyle = computed(() => ({
    '--combined-font-family': fontFamily.value,
    '--combined-font-size': `${fontSize.value}pt`,
}));
const visibleSubjects = computed(() => visibleSubjectIds.value
    .map((id) => props.report?.subjects.find((subject) => String(subject.id) === String(id)))
    .filter(Boolean));
const visibleExams = computed(() => visibleExamIds.value
    .map((id) => props.report?.exams.find((exam) => String(exam.id) === String(id)))
    .filter(Boolean));
const selectionOrder = (ids, id) => {
    const index = ids.findIndex((selectedId) => String(selectedId) === String(id));
    return index === -1 ? null : index + 1;
};
const summaryColumns = computed(() => viewMode.value === 'tabulation'
    ? [
        { key: 'total', en: 'Total marks', bn: 'মোট নম্বর' },
        { key: 'gpa', en: 'GPA', bn: 'GPA' },
        { key: 'grade', en: 'Grade', bn: 'গ্রেড' },
        { key: 'merit_rank', en: 'Merit rank', bn: 'মেধাক্রম' },
    ]
    : [
        { key: 'total', en: 'Total marks', bn: 'মোট নম্বর' },
        { key: 'gpa', en: 'GPA', bn: 'GPA' },
        { key: 'grade', en: 'Grade', bn: 'গ্রেড' },
        { key: 'point', en: 'Point', bn: 'পয়েন্ট' },
    ]);
const orderedSummaryColumns = computed(() => activeSummaryColumnKeys.value
    .map((key) => summaryColumns.value.find((column) => column.key === key))
    .filter(Boolean));
const visibleSummaryColumns = computed(() => {
    if (viewMode.value === 'tabulation') return orderedSummaryColumns.value;

    const columns = [];
    let hasPointGpaColumn = false;
    for (const column of orderedSummaryColumns.value) {
        if (column.key === 'gpa' || column.key === 'point') {
            if (!hasPointGpaColumn) {
                columns.push({ key: 'point-gpa' });
                hasPointGpaColumn = true;
            }
        } else {
            columns.push(column);
        }
    }

    return columns;
});
const selectedStudent = computed(() => props.report?.rows.find(
    (row) => String(row.student.id) === String(selectedStudentId.value),
));
const selectedExamIds = computed(() => form.exam_ids.map(String));
const reportIsCurrent = computed(() => {
    const report = props.report;
    if (!report
        || String(report.class_id) !== String(form.class_id)
        || String(report.academic_year_id) !== String(form.academic_year_id)
        || String(report.section_id ?? '') !== String(form.section_id ?? '')
        || report.mode !== form.mode
        || report.exams.length !== selectedExamIds.value.length) {
        return false;
    }

    return selectedExamIds.value.every((id) => {
        const exam = report.exams.find((item) => String(item.id) === id);
        return exam && Number(exam.allocation) === Number(form.allocations[id]);
    });
});
const allocationTotal = computed(() => selectedExamIds.value.reduce(
    (total, id) => total + (Number(form.allocations[id]) || 0),
    0,
));
const allocationIsValid = computed(() => form.mode === 'conversion'
    ? allocationTotal.value > 0
    : Math.abs(allocationTotal.value - 100) < 0.01);
const errorMessage = computed(() => Object.values(page.props.errors ?? {}).flat().join(' '));
const defaultAllocations = (ids = selectedExamIds.value, mode = form.mode) => {
    if (mode === 'conversion') {
        return Object.fromEntries(ids.map((id) => [id, 100]));
    }

    const equalPart = Number((100 / Math.max(ids.length, 1)).toFixed(2));
    const allocations = ids.map(() => equalPart);
    if (allocations.length) {
        allocations[allocations.length - 1] = Number((100 - equalPart * (allocations.length - 1)).toFixed(2));
    }

    return Object.fromEntries(ids.map((id, index) => [id, allocations[index]]));
};

const resetAllocations = () => {
    form.allocations = defaultAllocations();
};

watch(() => props.filters, (filters) => {
    form.class_id = filters.class_id ?? '';
    form.academic_year_id = filters.academic_year_id ?? '';
    form.section_id = filters.section_id ?? '';
    form.exam_ids = filters.exam_ids ?? [];
    form.mode = filters.mode ?? 'percentage';
    form.allocations = { ...(filters.allocations ?? {}) };
});

watch(() => props.report, async (report) => {
    if (!report) {
        combinedQrCode.value = '';
        return;
    }

    visibleSubjectIds.value = report.subjects.map((subject) => subject.id);
    visibleExamIds.value = report.exams.map((exam) => exam.id);

    try {
        const publicUrl = window.location.origin || 'http://localhost';
        combinedQrCode.value = await QRCode.toDataURL(publicUrl, {
            errorCorrectionLevel: 'M',
            margin: 1,
            width: 150,
        });
    } catch (error) {
        console.error('Unable to generate combined result QR code.', error);
        combinedQrCode.value = '';
    }
}, { immediate: true });

const loadExams = () => {
    form.academic_year_id = '';
    form.section_id = '';
    form.exam_ids = [];
    form.allocations = {};
    router.get(route('combined-results.index'), {
        class_id: form.class_id,
    }, { preserveState: true, preserveScroll: true });
};

const loadAcademicYearOptions = () => {
    form.section_id = '';
    form.exam_ids = [];
    form.allocations = {};
    router.get(route('combined-results.index'), {
        class_id: form.class_id,
        academic_year_id: form.academic_year_id,
    }, { preserveState: true, preserveScroll: true });
};

const generateReport = () => {
    const url = new URL(route('combined-results.index'), window.location.origin);
    url.searchParams.set('class_id', form.class_id);
    url.searchParams.set('academic_year_id', form.academic_year_id);
    if (form.section_id) url.searchParams.set('section_id', form.section_id);
    form.exam_ids.forEach((id) => url.searchParams.append('exam_ids[]', id));
    url.searchParams.set('mode', form.mode);
    form.exam_ids.forEach((id) => {
        url.searchParams.set(`allocations[${id}]`, form.allocations[id]);
    });
    window.open(url.toString(), '_blank', 'noopener,noreferrer');
};

const finalizeReport = () => {
    finalizationMessage.value = '';
    router.post(route('combined-results.finalize'), {
        class_id: Number(form.class_id),
        academic_year_id: Number(form.academic_year_id),
        section_id: form.section_id ? Number(form.section_id) : null,
        exam_ids: form.exam_ids.map(Number),
        mode: form.mode,
        allocations: Object.fromEntries(selectedExamIds.value.map((id) => [id, Number(form.allocations[id])])),
    }, {
        preserveScroll: true,
        onStart: () => { isFinalizing.value = true; },
        onFinish: () => { isFinalizing.value = false; },
        onSuccess: () => {
            finalizationMessage.value = 'কম্বাইন ফলাফল চূড়ান্ত করে শিক্ষার্থীদের জন্য প্রকাশ করা হয়েছে।';
        },
    });
};

const printReport = () => {
    const printStyle = document.createElement('style');
    printStyle.textContent = `@media print { @page { size: A4 ${pageOrientation.value}; margin: 10mm; } }`;
    document.head.append(printStyle);
    window.addEventListener('afterprint', () => printStyle.remove(), { once: true });
    window.print();
};
const label = (english, bengali) => language.value === 'bn' ? bengali : english;
const localized = (item) => language.value === 'bn'
    ? (item?.bn_name || item?.name || '—')
    : (item?.name || item?.bn_name || '—');
const subjectResult = (row, subject) => row.subjects.find(
    (result) => (result.key ?? `subjects-${result.id}`) === (subject.key ?? `subjects-${subject.id}`),
);
const examComponent = (subject, examId) => subject?.components?.find(
    (component) => String(component.exam_id) === String(examId),
);
const studentName = (student) => localized(student);
const toggleSummaryColumn = (key) => {
    visibleColumns[key] = !visibleColumns[key];
    selectedSummaryColumnKeys[summaryColumnGroup.value] = visibleColumns[key]
        ? [...activeSummaryColumnKeys.value, key]
        : activeSummaryColumnKeys.value.filter((selectedKey) => selectedKey !== key);
};
const tabulationSummaryValue = (row, key) => {
    if (key === 'total') return `${row.total_marks} / ${row.total_full_marks}`;
    if (key === 'gpa') return row.incomplete ? '—' : row.gpa;
    if (key === 'grade') return row.grade;
    if (key === 'merit_rank') return row.merit_rank ?? '—';

    return '—';
};
</script>

<template>
    <InstituteLayout :show-sidebar="false">
        <Head title="Combined Marksheet & Tabulation" />
        <main class="min-w-0 flex-1 p-4 sm:p-6">
            <div v-if="!report" class="mb-6 flex flex-wrap items-start justify-between gap-3 no-print">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">কম্বাইন মার্কশিট / টেবুলেশন</h1>
                    <p class="mt-1 text-sm text-gray-600">একাধিক পরীক্ষার নম্বর শতাংশে ওজন দিন অথবা নির্দিষ্ট পূর্ণমানে রূপান্তর করুন।</p>
                </div>
                <Link :href="route('exam-documents.index')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700">
                    Exam Documents
                </Link>
            </div>

            <section v-if="!report" class="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6 no-print">
                <form @submit.prevent="generateReport" class="space-y-5">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="text-sm font-medium text-gray-700">
                            শ্রেণি
                            <select v-model="form.class_id" required class="mt-1 w-full rounded-lg border-gray-300 text-sm"
                                @change="form.section_id = ''; loadExams">
                                <option value="">শ্রেণি নির্বাচন করুন</option>
                                <option v-for="classItem in classes" :key="classItem.id" :value="String(classItem.id)">
                                    {{ getClassLabel(classItem) }}
                                </option>
                            </select>
                        </label>
                        <label class="text-sm font-medium text-gray-700">
                            শিক্ষাবর্ষ
                            <select v-model="form.academic_year_id" required class="mt-1 w-full rounded-lg border-gray-300 text-sm"
                                :disabled="!form.class_id"                                 @change="loadAcademicYearOptions">
                                <option value="">শিক্ষাবর্ষ নির্বাচন করুন</option>
                                <option v-for="year in academicYears" :key="year.id" :value="String(year.id)">
                                    {{ year.session || year.year }}
                                </option>
                            </select>
                        </label>
                        <label class="text-sm font-medium text-gray-700">
                            সেকশন
                            <select v-model="form.section_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm"
                                :disabled="!form.academic_year_id">
                                <option value="">সকল সেকশন</option>
                                <option v-for="section in availableSections" :key="section.id" :value="String(section.id)">
                                    {{ section.name }}
                                </option>
                            </select>
                        </label>
                        <label class="text-sm font-medium text-gray-700">
                            হিসাবের ধরন
                            <select v-model="form.mode" class="mt-1 w-full rounded-lg border-gray-300 text-sm"
                                @change="resetAllocations">
                                <option value="percentage">শতাংশের ওজন (মোট ১০০%)</option>
                                <option value="conversion">নির্দিষ্ট নম্বরে রূপান্তর</option>
                            </select>
                        </label>
                    </div>

                    <fieldset :disabled="!form.academic_year_id" class="rounded-lg border border-gray-200 p-4">
                        <legend class="px-2 text-sm font-semibold text-gray-800">পরীক্ষা নির্বাচন ও অবদান</legend>
                        <p v-if="!exams.length" class="text-sm text-gray-500">শ্রেণি ও শিক্ষাবর্ষ নির্বাচন করলে পরীক্ষার তালিকা আসবে।</p>
                        <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <div v-for="exam in exams" :key="exam.id"
                                class="flex items-center gap-3 rounded-lg border border-gray-200 p-3">
                                <input :id="`combined-exam-${exam.id}`" v-model="form.exam_ids" type="checkbox" :value="String(exam.id)"
                                    class="rounded border-gray-300 text-blue-600" @change="resetAllocations">
                                <label :for="`combined-exam-${exam.id}`" class="min-w-0 flex-1 text-sm text-gray-800">
                                    {{ exam.bn_name || exam.name }}
                                    <span v-if="exam.class_name" class="block text-xs text-gray-500">{{ exam.class_name }}</span>
                                </label>
                                <span v-if="selectedExamIds.includes(String(exam.id))" class="flex items-center gap-1">
                                    <input v-model.number="form.allocations[String(exam.id)]" type="number" min="0.01"
                                        :max="form.mode === 'percentage' ? 100 : 1000" step="0.01"
                                        :aria-label="`${exam.name} ${form.mode === 'percentage' ? 'weight percent' : 'converted full marks'}`"
                                        class="w-24 rounded border-gray-300 text-right text-sm">
                                    <span class="text-xs text-gray-500">{{ form.mode === 'percentage' ? '%' : 'নম্বর' }}</span>
                                </span>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span :class="allocationIsValid ? 'text-green-700' : 'text-amber-700'">
                                {{ form.mode === 'percentage' ? `মোট ওজন: ${allocationTotal.toFixed(2)}% (১০০% হতে হবে)` : `মোট রূপান্তর পূর্ণমান: ${allocationTotal.toFixed(2)}` }}
                            </span>
                            <span class="text-gray-500">কমপক্ষে ২টি পরীক্ষা নির্বাচন করুন।</span>
                        </div>
                    </fieldset>

                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit" :disabled="form.exam_ids.length < 2 || !allocationIsValid"
                            class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                            ফলাফল তৈরি করুন
                        </button>
                        <span v-if="errorMessage" class="text-sm text-red-700">{{ errorMessage }}</span>
                    </div>
                </form>
            </section>

            <div v-if="report && reportIsCurrent" class="combined-editor">
            <section id="combined-report" :style="reportStyle" class="combined-preview min-w-0 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
                <header v-if="viewMode !== 'allMarksheets'" class="combined-document-header mb-5">
                    <div class="combined-header-logo">
                        <img v-if="institute.logo_url" :src="institute.logo_url" :alt="`${institute.name || 'Institute'} logo`" class="combined-institute-logo">
                    </div>
                    <div class="combined-header-content text-center">
                        <h2 class="text-xl font-bold text-gray-900">{{ institute.bn_name || institute.name }}</h2>
                        <p v-if="institute.address" class="text-sm text-gray-600">{{ institute.address }}</p>
                        <h3 class="mt-2 text-lg font-bold">
                            {{ viewMode === 'tabulation' ? label('Combined Tabulation Sheet', 'সমন্বিত টেবুলেশন শিট') : label('Combined Marksheet', 'সমন্বিত নম্বরপত্র') }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-700">
                            {{ localized({ name: report.class_name, bn_name: report.class_bn_name }) }} · {{ report.academic_year }} · {{ report.section_name }}
                        </p>
                        <p class="mt-1 text-xs text-gray-600">
                            <span v-for="(exam, index) in report.exams" :key="exam.id">
                                {{ localized(exam) }} ({{ exam.allocation }}{{ report.mode === 'percentage' ? '%' : label(' marks', ' নম্বর') }})<span v-if="index < report.exams.length - 1"> + </span>
                            </span>
                        </p>
                    </div>
                    <div class="combined-header-qr">
                        <img v-if="combinedQrCode" :src="combinedQrCode" :alt="label('Combined results QR code', 'সমন্বিত ফলাফলের QR কোড')" class="combined-qr-code">
                        <span v-else class="combined-qr-placeholder" aria-hidden="true"></span>
                    </div>
                </header>

                <div v-if="viewMode === 'tabulation'" id="tabulation-sheet" class="print-surface overflow-x-auto">
                    <table class="w-full min-w-max border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="border border-gray-400 px-2 py-2">{{ label('Roll', 'রোল') }}</th>
                                <th class="border border-gray-400 px-2 py-2 text-left">{{ label('Student', 'শিক্ষার্থী') }}</th>
                                <th v-for="subject in visibleSubjects" :key="subject.id" class="border border-gray-400 px-2 py-2">
                                    {{ localized(subject) }}
                                </th>
                                <th v-for="column in visibleSummaryColumns" :key="column.key" class="border border-gray-400 px-2 py-2">
                                    {{ label(column.en, column.bn) }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in report.rows" :key="row.student.id">
                                <td class="border border-gray-400 px-2 py-2 text-center">{{ row.student.roll_number }}</td>
                                <td class="border border-gray-400 px-2 py-2">{{ row.student.bn_name || row.student.name }}</td>
                                <td v-for="subject in visibleSubjects" :key="subject.id" class="border border-gray-400 px-2 py-2 text-center">
                                    <template v-if="subjectResult(row, subject)?.applies">
                                        {{ subjectResult(row, subject).obtained }} / {{ subjectResult(row, subject).full_marks }}
                                        <span v-if="subjectResult(row, subject).incomplete" class="block text-xs text-red-700">{{ label('Incomplete', 'অসম্পূর্ণ') }}</span>
                                    </template>
                                    <span v-else>—</span>
                                </td>
                                <td v-for="column in visibleSummaryColumns" :key="column.key" class="border border-gray-400 px-2 py-2 text-center"
                                    :class="{ 'font-semibold': column.key === 'total' }">
                                    {{ tabulationSummaryValue(row, column.key) }}
                                </td>
                            </tr>
                            <tr v-if="!report.rows.length">
                                <td :colspan="visibleSubjects.length + 2 + visibleSummaryColumns.length"
                                    class="border border-gray-400 px-3 py-5 text-center text-gray-500">
                                    {{ label('No students found.', 'এই শ্রেণিতে কোনো শিক্ষার্থী পাওয়া যায়নি।') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <article v-else-if="viewMode === 'marksheet' && selectedStudent" id="individual-sheet" class="print-surface mx-auto max-w-3xl">
                    <div class="mb-4 grid grid-cols-2 gap-2 border-y border-gray-300 py-3 text-sm">
                        <p><strong>{{ label('Name', 'নাম') }}:</strong> {{ localized(selectedStudent.student) }}</p>
                        <p><strong>{{ label('Roll', 'রোল') }}:</strong> {{ selectedStudent.student.roll_number }}</p>
                        <p><strong>{{ label('Student ID', 'আইডি') }}:</strong> {{ selectedStudent.student.student_id }}</p>
                        <p><strong>{{ label('Section', 'সেকশন') }}:</strong> {{ selectedStudent.student.section_name || '—' }}</p>
                        <p><strong>{{ label("Father's Name", 'পিতার নাম') }}:</strong> {{ selectedStudent.student.father_name || '—' }}</p>
                        <p><strong>{{ label("Mother's Name", 'মাতার নাম') }}:</strong> {{ selectedStudent.student.mother_name || '—' }}</p>
                        <p><strong>{{ label('Merit Rank', 'মেধাক্রম') }}:</strong> {{ selectedStudent.merit_rank ?? '—' }}</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="border border-gray-400 px-2 py-2">{{ label('Subject', 'বিষয়') }}</th>
                                    <th v-for="exam in visibleExams" :key="exam.id" class="border border-gray-400 px-2 py-2">
                                        {{ localized(exam) }}
                                    </th>
                                    <th v-for="column in visibleSummaryColumns" :key="column.key" class="border border-gray-400 px-2 py-2">
                                        {{ column.key === 'total' ? label('Obtained / Full Marks', 'প্রাপ্ত / পূর্ণমান')
                                            : column.key === 'grade' ? label('Grade', 'গ্রেড')
                                            : visibleColumns.point ? label('Point / GPA', 'পয়েন্ট / GPA') : label('GPA', 'GPA') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="subject in visibleSubjects" :key="subject.id">
                                    <template v-if="subjectResult(selectedStudent, subject)?.applies">
                                        <td class="border border-gray-400 px-2 py-2">{{ localized(subject) }}</td>
                                        <td v-for="exam in visibleExams" :key="exam.id" class="border border-gray-400 px-2 py-2 text-center">
                                            <template v-if="examComponent(subjectResult(selectedStudent, subject), exam.id)">
                                                {{ examComponent(subjectResult(selectedStudent, subject), exam.id).obtained ?? label('Missing', 'অসম্পূর্ণ') }}
                                                / {{ examComponent(subjectResult(selectedStudent, subject), exam.id).full_marks }}
                                                <small class="block text-gray-500">
                                                    ({{ label('Original', 'মূল') }} {{ examComponent(subjectResult(selectedStudent, subject), exam.id).original_marks ?? '—' }}
                                                    / {{ examComponent(subjectResult(selectedStudent, subject), exam.id).original_full_marks }})
                                                </small>
                                            </template>
                                            <span v-else>—</span>
                                        </td>
                                        <td v-for="column in visibleSummaryColumns" :key="column.key" class="border border-gray-400 px-2 py-2 text-center">
                                            <template v-if="column.key === 'total'">
                                                {{ subjectResult(selectedStudent, subject).obtained }} / {{ subjectResult(selectedStudent, subject).full_marks }}
                                            </template>
                                            <template v-else-if="column.key === 'grade'">{{ subjectResult(selectedStudent, subject).grade }}</template>
                                            <template v-else>{{ visibleColumns.point ? subjectResult(selectedStudent, subject).point : '—' }}</template>
                                        </td>
                                    </template>
                                </tr>
                            </tbody>
                            <tfoot v-if="visibleSummaryColumns.length">
                                <tr class="font-semibold">
                                    <td :colspan="1 + visibleExams.length" class="border border-gray-400 px-2 py-2 text-right">{{ label('Grand Total', 'সর্বমোট') }}</td>
                                    <td v-for="column in visibleSummaryColumns" :key="column.key" class="border border-gray-400 px-2 py-2 text-center">
                                        <template v-if="column.key === 'total'">{{ selectedStudent.total_marks }} / {{ selectedStudent.total_full_marks }}</template>
                                        <template v-else-if="column.key === 'grade'">{{ selectedStudent.grade }}</template>
                                        <template v-else>{{ label('GPA', 'GPA') }}: {{ selectedStudent.incomplete ? '—' : selectedStudent.gpa }}</template>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p v-if="selectedStudent.incomplete" class="mt-3 text-sm font-medium text-red-700">
                        {{ label('Some marks are missing. This result is incomplete; GPA and grade are not final.', 'কিছু পরীক্ষার নম্বর এন্ট্রি হয়নি। ফলাফল অসম্পূর্ণ; GPA/গ্রেড চূড়ান্ত নয়।') }}
                    </p>
                    <div class="mt-12 grid grid-cols-3 gap-6 text-center text-xs">
                        <p class="border-t border-gray-500 pt-2">{{ label('Class Teacher', 'শ্রেণি শিক্ষকের স্বাক্ষর') }}</p>
                        <p class="border-t border-gray-500 pt-2">{{ label('Exam Controller', 'পরীক্ষা নিয়ন্ত্রকের স্বাক্ষর') }}</p>
                        <p class="border-t border-gray-500 pt-2">{{ label('Head Teacher', 'প্রধান শিক্ষকের স্বাক্ষর') }}</p>
                    </div>
                </article>
                <div v-else-if="viewMode === 'allMarksheets'" id="all-marksheets" class="print-surface">
                    <article v-for="row in report.rows" :key="row.student.id" class="marksheet-page mx-auto max-w-3xl">
                        <header class="combined-document-header mb-5">
                            <div class="combined-header-logo">
                                <img v-if="institute.logo_url" :src="institute.logo_url" :alt="`${institute.name || 'Institute'} logo`" class="combined-institute-logo">
                            </div>
                            <div class="combined-header-content text-center">
                                <h2 class="text-xl font-bold">{{ localized(institute) }}</h2>
                                <p v-if="institute.address" class="text-sm">{{ institute.address }}</p>
                                <h3 class="mt-2 text-lg font-bold">{{ label('Combined Marksheet', 'সমন্বিত নম্বরপত্র') }}</h3>
                                <p class="text-sm">{{ localized({ name: report.class_name, bn_name: report.class_bn_name }) }} · {{ report.academic_year }} · {{ report.section_name }}</p>
                            </div>
                            <div class="combined-header-qr">
                                <img v-if="combinedQrCode" :src="combinedQrCode" :alt="label('Combined results QR code', 'সমন্বিত ফলাফলের QR কোড')" class="combined-qr-code">
                                <span v-else class="combined-qr-placeholder" aria-hidden="true"></span>
                            </div>
                        </header>
                        <div class="mb-4 grid grid-cols-2 gap-2 border-y border-gray-300 py-3 text-sm">
                            <p><strong>{{ label('Name', 'নাম') }}:</strong> {{ localized(row.student) }}</p>
                            <p><strong>{{ label('Roll', 'রোল') }}:</strong> {{ row.student.roll_number }}</p>
                            <p><strong>{{ label('Student ID', 'আইডি') }}:</strong> {{ row.student.student_id }}</p>
                            <p><strong>{{ label('Section', 'সেকশন') }}:</strong> {{ row.student.section_name || '—' }}</p>
                            <p><strong>{{ label("Father's Name", 'পিতার নাম') }}:</strong> {{ row.student.father_name || '—' }}</p>
                            <p><strong>{{ label("Mother's Name", 'মাতার নাম') }}:</strong> {{ row.student.mother_name || '—' }}</p>
                            <p><strong>{{ label('Merit Rank', 'মেধাক্রম') }}:</strong> {{ row.merit_rank ?? '—' }}</p>
                        </div>
                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="border border-gray-400 px-2 py-2">{{ label('Subject', 'বিষয়') }}</th>
                                    <th v-for="exam in visibleExams" :key="exam.id" class="border border-gray-400 px-2 py-2">
                                        {{ localized(exam) }}
                                    </th>
                                    <th v-for="column in visibleSummaryColumns" :key="column.key" class="border border-gray-400 px-2 py-2">
                                        {{ column.key === 'total' ? label('Obtained / Full Marks', 'প্রাপ্ত / পূর্ণমান')
                                            : column.key === 'grade' ? label('Grade', 'গ্রেড')
                                            : visibleColumns.point ? label('Point / GPA', 'পয়েন্ট / GPA') : label('GPA', 'GPA') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="subject in visibleSubjects" :key="subject.id">
                                    <template v-if="subjectResult(row, subject)?.applies">
                                        <td class="border border-gray-400 px-2 py-2">{{ localized(subject) }}</td>
                                        <td v-for="exam in visibleExams" :key="exam.id" class="border border-gray-400 px-2 py-2 text-center">
                                            <template v-if="examComponent(subjectResult(row, subject), exam.id)">
                                                        {{ examComponent(subjectResult(row, subject), exam.id).obtained ?? '—' }}
                                                        / {{ examComponent(subjectResult(row, subject), exam.id).full_marks }}
                                            </template>
                                            <span v-else>—</span>
                                        </td>
                                        <td v-for="column in visibleSummaryColumns" :key="column.key" class="border border-gray-400 px-2 py-2 text-center">
                                            <template v-if="column.key === 'total'">
                                                {{ subjectResult(row, subject).obtained }} / {{ subjectResult(row, subject).full_marks }}
                                            </template>
                                            <template v-else-if="column.key === 'grade'">{{ subjectResult(row, subject).grade }}</template>
                                            <template v-else>{{ visibleColumns.point ? subjectResult(row, subject).point : '—' }}</template>
                                        </td>
                                    </template>
                                </tr>
                            </tbody>
                            <tfoot v-if="visibleSummaryColumns.length">
                                <tr class="font-semibold">
                                    <td :colspan="visibleExams.length + 1" class="border border-gray-400 px-2 py-2 text-right">{{ label('Grand Total', 'সর্বমোট') }}</td>
                                    <td v-for="column in visibleSummaryColumns" :key="column.key" class="border border-gray-400 px-2 py-2 text-center">
                                        <template v-if="column.key === 'total'">{{ row.total_marks }} / {{ row.total_full_marks }}</template>
                                        <template v-else-if="column.key === 'grade'">{{ row.grade }}</template>
                                        <template v-else>{{ label('GPA', 'GPA') }}: {{ row.incomplete ? '—' : row.gpa }}</template>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                        <div class="mt-12 grid grid-cols-3 gap-6 text-center text-xs">
                            <p class="border-t border-gray-500 pt-2">{{ label('Class Teacher', 'শ্রেণি শিক্ষকের স্বাক্ষর') }}</p>
                            <p class="border-t border-gray-500 pt-2">{{ label('Exam Controller', 'পরীক্ষা নিয়ন্ত্রকের স্বাক্ষর') }}</p>
                            <p class="border-t border-gray-500 pt-2">{{ label('Head Teacher', 'প্রধান শিক্ষকের স্বাক্ষর') }}</p>
                        </div>
                    </article>
                </div>
                <p v-else-if="viewMode === 'marksheet'" class="py-8 text-center text-sm text-gray-500 no-print">
                    ব্যক্তিগত মার্কশিট দেখতে শিক্ষার্থী নির্বাচন করুন।
                </p>
            </section>
            <aside class="combined-sidebar no-print">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-900">{{ label('Print & Layout', 'প্রিন্ট ও সাজসজ্জা') }}</h2>
                    <Link :href="route('combined-results.index')" class="text-xs text-blue-700 hover:underline">
                        {{ label('New Report', 'নতুন রিপোর্ট') }}
                    </Link>
                </div>
                <button type="button" @click="finalizeReport" :disabled="isFinalizing || !reportIsCurrent"
                    class="mb-2 w-full rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">
                    {{ isFinalizing ? 'প্রকাশ করা হচ্ছে…' : 'চূড়ান্ত করে শিক্ষার্থীদের দেখান' }}
                </button>
                <p v-if="finalizationMessage" class="mb-3 rounded-lg bg-green-50 p-3 text-xs text-green-800">
                    {{ finalizationMessage }}
                </p>
                <p v-if="errorMessage" class="mb-3 rounded-lg bg-red-50 p-3 text-xs text-red-700">
                    {{ errorMessage }}
                </p>
                <button type="button" @click="printReport"
                    :disabled="viewMode === 'marksheet' && !selectedStudent"
                    class="mb-4 w-full rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-50">
                    {{ label('Print current view', 'বর্তমান শিট প্রিন্ট') }}
                </button>
                <div class="mb-4 grid gap-2">
                    <button v-for="option in [
                        { value: 'tabulation', en: 'Tabulation sheet', bn: 'টেবুলেশন শিট' },
                        { value: 'marksheet', en: 'Individual marksheet', bn: 'ব্যক্তিগত মার্কশিট' },
                        { value: 'allMarksheets', en: 'Print all marksheets', bn: 'সবার মার্কশিট একসাথে' },
                    ]" :key="option.value" type="button" @click="viewMode = option.value"
                        :class="viewMode === option.value ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-gray-200 text-gray-700'"
                        class="rounded-md border px-3 py-2 text-left text-sm">
                        {{ label(option.en, option.bn) }}
                    </button>
                </div>
                <label v-if="viewMode === 'marksheet'" class="mb-4 block text-sm font-medium text-gray-700">
                    {{ label('Student', 'শিক্ষার্থী') }}
                    <select v-model="selectedStudentId" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        <option value="">{{ label('Select student', 'শিক্ষার্থী নির্বাচন করুন') }}</option>
                        <option v-for="row in report.rows" :key="row.student.id" :value="String(row.student.id)">
                            {{ row.student.roll_number }} — {{ localized(row.student) }}
                        </option>
                    </select>
                </label>
                <label class="mb-3 block text-sm font-medium text-gray-700">
                    {{ label('Language', 'ভাষা') }}
                    <select v-model="language" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        <option value="bn">বাংলা</option>
                        <option value="en">English</option>
                    </select>
                </label>
                <label class="mb-3 block text-sm font-medium text-gray-700">
                    {{ label('Font', 'ফন্ট') }}
                    <select v-model="fontFamily" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        <option value='"Noto Sans Bengali", "SolaimanLipi", sans-serif'>Noto Sans Bengali</option>
                        <option value='"Noto Serif Bengali", "SolaimanLipi", serif'>Noto Serif Bengali</option>
                        <option value="Arial, sans-serif">Arial</option>
                        <option value="Tahoma, sans-serif">Tahoma</option>
                    </select>
                </label>
                <label class="mb-4 block text-sm font-medium text-gray-700">
                    {{ label('Font size', 'ফন্ট সাইজ') }}: {{ fontSize }}pt
                    <input v-model.number="fontSize" type="range" min="9" max="20" class="mt-2 w-full">
                </label>
                <label class="mb-4 block text-sm font-medium text-gray-700">
                    {{ label('Page orientation', 'পেজের দিক') }}
                    <select v-model="pageOrientation" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        <option value="portrait">{{ label('Portrait', 'পোর্ট্রেট') }}</option>
                        <option value="landscape">{{ label('Landscape', 'ল্যান্ডস্কেপ') }}</option>
                    </select>
                </label>
                <fieldset class="border-t border-gray-200 pt-3">
                    <legend class="mb-2 text-sm font-semibold text-gray-800">{{ label('Add / remove columns', 'কলাম সংযোজন / বিয়োজন') }}</legend>
                    <p class="mb-1 text-xs font-medium text-gray-500">{{ label('Subjects', 'বিষয়') }}</p>
                    <label v-for="subject in report.subjects" :key="`col-subject-${subject.id}`"
                        class="mb-1 flex items-center gap-2 text-sm">
                        <input v-model="visibleSubjectIds" type="checkbox" :value="subject.id" class="rounded border-gray-300">
                        {{ localized(subject) }}
                        <span v-if="selectionOrder(visibleSubjectIds, subject.id)" class="combined-column-order" aria-hidden="true">
                            {{ selectionOrder(visibleSubjectIds, subject.id) }}
                        </span>
                    </label>
                    <template v-if="viewMode !== 'tabulation'">
                        <p class="mb-1 mt-3 text-xs font-medium text-gray-500">{{ label('Exams', 'পরীক্ষা') }}</p>
                        <label v-for="exam in report.exams" :key="`col-exam-${exam.id}`"
                            class="mb-1 flex items-center gap-2 text-sm">
                            <input v-model="visibleExamIds" type="checkbox" :value="exam.id" class="rounded border-gray-300">
                            {{ localized(exam) }}
                            <span v-if="selectionOrder(visibleExamIds, exam.id)" class="combined-column-order" aria-hidden="true">
                                {{ selectionOrder(visibleExamIds, exam.id) }}
                            </span>
                        </label>
                    </template>
                    <p class="mb-1 mt-3 text-xs font-medium text-gray-500">{{ label('Summary', 'সারাংশ') }}</p>
                    <label v-for="column in summaryColumns" :key="column.key" class="mb-1 flex items-center gap-2 text-sm">
                        <input :checked="visibleColumns[column.key]" type="checkbox" class="rounded border-gray-300"
                            @change="toggleSummaryColumn(column.key)">
                        {{ label(column.en, column.bn) }}
                        <span v-if="selectionOrder(activeSummaryColumnKeys, column.key)" class="combined-column-order" aria-hidden="true">
                            {{ selectionOrder(activeSummaryColumnKeys, column.key) }}
                        </span>
                    </label>
                </fieldset>
            </aside>
            </div>
            <p v-else-if="report" class="rounded-lg bg-amber-50 p-4 text-sm text-amber-800 no-print">
                নির্বাচিত পরীক্ষা, ওজন বা শ্রেণি পরিবর্তিত হয়েছে। নতুন সেটিংস অনুযায়ী শিট দেখতে আবার “ফলাফল তৈরি করুন” চাপুন।
            </p>
        </main>
    </InstituteLayout>
</template>

<style>
.combined-editor {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 18rem;
    align-items: start;
    gap: 1rem;
}

.combined-sidebar {
    position: sticky;
    top: 1rem;
    max-height: calc(100vh - 2rem);
    overflow-y: auto;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    background: white;
    padding: 1rem;
}

.combined-column-order {
    display: inline-grid;
    width: 1.25rem;
    height: 1.25rem;
    flex: 0 0 1.25rem;
    place-items: center;
    margin-left: auto;
    border-radius: 9999px;
    background: #dbeafe;
    color: #1d4ed8;
    font-size: 0.625rem;
    font-weight: 700;
}

.combined-preview {
    font-family: var(--combined-font-family);
    font-size: var(--combined-font-size);
}

.combined-document-header {
    display: grid;
    grid-template-columns: 96px minmax(0, 1fr) 96px;
    align-items: center;
    gap: 1rem;
    border-bottom: 2px solid #1f2937;
    padding-bottom: 0.75rem;
}

.combined-header-logo,
.combined-header-qr {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 72px;
}

.combined-institute-logo,
.combined-qr-code {
    max-width: 72px;
    max-height: 72px;
    object-fit: contain;
    border-radius: 0.5rem;
}

.combined-header-content {
    min-width: 0;
}

.combined-qr-placeholder {
    display: inline-block;
    width: 72px;
    height: 72px;
    border: 1px dashed #d1d5db;
    border-radius: 0.5rem;
    background: #f9fafb;
}

.combined-preview table {
    font-size: var(--combined-font-size);
}

@media (max-width: 1023px) {
    .combined-editor {
        grid-template-columns: minmax(0, 1fr);
    }

    .combined-sidebar {
        position: static;
        max-height: none;
    }
}

@media print {
    body * {
        visibility: hidden !important;
    }

    #combined-report, #combined-report * {
        visibility: visible !important;
    }

    #combined-report {
        position: absolute;
        inset: 0;
        width: 100%;
        max-width: 100%;
        border: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
    }

    #combined-report .print-surface,
    #combined-report .overflow-x-auto {
        width: 100% !important;
        max-width: 100% !important;
        overflow: visible !important;
    }

    .no-print {
        display: none !important;
    }

    .combined-preview {
        font-family: var(--combined-font-family) !important;
        font-size: var(--combined-font-size) !important;
    }

    .combined-preview table {
        width: 100% !important;
        table-layout: fixed;
        font-size: clamp(6pt, var(--combined-font-size), 9pt) !important;
        overflow-wrap: anywhere;
    }

    .marksheet-page {
        break-after: page;
        page-break-after: always;
    }

    .marksheet-page:last-child {
        break-after: auto;
        page-break-after: auto;
    }

    @page {
        size: landscape;
        margin: 10mm;
    }
}
</style>
