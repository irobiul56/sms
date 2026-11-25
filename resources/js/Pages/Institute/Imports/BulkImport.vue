<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import InstituteLayout from '@/Layouts/InstituteLayout.vue';

const props = defineProps({
    kind: { type: String, required: true },
    title: { type: String, required: true },
    columns: { type: Array, required: true },
    requiredColumns: { type: Array, required: true },
    classes: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
    academicYears: { type: Array, default: () => [] },
    electiveSubjects: { type: Array, default: () => [] },
    optionalSubjects: { type: Array, default: () => [] },
    additionalSubjects: { type: Array, default: () => [] },
    returnRoute: { type: String, required: true },
});

const fileInput = ref(null);
const fileName = ref('');
const parsing = ref(false);
const parseError = ref('');
const previewRows = ref([]);
const form = useForm({
    rows: [],
    academic_year_id: '',
    class_id: '',
    section_id: '',
    group: '',
});
const isStudentImport = computed(() => props.kind === 'students');
const availableSections = computed(() => props.sections.filter(
    section => String(section.institute_class_id) === String(form.class_id)
));
const availableElectiveSubjects = computed(() => props.electiveSubjects.filter(
    subject => String(subject.class_id) === String(form.class_id)
));
const availableOptionalSubjects = computed(() => props.optionalSubjects.filter(
    subject => String(subject.class_id) === String(form.class_id)
));
const availableAdditionalSubjects = computed(() => props.additionalSubjects.filter(
    subject => String(subject.class_id) === String(form.class_id)
));
const selectedClass = computed(() => props.classes.find(
    item => String(item.id) === String(form.class_id)
));
const selectedClassGroup = computed(() => selectedClass.value?.group || '');
const groupLabel = (group) => ({
    science: 'Science (বিজ্ঞান)',
    commerce: 'Commerce (বাণিজ্য)',
    arts: 'Arts (মানবিক)',
    general: 'General (সাধারণ)',
}[group] || group);
const canSubmitImport = computed(() => (
    !isStudentImport.value
    || (!!form.academic_year_id && !!form.class_id && !!form.section_id)
));
watch(selectedClassGroup, (group) => {
    form.group = group || '';
});
const uploadRoute = computed(() => route(`${props.kind}.import.store`));
const fileId = computed(() => `bulk-${props.kind}-xlsx`);
let excelJsPromise;

const loadExcelJS = () => {
    if (!excelJsPromise) {
        excelJsPromise = import('exceljs').then(module => module.default);
    }

    return excelJsPromise;
};

const cellText = (value) => {
    if (value === null || value === undefined) return '';
    if (value instanceof Date) return value.toISOString().slice(0, 10);
    if (typeof value === 'object') {
        if (value.text) return String(value.text).trim();
        if (value.result !== undefined) return cellText(value.result);
        if (Array.isArray(value.richText)) return value.richText.map(part => part.text).join('').trim();
        return '';
    }

    return String(value).trim();
};

const excelDate = (value) => {
    if (value instanceof Date) return value.toISOString().slice(0, 10);
    if (typeof value === 'number' && value > 0) {
        const date = new Date(Date.UTC(1899, 11, 30) + Math.round(value) * 86400000);
        return date.toISOString().slice(0, 10);
    }

    const text = cellText(value);
    if (/^\d{4}-\d{2}-\d{2}$/.test(text)) return text;
    const dmy = text.match(/^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$/);
    if (dmy) return `${dmy[3]}-${dmy[2].padStart(2, '0')}-${dmy[1].padStart(2, '0')}`;

    return text;
};

const parseWorkbook = async (file) => {
    parseError.value = '';
    form.clearErrors();
    form.rows = [];
    previewRows.value = [];
    fileName.value = file?.name ?? '';
    if (!file) return;

    if (!/\.xlsx$/i.test(file.name)) {
        parseError.value = 'Please select an .xlsx file.';
        return;
    }
    if (file.size > 10 * 1024 * 1024) {
        parseError.value = 'The file must be 10 MB or smaller.';
        return;
    }

    parsing.value = true;
    try {
        const ExcelJS = await loadExcelJS();
        const workbook = new ExcelJS.Workbook();
        await workbook.xlsx.load(await file.arrayBuffer());
        const sheet = workbook.worksheets[0];
        if (!sheet || sheet.rowCount < 2) {
            throw new Error('The first worksheet must contain a header row and at least one data row.');
        }

        const headers = sheet.getRow(1).values
            .slice(1)
            .map(value => cellText(value).toLowerCase().replace(/\s+/g, '_'));
        const missing = props.requiredColumns.filter(column => !headers.includes(column));
        if (missing.length) {
            throw new Error(`Missing required column headers: ${missing.join(', ')}`);
        }

        const parsedRows = [];
        sheet.eachRow((row, rowNumber) => {
            if (rowNumber === 1) return;
            const values = {};
            headers.forEach((header, index) => {
                if (!header || !props.columns.includes(header)) return;
                const value = row.getCell(index + 1).value;
                values[header] = ['date_of_birth', 'joining_date'].includes(header)
                    ? excelDate(value)
                    : cellText(value);
            });
            if (Object.values(values).some(value => value !== '')) parsedRows.push(values);
        });

        if (!parsedRows.length) {
            throw new Error('No data rows were found in the first worksheet.');
        }
        if (parsedRows.length > 500) {
            throw new Error('A maximum of 500 rows can be imported at once.');
        }

        form.rows = parsedRows;
        previewRows.value = parsedRows.slice(0, 5);
    } catch (error) {
        parseError.value = error instanceof Error ? error.message : 'The XLSX file could not be read.';
    } finally {
        parsing.value = false;
    }
};

