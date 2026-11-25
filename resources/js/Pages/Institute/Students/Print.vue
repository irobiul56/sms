<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    institute: { type: Object, default: () => ({}) },
    students: { type: Array, default: () => [] },
    classes: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const selectedClass = ref(props.filters.class_id ? String(props.filters.class_id) : '');
const getClassLabel = (cls) => cls.group
    ? `${cls.name} (${cls.group_label || cls.group})`
    : cls.name;
const orientation = ref('portrait');
const colourMode = ref('colour');
const textSize = ref('normal');
const showColumns = ref(false);
const columns = [
    { key: 'student_id', label: 'Student ID' },
    { key: 'name', label: 'Student Name' },
    { key: 'bn_name', label: 'Bangla Name' },
    { key: 'roll_no', label: 'Roll' },
    { key: 'registration_no', label: 'Registration No.' },
    { key: 'class_name', label: 'Class' },
    { key: 'group', label: 'Group' },
    { key: 'section_name', label: 'Section' },
    { key: 'father_name', label: "Father's Name" },
    { key: 'mother_name', label: "Mother's Name" },
    { key: 'phone', label: 'Phone' },
    { key: 'status', label: 'Status' },
];
const selectedColumns = ref(['student_id', 'name', 'roll_no']);
const showGroupColumn = computed(() => props.students.some(
    student => Boolean(student.class_group)
));
const availableColumns = computed(() => columns.filter(
    column => column.key !== 'group' || showGroupColumn.value
));
const visibleColumns = computed(() => selectedColumns.value
    .map(key => availableColumns.value.find(column => column.key === key))
    .filter(Boolean));
const boys = computed(() => props.students.filter(student => student.gender === 'male').length);
const girls = computed(() => props.students.filter(student => student.gender === 'female').length);
const printedOn = new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
}).format(new Date()).replaceAll('/', '-');
const listScope = computed(() => [
    props.filters.class_name,
    props.filters.section_name,
    props.filters.academic_year,
    props.filters.status ? props.filters.status[0].toUpperCase() + props.filters.status.slice(1) : null,
    props.filters.search ? `Search: ${props.filters.search}` : null,
].filter(Boolean).join(' · ') || 'All students');

const toggleColumn = (key) => {
    selectedColumns.value = selectedColumns.value.includes(key)
        ? selectedColumns.value.filter(column => column !== key)
        : [...selectedColumns.value, key];
};
const printList = () => window.print();
const cellValue = (student, key) => {
    if (key === 'status') {
        return student.status ? student.status[0].toUpperCase() + student.status.slice(1) : '—';
    }
    if (key === 'group') {
        const labels = {
            science: 'Science (বিজ্ঞান)',
            commerce: 'Commerce (বাণিজ্য)',
            arts: 'Arts (মানবিক)',
            general: 'General (সাধারণ)',
        };
        return labels[student.group || student.class_group] || '—';
    }

    return student[key] || '—';
};
</script>

