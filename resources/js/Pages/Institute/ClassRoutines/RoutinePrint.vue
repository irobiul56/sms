<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    institute: { type: Object, required: true },
    academicYear: { type: String, default: '' },
    routines: { type: Array, default: () => [] },
});

const language = ref('bn');
const fontSize = ref(13);
const fontFamily = ref('Arial, sans-serif');
const headingText = ref('ক্লাস রুটিন');
const headingColor = ref('#111827');
const textColor = ref('#1f2937');
const showDay = ref(true);

const weekdaysBn = ['রবিবার', 'সোমবার', 'মঙ্গলবার', 'বুধবার', 'বৃহস্পতিবার', 'শুক্রবার', 'শনিবার'];
const weekdaysEn = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const label = (en, bn) => language.value === 'bn' ? bn : en;

const groupedClasses = computed(() => {
    const groups = new Map();
    props.routines.forEach(item => {
        const name = language.value === 'bn' ? item.class_name : item.class_name_en;
        if (!groups.has(name)) groups.set(name, []);
        groups.get(name).push(item);
    });
    return [...groups.entries()].map(([name, items]) => ({ name, items }));
});

const periodSlots = computed(() => {
    const slots = new Map();
    props.routines.forEach(item => {
        const key = `${item.start_time}-${item.end_time}`;
        if (!slots.has(key)) slots.set(key, { start_time: item.start_time, end_time: item.end_time });
    });
    return [...slots.values()].sort((a, b) => a.start_time.localeCompare(b.start_time));
});

const getCellItems = (items, slot) =>
    items.filter(item => item.start_time === slot.start_time && item.end_time === slot.end_time);

const ordinalBn = ['১ম', '২য়', '৩য়', '৪র্থ', '৫ম', '৬ষ্ঠ', '৭ম', '৮ম'];
const ordinalEn = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th'];

const previewStyle = computed(() => ({
    fontFamily: fontFamily.value,
    fontSize: `${fontSize.value}px`,
    color: textColor.value,
    '--heading-color': headingColor.value,
}));

const dayName = day => language.value === 'bn' ? weekdaysBn[day] : weekdaysEn[day];
const subjectName = item => language.value === 'bn' ? item.subject_name : item.subject_name_en;
const teacherName = item => language.value === 'bn' ? item.teacher_name : item.teacher_name_en;
const resetSettings = () => {
    language.value = 'bn';
    fontSize.value = 13;
    fontFamily.value = 'Arial, sans-serif';
    headingText.value = 'ক্লাস রুটিন';
    headingColor.value = '#111827';
    textColor.value = '#1f2937';
    showDay.value = true;
};
const printRoutine = () => window.print();
</script>