const downloadTemplate = async () => {
    const ExcelJS = await loadExcelJS();
    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet('Import');
    sheet.addRow(props.columns);
    sheet.getRow(1).font = { bold: true, color: { argb: 'FFFFFFFF' } };
    sheet.getRow(1).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF116653' } };
    sheet.views = [{ state: 'frozen', ySplit: 1 }];
    props.columns.forEach((column, index) => {
        sheet.getColumn(index + 1).width = Math.max(16, column.length + 3);
    });

    const readme = workbook.addWorksheet('Instructions');
    readme.addRow(['Required columns']);
    readme.addRow([props.requiredColumns.join(', ')]);
    readme.addRow([]);
    readme.addRow(['Use the exact column headings in the Import sheet. Maximum 500 rows per upload.']);
    if (isStudentImport.value) {
        readme.addRow(['Select academic year, class, and section on the Bulk Import Students page. A class group is selected automatically when applicable.']);
        readme.addRow(['password: required student login password, at least 8 characters. Keep this workbook private and share passwords securely.']);
        readme.addRow(['If the selected class has elective subjects, provide one elective_subject_code per student.']);
        readme.addRow(['If the selected class has optional subjects, provide one optional_subject_code per student.']);
        readme.addRow(['If the selected class has additional subjects, provide one additional_subject_code per student.']);
        readme.addRow(['Student status is set to active automatically.']);
        readme.addRow(['gender: male, female, or other']);
        readme.addRow(['date_of_birth: YYYY-MM-DD']);
    } else {
        readme.addRow(['gender: male, female, or other']);
        readme.addRow(['joining_date and date_of_birth: YYYY-MM-DD']);
        readme.addRow(['status: active, inactive, retired, transferred; defaults to active']);
    }
    readme.getColumn(1).width = 100;
    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `${props.kind}-import-template.xlsx`;
    anchor.click();
    URL.revokeObjectURL(url);
};

const submitImport = () => {
    if (!form.rows.length || parsing.value) return;
    form.post(uploadRoute.value, {
        preserveScroll: true,
        onError: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
    });
};
</script>

