<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    institute: { type: Object, required: true },
    schoolClass: { type: Object, required: true },
    exam: { type: Object, required: true },
    subjects: { type: Array, default: () => [] },
});

const language = ref('bn');
const fontFamily = ref('Arial, sans-serif');
const fontSize = ref(13);
const headingColor = ref('#111827');
const textColor = ref('#1f2937');
const tableHeaderColor = ref('#e5e7eb');
const headingText = ref('পরীক্ষার সময়সূচি');
const showSchoolName = ref(true);
const showAddress = ref(true);
const showClass = ref(true);
const showExam = ref(true);
const showAcademicYear = ref(true);
const showSignature = ref(true);
const columns = ref([
    { key: 'serial', label: 'ক্রমিক', bnLabel: 'ক্রমিক', visible: true },
    { key: 'subject', label: 'বিষয়', bnLabel: 'বিষয়', visible: true },
    { key: 'date', label: 'তারিখ', bnLabel: 'তারিখ', visible: true },
    { key: 'time', label: 'সময়', bnLabel: 'সময়', visible: true },
    { key: 'duration', label: 'সময়কাল', bnLabel: 'সময়কাল', visible: true },
    { key: 'full_marks', label: 'পূর্ণমান', bnLabel: 'পূর্ণমান', visible: true },
    { key: 'pass_marks', label: 'পাস নম্বর', bnLabel: 'পাস নম্বর', visible: false },
]);

const label = (english, bengali) => language.value === 'bn' ? bengali : english;
const title = computed(() => `${props.exam.name} - ${props.schoolClass.name}`);
const previewStyle = computed(() => ({
    fontFamily: fontFamily.value,
    fontSize: `${fontSize.value}px`,
    color: textColor.value,
    '--heading-color': headingColor.value,
    '--table-header-color': tableHeaderColor.value,
}));
const visibleColumns = computed(() => columns.value.filter(column => column.visible));

const localized = (english, bengali) => language.value === 'bn'
    ? (bengali || english || '—')
    : (english || bengali || '—');

const dateLabel = (date) => {
    if (!date) return '—';
    const value = new Date(`${date}T00:00:00`);
    return Number.isNaN(value.getTime())
        ? date
        : value.toLocaleDateString(language.value === 'bn' ? 'bn-BD' : 'en-GB', {
            day: '2-digit',
            month: 'long',
            year: 'numeric',
        });
};