<template>
    <Head title="ক্লাস রুটিন প্রিন্ট" />
    <main class="routine-print-editor">
        <section class="print-preview no-print-area">
            <div class="preview-toolbar no-print">
                <span>{{ label('Print preview', 'প্রিন্ট প্রিভিউ') }}</span>
                <button type="button" class="print-button" @click="printRoutine">
                    {{ label('Print routine', 'রুটিন প্রিন্ট করুন') }}
                </button>
            </div>
            <article class="routine-paper" :style="previewStyle">
                <header class="paper-heading">
                    <h1 :style="{ color: headingColor }">{{ institute.name }}</h1>
                    <p v-if="institute.address">{{ institute.address }}</p>
                    <h2 :style="{ color: headingColor }">{{ headingText }}</h2>
                    <p v-if="academicYear">{{ academicYear }}</p>
                </header>

                <section class="class-print-group">
                    <table class="matrix-table">
                        <thead>
                            <tr>
                                <th class="class-column">{{ label('Class', 'শ্রেণির নাম') }}</th>
                                <th v-for="(slot, index) in periodSlots" :key="`${slot.start_time}-${slot.end_time}`">
                                    {{ language === 'bn' ? ordinalBn[index] : ordinalEn[index] }}
                                    {{ language === 'bn' ? 'পিরিয়ড' : 'Period' }}<br>
                                    {{ slot.start_time }}–{{ slot.end_time }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="group in groupedClasses" :key="group.name">
                                <th class="class-name">{{ group.name }}</th>
                                <td v-for="slot in periodSlots" :key="`${slot.start_time}-${slot.end_time}`" class="routine-cell">
                                    <div v-for="item in getCellItems(group.items, slot)" :key="item.id" class="cell-content">
                                        <strong v-if="item.section_name">{{ item.section_name }}</strong>
                                        <strong>{{ subjectName(item) }}</strong>
                                        <span v-if="teacherName(item)">{{ teacherName(item) }}</span>
                                        <span v-if="showDay">{{ dayName(item.day_of_week) }}</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <footer class="signature-row">
                    <div>{{ label('Class Teacher', 'শ্রেণি শিক্ষক') }}</div>
                    <div>{{ label('Head of Institution', 'প্রতিষ্ঠান প্রধান') }}</div>
                </footer>
            </article>
        </section>

        <aside class="settings-panel no-print">
            <div class="settings-heading">
                <div>
                    <h2>{{ label('Print settings', 'প্রিন্ট সেটিংস') }}</h2>
                    <p>{{ label('Customize before printing', 'প্রিন্টের আগে সেটিংস পরিবর্তন করুন') }}</p>
                </div>
                <button type="button" title="Reset" @click="resetSettings">↻</button>
            </div>

            <div class="setting-group">
                <label>{{ label('Language', 'ভাষা') }}</label>
                <div class="language-switch">
                    <button type="button" :class="{ active: language === 'bn' }" @click="language = 'bn'">বাংলা</button>
                    <button type="button" :class="{ active: language === 'en' }" @click="language = 'en'">English</button>
                </div>
            </div>
            <div class="setting-group">
                <label>{{ label('Heading', 'শিরোনাম') }}</label>
                <input v-model="headingText" type="text">
            </div>
            <div class="setting-group">
                <label>{{ label('Font', 'ফন্ট') }}</label>
                <select v-model="fontFamily">
                    <option value="Arial, sans-serif">Arial</option>
                    <option value="'Hind Siliguri', sans-serif">Hind Siliguri</option>
                    <option value="'Times New Roman', serif">Times New Roman</option>
                </select>
            </div>
            <div class="setting-group">
                <label>{{ label('Font size', 'ফন্ট সাইজ') }}: {{ fontSize }}px</label>
                <input v-model.number="fontSize" type="range" min="10" max="20">
            </div>
            <div class="setting-group">
                <label>{{ label('Heading color', 'শিরোনামের রঙ') }}</label>
                <input v-model="headingColor" type="color">
            </div>
            <div class="setting-group">
                <label>{{ label('Text color', 'লেখার রঙ') }}</label>
                <input v-model="textColor" type="color">
            </div>
            <div class="setting-group">
                <label class="check-row">
                    <input v-model="showDay" type="checkbox">
                    {{ label('Show day', 'বার দেখান') }}
                </label>
            </div>
            <button type="button" class="print-button full-width" @click="printRoutine">
                {{ label('Print routine', 'রুটিন প্রিন্ট করুন') }}
            </button>
        </aside>
    </main>
</template>

<style scoped>
.routine-print-editor{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:20px;min-height:100vh;padding:20px;background:#f3f4f6}
.preview-toolbar{max-width:210mm;margin:0 auto 12px;display:flex;justify-content:space-between;align-items:center}
.routine-paper{width:100%;max-width:210mm;min-height:297mm;margin:auto;padding:16mm;background:#fff;box-shadow:0 2px 14px #0002}
.paper-heading{text-align:center;margin-bottom:22px}.paper-heading h1{margin:0;font-size:1.8em}.paper-heading h2{margin:12px 0 4px;font-size:1.35em}.paper-heading p{margin:3px 0}
.class-print-group{break-inside:avoid;margin-top:18px}.class-print-group h3{margin:0 0 8px;font-size:1.1em}
.class-print-group table{width:100%;border-collapse:collapse}.class-print-group th,.class-print-group td{border:1px solid #6b7280;padding:8px;text-align:left}
.class-print-group th{background:#e5e7eb;color:var(--heading-color)}
.matrix-table{table-layout:fixed;text-align:center!important}.matrix-table th,.matrix-table td{text-align:center;vertical-align:middle;padding:6px 4px}.matrix-table thead th{background:#f3f4f6;font-size:.92em}.matrix-table .class-column{width:14%;text-align:left}.matrix-table .class-name{width:14%;background:#f9fafb;font-weight:700;text-align:left}.routine-cell{min-height:54px;font-size:.9em}.cell-content{display:flex;flex-direction:column;gap:1px;margin:3px 0}.cell-content strong{font-weight:700}.cell-content span{font-size:.88em}
.signature-row{display:flex;justify-content:space-between;margin:65px 14px 0}.signature-row div{min-width:150px;border-top:1px solid #374151;text-align:center;padding-top:8px}
.settings-panel{position:sticky;top:20px;align-self:start;max-height:calc(100vh - 40px);overflow-y:auto;border-radius:12px;padding:18px;background:#fff;box-shadow:0 2px 10px #0001}
.settings-heading{display:flex;justify-content:space-between}.settings-heading h2{margin:0;font-size:16px}.settings-heading p{margin:4px 0 12px;font-size:11px;color:#6b7280}
.setting-group{margin:14px 0}.setting-group>label{display:block;margin-bottom:6px;font-size:12px;font-weight:600}.setting-group input[type=text],.setting-group select{width:100%;border:1px solid #d1d5db;border-radius:6px;padding:8px}.setting-group input[type=range]{width:100%}.setting-group input[type=color]{width:100%;height:36px}
.check-row{display:flex!important;align-items:center;gap:8px}.check-row input{width:auto!important}
.language-switch{display:grid;grid-template-columns:1fr 1fr;gap:6px}.language-switch button{border:1px solid #d1d5db;border-radius:6px;padding:7px;background:#fff}.language-switch button.active{border-color:#2563eb;background:#eff6ff;color:#1d4ed8}
.print-button{border:0;border-radius:7px;padding:10px 14px;background:#2563eb;color:#fff;font-weight:600;cursor:pointer}.full-width{width:100%;margin-top:8px}
@media(max-width:900px){.routine-print-editor{grid-template-columns:1fr}.settings-panel{position:static;max-height:none}}
@media print{body{background:#fff!important}.routine-print-editor{display:block;padding:0;background:#fff}.no-print{display:none!important}.routine-paper{width:auto;max-width:none;min-height:0;margin:0;padding:10mm;box-shadow:none}.class-print-group{break-inside:avoid}}
</style>