<template>
    <Head title="Print Student List" />
    <main class="print-workspace" :class="[`orientation-${orientation}`, `text-${textSize}`, { 'white-mode': colourMode === 'white' }]">
        <section id="student-list-print" class="paper">
            <div class="paper-topline">
                <span><i class="fa-regular fa-calendar"></i> Printed on: <strong>{{ printedOn }}</strong></span>
                <span class="school-level">Student Register</span>
                <span>{{ institute.phone ? `Phone: ${institute.phone}` : '' }}</span>
            </div>
            <header class="school-header">
                <img v-if="institute.logo_url" :src="institute.logo_url" :alt="`${institute.name} logo`" class="school-mark school-logo">
                <div v-else class="school-mark" aria-hidden="true">
                    <svg viewBox="0 0 64 64" role="presentation">
                        <path d="M8 28 32 9l24 19v27H8z" />
                        <path d="M18 29v-7l14-11 14 11v7M23 55V37h18v18M27 37v18m10-18v18M14 30h36M29 17h6" />
                        <path d="M26 27h12M28 22h8" />
                    </svg>
                </div>
                <div class="school-name">
                    <h1>{{ institute.name || 'School Name' }}</h1>
                    <p v-if="institute.address">{{ institute.address }}</p>
                </div>
                <div v-if="institute.phone || institute.eiin" class="school-contact">
                    <span v-if="institute.phone"><i class="fa-solid fa-mobile-screen-button"></i>{{ institute.phone }}</span>
                    <span v-if="institute.eiin"><i class="fa-solid fa-building-columns"></i> EIIN: {{ institute.eiin }}</span>
                </div>
            </header>

            <div class="list-title">
                <h2>Student List</h2>
                <span class="scope-chip">{{ listScope }}</span>
            </div>

            <section class="summary-cards" aria-label="Student counts">
                <div><strong>{{ students.length }}</strong><span>Total students</span></div>
                <div><strong>{{ boys }}</strong><span>Boys</span></div>
                <div><strong class="girls-count">{{ girls }}</strong><span>Girls</span></div>
            </section>

            <div v-if="students.length" class="student-table-wrap">
                <table class="student-table">
                    <thead>
                        <tr>
                            <th class="serial-column">#</th>
                            <th v-for="column in visibleColumns" :key="column.key">{{ column.label }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(student, index) in students" :key="student.id">
                            <td class="serial-column">{{ index + 1 }}</td>
                            <td v-for="column in visibleColumns" :key="column.key">{{ cellValue(student, column.key) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="empty-state">No students match these filters</div>

            <footer class="signature-row">
                <div><span></span><p>Class Teacher</p></div>
                <div><span></span><p>Head of Institution</p></div>
            </footer>
        </section>

        <aside class="print-controls">
            <p class="student-count"><strong>{{ students.length }}</strong> students</p>
            <button type="button" class="control-button print-button" @click="printList">
                <i class="fa-solid fa-print"></i> Print
            </button>
            <button type="button" class="control-button pdf-button" @click="printList">
                <i class="fa-regular fa-file-pdf"></i> Download PDF
            </button>
            <p class="pdf-hint">Choose “Save as PDF” in the print dialog.</p>
            <button type="button" class="columns-button" @click="showColumns = true">
                <span><i class="fa-solid fa-table-columns"></i> Columns</span>
                <span class="column-count">{{ selectedColumns.length }}/{{ availableColumns.length }}</span>
            </button>

            <form :action="route('students.print')" method="get" class="class-filter-form">
                <h3>Filter by class</h3>
                <select v-model="selectedClass" name="class_id" class="class-filter-select">
                    <option value="">All Classes</option>
                    <option v-for="schoolClass in classes" :key="schoolClass.id" :value="String(schoolClass.id)">
                        {{ getClassLabel(schoolClass) }}
                    </option>
                </select>
                <input v-if="filters.search" type="hidden" name="search" :value="filters.search" />
                <input v-if="filters.status" type="hidden" name="status" :value="filters.status" />
                <input v-if="filters.academic_year_id" type="hidden" name="academic_year_id" :value="filters.academic_year_id" />
                <button type="submit" class="apply-filter-button">
                    <i class="fa-solid fa-filter"></i> Apply class filter
                </button>
            </form>

            <div class="setting-group">
                <h3>Page</h3>
                <div class="segmented-control">
                    <button :class="{ active: orientation === 'portrait' }" @click="orientation = 'portrait'">Portrait</button>
                    <button :class="{ active: orientation === 'landscape' }" @click="orientation = 'landscape'">Landscape</button>
                </div>
            </div>
            <div class="setting-group">
                <h3>Colour</h3>
                <div class="segmented-control">
                    <button :class="{ active: colourMode === 'colour' }" @click="colourMode = 'colour'">Colour</button>
                    <button :class="{ active: colourMode === 'white' }" @click="colourMode = 'white'">White</button>
                </div>
            </div>
            <div class="setting-group">
                <h3>Text Size</h3>
                <div class="segmented-control three-options">
                    <button :class="{ active: textSize === 'small' }" @click="textSize = 'small'">Small</button>
                    <button :class="{ active: textSize === 'normal' }" @click="textSize = 'normal'">Normal</button>
                    <button :class="{ active: textSize === 'large' }" @click="textSize = 'large'">Large</button>
                </div>
            </div>
            <a :href="route('students.index')" class="close-button"><i class="fa-solid fa-xmark"></i> Close</a>
        </aside>

        <div v-if="showColumns" class="columns-overlay" @click.self="showColumns = false">
            <section class="columns-dialog" role="dialog" aria-modal="true" aria-labelledby="columns-title">
                <header>
                    <div>
                        <h2 id="columns-title">Choose columns</h2>
                        <p>Columns appear in the same order you select them.</p>
                    </div>
                    <button type="button" aria-label="Close" @click="showColumns = false">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="columns-options">
                    <label v-for="column in availableColumns" :key="column.key">
                        <input
                            type="checkbox"
                            :checked="selectedColumns.includes(column.key)"
                            @change="toggleColumn(column.key)"
                        />
                        <span>{{ column.label }}</span>
                        <span v-if="selectedColumns.includes(column.key)" class="column-order">
                            {{ selectedColumns.indexOf(column.key) + 1 }}
                        </span>
                    </label>
                </div>
                <footer>
                    <span>{{ selectedColumns.length }} of {{ availableColumns.length }} selected</span>
                    <button type="button" @click="showColumns = false">Done</button>
                </footer>
            </section>
        </div>
    </main>
</template>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
}

.print-workspace {
    min-height: 100vh;
    padding: 20px 300px 30px 24px;
    background: #e8edf3;
    color: #173c36;
    font-family: Georgia, "Noto Serif Bengali", serif;
}

.paper {
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto;
    padding: 13mm 13mm 17mm;
    background: #fff;
    box-shadow: 0 8px 30px rgba(32, 54, 72, .12);
}

.orientation-landscape .paper {
    width: 297mm;
    min-height: 210mm;
}

.paper-topline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 9mm;
    padding-bottom: 3mm;
    border-bottom: 1px solid #d5e0dc;
    color: #49645e;
    font-size: 9pt;
}

.paper-topline strong {
    color: #116653;
}

.school-level,
.scope-chip {
    padding: 1mm 4mm;
    border: 1px solid #cee5dc;
    border-radius: 20px;
    background: #edf8f3;
    color: #126551;
}

.school-header {
    display: grid;
    grid-template-columns: 19mm minmax(0, 1fr);
    align-items: center;
    min-height: 23mm;
    padding: 4mm 0;
    border-bottom: 2px solid #126551;
}

.school-mark {
    width: 15mm;
    height: 15mm;
    color: #28775e;
}

.school-logo {
    height: 18mm;
    object-fit: contain;
}

.school-mark svg {
    width: 100%;
    height: 100%;
    fill: none;
    stroke: currentColor;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 2;
}

.school-name {
    text-align: center;
}

.school-name h1 {
    margin: 0;
    color: #075d4a;
    font-size: 20pt;
    line-height: 1.2;
}

.school-name p {
    margin: 1mm 0 0;
    color: #314c47;
    font-size: 10pt;
}

.school-contact {
    grid-column: 1 / -1;
    display: flex;
    justify-content: center;
    gap: 12mm;
    padding: 2mm;
    border-top: 1px solid #126551;
    border-bottom: 1px solid #126551;
    background: #f0f8f5;
    color: #173c36;
    font-size: 9pt;
}

.list-title {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2mm;
    margin: 4mm 0;
}

.list-title h2 {
    margin: 0;
    padding: 1.5mm 9mm;
    border-radius: 20px;
    background: #126551;
    color: #fff;
    font-size: 14pt;
}

.scope-chip {
    font-size: 9pt;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2mm;
    margin: 3mm 0;
}

.summary-cards div {
    display: flex;
    min-height: 14mm;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 1px solid #d3e1ed;
    border-radius: 3mm;
}

.summary-cards strong {
    color: #08705b;
    font-size: 16pt;
    line-height: 1;
}

.summary-cards .girls-count {
    color: #d20d4f;
}

.summary-cards span {
    margin-top: 1mm;
    color: #61758a;
    font-size: 9pt;
}

.student-table-wrap {
    width: 100%;
    overflow: visible;
}

.student-table {
    width: 100%;
    border-collapse: collapse;
    color: #243d38;
    font-size: 9pt;
}

.student-table th,
.student-table td {
    padding: 2.2mm;
    border: 1px solid #cbd9d5;
    text-align: left;
    vertical-align: top;
}

.student-table th {
    background: #edf6f2;
    color: #135c4c;
    font-weight: 700;
}

.student-table tbody tr:nth-child(even) {
    background: #f8fbfa;
}

.serial-column {
    width: 9mm;
    text-align: center !important;
}

.empty-state {
    display: grid;
    min-height: 26mm;
    place-items: center;
    margin-top: 3mm;
    border: 1px dashed #c4d5e8;
    border-radius: 3mm;
    color: #687b91;
    font-size: 11pt;
}

.signature-row {
    display: flex;
    justify-content: space-between;
    margin-top: 12mm;
    color: #173c36;
    font-size: 9pt;
}

.signature-row div {
    width: 22%;
    text-align: center;
}

.signature-row span {
    display: block;
    border-top: 1px solid #243b38;
}

.signature-row p {
    margin: 1mm 0;
}

.print-controls {
    position: fixed;
    top: 16px;
    right: 18px;
    z-index: 20;
    width: 254px;
    padding: 14px;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 12px 32px rgba(31, 51, 70, .18);
    color: #182b36;
    font-family: Arial, sans-serif;
}

.student-count {
    margin: 0 0 12px;
    color: #536475;
    font-size: 12px;
}

.student-count strong,
.column-count {
    color: #116653;
}

.control-button,
.columns-button,
.close-button {
    display: flex;
    width: 100%;
    min-height: 40px;
    align-items: center;
    justify-content: center;
    gap: 9px;
    margin-bottom: 8px;
    border: 0;
    border-radius: 10px;
    color: #fff;
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
}

.control-button,
.columns-button,
.apply-filter-button,
.segmented-control button,
.close-button {
    transition: background-color .18s ease, box-shadow .18s ease, transform .18s ease;
}

.control-button:hover,
.apply-filter-button:hover {
    filter: brightness(.96);
}

.control-button:focus-visible,
.columns-button:focus-visible,
.apply-filter-button:focus-visible,
.segmented-control button:focus-visible,
.close-button:focus-visible,
.columns-dialog button:focus-visible {
    outline: 3px solid rgba(17, 102, 83, .3);
    outline-offset: 2px;
}

.print-button {
    background: linear-gradient(110deg, #f8a000, #ee5b00);
}

.pdf-button {
    background: #0e6553;
}

.pdf-hint {
    margin: -2px 0 8px;
    color: #78838d;
    font: 10px/1.4 Arial, sans-serif;
    text-align: center;
}

.columns-button {
    justify-content: space-between;
    padding: 0 12px;
    border: 1px solid #d6e0e8;
    background: #fff;
    color: #263445;
}

.column-count {
    padding: 4px 8px;
    border-radius: 12px;
    background: #e7f4ef;
    font-size: 10px;
}

.class-filter-form {
    display: grid;
    gap: 7px;
    margin-top: 12px;
}

.class-filter-form h3 {
    margin: 0;
    color: #738092;
    font-size: 9px;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.class-filter-select {
    width: 100%;
    min-height: 38px;
    padding: 0 10px;
    border: 1px solid #d6e0e8;
    border-radius: 9px;
    background: #fff;
    color: #263445;
    font-size: 12px;
}

.apply-filter-button {
    min-height: 36px;
    border: 0;
    border-radius: 9px;
    background: #e7f4ef;
    color: #116653;
    cursor: pointer;
    font-size: 11px;
    font-weight: 700;
}

.setting-group {
    margin-top: 16px;
}

.setting-group h3 {
    margin: 0 0 8px;
    color: #738092;
    font-size: 9px;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.segmented-control {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3px;
    padding: 3px;
    border-radius: 10px;
    background: #eff3f7;
}

.segmented-control button {
    min-height: 34px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: #526174;
    cursor: pointer;
    font-size: 11px;
}

.segmented-control button.active {
    background: #fff;
    box-shadow: 0 1px 4px rgba(29, 47, 64, .13);
    color: #116653;
    font-weight: 700;
}

.three-options {
    grid-template-columns: repeat(3, 1fr);
}

.close-button {
    min-height: 40px;
    margin: 14px 0 0;
    border: 1px solid #d6e0e8;
    background: #fff;
    color: #263445;
}

.white-mode .paper,
.white-mode .school-contact,
.white-mode .school-level,
.white-mode .scope-chip,
.white-mode .list-title h2,
.white-mode .summary-cards div,
.white-mode .student-table th,
.white-mode .student-table tbody tr:nth-child(even) {
    background: #fff;
}

.white-mode .list-title h2 {
    border: 1px solid #333;
    color: #111;
}

.text-small .student-table {
    font-size: 7.5pt;
}

.text-large .student-table {
    font-size: 11pt;
}

.columns-overlay {
    position: fixed;
    z-index: 40;
    inset: 0;
    display: grid;
    place-items: center;
    padding: 20px;
    background: rgba(15, 23, 42, .45);
    font-family: Arial, sans-serif;
}

.columns-dialog {
    width: min(440px, 100%);
    overflow: hidden;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 20px 50px rgba(0, 0, 0, .2);
}

.columns-dialog header,
.columns-dialog footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 18px;
    border-bottom: 1px solid #e5e7eb;
}

.columns-dialog header h2 {
    margin: 0;
    color: #172b35;
    font-size: 16px;
}

.columns-dialog header p {
    margin: 5px 0 0;
    color: #718096;
    font-size: 12px;
}

.columns-dialog header button {
    border: 0;
    background: none;
    color: #697586;
    cursor: pointer;
    font-size: 18px;
}

.columns-options {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    padding: 18px;
}

.columns-options label {
    display: flex;
    min-height: 38px;
    align-items: center;
    gap: 8px;
    padding: 7px 9px;
    border: 1px solid #e5ebef;
    border-radius: 9px;
    color: #344456;
    font-size: 12px;
    cursor: pointer;
    transition: border-color .18s ease, background-color .18s ease;
}

.columns-options label:hover {
    border-color: #b7d8cb;
    background: #f7fbf9;
}

.columns-options input {
    accent-color: #116653;
}

.column-order {
    display: grid;
    width: 21px;
    height: 21px;
    flex: 0 0 21px;
    place-items: center;
    margin-left: auto;
    border-radius: 50%;
    background: #e7f4ef;
    color: #116653;
    font-size: 10px;
    font-weight: 700;
}

.columns-dialog footer {
    border-top: 1px solid #e5e7eb;
    border-bottom: 0;
    color: #778393;
    font-size: 12px;
}

.columns-dialog footer button {
    padding: 8px 18px;
    border: 0;
    border-radius: 8px;
    background: #116653;
    color: #fff;
    cursor: pointer;
    font-weight: 700;
}

@media (max-width: 1100px) {
    .print-workspace {
        padding: 20px 20px 340px;
    }

    .paper,
    .orientation-landscape .paper {
        width: min(100%, 210mm);
    }

    .print-controls {
        position: fixed;
        top: auto;
        right: 12px;
        bottom: 12px;
        left: 12px;
        display: grid;
        width: auto;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 5px 8px;
        max-height: 48vh;
        overflow: auto;
    }

    .student-count,
    .pdf-hint {
        grid-column: 1 / -1;
    }

    .setting-group {
        margin-top: 4px;
    }

    .close-button {
        margin-top: 4px;
    }
}

@media print {
    @page {
        size: A4 portrait;
        margin: 10mm;
    }

    @page landscape {
        size: A4 landscape;
        margin: 10mm;
    }

    .print-workspace {
        min-height: 0;
        padding: 0;
        background: #fff;
    }

    .orientation-landscape {
        page: landscape;
    }

    .paper,
    .orientation-landscape .paper {
        width: 100%;
        min-height: 0;
        margin: 0;
        padding: 0;
        box-shadow: none;
    }

    .print-controls,
    .columns-overlay {
        display: none !important;
    }

    .student-table thead {
        display: table-header-group;
    }

    .student-table tr {
        break-inside: avoid;
        page-break-inside: avoid;
    }

    .signature-row {
        break-inside: avoid;
    }
}
</style>
