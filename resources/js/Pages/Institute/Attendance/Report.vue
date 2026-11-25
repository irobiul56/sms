<script setup>
import { computed, ref, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import TeacherMobileNavigation from '@/Components/TeacherMobileNavigation.vue';

const props = defineProps({
    institute: { type: Object, default: () => ({}) },
    records: { type: Array, default: () => [] },
    classes: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const isTeacher = computed(() => String(page.props.auth?.user?.usertype || '').toLowerCase() === 'teacher');
const fromDate = ref(props.filters.from_date);
const toDate = ref(props.filters.to_date);
const selectedClass = ref(props.filters.class_id ? String(props.filters.class_id) : '');
const selectedSection = ref(props.filters.section_id ? String(props.filters.section_id) : '');
const orientation = ref('landscape');
const columns = [
    { key: 'roll_no', label: 'Roll' },
    { key: 'student_id', label: 'Student ID' },
    { key: 'name', label: 'Student name' },
    { key: 'phone', label: 'Phone' },
    { key: 'class_name', label: 'Class' },
    { key: 'section_name', label: 'Section' },
    { key: 'present_days', label: 'Present days' },
    { key: 'absent_days', label: 'Absent days' },
    { key: 'absent_dates', label: 'Absent dates' },
    { key: 'leave_days', label: 'Leave days' },
];
const selectedColumns = ref(columns.map((column) => column.key));
const visibleColumns = computed(() => columns.filter((column) => selectedColumns.value.includes(column.key)));
const visibleSections = computed(() => props.sections.filter(
    (section) => !selectedClass.value || String(section.institute_class_id) === selectedClass.value,
));
const totalPresent = computed(() => props.records.reduce((sum, record) => sum + Number(record.present_days), 0));
const totalAbsent = computed(() => props.records.reduce((sum, record) => sum + Number(record.absent_days), 0));
const totalLeave = computed(() => props.records.reduce((sum, record) => sum + Number(record.leave_days), 0));
const dateRange = computed(() => `${props.filters.from_date} — ${props.filters.to_date}`);

watch(selectedClass, () => {
    if (!visibleSections.value.some((section) => String(section.id) === selectedSection.value)) {
        selectedSection.value = '';
    }
});

function toggleColumn(key) {
    selectedColumns.value = selectedColumns.value.includes(key)
        ? selectedColumns.value.filter((selected) => selected !== key)
        : [...selectedColumns.value, key];
}

function phoneLink(phone) {
    return `tel:${String(phone).replace(/[^\d+]/g, '')}`;
}

function formatAbsentDate(date) {
    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${date}T00:00:00Z`));
}

function printReport() {
    let pageStyle = document.getElementById('attendance-report-page-style');
    if (!pageStyle) {
        pageStyle = document.createElement('style');
        pageStyle.id = 'attendance-report-page-style';
        document.head.appendChild(pageStyle);
    }
    pageStyle.textContent = `@page { size: A4 ${orientation.value}; margin: 12mm; }`;
    window.addEventListener('afterprint', () => pageStyle.remove(), { once: true });
    window.print();
}
</script>

<template>
    <Head title="Attendance report" />
    <TeacherMobileNavigation />
    <main class="report-workspace" :class="{ 'teacher-report-workspace': isTeacher }">
        <aside class="report-sidebar">
            <a :href="route('attendance.index')" class="back-link">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                Back to attendance
            </a>

            <form :action="route('attendance.report')" method="get" class="filter-form">
                <h1>Attendance report</h1>
                <label>
                    From date
                    <input v-model="fromDate" type="date" name="from_date" required>
                </label>
                <label>
                    To date
                    <input v-model="toDate" type="date" name="to_date" :min="fromDate" required>
                </label>
                <label>
                    Class
                    <select v-model="selectedClass" name="class_id">
                        <option value="">All classes</option>
                        <option v-for="item in classes" :key="item.id" :value="String(item.id)">{{ item.name }}{{ item.group ? ` — ${item.group}` : '' }}</option>
                    </select>
                </label>
                <label>
                    Section
                    <select v-model="selectedSection" name="section_id" :disabled="!visibleSections.length">
                        <option value="">All sections</option>
                        <option v-for="item in visibleSections" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
                    </select>
                </label>
                <button class="apply-button" type="submit">
                    <i class="fa-solid fa-filter" aria-hidden="true"></i>
                    Apply filters
                </button>
            </form>

            <section class="column-controls">
                <div class="column-heading">
                    <h2>Report columns</h2>
                    <span>{{ selectedColumns.length }}/{{ columns.length }}</span>
                </div>
                <label v-for="column in columns" :key="column.key" class="column-option">
                    <input
                        type="checkbox"
                        :checked="selectedColumns.includes(column.key)"
                        @change="toggleColumn(column.key)"
                    >
                    <span>{{ column.label }}</span>
                </label>
            </section>

            <section class="orientation-controls">
                <h2>Print orientation</h2>
                <div>
                    <button type="button" :class="{ active: orientation === 'portrait' }" @click="orientation = 'portrait'">
                        <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
                        Portrait
                    </button>
                    <button type="button" :class="{ active: orientation === 'landscape' }" @click="orientation = 'landscape'">
                        <i class="fa-solid fa-table-columns" aria-hidden="true"></i>
                        Landscape
                    </button>
                </div>
            </section>

            <button type="button" class="print-button" @click="printReport">
                <i class="fa-solid fa-print" aria-hidden="true"></i>
                Print report
            </button>
            <p class="sidebar-hint">Phone numbers open your device's dialer when clicked.</p>
        </aside>

        <section class="report-content">
            <div class="report-summary">
                <div>
                    <p class="eyebrow">Attendance summary</p>
                    <h2>{{ institute.name || 'School' }}</h2>
                    <p v-if="institute.eiin || institute.phone" class="school-contact">
                        <span v-if="institute.eiin">EIIN: {{ institute.eiin }}</span>
                        <span v-if="institute.eiin && institute.phone" aria-hidden="true">·</span>
                        <span v-if="institute.phone">Phone: {{ institute.phone }}</span>
                    </p>
                    <p>{{ dateRange }}</p>
                </div>
                <img v-if="institute.logo_url" :src="institute.logo_url" :alt="`${institute.name} logo`">
            </div>

            <div class="summary-cards">
                <article><span>Students</span><strong>{{ records.length }}</strong></article>
                <article><span>Present records</span><strong>{{ totalPresent }}</strong></article>
                <article><span>Absent records</span><strong>{{ totalAbsent }}</strong></article>
                <article><span>Leave records</span><strong>{{ totalLeave }}</strong></article>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th v-for="column in visibleColumns" :key="column.key">{{ column.label }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(record, index) in records" :key="`${record.id}-${record.class_name}-${record.section_name}`">
                            <td>{{ index + 1 }}</td>
                            <td
                                v-for="column in visibleColumns"
                                :key="column.key"
                                :class="{ 'absent-dates-cell': column.key === 'absent_dates' }"
                            >
                                <a v-if="column.key === 'phone' && record.phone" :href="phoneLink(record.phone)">
                                    {{ record.phone }}
                                </a>
                                <div v-else-if="column.key === 'absent_dates'" class="absent-date-list">
                                    <span v-for="date in record.absent_dates" :key="date" class="absent-date-chip">
                                        {{ formatAbsentDate(date) }}
                                    </span>
                                    <span v-if="!record.absent_dates?.length" class="no-absence">No absences</span>
                                </div>
                                <span v-else>{{ record[column.key] ?? '—' }}</span>
                            </td>
                        </tr>
                        <tr v-if="!records.length">
                            <td :colspan="visibleColumns.length + 1" class="empty-state">
                                No attendance records found for this period.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <footer class="signature-row">
                <div><span></span><p>Class Teacher</p></div>
                <div><span></span><p>Head of Institution</p></div>
            </footer>
        </section>
    </main>
</template>

<style scoped>
.report-workspace {
    min-height: 100vh;
    display: grid;
    grid-template-columns: 280px minmax(0, 1fr);
    background: #eef2f7;
    color: #172033;
    font-family: Arial, sans-serif;
}

.report-sidebar {
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
    padding: 22px 18px;
    background: #fff;
    border-right: 1px solid #dfe5ee;
}

.back-link {
    display: inline-flex;
    gap: 9px;
    align-items: center;
    margin-bottom: 22px;
    color: #475569;
    font-size: 13px;
    text-decoration: none;
}

.filter-form,
.column-controls {
    display: grid;
    gap: 14px;
}

.filter-form h1 {
    margin: 0 0 2px;
    font-size: 20px;
}

.filter-form label {
    display: grid;
    gap: 6px;
    color: #475569;
    font-size: 13px;
    font-weight: 600;
}

.filter-form select,
.filter-form input {
    width: 100%;
    min-height: 42px;
    padding: 8px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #fff;
    color: #172033;
}

.apply-button,
.print-button {
    min-height: 43px;
    border: 0;
    border-radius: 8px;
    color: #fff;
    font-weight: 700;
    cursor: pointer;
}

.orientation-controls {
    display: grid;
    gap: 10px;
    margin-top: 20px;
}

.orientation-controls h2 {
    margin: 0;
    font-size: 15px;
}

.orientation-controls div {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.orientation-controls button {
    min-height: 40px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #fff;
    color: #475569;
    font-size: 12px;
    cursor: pointer;
}

.orientation-controls button.active {
    border-color: #2563eb;
    background: #eff6ff;
    color: #1d4ed8;
    font-weight: 700;
}

.apply-button {
    background: #2563eb;
}

.column-controls {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
    gap: 10px;
}

.column-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2px;
}

.column-heading h2 {
    margin: 0;
    font-size: 15px;
}

.column-heading span {
    color: #64748b;
    font-size: 12px;
}

.column-option {
    display: flex;
    align-items: center;
    gap: 9px;
    color: #334155;
    font-size: 13px;
    cursor: pointer;
}

.column-option input {
    width: 16px;
    height: 16px;
    accent-color: #2563eb;
}

.print-button {
    width: 100%;
    margin-top: 22px;
    background: #059669;
}

.sidebar-hint {
    margin: 10px 0 0;
    color: #64748b;
    font-size: 11px;
    line-height: 1.5;
}

.report-content {
    width: min(100%, 1200px);
    margin: 0 auto;
    padding: 32px;
}

.report-summary {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding: 25px 28px;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #fff;
}

.report-summary h2,
.report-summary p {
    margin: 4px 0 0;
}

.report-summary h2 {
    font-size: 24px;
}

.report-summary p {
    color: #64748b;
}

.report-summary .school-contact {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
    color: #334155;
    font-size: 13px;
    font-weight: 600;
}

.report-summary .eyebrow {
    margin: 0;
    color: #2563eb;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.report-summary img {
    max-width: 76px;
    max-height: 76px;
    object-fit: contain;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin: 18px 0;
}

.summary-cards article {
    display: grid;
    gap: 8px;
    padding: 17px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #fff;
}

.summary-cards span {
    color: #64748b;
    font-size: 12px;
}

.summary-cards strong {
    font-size: 23px;
}

.summary-cards article:first-child {
    border-top: 3px solid #3b82f6;
}

.summary-cards article:nth-child(2) {
    border-top: 3px solid #10b981;
}

.summary-cards article:nth-child(3) {
    border-top: 3px solid #ef4444;
}

.summary-cards article:nth-child(4) {
    border-top: 3px solid #f59e0b;
}

.table-wrap {
    overflow-x: auto;
    border: 1px solid #dfe5ee;
    border-radius: 12px;
    background: #fff;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    text-align: left;
}

th,
td {
    padding: 12px 13px;
    border-bottom: 1px solid #e7ebf1;
    white-space: nowrap;
}

.absent-dates-cell {
    width: 220px;
    max-width: 220px;
    white-space: normal;
}

.absent-date-list {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    max-width: 220px;
}

.absent-date-chip {
    display: inline-flex;
    max-width: 100%;
    padding: 3px 7px;
    border: 1px solid #fecaca;
    border-radius: 999px;
    background: #fff1f2;
    color: #be123c;
    font-size: 10px;
    font-weight: 600;
    line-height: 1.4;
    white-space: nowrap;
}

.no-absence {
    color: #94a3b8;
    font-size: 11px;
}

th {
    background: #f8fafc;
    color: #475569;
    font-weight: 700;
}

tbody tr:last-child td {
    border-bottom: 0;
}

td a {
    color: #2563eb;
    text-decoration: underline;
    text-underline-offset: 2px;
}

.empty-state {
    padding: 34px;
    color: #64748b;
    text-align: center;
}

.signature-row {
    display: flex;
    justify-content: space-between;
    gap: 48px;
    margin: 70px 36px 0;
}

.signature-row div {
    width: 190px;
    text-align: center;
}

.signature-row span {
    display: block;
    border-top: 1px solid #475569;
}

.signature-row p {
    margin: 8px 0 0;
    color: #475569;
    font-size: 12px;
}

@media (max-width: 800px) {
    .report-workspace {
        grid-template-columns: 1fr;
    }

    .teacher-report-workspace {
        padding-bottom: 76px;
    }

    .report-sidebar {
        position: static;
        height: auto;
        overflow: visible;
        border-right: 0;
        border-bottom: 1px solid #dfe5ee;
    }

    .report-content {
        padding: 18px 12px;
    }

    .summary-cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 9px;
    }

    .report-summary {
        padding: 18px;
    }

    .report-summary h2 {
        font-size: 20px;
    }

    .absent-dates-cell {
        width: 190px;
        max-width: 190px;
    }

    .absent-date-list {
        max-width: 190px;
    }
}

@media print {
    .report-workspace {
        display: block;
        min-height: 0;
        padding-bottom: 0;
        background: #fff;
    }

    .report-sidebar {
        display: none !important;
    }

    .report-content {
        width: 100%;
        max-width: none;
        padding: 0;
    }

    .report-summary,
    .summary-cards article,
    .table-wrap {
        border-color: #b8c0cc;
        box-shadow: none;
    }

    .summary-cards {
        grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
        gap: 8px;
        margin: 12px 0;
        break-inside: avoid;
    }

    .summary-cards article {
        padding: 10px;
    }

    .summary-cards span {
        font-size: 10px;
    }

    .summary-cards strong {
        font-size: 18px;
    }

    .table-wrap {
        overflow: visible;
    }

    table {
        font-size: 10px;
    }

    th,
    td {
        padding: 7px;
        white-space: normal;
    }

    .absent-dates-cell {
        width: 205px;
        max-width: 205px;
    }

    .absent-date-list {
        max-width: 205px;
    }

    .absent-date-chip {
        padding: 2px 5px;
        font-size: 9px;
    }

    .signature-row {
        margin-top: 48px;
    }

    td a {
        color: inherit;
        text-decoration: none;
    }
}
</style>
