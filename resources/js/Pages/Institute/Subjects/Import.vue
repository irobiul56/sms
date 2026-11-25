<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import InstituteLayout from '@/Layouts/InstituteLayout.vue';

const props = defineProps({
    classes: { type: Array, default: () => [] },
});

const columns = [
    'name', 'bn_name', 'code', 'type', 'full_marks', 'pass_marks',
    'theory_marks', 'theory_pass_marks', 'practical_marks', 'practical_pass_marks',
    'mcq_marks', 'mcq_pass_marks', 'display_order', 'has_practical',
];
const requiredColumns = ['name', 'code', 'type', 'full_marks', 'pass_marks'];
const fileName = ref('');
const parsing = ref(false);
const parseError = ref('');
const previewRows = ref([]);
const form = useForm({
    class_ids: [],
    rows: [],
});
let excelJsPromise;

const loadExcelJS = () => {
    if (!excelJsPromise) {
        excelJsPromise = import('exceljs').then(module => module.default);
    }

    return excelJsPromise;
};

const cellText = (value) => {
    if (value === null || value === undefined) return '';
    if (typeof value === 'object') {
        if (value.text) return String(value.text).trim();
        if (value.result !== undefined) return cellText(value.result);
        if (Array.isArray(value.richText)) return value.richText.map(part => part.text).join('').trim();
        return '';
    }

    return String(value).trim();
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
            throw new Error('The first worksheet must contain a header row and at least one subject row.');
        }

        const headers = sheet.getRow(1).values
            .slice(1)
            .map(value => cellText(value).toLowerCase().replace(/\s+/g, '_'));
        const missing = requiredColumns.filter(column => !headers.includes(column));
        if (missing.length) {
            throw new Error(`Missing required column headers: ${missing.join(', ')}`);
        }

        const parsedRows = [];
        sheet.eachRow((row, rowNumber) => {
            if (rowNumber === 1) return;
            const values = {};
            headers.forEach((header, index) => {
                if (!header || !columns.includes(header)) return;
                values[header] = cellText(row.getCell(index + 1).value);
            });
            if (Object.values(values).some(value => value !== '')) parsedRows.push(values);
        });

        if (!parsedRows.length) {
            throw new Error('No subject rows were found in the first worksheet.');
        }
        if (parsedRows.length > 500) {
            throw new Error('A maximum of 500 subjects can be imported at once.');
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
    const sheet = workbook.addWorksheet('Subjects');
    sheet.addRow(columns);
    sheet.getRow(1).font = { bold: true, color: { argb: 'FFFFFFFF' } };
    sheet.getRow(1).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF116653' } };
    sheet.views = [{ state: 'frozen', ySplit: 1 }];
    columns.forEach((column, index) => {
        sheet.getColumn(index + 1).width = Math.max(18, column.length + 3);
    });

    const instructions = workbook.addWorksheet('Instructions');
    instructions.addRow(['Required columns']);
    instructions.addRow([requiredColumns.join(', ')]);
    instructions.addRow([]);
    instructions.addRow(['type: compulsory, optional, elective, or additional']);
    instructions.addRow(['Marks must be whole numbers. Optional component marks can be left blank.']);
    instructions.addRow(['has_practical: 1 for yes, 0 for no']);
    instructions.addRow(['Keep the exact headers in the Subjects sheet. Maximum 500 rows per upload.']);
    instructions.getColumn(1).width = 100;

    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = 'subject-import-template.xlsx';
    anchor.click();
    URL.revokeObjectURL(url);
};

const submitImport = () => {
    if (!form.rows.length || !form.class_ids.length || parsing.value) return;
    form.post(route('subjects.import.store'), {
        preserveScroll: true,
        onError: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
    });
};

const allClassesSelected = computed(() => (
    props.classes.length > 0 && form.class_ids.length === props.classes.length
));
const selectedClassNames = computed(() => props.classes
    .filter(schoolClass => form.class_ids.includes(String(schoolClass.id)))
    .map(schoolClass => schoolClass.name));

const toggleClass = (classId) => {
    const id = String(classId);
    form.class_ids = form.class_ids.includes(id)
        ? form.class_ids.filter(selectedId => selectedId !== id)
        : [...form.class_ids, id];
    form.clearErrors('class_ids');
    form.clearErrors('class_ids.0');
};

const toggleAllClasses = () => {
    form.class_ids = allClassesSelected.value
        ? []
        : props.classes.map(schoolClass => String(schoolClass.id));
    form.clearErrors('class_ids');
    form.clearErrors('class_ids.0');
};

const getClassGroupLabel = (group) => {
    const labels = {
        science: 'Science (বিজ্ঞান)',
        commerce: 'Commerce (বাণিজ্য)',
        arts: 'Arts (মানবিক)',
        general: 'General (সাধারণ)',
    };
    return labels[group] || '';
};
</script>

