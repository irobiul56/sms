<script setup>
import { Head, Link } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    documentType: { type: String, required: true },
    institute: { type: Object, default: () => ({}) },
    exam: { type: Object, default: () => ({}) },
    student: { type: Object, default: null },
    subjects: { type: Array, default: () => [] },
    result: { type: Object, default: null },
    documents: { type: Array, default: () => [] },
    seatPlan: { type: Object, default: null },
    tabulation: { type: Object, default: null },
    isBulkMarksheets: { type: Boolean, default: false },
    isPublicMarksheet: { type: Boolean, default: false },
    publicMarksheetUrl: { type: String, default: null },
});

const language = ref('bn');
const fontFamily = ref('"Noto Serif Bengali", "Noto Sans Bengali", "SolaimanLipi", serif');
const fontSize = ref(13);
const pageSize = ref('A4');
const pageOrientation = ref('portrait');
const textColor = ref('#111827');
const headingColor = ref('#111827');
const tableHeaderColor = ref('#e5e7eb');
const showSchoolName = ref(true);
const showAddress = ref(true);
const showPhone = ref(true);
const showDocumentTitle = ref(true);
const showStudentDetails = ref(true);
const showResultSummary = ref(true);
const showSignatures = ref(true);
const qrCodes = ref({});
const qrCodeError = ref(false);
const marksheetColumns = ref([
    { key: 'serial', label: '#', bnLabel: '#' },
    { key: 'subject', label: 'Subject', bnLabel: 'বিষয়' },
    { key: 'full_marks', label: 'Full Marks', bnLabel: 'পূর্ণমান' },
    { key: 'pass_marks', label: 'Pass Marks', bnLabel: 'পাস নম্বর' },
    { key: 'theory_marks', label: 'Theory', bnLabel: 'তত্ত্বীয়', component: true },
    { key: 'mcq_marks', label: 'MCQ', bnLabel: 'এমসিকিউ', component: true },
    { key: 'practical_marks', label: 'Practical', bnLabel: 'ব্যবহারিক', component: true },
    { key: 'obtained', label: 'Obtained', bnLabel: 'প্রাপ্ত নম্বর' },
    { key: 'grade', label: 'Grade', bnLabel: 'গ্রেড' },
    { key: 'point', label: 'Point', bnLabel: 'পয়েন্ট' },
]);
const selectedMarksheetColumnKeys = ref([
    'serial', 'subject', 'full_marks', 'pass_marks', 'obtained', 'grade', 'point',
]);
const label = (english, bengali) => language.value === 'bn' ? bengali : english;
const localized = (item, englishKey = 'name', bengaliKey = 'bn_name') => (
    language.value === 'bn'
        ? (item?.[bengaliKey] || item?.[englishKey] || '—')
        : (item?.[englishKey] || item?.[bengaliKey] || '—')
);
const pageTitle = computed(() => {
    const names = {
        marksheet: label('Student Marksheet', 'শিক্ষার্থীর নম্বরপত্র'),
        'admit-card': label('Examination Admit Card', 'পরীক্ষার প্রবেশপত্র'),
        'seat-plan': label('Examination Seat Plan', 'পরীক্ষার আসন বিন্যাস'),
        tabulation: label('Class Tabulation Sheet', 'শ্রেণির টেবুলেশন শিট'),
    };

    return names[props.documentType] ?? label('Exam Document', 'পরীক্ষার নথি');
});
const printDocuments = computed(() => props.documents.length
    ? props.documents
    : props.student
    ? [{
        student: props.student,
        subjects: props.subjects,
        result: props.result,
        publicMarksheetUrl: props.publicMarksheetUrl,
    }]
    : []);
watch(printDocuments, async (documents) => {
    qrCodeError.value = false;
    try {
    const generated = await Promise.all(documents.map(async (document) => {
        if (props.documentType !== 'marksheet' || !document.publicMarksheetUrl) return null;
        const dataUrl = await QRCode.toDataURL(document.publicMarksheetUrl, {
            errorCorrectionLevel: 'M',
            margin: 1,
            width: 144,
        });

        return [document.student.id, dataUrl];
    }));
    qrCodes.value = Object.fromEntries(generated.filter(Boolean));
    } catch (error) {
    console.error('Unable to generate marksheet QR codes.', error);
    qrCodeError.value = true;
    }
}, { immediate: true });
const allMarksheetSubjects = computed(() => printDocuments.value.flatMap(document => document.subjects || []));
const availableMarksheetColumns = computed(() => marksheetColumns.value.filter(column => (
    !column.component || allMarksheetSubjects.value.some(subject => Number(subject[column.key.replace('_marks', '_full_marks')]) > 0)
)));
const visibleMarksheetColumns = computed(() => selectedMarksheetColumnKeys.value
    .map(key => availableMarksheetColumns.value.find(column => column.key === key))
    .filter(Boolean));
const documentNavigation = computed(() => [
    { type: 'admit-card', routeName: 'exam-documents.class-admit-cards', english: 'Admit Card', bengali: 'প্রবেশপত্র', icon: 'fa-id-card' },
    { type: 'seat-plan', routeName: 'exam-documents.seat-plan', english: 'Seat Plan', bengali: 'আসন বিন্যাস', icon: 'fa-chair' },
    { type: 'tabulation', routeName: 'exam-documents.class-tabulation-sheet', english: 'Tabulation Sheet', bengali: 'টেবুলেশন শিট', icon: 'fa-table-list' },
    { type: 'marksheet', routeName: 'exam-documents.class-marksheets', english: 'Marksheet', bengali: 'নম্বরপত্র', icon: 'fa-file-lines' },
].map(item => {
    const url = new URL(route(item.routeName, { exam: props.exam.id }), window.location.origin);
    if (props.exam.class_id) url.searchParams.set('class_id', props.exam.class_id);

    return { ...item, href: url.toString() };
}));
const printPageName = computed(() =>
    `marksheet-${pageSize.value.toLowerCase()}-${pageOrientation.value}`
);
const marksheetStyle = computed(() => ({
    '--marksheet-font-family': fontFamily.value,
    '--marksheet-font-size': `${fontSize.value}pt`,
    '--marksheet-text-color': textColor.value,
    '--marksheet-heading-color': headingColor.value,
    '--marksheet-table-header-color': tableHeaderColor.value,
}));
const doorColumns = computed(() => {
    const students = props.seatPlan?.students ?? [];
    const midpoint = Math.ceil(students.length / 2);

    return [students.slice(0, midpoint), students.slice(midpoint)];
});
const seatLabelSheets = computed(() => {
    const students = props.seatPlan?.students ?? [];
    const labelsPerSheet = 8;
    const sheets = [];

    for (let index = 0; index < students.length; index += labelsPerSheet) {
        sheets.push(students.slice(index, index + labelsPerSheet));
    }

    return sheets;
});
const roomSeat = (room, row, seat) => room.students.find(
    assignedSeat => assignedSeat.row === row && assignedSeat.seat === seat,
);
const printDocument = () => window.print();