const timeLabel = (time) => {
    if (!time) return '—';
    const [hour, minute] = time.split(':').map(Number);
    if (Number.isNaN(hour) || Number.isNaN(minute)) return time;
    const period = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${String(hour12).padStart(2, '0')}:${String(minute).padStart(2, '0')} ${period}`;
};

const valueFor = (subject, key, index) => {
    switch (key) {
    case 'serial':
        return new Intl.NumberFormat(language.value === 'bn' ? 'bn-BD' : 'en').format(index + 1);
    case 'subject':
        return localized(subject.subject_name, subject.subject_bn_name);
    case 'date':
        return dateLabel(subject.exam_date);
    case 'time':
        return `${timeLabel(subject.start_time)} - ${timeLabel(subject.end_time)}`;
    case 'duration':
        return subject.duration || '—';
    case 'full_marks':
        return subject.full_marks ?? '—';
    case 'pass_marks':
        return subject.pass_marks ?? '—';
    default:
        return '—';
    }
};

const printRoutine = () => window.print();

const resetSettings = () => {
    language.value = 'bn';
    fontFamily.value = 'Arial, sans-serif';
    fontSize.value = 13;
    headingColor.value = '#111827';
    textColor.value = '#1f2937';
    tableHeaderColor.value = '#e5e7eb';
    headingText.value = 'পরীক্ষার সময়সূচি';
    showSchoolName.value = true;
    showAddress.value = true;
    showClass.value = true;
    showExam.value = true;
    showAcademicYear.value = true;
    showSignature.value = true;
    columns.value.forEach(column => {
        column.visible = column.key !== 'pass_marks';
    });
};
</script>

<template>
    <Head :title="title" />

    <main class="routine-editor">
        <section class="preview-area">
            <div class="preview-toolbar no-print">
                <span>{{ label('Print preview', 'প্রিন্ট প্রিভিউ') }}</span>
                <button type="button" class="print-button" @click="printRoutine">
                    <i class="fa-solid fa-print"></i>
                    {{ label('Print routine', 'রুটিন প্রিন্ট করুন') }}
                </button>
            </div>

            <article class="routine-paper" :style="previewStyle">
                <header class="paper-heading">
                    <h1 v-if="showSchoolName" class="school-name">{{ institute.name }}</h1>
                    <p v-if="showAddress && institute.address" class="school-address">{{ institute.address }}</p>
                    <h2 class="routine-title">{{ headingText || label('Exam routine', 'পরীক্ষার সময়সূচি') }}</h2>
                    <p v-if="showExam" class="exam-name">{{ localized(exam.name, exam.bn_name) }}</p>
                    <p v-if="showClass || showAcademicYear" class="class-session">
                        <span v-if="showClass">{{ localized(schoolClass.name, schoolClass.bn_name) }}</span>
                        <span v-if="showClass && showAcademicYear && exam.academic_year"> · </span>
                        <span v-if="showAcademicYear && exam.academic_year">{{ exam.academic_year }}</span>
                    </p>
                </header>

                <table class="routine-table">
                    <thead>
                        <tr>
                            <th v-for="column in visibleColumns" :key="column.key">
                                {{ language === 'bn' ? column.bnLabel : column.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(subject, index) in subjects" :key="subject.id">
                            <td v-for="column in visibleColumns" :key="column.key">
                                {{ valueFor(subject, column.key, index) }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <footer v-if="showSignature" class="signature-row">
                    <div>{{ label('Class Teacher', 'শ্রেণি শিক্ষক') }}</div>
                    <div>{{ label('Head of Institution', 'প্রতিষ্ঠান প্রধান') }}</div>
                </footer>
            </article>
        </section>

        <aside class="settings-panel no-print">
            <div class="settings-heading">
                <div>
                    <h2>{{ label('Print settings', 'প্রিন্ট সেটিংস') }}</h2>
                    <p>{{ label('Customize the routine before printing', 'প্রিন্টের আগে রুটিন সাজিয়ে নিন') }}</p>
                </div>
                <button type="button" class="icon-button" :title="label('Reset settings', 'সেটিংস রিসেট')" @click="resetSettings">
                    <i class="fa-solid fa-rotate-left"></i>
                </button>
            </div>

            <div class="setting-group">
                <label class="field-label">{{ label('Language', 'ভাষা') }}</label>
                <div class="language-switch">
                    <button type="button" :class="{ active: language === 'bn' }" @click="language = 'bn'">বাংলা</button>
                    <button type="button" :class="{ active: language === 'en' }" @click="language = 'en'">English</button>
                </div>
            </div>

            <div class="setting-group">
                <label class="field-label" for="routine-title">{{ label('Heading', 'শিরোনাম') }}</label>
                <input id="routine-title" v-model="headingText" type="text">
            </div>

            <div class="setting-group">
                <label class="field-label" for="routine-font">{{ label('Font', 'ফন্ট') }}</label>
                <select id="routine-font" v-model="fontFamily">
                    <option value="Arial, sans-serif">Arial</option>
                    <option value="'Times New Roman', serif">Times New Roman</option>
                    <option value="Georgia, serif">Georgia</option>
                    <option value="Verdana, sans-serif">Verdana</option>
                </select>
            </div>

            <div class="setting-group">
                <label class="field-label" for="routine-font-size">
                    {{ label('Font size', 'ফন্ট সাইজ') }} <span>{{ fontSize }}px</span>
                </label>
                <input id="routine-font-size" v-model.number="fontSize" type="range" min="10" max="20">
            </div>

            <div class="color-grid">
                <label>
                    <span>{{ label('Heading color', 'শিরোনামের রঙ') }}</span>
                    <input v-model="headingColor" type="color">
                </label>
                <label>
                    <span>{{ label('Text color', 'লেখার রঙ') }}</span>
                    <input v-model="textColor" type="color">
                </label>
                <label>
                    <span>{{ label('Table heading', 'টেবিল হেডারের রঙ') }}</span>
                    <input v-model="tableHeaderColor" type="color">
                </label>
            </div>

            <div class="setting-group">
                <h3 class="field-label">{{ label('Show / hide heading', 'হেডিং দেখান / লুকান') }}</h3>
                <label class="check-row"><input v-model="showSchoolName" type="checkbox"> {{ label('School name', 'প্রতিষ্ঠানের নাম') }}</label>
                <label class="check-row"><input v-model="showAddress" type="checkbox"> {{ label('Address', 'ঠিকানা') }}</label>
                <label class="check-row"><input v-model="showExam" type="checkbox"> {{ label('Exam name', 'পরীক্ষার নাম') }}</label>
                <label class="check-row"><input v-model="showClass" type="checkbox"> {{ label('Class', 'শ্রেণি') }}</label>
                <label class="check-row"><input v-model="showAcademicYear" type="checkbox"> {{ label('Academic year', 'শিক্ষাবর্ষ') }}</label>
            </div>

            <div class="setting-group">
                <h3 class="field-label">{{ label('Add / remove columns', 'কলাম যোগ / বাদ দিন') }}</h3>
                <label v-for="column in columns" :key="column.key" class="check-row">
                    <input v-model="column.visible" type="checkbox">
                    {{ language === 'bn' ? column.bnLabel : column.label }}
                </label>
            </div>

            <div class="setting-group">
                <label class="check-row"><input v-model="showSignature" type="checkbox"> {{ label('Show signature area', 'স্বাক্ষরের জায়গা দেখান') }}</label>
            </div>

            <button type="button" class="print-button full-width" @click="printRoutine">
                <i class="fa-solid fa-print"></i>
                {{ label('Print routine', 'রুটিন প্রিন্ট করুন') }}
            </button>
        </aside>
    </main>
</template>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f3f4f6;
}

.routine-editor {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 320px;
    min-height: 100vh;
    gap: 20px;
    padding: 20px;
    color: #111827;
    font-family: Arial, sans-serif;
}

.preview-area {
    min-width: 0;
}

.preview-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 0 auto 12px;
    max-width: 210mm;
    color: #4b5563;
    font-size: 13px;
}

.print-button {
    display: inline-flex;
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

.print-button:hover {
    background: #1d4ed8;
}

.routine-paper {
    width: 100%;
    max-width: 210mm;
    min-height: 297mm;
    margin: 0 auto;
    padding: 18mm 16mm;
    background: white;
    box-shadow: 0 2px 14px rgb(15 23 42 / 12%);
}

.paper-heading {
    margin-bottom: 28px;
    text-align: center;
    line-height: 1.5;
}

.school-name {
    margin: 0;
    color: var(--heading-color);
    font-size: 1.8em;
    font-weight: 700;
}

.school-address,
.class-session {
    margin: 3px 0;
}

.routine-title {
    margin: 18px 0 4px;
    color: var(--heading-color);
    font-size: 1.35em;
    font-weight: 700;
}

.exam-name {
    margin: 3px 0;
    font-size: 1.15em;
    font-weight: 600;
}

.routine-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

.routine-table th,
.routine-table td {
    border: 1px solid #6b7280;
    padding: 9px 8px;
    vertical-align: middle;
}

.routine-table th {
    background: var(--table-header-color);
    color: var(--heading-color);
    font-weight: 700;
}

.signature-row {
    display: flex;
    justify-content: space-between;
    margin: 72px 14px 0;
    text-align: center;
}

.signature-row div {
    min-width: 150px;
    border-top: 1px solid #374151;
    padding-top: 8px;
}

.settings-panel {
    align-self: start;
    position: sticky;
    top: 20px;
    max-height: calc(100vh - 40px);
    overflow-y: auto;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 18px;
    background: white;
    box-shadow: 0 2px 10px rgb(15 23 42 / 7%);
}

.settings-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 18px;
}

.settings-heading h2 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
}

.settings-heading p {
    margin: 4px 0 0;
    color: #6b7280;
    font-size: 11px;
}

.icon-button {
    width: 32px;
    height: 32px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: white;
    color: #4b5563;
    cursor: pointer;
}

.setting-group {
    margin: 16px 0;
}

.field-label {
    display: flex;
    justify-content: space-between;
    margin-bottom: 7px;
    color: #374151;
    font-size: 12px;
    font-weight: 600;
}

.setting-group input[type="text"],
.setting-group select {
    width: 100%;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 8px;
    background: white;
    color: #111827;
    font-size: 12px;
}

.language-switch {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
}

.language-switch button {
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 7px;
    background: white;
    cursor: pointer;
}

.language-switch button.active {
    border-color: #2563eb;
    background: #eff6ff;
    color: #1d4ed8;
}

.color-grid {
    display: grid;
    gap: 8px;
}

.color-grid label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: #374151;
    font-size: 12px;
}

.color-grid input[type="color"] {
    width: 42px;
    height: 28px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    padding: 2px;
    background: white;
}

.check-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 8px 0;
    color: #374151;
    font-size: 12px;
}

.check-row input {
    accent-color: #2563eb;
}

.full-width {
    width: 100%;
}

@media (max-width: 900px) {
    .routine-editor {
        grid-template-columns: 1fr;
    }

    .settings-panel {
        position: static;
        grid-row: 1;
        max-height: none;
    }
}

@page {
    size: A4 portrait;
    margin: 12mm;
}

@media print {
    body {
        background: white;
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }

    .no-print {
        display: none !important;
    }

    .routine-editor {
        display: block;
        min-height: 0;
        padding: 0;
    }

    .routine-paper {
        width: 100%;
        max-width: none;
        min-height: 0;
        margin: 0;
        padding: 0;
        box-shadow: none;
    }
}
</style>