<template>
    <Head title="Bulk Import Subjects" />
    <InstituteLayout>
        <main class="min-w-0 flex-1 p-5">
            <section class="mx-auto max-w-5xl space-y-5">
                <header class="flex flex-col justify-between gap-4 rounded-xl bg-white p-5 shadow-sm sm:flex-row sm:items-center">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-800">Bulk Import Subjects</h1>
                        <p class="mt-1 text-sm text-gray-500">Choose one or more classes, download the XLSX format, and import their subjects.</p>
                    </div>
                    <Link :href="route('subjects.index')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        Back to Subjects
                    </Link>
                </header>

                <section class="rounded-xl bg-white p-5 shadow-sm">
                    <h2 class="font-semibold text-gray-800">1. Assign to classes <span class="text-red-600">*</span></h2>
                    <div v-if="classes.length" class="mt-4 space-y-4">
                        <label class="flex cursor-pointer items-center justify-between rounded-lg bg-gray-50 p-3">
                            <span class="flex items-center">
                                <input
                                    type="checkbox"
                                    :checked="allClassesSelected"
                                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    @change="toggleAllClasses"
                                />
                                <span class="ml-2 text-sm font-medium text-gray-700">Select All Classes</span>
                            </span>
                            <span class="text-xs text-gray-500">{{ form.class_ids.length }} of {{ classes.length }} selected</span>
                        </label>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div
                                v-for="schoolClass in classes"
                                :key="schoolClass.id"
                                class="flex cursor-pointer items-center space-x-3 rounded-lg border-2 p-3 transition"
                                :class="form.class_ids.includes(String(schoolClass.id))
                                    ? 'border-blue-500 bg-blue-50'
                                    : 'border-gray-200 hover:border-gray-300'"
                                @click="toggleClass(schoolClass.id)"
                            >
                                <input
                                    type="checkbox"
                                    :checked="form.class_ids.includes(String(schoolClass.id))"
                                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    @click.stop
                                    @change="toggleClass(schoolClass.id)"
                                />
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ schoolClass.name }}</p>
                                    <p class="text-xs text-gray-500">Class {{ schoolClass.numeric_value }}</p>
                                    <p v-if="schoolClass.group" class="mt-1 text-xs font-medium text-purple-700">
                                        <i class="fa-solid fa-layer-group mr-1"></i>{{ getClassGroupLabel(schoolClass.group) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <p v-if="form.errors.class_ids || form.errors['class_ids.0']" class="text-sm text-red-600">
                            {{ form.errors.class_ids || form.errors['class_ids.0'] }}
                        </p>
                    </div>
                    <p v-else class="mt-3 text-sm text-gray-500">No classes are available for this institute.</p>
                </section>

                <section class="rounded-xl bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="font-semibold text-gray-800">2. Download the XLSX format</h2>
                            <p class="mt-1 text-sm text-gray-500">Use the exact column headings provided in the file.</p>
                        </div>
                        <button type="button" @click="downloadTemplate" class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-800">
                            <i class="fa-solid fa-download mr-2"></i>Download format
                        </button>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2 text-xs">
                        <span v-for="column in requiredColumns" :key="column" class="rounded-full bg-amber-50 px-3 py-1 text-amber-800">
                            {{ column }} *
                        </span>
                    </div>
                </section>

                <section class="rounded-xl bg-white p-5 shadow-sm">
                    <h2 class="font-semibold text-gray-800">3. Upload the completed XLSX file</h2>
                    <p class="mt-1 text-sm text-gray-500">Maximum file size: 10 MB; maximum rows: 500. The first worksheet is read.</p>
                    <label for="subject-xlsx" class="mt-4 flex min-h-32 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-4 text-center hover:border-emerald-500 hover:bg-emerald-50">
                        <i class="fa-solid fa-file-excel text-3xl text-emerald-700"></i>
                        <span class="mt-2 text-sm font-medium text-gray-700">{{ fileName || 'Choose an .xlsx file' }}</span>
                        <span class="mt-1 text-xs text-gray-500">Click to browse</span>
                        <input id="subject-xlsx" type="file" accept=".xlsx" class="sr-only" @change="parseWorkbook($event.target.files[0])" />
                    </label>
                    <p v-if="parsing" class="mt-3 text-sm text-blue-700"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Reading workbook…</p>
                    <p v-if="parseError" class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ parseError }}</p>
                    <p v-if="form.errors.import" class="mt-3 whitespace-pre-line rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ form.errors.import }}</p>
                    <p v-if="form.errors.rows" class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ form.errors.rows }}</p>
                </section>

                <section v-if="form.rows.length" class="rounded-xl bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="font-semibold text-gray-800">4. Review and import</h2>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ form.rows.length }} subject(s) for {{ selectedClassNames.join(', ') || 'the selected classes' }}. First {{ previewRows.length }} shown below.
                            </p>
                        </div>
                        <button type="button" :disabled="form.processing || parsing || !form.class_ids.length" @click="submitImport" class="rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">
                            <i :class="['fa-solid mr-2', form.processing ? 'fa-spinner fa-spin' : 'fa-upload']"></i>
                            {{ form.processing ? 'Importing…' : `Import ${form.rows.length} subjects` }}
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
                    <p class="mt-3 text-xs text-gray-500">Import is all-or-nothing. If any row is invalid or duplicated, none of the subjects will be saved.</p>
                </section>
            </section>
        </main>
    </InstituteLayout>
</template>