const resetMarksheetSettings = () => {
    language.value = 'bn';
    fontFamily.value = '"Noto Serif Bengali", "Noto Sans Bengali", "SolaimanLipi", serif';
    fontSize.value = 13;
    pageSize.value = 'A4';
    pageOrientation.value = 'portrait';
    textColor.value = '#111827';
    headingColor.value = '#111827';
    tableHeaderColor.value = '#e5e7eb';
    showSchoolName.value = true;
    showAddress.value = true;
    showPhone.value = true;
    showDocumentTitle.value = true;
    showStudentDetails.value = true;
    showResultSummary.value = true;
    showSignatures.value = true;
    selectedMarksheetColumnKeys.value = [
        'serial', 'subject', 'full_marks', 'pass_marks', 'obtained', 'grade', 'point',
    ];
};
const toggleMarksheetColumn = (key) => {
    selectedMarksheetColumnKeys.value = selectedMarksheetColumnKeys.value.includes(key)
        ? selectedMarksheetColumnKeys.value.filter(columnKey => columnKey !== key)
        : [...selectedMarksheetColumnKeys.value, key];
};
</script>

<template>
    <div>
        <Head :title="pageTitle" />
        <main :class="['min-w-0 p-4 sm:p-6', { 'exam-document-editor': !isPublicMarksheet }]">
            <div class="mb-4 flex flex-wrap gap-3 no-print">
                <Link v-if="!isPublicMarksheet" :href="route('exam-documents.index')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700">
                    {{ label('Back to Exam Documents', 'পরীক্ষার নথিতে ফিরে যান') }}
                </Link>
            </div>
            <p v-if="qrCodeError" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 no-print">
                {{ label('Unable to create the public marksheet QR code. Please refresh the page and try again.', 'পাবলিক মার্কশিটের QR কোড তৈরি করা যায়নি। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।') }}
            </p>

            <div id="exam-document" :class="{ 'bulk-marksheet-preview': isBulkMarksheets }"
                :style="isBulkMarksheets ? marksheetStyle : undefined">
                <article v-if="documentType === 'tabulation' && tabulation"
                    class="tabulation-page bg-white p-5 text-gray-900 shadow-sm">
                    <header class="document-heading-with-logo tabulation-heading mb-5">
                        <div class="document-logo-slot">
                            <img v-if="institute.logo_url" :src="institute.logo_url" :alt="`${institute.name} logo`" class="document-institute-logo">
                        </div>
                        <div class="text-center">
                            <h1 class="text-xl font-bold">{{ institute.name }}</h1>
                            <p v-if="institute.address" class="text-sm">{{ institute.address }}</p>
                            <h2 class="mt-2 text-lg font-bold">{{ localized(exam) }} — {{ label('Tabulation Sheet', 'টেবুলেশন শিট') }}</h2>
                            <p>{{ localized(exam, 'class_name', 'class_bn_name') }} · {{ exam.academic_year }} · {{ tabulation.section_name === 'All Sections' ? label('All Sections', 'সকল শাখা') : tabulation.section_name }}</p>
                        </div>
                        <div aria-hidden="true"></div>
                    </header>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-xs">
                            <thead>
                                <tr>
                                    <th class="border border-gray-600 p-2">{{ label('Roll', 'রোল') }}</th>
                                    <th class="border border-gray-600 p-2">{{ label('Student Name', 'শিক্ষার্থীর নাম') }}</th>
                                    <th v-for="subject in tabulation.subjects" :key="subject.id"
                                        class="border border-gray-600 p-2">
                                        {{ localized(subject) }}<span v-if="subject.full_marks"> / {{ subject.full_marks }}</span>
                                        <span v-if="subject.pass_marks !== null && subject.pass_marks !== undefined" class="block font-normal">
                                            {{ label('Pass', 'পাস') }}: {{ subject.pass_marks }}
                                        </span>
                                    </th>
                                    <th class="border border-gray-600 p-2">{{ label('Total', 'মোট') }}</th>
                                    <th class="border border-gray-600 p-2">GPA</th>
                                    <th class="border border-gray-600 p-2">{{ label('Grade', 'গ্রেড') }}</th>
                                    <th class="border border-gray-600 p-2">{{ label('Position', 'মেধাক্রম') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in tabulation.rows" :key="row.student.id">
                                    <td class="border border-gray-500 p-2 text-center">{{ row.student.roll_number }}</td>
                                    <td class="border border-gray-500 p-2">{{ localized(row.student) }}</td>
                                    <td v-for="subject in row.subjects" :key="subject.id"
                                        class="border border-gray-500 p-2 text-center">
                                        <span v-if="subject.mark?.is_absent">{{ label('Absent', 'অনুপস্থিত') }}</span>
                                        <span v-else>{{ subject.mark?.total_marks ?? '—' }}</span>
                                    </td>
                                    <td class="border border-gray-500 p-2 text-center">
                                        {{ row.result ? `${row.result.total_marks} / ${row.result.total_full_marks}` : '—' }}
                                    </td>
                                    <td class="border border-gray-500 p-2 text-center">{{ row.result?.gpa ?? '—' }}</td>
                                    <td class="border border-gray-500 p-2 text-center">{{ row.result?.final_grade ?? '—' }}</td>
                                    <td class="border border-gray-500 p-2 text-center">{{ row.result?.class_position ?? row.result?.position ?? '—' }}</td>
                                </tr>
                                <tr v-if="!tabulation.rows.length">
                                    <td :colspan="tabulation.subjects.length + 6" class="border border-gray-500 p-5 text-center">
                                        {{ label('No students found for the selected filters.', 'নির্বাচিত ফিল্টারে কোনো শিক্ষার্থী পাওয়া যায়নি।') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <template v-else-if="documentType !== 'seat-plan'">
                <article v-for="document in printDocuments"
                    :key="document.student.id"
                    :class="['print-page', {
                        'admit-card-page': documentType === 'admit-card',
                        'bulk-marksheet-page': isBulkMarksheets,
                    }]"
                    :style="isBulkMarksheets ? { page: printPageName } : undefined"
                    class="mb-6 bg-white p-6 text-gray-900 shadow-sm sm:p-10">
                    <header v-if="documentType === 'admit-card'" class="admit-card-header">
                        <img v-if="institute.logo_url" :src="institute.logo_url" :alt="`${institute.name} logo`" class="admit-card-school-logo">
                        <svg v-else class="admit-card-emblem" viewBox="0 0 100 100" aria-hidden="true">
                            <circle cx="50" cy="50" r="44" fill="none" stroke="currentColor" stroke-width="2" />
                            <path d="M22 37c10-4 19-2 28 4 9-6 18-8 28-4v30c-10-4-19-2-28 4-9-6-18-8-28-4z"
                                fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round" />
                            <path d="M50 41v30M31 44c6-1 11 0 15 3m23-3c-6-1-11 0-15 3"
                                fill="none" stroke="currentColor" stroke-width="2" />
                            <path d="M30 25h40M36 19h28" stroke="currentColor" stroke-width="2" />
                        </svg>
                        <div class="admit-card-heading">
                            <h1>{{ institute.name }}</h1>
                            <p v-if="institute.address">{{ institute.address }}</p>
                            <p v-if="institute.phone">{{ label('Phone', 'ফোন') }}: {{ institute.phone }}</p>
                            <p class="admit-exam-title">{{ localized(exam) }} - {{ exam.academic_year }}</p>
                            <h2>{{ label('Admit Card', 'প্রবেশপত্র') }}</h2>
                        </div>
                        <img v-if="document.student.photo" :src="document.student.photo" :alt="`${document.student.name} photo`" class="admit-card-student-photo">
                    </header>
                    <header v-else-if="documentType === 'marksheet'" class="marksheet-header mb-6 border-b-2 border-gray-800 pb-4">
                        <img v-if="institute.logo_url" :src="institute.logo_url" :alt="`${institute.name} logo`" class="marksheet-institute-logo">
                        <div class="text-center">
                            <h1 v-if="!isBulkMarksheets || showSchoolName" class="text-2xl font-bold uppercase">{{ institute.name }}</h1>
                            <p v-if="institute.address && (!isBulkMarksheets || showAddress)" class="mt-1 text-sm">{{ institute.address }}</p>
                            <p v-if="institute.phone && (!isBulkMarksheets || showPhone)" class="text-sm">{{ label('Phone', 'ফোন') }}: {{ institute.phone }}</p>
                            <h2 v-if="!isBulkMarksheets || showDocumentTitle" class="mt-3 text-xl font-bold">{{ pageTitle }}</h2>
                            <p class="mt-1 text-sm">{{ localized(exam) }} · {{ localized(exam, 'class_name', 'class_bn_name') }} · {{ exam.academic_year }}</p>
                        </div>
                        <img v-if="qrCodes[document.student.id]" :src="qrCodes[document.student.id]" :alt="label('Public marksheet QR code', 'পাবলিক মার্কশিটের QR কোড')" class="marksheet-qr-code">
                        <span v-else class="marksheet-qr-placeholder" aria-hidden="true"></span>
                    </header>
                    <header v-else class="mb-6 border-b-2 border-gray-800 pb-4 text-center">
                        <h1 v-if="!isBulkMarksheets || showSchoolName" class="text-2xl font-bold uppercase">{{ institute.name }}</h1>
                        <p v-if="institute.address && (!isBulkMarksheets || showAddress)" class="mt-1 text-sm">{{ institute.address }}</p>
                        <p v-if="institute.phone && (!isBulkMarksheets || showPhone)" class="text-sm">{{ label('Phone', 'ফোন') }}: {{ institute.phone }}</p>
                        <h2 v-if="!isBulkMarksheets || showDocumentTitle" class="mt-4 text-xl font-bold">{{ pageTitle }}</h2>
                        <p class="mt-1 text-sm">{{ localized(exam) }} · {{ localized(exam, 'class_name', 'class_bn_name') }} · {{ exam.academic_year }}</p>
                    </header>

                    <img v-if="documentType === 'admit-card' && institute.logo_url"
                        :src="institute.logo_url" alt="" class="admit-card-watermark">
                    <svg v-else-if="documentType === 'admit-card'" class="admit-card-watermark" viewBox="0 0 100 100" aria-hidden="true">
                        <circle cx="50" cy="50" r="44" fill="none" stroke="currentColor" stroke-width="2" />
                        <path d="M22 37c10-4 19-2 28 4 9-6 18-8 28-4v30c-10-4-19-2-28 4-9-6-18-8-28-4z"
                            fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round" />
                        <path d="M50 41v30M31 44c6-1 11 0 15 3m23-3c-6-1-11 0-15 3"
                            fill="none" stroke="currentColor" stroke-width="2" />
                    </svg>

                    <template v-if="documentType === 'marksheet'">
                        <section v-if="showStudentDetails || !isBulkMarksheets" class="marksheet-content mb-5 grid gap-2 text-sm sm:grid-cols-2">
                            <p><strong>{{ label('Student', 'শিক্ষার্থী') }}:</strong> {{ localized(document.student) }}</p>
                            <p><strong>{{ label('Student ID', 'শিক্ষার্থী আইডি') }}:</strong> {{ document.student.student_id }}</p>
                            <p><strong>{{ label("Father's Name", 'পিতার নাম') }}:</strong> {{ document.student.father_name || '—' }}</p>
                            <p><strong>{{ label("Mother's Name", 'মাতার নাম') }}:</strong> {{ document.student.mother_name || '—' }}</p>
                            <p><strong>{{ label('Class / Section', 'শ্রেণি / শাখা') }}:</strong> {{ localized(exam, 'class_name', 'class_bn_name') }} / {{ document.student.section_name || '—' }}</p>
                            <p><strong>{{ label('Roll', 'রোল') }}:</strong> {{ document.student.roll_number }}</p>
                        </section>
                        <section v-if="showResultSummary || !isBulkMarksheets" class="marksheet-content mb-5 grid gap-2 rounded border border-gray-400 p-4 text-sm sm:grid-cols-3">
                            <p><strong>{{ label('Total', 'মোট নম্বর') }}:</strong> {{ document.result.total_marks }} / {{ document.result.total_full_marks }}</p>
                            <p><strong>GPA:</strong> {{ document.result.gpa }}</p>
                            <p><strong>{{ label('Final Grade', 'চূড়ান্ত গ্রেড') }}:</strong> {{ document.result.final_grade }}</p>
                            <p><strong>{{ label('Position', 'মেধাক্রম') }}:</strong> {{ document.result.position ?? document.result.class_position ?? '—' }}</p>
                            <p><strong>{{ label('Absent Subjects', 'অনুপস্থিত বিষয়') }}:</strong> {{ document.result.total_absent }}</p>
                            <p><strong>{{ label('Subjects', 'বিষয় সংখ্যা') }}:</strong> {{ document.result.total_subjects }}</p>
                        </section>
                        <table class="marksheet-content marksheet-table w-full border-collapse text-sm">
                            <thead>
                                <tr>
                                    <th v-for="column in visibleMarksheetColumns" :key="column.key"
                                        class="border border-gray-700 p-2"
                                        :class="{ 'text-left': ['serial', 'subject'].includes(column.key) }">
                                        {{ language === 'bn' ? column.bnLabel : column.label }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(subject, index) in document.subjects" :key="subject.name">
                                    <td v-for="column in visibleMarksheetColumns" :key="column.key"
                                        class="border border-gray-700 p-2 text-center">
                                        <template v-if="column.key === 'serial'">{{ index + 1 }}</template>
                                        <template v-else-if="column.key === 'subject'">{{ localized(subject) }}</template>
                                        <template v-else-if="column.key === 'full_marks'">{{ subject.full_marks }}</template>
                                        <template v-else-if="column.key === 'pass_marks'">{{ subject.pass_marks }}</template>
                                        <template v-else-if="column.key === 'theory_marks'">{{ subject.mark?.theory_marks ?? '—' }}</template>
                                        <template v-else-if="column.key === 'mcq_marks'">{{ subject.mark?.mcq_marks ?? '—' }}</template>
                                        <template v-else-if="column.key === 'practical_marks'">{{ subject.mark?.practical_marks ?? '—' }}</template>
                                        <template v-else-if="column.key === 'obtained'">
                                            {{ subject.mark?.is_absent ? label('Absent', 'অনুপস্থিত') : (subject.mark?.total_marks ?? '—') }}
                                        </template>
                                        <template v-else-if="column.key === 'grade'">{{ subject.mark?.grade ?? '—' }}</template>
                                        <template v-else-if="column.key === 'point'">{{ subject.mark?.grade_point ?? '—' }}</template>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-if="showSignatures || !isBulkMarksheets" class="marksheet-content mt-16 grid grid-cols-2 gap-16 text-center text-sm">
                            <p class="border-t border-gray-700 pt-2">{{ label('Class Teacher', 'শ্রেণি শিক্ষক') }}</p>
                            <p class="border-t border-gray-700 pt-2">{{ label('Head Teacher', 'প্রধান শিক্ষক') }}</p>
                        </div>
                    </template>

                    <template v-else-if="documentType === 'admit-card'">
                        <section class="admit-card-details">
                            <div class="admit-card-row">
                                <strong>{{ label('Student Name:', 'ছাত্র/ছাত্রীর নাম:') }}</strong>
                                <span>{{ localized(document.student) }}</span>
                            </div>
                            <div class="admit-card-row">
                                <strong>{{ label("Father's Name:", 'পিতার নাম:') }}</strong>
                                <span>{{ document.student.father_name || '—' }}</span>
                            </div>
                            <div class="admit-card-row">
                                <strong>{{ label("Mother's Name:", 'মাতার নাম:') }}</strong>
                                <span>{{ document.student.mother_name || '—' }}</span>
                            </div>
                            <div class="admit-card-bottom-row">
                                <div class="admit-card-row">
                                    <strong>{{ label('Class:', 'শ্রেণি:') }}</strong>
                                    <span>{{ localized(exam, 'class_name', 'class_bn_name') }}{{ document.student.section_name ? ` - ${document.student.section_name}` : '' }}</span>
                                </div>
                                <div class="admit-card-row">
                                    <strong>{{ label('Roll No:', 'রোল নং:') }}</strong>
                                    <span>{{ document.student.roll_number }}</span>
                                </div>
                            </div>
                        </section>
                        <p class="admit-card-notice">
                            <strong>{{ label('Note:', 'বি. দ্র.:') }}</strong>
                            {{ label('Bring required materials such as this admit card, pens, geometry box and calculator (if applicable). No other papers are allowed.', 'পরীক্ষার সময় প্রয়োজনীয় উপকরণ, যেমন—প্রবেশপত্র, কলম, জ্যামিতি বক্স ও ক্যালকুলেটর (প্রযোজ্য ক্ষেত্রে) সঙ্গে আনতে হবে। এগুলো ছাড়া অন্য কোনো কাগজপত্র আনা যাবে না।') }}
                        </p>
                        <p class="admit-card-signature">{{ label('Class Teacher', 'শ্রেণি শিক্ষক') }}</p>
                    </template>
                </article>
                </template>

                <template v-else-if="documentType === 'seat-plan' && seatPlan">
                    <template v-if="!seatPlan.single_student && seatPlan.rooms?.length">
                    <article v-for="room in seatPlan.rooms" :key="room.room_name"
                        class="room-page mb-6 bg-white p-5 text-gray-900 shadow-sm">
                        <header class="document-heading-with-logo mb-4 border-b-2 border-gray-800 pb-3">
                            <div class="document-logo-slot">
                                <img v-if="institute.logo_url" :src="institute.logo_url" :alt="`${institute.name} logo`" class="document-institute-logo">
                            </div>
                            <div class="text-center">
                                <h1 class="text-2xl font-bold">{{ institute.name }}</h1>
                                <p v-if="institute.address" class="mt-1 text-xs">{{ institute.address }}</p>
                                <h2 class="mt-2 text-xl font-bold">{{ label('Examination Seat Plan', 'পরীক্ষার আসন বিন্যাস') }}</h2>
                                <p class="mt-1 text-sm">{{ localized(exam) }} · {{ localized(exam, 'class_name', 'class_bn_name') }} · {{ exam.academic_year }}</p>
                            </div>
                            <div aria-hidden="true"></div>
                        </header>
                        <div class="mb-3 flex flex-wrap justify-between gap-2 text-sm">
                            <p><strong>{{ label('Room:', 'কক্ষ:') }}</strong> {{ room.room_name }}</p>
                            <p><strong>{{ label('Class:', 'শ্রেণি:') }}</strong> {{ localized(exam, 'class_name', 'class_bn_name') }}</p>
                            <p><strong>{{ label('Section:', 'সেকশন:') }}</strong> {{ seatPlan.section_name }}</p>
                            <p><strong>{{ label('Total Students:', 'মোট শিক্ষার্থী:') }}</strong> {{ room.students.length }}</p>
                        </div>
                        <div v-if="room.students.length" class="door-seat-plan">
                            <table class="bench-row-table w-full table-fixed border-collapse text-center">
                                <thead>
                                    <tr>
                                        <th :colspan="room.bench_rows" class="border border-gray-500 bg-green-50 px-2 py-1 text-sm font-bold text-red-700">
                                            {{ label('Bench Row', 'বেঞ্চের সারি') }}
                                        </th>
                                    </tr>
                                    <tr>
                                        <th v-for="benchRow in room.bench_rows" :key="benchRow"
                                            class="border border-gray-500 bg-slate-100 px-2 py-1 text-sm">
                                            {{ label('Row', 'সারি') }} {{ benchRow }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="seatNumber in room.seats_per_bench" :key="seatNumber">
                                        <td v-for="benchRow in room.bench_rows" :key="benchRow"
                                            class="border border-gray-500 px-1 py-1 text-blue-800">
                                            <template v-if="roomSeat(room, benchRow, seatNumber)">
                                                <div class="text-[9px] leading-tight">
                                                    {{ localized(roomSeat(room, benchRow, seatNumber), 'class_name', 'class_bn_name') }}
                                                </div>
                                                <div class="text-base font-semibold">
                                                    {{ roomSeat(room, benchRow, seatNumber).student.roll_number }}
                                                </div>
                                            </template>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-else class="py-8 text-center text-gray-500">{{ label('No students assigned to this room.', 'এই রুমে কোনো শিক্ষার্থী বরাদ্দ হয়নি।') }}</p>
                    </article>
                    </template>

                    <article v-else-if="!seatPlan.single_student" class="room-page mb-6 bg-white p-5 text-gray-900 shadow-sm">
                        <header class="document-heading-with-logo mb-4 border-b-2 border-gray-800 pb-3">
                            <div class="document-logo-slot">
                                <img v-if="institute.logo_url" :src="institute.logo_url" :alt="`${institute.name} logo`" class="document-institute-logo">
                            </div>
                            <div class="text-center">
                                <h1 class="text-xl font-bold">{{ institute.name }}</h1>
                                <p v-if="institute.address" class="text-xs">{{ institute.address }}</p>
                                <h2 class="mt-1 text-lg font-bold">{{ label('Examination Seat Plan', 'পরীক্ষার আসন বিন্যাস') }}</h2>
                                <p class="text-sm">{{ localized(exam) }} · {{ localized(exam, 'class_name', 'class_bn_name') }} · {{ exam.academic_year }}</p>
                            </div>
                            <div aria-hidden="true"></div>
                        </header>
                        <div v-if="seatPlan.students.length" class="grid gap-3">
                            <table v-for="(column, columnIndex) in doorColumns" :key="columnIndex"
                                class="w-full border-collapse text-[9px]">
                                <thead>
                                    <tr>
                                        <th class="border border-gray-500 px-1 py-1 text-left">{{ label('Seat', 'আসন') }}</th>
                                        <th class="border border-gray-500 px-1 py-1 text-left">{{ label('Student Name', 'শিক্ষার্থীর নাম') }}</th>
                                        <th class="border border-gray-500 px-1 py-1 text-left">{{ label('Roll', 'রোল') }}</th>
                                        <th class="border border-gray-500 px-1 py-1 text-left">{{ label('Section', 'সেকশন') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="seat in column" :key="`${seat.row}-${seat.seat}`">
                                        <td class="border border-gray-400 px-1 py-1 whitespace-nowrap">{{ seat.row }}-{{ seat.seat }}</td>
                                        <td class="border border-gray-400 px-1 py-1 whitespace-nowrap">{{ localized(seat.student) }}</td>
                                        <td class="border border-gray-400 px-1 py-1 whitespace-nowrap">
                                            {{ localized(seat, 'class_name', 'class_bn_name') }} — {{ seat.student.roll_number }}
                                        </td>
                                        <td class="border border-gray-400 px-1 py-1 whitespace-nowrap">{{ seat.student.section_name || '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-else class="py-8 text-center text-gray-500">{{ label('No students found for this exam.', 'এই পরীক্ষায় কোনো শিক্ষার্থী পাওয়া যায়নি।') }}</p>
                    </article>

                    <article v-if="seatPlan.single_student && seatPlan.students.length"
                        class="single-seat-sheet bg-white text-gray-900 shadow-sm">
                        <section class="flex items-center justify-center">
                            <div class="seat-label w-full border-2 border-black p-1">
                                <div class="seat-label-inner border border-black px-2 py-1 text-center">
                                    <div class="seat-label-heading">
                                        <div class="seat-label-logo-slot">
                                            <img v-if="institute.logo_url" :src="institute.logo_url" :alt="`${institute.name} logo`" class="seat-label-logo">
                                        </div>
                                        <div class="text-center">
                                            <h2 class="truncate text-sm font-bold">{{ institute.name }}</h2>
                                            <p v-if="institute.address" class="truncate text-[9px]">{{ institute.address }}</p>
                                        </div>
                                        <div aria-hidden="true"></div>
                                    </div>
                                    <p class="text-[10px] font-semibold">{{ localized(exam) }} · {{ exam.academic_year }}</p>
                                    <p class="text-xs font-bold">{{ label('Seat Plan', 'আসন বিন্যাস') }}</p>
                                    <div class="mt-1 space-y-0.5 text-left text-[11px]">
                                        <p class="seat-label-field"><strong>{{ label('Name:', 'নাম:') }}</strong><span>{{ localized(seatPlan.students[0].student) }}</span></p>
                                        <p class="seat-label-field"><strong>{{ label('Class:', 'শ্রেণি:') }}</strong><span>{{ localized(seatPlan.students[0], 'class_name', 'class_bn_name') }}{{ seatPlan.students[0].student.section_name ? ` - ${seatPlan.students[0].student.section_name}` : '' }}</span></p>
                                        <p class="seat-label-field"><strong>{{ label('Roll:', 'রোল:') }}</strong><span>{{ seatPlan.students[0].student.roll_number }}</span></p>
                                        <p class="seat-label-field"><strong>{{ label('Room / Seat:', 'কক্ষ / আসন:') }}</strong><span>{{ seatPlan.students[0].room_name || seatPlan.room_name }} / {{ seatPlan.students[0].row }}-{{ seatPlan.students[0].seat }}</span></p>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </article>

                    <template v-if="!seatPlan.single_student">
                    <article v-for="(sheet, sheetIndex) in seatLabelSheets" :key="sheetIndex"
                        class="label-sheet mb-6 grid grid-cols-2 grid-rows-4 gap-3 bg-white p-2 text-gray-900 shadow-sm">
                        <section v-for="seat in sheet" :key="`${seat.row}-${seat.seat}`"
                            class="flex items-center justify-center">
                            <div class="seat-label w-full border-2 border-black p-1">
                                <div class="seat-label-inner border border-black px-2 py-1 text-center">
                                    <div class="seat-label-heading">
                                        <div class="seat-label-logo-slot">
                                            <img v-if="institute.logo_url" :src="institute.logo_url" :alt="`${institute.name} logo`" class="seat-label-logo">
                                        </div>
                                        <div class="text-center">
                                            <h2 class="truncate text-sm font-bold">{{ institute.name }}</h2>
                                            <p v-if="institute.address" class="truncate text-[9px]">{{ institute.address }}</p>
                                        </div>
                                        <div aria-hidden="true"></div>
                                    </div>
                                    <p class="text-[10px] font-semibold">{{ localized(exam) }} · {{ exam.academic_year }}</p>
                                    <p class="text-xs font-bold">{{ label('Seat Plan', 'আসন বিন্যাস') }}</p>
                                    <div class="mt-1 space-y-0.5 text-left text-[11px]">
                                        <p class="seat-label-field"><strong>{{ label('Name:', 'নাম:') }}</strong><span>{{ localized(seat.student) }}</span></p>
                                        <p class="seat-label-field"><strong>{{ label('Class:', 'শ্রেণি:') }}</strong><span>{{ localized(seat, 'class_name', 'class_bn_name') }}{{ seat.student.section_name ? ` - ${seat.student.section_name}` : '' }}</span></p>
                                        <p class="seat-label-field"><strong>{{ label('Roll:', 'রোল:') }}</strong><span>{{ seat.student.roll_number }}</span></p>
                                        <p class="seat-label-field"><strong>{{ label('Room / Seat:', 'কক্ষ / আসন:') }}</strong><span>{{ seat.room_name || seatPlan.room_name }} / {{ seat.row }}-{{ seat.seat }}</span></p>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </article>
                    </template>
                </template>
            </div>

            <aside v-if="!isPublicMarksheet" class="marksheet-settings no-print">
                <div class="marksheet-settings-heading">
                    <div>
                        <h2>{{ label('Document options', 'ডকুমেন্ট অপশন') }}</h2>
                        <p>{{ localized(exam) }} · {{ localized(exam, 'class_name', 'class_bn_name') }}</p>
                    </div>
                    <button v-if="isBulkMarksheets" type="button" class="marksheet-reset" :title="label('Reset settings', 'সেটিংস রিসেট')"
                        @click="resetMarksheetSettings">
                        <i class="fa-solid fa-rotate-left"></i>
                    </button>
                </div>

                <nav class="document-navigation" :aria-label="label('Exam documents', 'পরীক্ষার ডকুমেন্ট')">
                    <Link v-for="item in documentNavigation" :key="item.type" :href="item.href"
                        :aria-current="documentType === item.type ? 'page' : undefined"
                        :class="['document-navigation-link', { active: documentType === item.type }]">
                        <i :class="['fa-solid', item.icon]"></i>
                        <span>{{ label(item.english, item.bengali) }}</span>
                        <i v-if="documentType === item.type" class="fa-solid fa-chevron-right document-navigation-current"></i>
                    </Link>
                </nav>

                <div class="marksheet-setting-group">
                    <span class="marksheet-setting-label">{{ label('Language', 'ভাষা') }}</span>
                    <div class="marksheet-language-switch">
                        <button type="button" :class="{ active: language === 'bn' }" @click="language = 'bn'">বাংলা</button>
                        <button type="button" :class="{ active: language === 'en' }" @click="language = 'en'">English</button>
                    </div>
                </div>

                <label v-if="isBulkMarksheets" class="marksheet-setting-group">
                    <span class="marksheet-setting-label">{{ label('Font', 'ফন্ট') }}</span>
                    <select v-model="fontFamily">
                        <option value='"Noto Serif Bengali", "Noto Sans Bengali", "SolaimanLipi", serif'>Noto Bengali / Serif</option>
                        <option value="Arial, sans-serif">Arial</option>
                        <option value='"Times New Roman", serif'>Times New Roman</option>
                        <option value="Georgia, serif">Georgia</option>
                        <option value="Verdana, sans-serif">Verdana</option>
                    </select>
                </label>

                <label v-if="isBulkMarksheets" class="marksheet-setting-group">
                    <span class="marksheet-setting-label">
                        {{ label('Font size', 'ফন্ট সাইজ') }} <span>{{ fontSize }}pt</span>
                    </span>
                    <input v-model.number="fontSize" type="range" min="8" max="18">
                </label>

                <div v-if="isBulkMarksheets" class="marksheet-setting-row">
                    <label>
                        <span>{{ label('Page size', 'পেজ সাইজ') }}</span>
                        <select v-model="pageSize">
                            <option value="A4">A4</option>
                            <option value="Letter">Letter</option>
                            <option value="Legal">Legal</option>
                            <option value="A3">A3</option>
                        </select>
                    </label>
                    <label>
                        <span>{{ label('Orientation', 'পেজের দিক') }}</span>
                        <select v-model="pageOrientation">
                            <option value="portrait">{{ label('Portrait', 'পোর্ট্রেট') }}</option>
                            <option value="landscape">{{ label('Landscape', 'ল্যান্ডস্কেপ') }}</option>
                        </select>
                    </label>
                </div>

                <div v-if="isBulkMarksheets" class="marksheet-color-options">
                    <label>
                        <span>{{ label('Text color', 'লেখার রঙ') }}</span>
                        <input v-model="textColor" type="color">
                    </label>
                    <label>
                        <span>{{ label('Heading color', 'হেডিংয়ের রঙ') }}</span>
                        <input v-model="headingColor" type="color">
                    </label>
                    <label>
                        <span>{{ label('Table header', 'টেবিল হেডারের রঙ') }}</span>
                        <input v-model="tableHeaderColor" type="color">
                    </label>
                </div>

                <div v-if="isBulkMarksheets" class="marksheet-setting-group">
                    <h3 class="marksheet-setting-label">{{ label('Show / hide heading', 'হেডিং দেখান / লুকান') }}</h3>
                    <label class="marksheet-check-row"><input v-model="showSchoolName" type="checkbox"> {{ label('School name', 'প্রতিষ্ঠানের নাম') }}</label>
                    <label class="marksheet-check-row"><input v-model="showAddress" type="checkbox"> {{ label('Address', 'ঠিকানা') }}</label>
                    <label class="marksheet-check-row"><input v-model="showPhone" type="checkbox"> {{ label('Phone', 'ফোন') }}</label>
                    <label class="marksheet-check-row"><input v-model="showDocumentTitle" type="checkbox"> {{ label('Document title', 'ডকুমেন্টের শিরোনাম') }}</label>
                </div>

                <div v-if="documentType === 'marksheet'" class="marksheet-setting-group">
                    <h3 class="marksheet-setting-label">{{ label('Table columns', 'টেবিলের কলাম') }}</h3>
                    <label v-for="column in availableMarksheetColumns" :key="column.key" class="marksheet-check-row">
                        <input :checked="selectedMarksheetColumnKeys.includes(column.key)" type="checkbox"
                            :disabled="selectedMarksheetColumnKeys.includes(column.key) && visibleMarksheetColumns.length === 1"
                            @change="toggleMarksheetColumn(column.key)">
                        {{ language === 'bn' ? column.bnLabel : column.label }}
                        <span v-if="selectedMarksheetColumnKeys.includes(column.key)" class="column-order-badge">
                            {{ selectedMarksheetColumnKeys.indexOf(column.key) + 1 }}
                        </span>
                    </label>
                </div>

                <div v-if="isBulkMarksheets" class="marksheet-setting-group">
                    <h3 class="marksheet-setting-label">{{ label('Other sections', 'অন্যান্য অংশ') }}</h3>
                    <label class="marksheet-check-row"><input v-model="showStudentDetails" type="checkbox"> {{ label('Student details', 'শিক্ষার্থীর তথ্য') }}</label>
                    <label class="marksheet-check-row"><input v-model="showResultSummary" type="checkbox"> {{ label('Result summary', 'ফলাফলের সারাংশ') }}</label>
                    <label class="marksheet-check-row"><input v-model="showSignatures" type="checkbox"> {{ label('Signature area', 'স্বাক্ষরের জায়গা') }}</label>
                </div>

                <button type="button" @click="printDocument" class="marksheet-print-button">
                    <i class="fa-solid fa-print"></i>
                    {{ label('Print', 'প্রিন্ট') }} {{ printDocuments.length > 1 ? `(${printDocuments.length})` : '' }}
                </button>
            </aside>
        </main>
    </div>
</template>

<style>
.document-heading-with-logo {
    display: grid;
    grid-template-columns: 20mm minmax(0, 1fr) 20mm;
    align-items: center;
    gap: 3mm;
}

.document-logo-slot {
    display: flex;
    justify-content: flex-start;
}

.document-institute-logo {
    width: 18mm;
    height: 18mm;
    object-fit: contain;
}

.seat-label-heading {
    display: grid;
    grid-template-columns: 10mm minmax(0, 1fr) 10mm;
    align-items: center;
    gap: 1mm;
}

.seat-label-logo-slot {
    display: flex;
    justify-content: flex-start;
}

.seat-label-logo {
    width: 9mm;
    height: 9mm;
    object-fit: contain;
}

.marksheet-header {
    display: grid;
    grid-template-columns: 20mm minmax(0, 1fr) 22mm;
    align-items: center;
    gap: 3mm;
}

.marksheet-institute-logo,
.marksheet-qr-code {
    width: 20mm;
    height: 20mm;
    object-fit: contain;
}

.marksheet-qr-placeholder {
    width: 20mm;
    height: 20mm;
}

.exam-document-editor {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 300px;
    align-items: start;
    gap: 20px;
}

.exam-document-editor > div.no-print:first-child {
    grid-column: 1;
    grid-row: 1;
}

.exam-document-editor #exam-document {
    grid-column: 1;
    width: 100%;
    max-width: none;
}

.bulk-marksheet-preview .print-page {
    font-family: var(--marksheet-font-family);
    color: var(--marksheet-text-color);
}

.bulk-marksheet-preview .print-page > header h1,
.bulk-marksheet-preview .print-page > header h2 {
    color: var(--marksheet-heading-color);
}

.bulk-marksheet-preview .marksheet-content {
    font-family: var(--marksheet-font-family) !important;
    font-size: var(--marksheet-font-size) !important;
    color: var(--marksheet-text-color) !important;
}

.bulk-marksheet-preview .marksheet-table th {
    background: var(--marksheet-table-header-color);
    color: var(--marksheet-heading-color);
}

.marksheet-settings {
    position: sticky;
    top: 20px;
    grid-column: 2;
    grid-row: 1 / span 3;
    max-height: calc(100vh - 40px);
    overflow-y: auto;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 18px;
    background: white;
    box-shadow: 0 2px 10px rgb(15 23 42 / 7%);
}

.document-navigation {
    display: grid;
    gap: 5px;
    margin-bottom: 16px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e5e7eb;
}

.document-navigation-link {
    display: flex;
    min-height: 40px;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border: 1px solid transparent;
    border-radius: 8px;
    color: #475569;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: background-color .18s ease, color .18s ease, border-color .18s ease;
}

.document-navigation-link:hover {
    border-color: #dbeafe;
    background: #f8fafc;
    color: #1d4ed8;
}

.document-navigation-link.active {
    border-color: #bfdbfe;
    background: #eff6ff;
    color: #1d4ed8;
}

.document-navigation-link > i:first-child {
    width: 16px;
    text-align: center;
}

.document-navigation-current {
    margin-left: auto;
    font-size: 10px;
}

.column-order-badge {
    display: inline-grid;
    width: 20px;
    height: 20px;
    flex: 0 0 20px;
    place-items: center;
    margin-left: auto;
    border-radius: 50%;
    background: #dbeafe;
    color: #1d4ed8;
    font-size: 10px;
    font-weight: 700;
}

.marksheet-settings-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 18px;
}

.marksheet-settings-heading h2 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
}

.marksheet-settings-heading p {
    margin: 4px 0 0;
    color: #6b7280;
    font-size: 11px;
}

.marksheet-reset {
    width: 32px;
    height: 32px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: white;
    color: #4b5563;
    cursor: pointer;
}

.marksheet-setting-group {
    display: block;
    margin: 16px 0;
}

.marksheet-setting-label,
.marksheet-setting-row label > span,
.marksheet-color-options label > span {
    display: flex;
    justify-content: space-between;
    margin-bottom: 7px;
    color: #374151;
    font-size: 12px;
    font-weight: 600;
}

.marksheet-setting-group select,
.marksheet-setting-row select {
    width: 100%;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 8px;
    background: white;
    color: #111827;
    font-size: 12px;
}

.marksheet-language-switch,
.marksheet-setting-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 7px;
}

.marksheet-language-switch button {
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 7px;
    background: white;
    cursor: pointer;
}

.marksheet-language-switch button.active {
    border-color: #2563eb;
    background: #eff6ff;
    color: #1d4ed8;
}

.marksheet-color-options {
    display: grid;
    gap: 8px;
}

.marksheet-color-options label {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.marksheet-color-options input[type="color"] {
    width: 42px;
    height: 28px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    padding: 2px;
    background: white;
}

.marksheet-check-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 8px 0;
    color: #374151;
    font-size: 12px;
}

.marksheet-check-row input {
    accent-color: #2563eb;
}

.marksheet-print-button {
    display: inline-flex;
    width: 100%;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 0;
    border-radius: 7px;
    padding: 10px 14px;
    background: #2563eb;
    color: white;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}

.marksheet-print-button:hover {
    background: #1d4ed8;
}

@media (max-width: 1000px) {
    .exam-document-editor {
        grid-template-columns: minmax(0, 1fr);
    }

    .marksheet-settings {
        position: static;
        grid-column: 1;
        grid-row: auto;
        max-height: none;
    }
}

.admit-card-page {
    position: relative;
    box-sizing: border-box;
    width: 100%;
    min-height: 130mm;
    overflow: hidden;
    margin-bottom: 8mm;
    border: 1px solid #111;
    outline: 1px solid #111;
    outline-offset: 3px;
    padding: 5mm;
    font-family: "Noto Serif Bengali", "Noto Sans Bengali", "SolaimanLipi", serif;
}

.admit-card-watermark {
    position: absolute;
    z-index: 0;
    top: 50%;
    left: 50%;
    width: 70mm;
    height: 70mm;
    color: rgba(30, 64, 110, 0.12);
    opacity: 0.12;
    object-fit: contain;
    transform: translate(-50%, -40%);
}

.admit-card-page > :not(.admit-card-watermark) {
    position: relative;
    z-index: 1;
}

.admit-card-header {
    display: grid;
    grid-template-columns: 22mm minmax(0, 1fr) 24mm;
    align-items: start;
    gap: 3mm;
    position: relative;
    min-height: 42mm;
    padding: 1mm 2mm 3mm;
    text-align: center;
}

.admit-card-emblem {
    width: 22mm;
    height: 22mm;
    color: #172f50;
}

.admit-card-school-logo,
.admit-card-student-photo {
    width: 22mm;
    height: 26mm;
    object-fit: contain;
}

.admit-card-student-photo {
    border: 1px solid #444;
    object-fit: cover;
}

.admit-card-heading h1 {
    margin: 0;
    font-size: 20pt;
    font-weight: 700;
    line-height: 1.25;
}

.admit-card-heading p {
    margin: 1mm 0 0;
    font-size: 10pt;
    line-height: 1.25;
}

.admit-card-heading .admit-exam-title {
    margin-top: 2mm;
    font-size: 13pt;
    font-weight: 600;
}

.admit-card-heading h2 {
    display: inline-block;
    margin: 2mm 0 0;
    padding: 1mm 10mm;
    border: 1px solid #111;
    border-radius: 3mm;
    font-size: 18pt;
    font-weight: 700;
}

.admit-card-details {
    position: relative;
    z-index: 1;
    margin-top: 1mm;
    font-size: 12pt;
}

.admit-card-row {
    display: grid;
    grid-template-columns: 32mm minmax(0, 1fr);
    min-height: 10mm;
    align-items: stretch;
}

.admit-card-row > strong {
    display: flex;
    align-items: center;
    padding-right: 2mm;
    font-weight: 500;
}

.admit-card-row > span {
    display: flex;
    align-items: center;
    padding: 0 3mm;
    border: 1px dashed #333;
    font-weight: 600;
}

.admit-card-bottom-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
}

.admit-card-notice {
    position: relative;
    margin-top: 3mm;
    font-size: 11pt;
    line-height: 1.7;
}

.admit-card-notice strong {
    padding: 0 1mm;
    background: #111;
    color: white;
}

.admit-card-signature {
    position: absolute !important;
    right: 12mm;
    bottom: 4mm;
    font-size: 11pt;
}

.single-seat-sheet {
    display: flex;
    box-sizing: border-box;
    min-height: 281mm;
    align-items: center;
    justify-content: center;
    padding: 8mm;
}

.single-seat-sheet > section {
    width: 100%;
    max-width: 190mm;
}

@media print {
    @page marksheet-a4-portrait {
        size: A4 portrait;
        margin: 12mm;
    }

    @page marksheet-a4-landscape {
        size: A4 landscape;
        margin: 10mm;
    }

    @page marksheet-letter-portrait {
        size: letter portrait;
        margin: 12mm;
    }

    @page marksheet-letter-landscape {
        size: letter landscape;
        margin: 10mm;
    }

    @page marksheet-legal-portrait {
        size: legal portrait;
        margin: 12mm;
    }

    @page marksheet-legal-landscape {
        size: legal landscape;
        margin: 10mm;
    }

    @page marksheet-a3-portrait {
        size: A3 portrait;
        margin: 12mm;
    }

    @page marksheet-a3-landscape {
        size: A3 landscape;
        margin: 10mm;
    }

    @page {
        size: portrait;
        margin: 12mm;
    }

    @page admit-card {
        size: A4 portrait;
        margin: 8mm;
    }

    @page room {
        size: A4 landscape;
        margin: 8mm;
    }

    @page labels {
        size: A4 portrait;
        margin: 8mm;
    }

    @page tabulation {
        size: A4 landscape;
        margin: 10mm;
    }

    body * {
        visibility: hidden !important;
    }

    #exam-document,
    #exam-document * {
        visibility: visible !important;
    }

    #exam-document {
        position: absolute;
        inset: 0;
        width: 100%;
        max-width: none !important;
    }

    .print-page {
        width: 100%;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        break-after: page;
        page-break-after: always;
    }

    .bulk-marksheet-page {
        margin-bottom: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
    }

    .tabulation-page {
        page: tabulation;
        width: 100%;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
    }

    .tabulation-page table {
        width: 100%;
        border-collapse: collapse;
        font-size: 8pt;
    }

    .tabulation-page th,
    .tabulation-page td {
        border: 1px solid #444 !important;
        padding: 3px !important;
        color: #000 !important;
    }

    .tabulation-page thead {
        display: table-header-group;
    }

    .tabulation-page tr {
        break-inside: avoid;
    }

    .admit-card-page {
        page: admit-card;
        position: relative;
        box-sizing: border-box;
        width: 100%;
        height: 130mm;
        overflow: hidden;
        margin: 0 0 8mm !important;
        padding: 5mm !important;
        break-after: auto;
        page-break-after: auto;
    }

    .admit-card-page:nth-of-type(2n):not(:last-of-type) {
        break-after: page;
        page-break-after: always;
    }

    .room-page {
        page: room;
        box-sizing: border-box;
        width: 100%;
        height: 194mm;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        break-after: page;
        page-break-after: always;
    }

    .bench-row-table {
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .bench-row-table th,
    .bench-row-table td {
        height: 8mm;
        border: 1px solid #444 !important;
        padding: 1.5mm !important;
        color: #000 !important;
    }

    .bench-row-table thead {
        display: table-header-group;
    }

    .label-sheet {
        page: labels;
        box-sizing: border-box;
        width: 100%;
        height: 281mm;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        break-after: page;
        page-break-after: always;
    }

    .single-seat-sheet {
        page: labels;
        width: 100%;
        height: 281mm;
        min-height: 0;
        margin: 0 !important;
        padding: 0 !important;
        break-after: auto;
        page-break-after: auto;
    }

    .print-page:last-child,
    .admit-card-page:last-child,
    .room-page:last-child,
    .label-sheet:last-child {
        break-after: auto;
        page-break-after: auto;
    }

    .seat-label {
        aspect-ratio: 1.65 / 1;
    }

    .seat-label-inner {
        height: 100%;
    }

    .seat-label-field {
        display: grid;
        grid-template-columns: 19mm minmax(0, 1fr);
        align-items: center;
        min-height: 6mm;
        border: 1px dashed #222;
    }

    .seat-label-field strong {
        padding-left: 1mm;
    }

    .seat-label-field span {
        overflow: hidden;
        padding: 0 1mm;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .no-print {
        display: none !important;
    }

    .marksheet-settings {
        display: none !important;
    }

    table {
        break-inside: auto;
    }

    tr,
    img {
        break-inside: avoid;
    }
}
</style>