<template>
    <Head :title="title" />
    <InstituteLayout>
        <main class="min-w-0 flex-1 p-5">
            <section class="mx-auto max-w-5xl space-y-5">
                <header class="flex flex-col justify-between gap-4 rounded-xl bg-white p-5 shadow-sm sm:flex-row sm:items-center">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-800">{{ title }}</h1>
                        <p class="mt-1 text-sm text-gray-500">Upload an XLSX workbook to create multiple {{ kind }} at once.</p>
                    </div>
                    <Link :href="route(returnRoute)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        Back to {{ isStudentImport ? 'Students' : 'Teachers' }}
                    </Link>
                </header>

                <section class="rounded-xl bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="font-semibold text-gray-800">1. Download the XLSX template</h2>
                            <p class="mt-1 text-sm text-gray-500">Keep the provided column headers unchanged.</p>
                        </div>
                        <button type="button" @click="downloadTemplate" class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-800">
                            <i class="fa-solid fa-download mr-2"></i>Download template
                        </button>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2 text-xs">
                        <span v-for="column in requiredColumns" :key="column" class="rounded-full bg-amber-50 px-3 py-1 text-amber-800">
                            {{ column }} *
                        </span>
                    </div>
                </section>

                <section v-if="isStudentImport" class="rounded-xl bg-white p-5 shadow-sm">
                    <h2 class="font-semibold text-gray-800">2. Select student academic details</h2>
                    <p class="mt-1 text-sm text-gray-500">These details apply to every student in this upload and are not taken from the XLSX file.</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm font-medium text-gray-700">
                            Academic Year <span class="text-red-600">*</span>
                            <select v-model="form.academic_year_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-emerald-600 focus:ring-emerald-600">
                                <option value="">Select academic year</option>
                                <option v-for="year in academicYears" :key="year.id" :value="String(year.id)">
                                    {{ year.session }}{{ year.is_current ? ' (Current)' : '' }}
                                </option>
                            </select>
                            <span v-if="form.errors.academic_year_id" class="mt-1 block text-xs text-red-600">{{ form.errors.academic_year_id }}</span>
                        </label>
                        <label class="block text-sm font-medium text-gray-700">
                            Class <span class="text-red-600">*</span>
                            <select v-model="form.class_id" @change="form.section_id = ''" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-emerald-600 focus:ring-emerald-600">
                                <option value="">Select class</option>
                                <option v-for="schoolClass in classes" :key="schoolClass.id" :value="String(schoolClass.id)">
                                    {{ schoolClass.name }}{{ schoolClass.group ? ` — ${schoolClass.group_label || schoolClass.group}` : '' }}
                                </option>
                            </select>
                            <span v-if="form.errors.class_id" class="mt-1 block text-xs text-red-600">{{ form.errors.class_id }}</span>
                        </label>
                        <label class="block text-sm font-medium text-gray-700">
                            Section <span class="text-red-600">*</span>
                            <select v-model="form.section_id" :disabled="!form.class_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-emerald-600 focus:ring-emerald-600 disabled:bg-gray-100">
                                <option value="">Select section</option>
                                <option v-for="section in availableSections" :key="section.id" :value="String(section.id)">
                                    {{ section.name }}
                                </option>
                            </select>
                            <span v-if="form.errors.section_id" class="mt-1 block text-xs text-red-600">{{ form.errors.section_id }}</span>
                        </label>
                        <div v-if="availableElectiveSubjects.length" class="sm:col-span-2 rounded-lg bg-purple-50 p-3 text-sm text-purple-900">
                            <p class="font-medium">Elective subject code is required for each student in the XLSX file:</p>
                            <p class="mt-1">{{ availableElectiveSubjects.map(subject => `${subject.name} (${subject.code})`).join(', ') }}</p>
                        </div>
                        <div v-if="availableOptionalSubjects.length" class="sm:col-span-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                            <p class="font-medium">Optional subject code is required for each student (4th subject):</p>
                            <p class="mt-1">{{ availableOptionalSubjects.map(subject => `${subject.name} (${subject.code})`).join(', ') }}</p>
                        </div>
                        <div v-if="availableAdditionalSubjects.length" class="sm:col-span-2 rounded-lg bg-blue-50 p-3 text-sm text-blue-900">
                            <p class="font-medium">Additional subject code is required for each student:</p>
                            <p class="mt-1">{{ availableAdditionalSubjects.map(subject => `${subject.name} (${subject.code})`).join(', ') }}</p>
                        </div>
                        <label v-if="selectedClassGroup" class="block text-sm font-medium text-gray-700">
                            Group <span class="text-red-600">*</span>
                            <select v-model="form.group" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-emerald-600 focus:ring-emerald-600">
                                <option :value="selectedClassGroup">{{ groupLabel(selectedClassGroup) }}</option>
                            </select>
                            <span v-if="form.errors.group" class="mt-1 block text-xs text-red-600">{{ form.errors.group }}</span>
                        </label>
                    </div>
                </section>

                <section class="rounded-xl bg-white p-5 shadow-sm">
                    <h2 class="font-semibold text-gray-800">{{ isStudentImport ? '3' : '2' }}. Select your completed XLSX file</h2>
                    <p class="mt-1 text-sm text-gray-500">The first worksheet is read. Maximum file size: 10 MB; maximum rows: 500.</p>
                    <label :for="fileId" class="mt-4 flex min-h-32 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-4 text-center hover:border-emerald-500 hover:bg-emerald-50">
                        <i class="fa-solid fa-file-excel text-3xl text-emerald-700"></i>
                        <span class="mt-2 text-sm font-medium text-gray-700">{{ fileName || 'Choose an .xlsx file' }}</span>
                        <span class="mt-1 text-xs text-gray-500">Click to browse</span>
                        <input :id="fileId" ref="fileInput" type="file" accept=".xlsx" class="sr-only" @change="parseWorkbook($event.target.files[0])" />
                    </label>

                    <p v-if="parsing" class="mt-3 text-sm text-blue-700"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Reading workbook…</p>
                    <p v-if="parseError" class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ parseError }}</p>
                    <div v-if="form.errors.import" class="mt-3 whitespace-pre-line rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ form.errors.import }}</div>
                    <div v-if="form.errors.rows" class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ form.errors.rows }}</div>
                </section>

                <section v-if="form.rows.length" class="rounded-xl bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="font-semibold text-gray-800">{{ isStudentImport ? '4' : '3' }}. Review and import</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ form.rows.length }} row(s) parsed. First {{ previewRows.length }} shown below.</p>
                        </div>
                        <button type="button" :disabled="form.processing || !canSubmitImport" @click="submitImport" class="rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">
                            <i :class="['fa-solid mr-2', form.processing ? 'fa-spinner fa-spin' : 'fa-upload']"></i>
                            {{ form.processing ? 'Importing…' : `Import ${form.rows.length} ${kind}` }}
                        </button>
                    </div>
                    <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th v-for="column in columns" :key="column" class="whitespace-nowrap px-3 py-2 font-semibold text-gray-600">{{ column }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(row, index) in previewRows" :key="index">
                                    <td v-for="column in columns" :key="column" class="whitespace-nowrap px-3 py-2 text-gray-700">{{ row[column] || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">Import is all-or-nothing: if any row is invalid or duplicated, none of the rows will be saved.</p>
                </section>
            </section>
        </main>
    </InstituteLayout>
</template>
