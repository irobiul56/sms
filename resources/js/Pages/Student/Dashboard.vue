<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    student: { type: Object, required: true },
    results: { type: Array, default: () => [] },
    combinedResults: { type: Array, default: () => [] },
    attendance: { type: Object, required: true },
    examRoutine: { type: Array, default: () => [] },
    classRoutine: { type: Array, default: () => [] },
    notices: { type: Array, default: () => [] },
    homework: { type: Array, default: () => [] },
});

const activeTab = ref('home');
const selectedResultId = ref(null);
const selectedRoutineDay = ref(new Date().getDay());
const homeworkTab = ref('todo');
const currentTime = ref(new Date());
let clockInterval;
const todayDate = computed(() => {
    const date = currentTime.value;
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
});
const greeting = computed(() => {
    const hour = currentTime.value.getHours();
    if (hour >= 5 && hour < 12) return 'শুভ সকাল';
    if (hour >= 12 && hour < 17) return 'শুভ অপরাহ্ণ';
    if (hour >= 17 && hour < 21) return 'শুভ সন্ধ্যা';
    return 'শুভ রাত্রি';
});
const getExamState = subject => {
    if (!subject.date) return 'upcoming';

    const start = new Date(`${subject.date}T${subject.start_time || '00:00'}:00`);
    const end = new Date(`${subject.date}T${subject.end_time || '23:59'}:00`);
    if (end <= currentTime.value) return 'completed';
    if (start <= currentTime.value && currentTime.value < end) return 'ongoing';
    return subject.date === todayDate.value ? 'today' : 'upcoming';
};
const nextExamSubjectId = computed(() => {
    const subjects = props.examRoutine
        .flatMap(exam => exam.subjects.map(subject => ({ ...subject })))
        .sort((first, second) => `${first.date} ${first.start_time || '00:00'}`
            .localeCompare(`${second.date} ${second.start_time || '00:00'}`));

    const states = subjects.map(subject => getExamState(subject));
    const ongoingIndex = states.findIndex(status => status === 'ongoing');
    if (ongoingIndex >= 0) {
        const next = subjects
            .map((subject, index) => ({ subject, status: states[index] }))
            .find(({ subject, status }) => status !== 'completed' && status !== 'ongoing'
                && `${subject.date} ${subject.start_time || '00:00'}` > `${subjects[ongoingIndex].date} ${subjects[ongoingIndex].start_time || '00:00'}`);
        return next?.subject.id ?? null;
    }

    const hasCompleted = states.includes('completed');
    if (hasCompleted) {
        return subjects.find((subject, index) => states[index] !== 'completed')?.id ?? null;
    }

    if (states.includes('today')) return null;
    return subjects.find((subject, index) => states[index] === 'upcoming')?.id ?? null;
});
const weekdays = [
    { id: 6, name: 'শনিবার', short: 'শনি' },
    { id: 0, name: 'রবিবার', short: 'রবি' },
    { id: 1, name: 'সোমবার', short: 'সোম' },
    { id: 2, name: 'মঙ্গলবার', short: 'মঙ্গল' },
    { id: 3, name: 'বুধবার', short: 'বুধ' },
    { id: 4, name: 'বৃহস্পতিবার', short: 'বৃহঃ' },
    { id: 5, name: 'শুক্রবার', short: 'শুক্র' },
];
const studentResults = computed(() => [
    ...props.results,
    ...props.combinedResults,
].sort((first, second) => String(second.exam_date || '').localeCompare(String(first.exam_date || ''))));
const selectedResult = computed(() => studentResults.value.find(result => result.id === selectedResultId.value));
const latestResult = computed(() => studentResults.value[0] || null);
const nextExam = computed(() => {
    for (const exam of props.examRoutine) {
        const subject = exam.subjects.find(item => item.is_next);
        if (subject) {
            return {
                name: subject.name || exam.name,
                examName: exam.name,
                date: subject.date,
            };
        }
    }

    return null;
});
const routineItems = computed(() => props.classRoutine
    .filter(item => Number(item.day_of_week) === Number(selectedRoutineDay.value))
    .sort((first, second) => first.start_time.localeCompare(second.start_time)));
const routineTimeline = computed(() => {
    const items = [];
    routineItems.value.forEach((item, index) => {
        const previous = routineItems.value[index - 1];
        if (previous && timeToMinutes(item.start_time) - timeToMinutes(previous.end_time) >= 15) {
            items.push({
                id: `break-${previous.id}-${item.id}`,
                type: 'break',
                start_time: previous.end_time,
                end_time: item.start_time,
            });
        }
        items.push(item);
    });
    return items;
});
const attendanceMonths = computed(() => {
    const year = currentTime.value.getFullYear();
    const month = currentTime.value.getMonth() + 1;
    return [2, 1, 0].map(offset => {
        const date = new Date(year, month - 1 - offset, 1);
        return {
            value: `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`,
            label: new Intl.DateTimeFormat('bn-BD', { month: 'long', year: 'numeric' }).format(date),
        };
    });
});
const todoHomework = computed(() => props.homework.filter(item => !item.is_completed));
const completedHomework = computed(() => props.homework.filter(item => item.is_completed));
const visibleHomework = computed(() => homeworkTab.value === 'todo' ? todoHomework.value : completedHomework.value);
const attendanceByDay = computed(() => Object.fromEntries(
    (props.attendance.days || []).map(day => [Number(day.date.slice(-2)), day.status]),
));
const calendarDays = computed(() => {
    const [year, month] = props.attendance.month.split('-').map(Number);
    const firstDay = new Date(year, month - 1, 1);
    const offset = (firstDay.getDay() + 1) % 7;
    const daysInMonth = new Date(year, month, 0).getDate();
    return [
        ...Array(offset).fill(null),
        ...Array.from({ length: daysInMonth }, (_, index) => index + 1),
    ];
});

const toBangla = (value) => String(value ?? '').replace(/\d/g, digit => '০১২৩৪৫৬৭৮৯'[digit]);
const formatDate = (value) => value
    ? new Intl.DateTimeFormat('bn-BD', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(`${value}T00:00:00`))
    : '—';
const formatTime = (value) => value
    ? new Intl.DateTimeFormat('bn-BD', { hour: 'numeric', minute: '2-digit', hour12: true }).format(new Date(`2000-01-01T${value}:00`))
    : '';
const timeToMinutes = value => {
    const [hours, minutes] = value.split(':').map(Number);
    return hours * 60 + minutes;
};
const isOngoingPeriod = item => {
    if (Number(selectedRoutineDay.value) !== currentTime.value.getDay()) return false;
    const now = currentTime.value.getHours() * 60 + currentTime.value.getMinutes();
    return now >= timeToMinutes(item.start_time) && now < timeToMinutes(item.end_time);
};
const changeAttendanceMonth = month => {
    if (month === props.attendance.month) return;
    router.get(route('student.dashboard'), { month }, {
        only: ['attendance'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};
const toggleHomework = item => {
    router.post(route('student.homework.toggle', item.id), {}, {
        preserveState: true,
        preserveScroll: true,
    });
};
const refreshDashboard = () => window.location.reload();
const initials = computed(() => (props.student.name || 'শি').trim().split(/\s+/).slice(0, 2).map(part => part[0]).join(''));
const statusClass = (status) => ({
    present: 'present',
    late: 'late',
    half_day: 'present',
    absent: 'absent',
    holiday: 'holiday',
}[status] || '');
const statusLabel = (status) => ({
    present: 'উপস্থিত',
    late: 'দেরিতে',
    half_day: 'অর্ধদিবস',
    absent: 'অনুপস্থিত',
    holiday: 'ছুটি',
}[status] || 'রেকর্ড নেই');
const showTab = (tab) => {
    activeTab.value = tab;
    selectedResultId.value = null;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

onMounted(() => {
    clockInterval = window.setInterval(() => {
        currentTime.value = new Date();
    }, 60_000);
});

onUnmounted(() => window.clearInterval(clockInterval));
</script>

<template>
    <Head title="শিক্ষার্থী ড্যাশবোর্ড" />

    <main class="student-app">
        <div class="student-shell">
            <header v-if="activeTab === 'home'" class="topbar">
                <div class="school-mark">
                    <img v-if="student.institute.logo" :src="student.institute.logo" :alt="student.institute.name">
                    <i v-else class="fa-solid fa-school" aria-hidden="true"></i>
                </div>
                <div class="school-title">
                    <strong>{{ student.institute.name }}</strong>
                    <small>শিক্ষার্থী অ্যাপ <span class="beta-tag">বিটা</span></small>
                </div>
                <button type="button" class="header-action" aria-label="রিফ্রেশ" @click="refreshDashboard">
                    <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
                </button>
                <button type="button" class="header-action notification-action" aria-label="নোটিশ" @click="showTab('notices')">
                    <i class="fa-regular fa-bell" aria-hidden="true"></i>
                    <span v-if="notices.length" class="notification-count">{{ toBangla(notices.length) }}</span>
                </button>
            </header>

            <section v-if="activeTab === 'home'" class="screen">
                <div class="welcome-card">
                    <div class="welcome-orb"></div>
                    <div class="student-avatar">
                        <img v-if="student.photo" :src="student.photo" :alt="student.name">
                        <span v-else>{{ initials }}</span>
                    </div>
                    <div class="welcome-copy">
                        <p>{{ greeting }} 👋</p>
                        <h1>{{ student.name }}</h1>
                        <span>আইডি {{ student.student_id }}</span>
                    </div>
                    <div class="student-tags">
                        <span v-if="student.class">{{ student.class }}<template v-if="student.group"> · {{ student.group }}</template></span>
                        <span v-if="student.section">শাখা {{ student.section }}</span>
                        <span v-if="student.roll">রোল {{ toBangla(student.roll) }}</span>
                        <span v-if="student.year">{{ toBangla(student.year) }}</span>
                    </div>
                </div>

                <div class="quick-grid">
                    <button class="quick-card" @click="showTab('result')">
                        <span class="quick-icon rose"><i class="fa-solid fa-trophy" aria-hidden="true"></i></span><strong>ফলাফল</strong>
                        <span v-if="studentResults.length" class="quick-badge">{{ toBangla(studentResults.length) }}</span>
                    </button>
                    <button class="quick-card" @click="showTab('classRoutine')">
                        <span class="quick-icon blue"><i class="fa-regular fa-calendar-days" aria-hidden="true"></i></span><strong>ক্লাস রুটিন</strong>
                    </button>
                    <button class="quick-card" @click="showTab('attendance')">
                        <span class="quick-icon green"><i class="fa-regular fa-square-check" aria-hidden="true"></i></span><strong>হাজিরা</strong>
                        <span class="quick-badge green-badge">{{ toBangla(attendance.summary.percentage) }}%</span>
                    </button>
                    <button class="quick-card" @click="showTab('homework')">
                        <span class="quick-icon orange"><i class="fa-solid fa-book-open" aria-hidden="true"></i></span><strong>বাড়ির কাজ</strong>
                    </button>
                    <button class="quick-card" @click="showTab('notices')">
                        <span class="quick-icon amber"><i class="fa-regular fa-bell" aria-hidden="true"></i></span><strong>নোটিশ</strong>
                        <span v-if="notices.length" class="quick-badge">{{ toBangla(notices.length) }}</span>
                    </button>
                    <button class="quick-card" @click="showTab('routine')">
                        <span class="quick-icon rose"><i class="fa-regular fa-file-lines" aria-hidden="true"></i></span><strong>পরীক্ষার রুটিন</strong>
                    </button>
                    <button class="quick-card" @click="showTab('profile')">
                        <span class="quick-icon blue"><i class="fa-regular fa-user" aria-hidden="true"></i></span><strong>প্রোফাইল</strong>
                    </button>
                    <button class="quick-card" @click="showTab('menu')">
                        <span class="quick-icon green"><i class="fa-solid fa-grip" aria-hidden="true"></i></span><strong>আরও</strong>
                    </button>
                </div>

                <div class="section-heading">
                    <h2>সর্বশেষ ফলাফল</h2>
                    <button @click="showTab('result')">সব ফলাফল</button>
                </div>
                <button v-if="latestResult" class="result-banner" @click="selectedResultId = latestResult.id; activeTab = 'result'">
                    <div class="result-topline">
                        <span class="pass-pill">{{ latestResult.gpa >= 1 ? '● উত্তীর্ণ' : '● ফলাফল' }}</span>
                        <div class="gpa">
                            <small>{{ formatDate(latestResult.exam_date) }}</small>
                            <strong>{{ toBangla(Number(latestResult.gpa || 0).toFixed(2)) }}</strong>
                            <small>GPA</small>
                        </div>
                    </div>
                    <h3>{{ latestResult.exam_name }}</h3>
                    <div class="result-summary">
                        <span>গ্রেড {{ latestResult.grade || '—' }}</span>
                        <span v-if="latestResult.position">মেধাক্রম {{ toBangla(latestResult.position) }}</span>
                        <span>{{ toBangla(latestResult.total_marks) }}/{{ toBangla(latestResult.total_full_marks) }}</span>
                    </div>
                </button>
                <div v-else class="empty-card">
                    এখনো কোনো ফলাফল প্রকাশিত হয়নি।
                </div>

                <div class="section-heading">
                    <h2>জমা দিতে হবে</h2>
                    <button @click="showTab('homework')">সব কাজ</button>
                </div>
                <div v-if="todoHomework.length" class="content-card homework-preview">
                    <button v-for="item in todoHomework.slice(0, 2)" :key="item.id" class="homework-preview-row" @click="showTab('homework')">
                        <span class="subject-chip">{{ item.subject || 'বিষয়' }}</span>
                        <strong>{{ item.title }}</strong>
                        <span class="due-chip" :class="{ overdue: item.due_date < todayDate }">{{ item.due_date < todayDate ? 'বাকি' : formatDate(item.due_date) }}</span>
                    </button>
                </div>
                <div v-else class="empty-card">এখন কোনো বাড়ির কাজ বাকি নেই।</div>

                <div class="section-heading">
                    <h2>পরবর্তী পরীক্ষা</h2>
                    <button @click="showTab('routine')">পুরো রুটিন</button>
                </div>
                <div v-if="nextExam" class="content-card next-exam">
                    <div class="date-tile">
                        <strong>{{ toBangla(new Date(`${nextExam.date}T00:00:00`).getDate()) }}</strong>
                        <span>{{ formatDate(nextExam.date).split(' ')[1] }}</span>
                    </div>
                    <div class="next-exam-copy">
                        <strong>{{ nextExam.name }}</strong>
                        <span>{{ nextExam.examName }} · {{ formatDate(nextExam.date) }}</span>
                    </div>
                    <span class="arrow">›</span>
                </div>
                <div v-else class="empty-card">কোনো আসন্ন পরীক্ষা নির্ধারিত নেই।</div>

                <div class="section-heading">
                    <h2>সাম্প্রতিক নোটিশ</h2>
                    <button @click="showTab('notices')">সব নোটিশ</button>
                </div>
                <article v-for="notice in notices.slice(0, 2)" :key="notice.id" class="content-card notice-preview">
                    <span class="notice-dot" :class="notice.priority"></span>
                    <div><strong>{{ notice.title }}</strong><small>{{ formatDate(notice.publish_date) }}</small></div>
                </article>
                <div v-if="!notices.length" class="empty-card">এখনো কোনো নোটিশ নেই।</div>
            </section>

            <section v-else-if="activeTab === 'result'" class="screen">
                <template v-if="selectedResult">
                    <header class="page-heading">
                        <button class="back-button" aria-label="ফলাফলে ফিরে যান" @click="selectedResultId = null"><i class="fa-solid fa-arrow-left"></i></button>
                        <div><h1>{{ selectedResult.exam_name }}</h1><p>{{ student.name }}</p></div>
                    </header>
                    <div class="content-card result-detail">
                        <div class="gpa-circle">{{ toBangla(Number(selectedResult.gpa || 0).toFixed(2)) }}</div>
                        <div>
                            <strong>{{ selectedResult.exam_name }}</strong>
                            <p>{{ student.class }} · শাখা {{ student.section }} · রোল {{ toBangla(student.roll) }}</p>
                            <p v-if="selectedResult.combined" class="combined-exams">
                                <span v-for="(exam, index) in selectedResult.exams" :key="`${exam.name}-${index}`">
                                    {{ exam.name }} ({{ toBangla(exam.allocation) }}{{ selectedResult.mode === 'percentage' ? '%' : ' নম্বর' }})<template v-if="index < selectedResult.exams.length - 1"> + </template>
                                </span>
                            </p>
                            <span class="grade-pill">গ্রেড {{ selectedResult.grade || '—' }}</span>
                        </div>
                    </div>
                    <div class="stats-grid">
                        <div><span>মোট নম্বর</span><strong>{{ toBangla(selectedResult.total_marks) }}</strong></div>
                        <div><span>পূর্ণ নম্বর</span><strong>{{ toBangla(selectedResult.total_full_marks) }}</strong></div>
                        <div><span>মেধাক্রম</span><strong>{{ toBangla(selectedResult.position || '—') }}</strong></div>
                    </div>
                    <section v-if="selectedResult.combined" class="content-card calculation-guide">
                        <h2>কম্বাইন রেজাল্ট কীভাবে হিসাব হয়েছে?</h2>
                        <p v-if="selectedResult.mode === 'percentage'">
                            প্রতিটি পরীক্ষার নম্বর শতাংশে রূপান্তর করা হয়েছে:
                            <strong>(মূল প্রাপ্ত নম্বর ÷ মূল পূর্ণমান) × পরীক্ষার ওজন (%)</strong>।
                            সব পরীক্ষার ওজনের যোগফল ১০০%।
                        </p>
                        <p v-else>
                            প্রতিটি পরীক্ষার নম্বর নির্ধারিত পূর্ণমানে রূপান্তর করা হয়েছে:
                            <strong>(মূল প্রাপ্ত নম্বর ÷ মূল পূর্ণমান) × নির্ধারিত পূর্ণমান</strong>।
                        </p>
                        <p>কোন পরীক্ষার কত ওজন বা পূর্ণমান ছিল:</p>
                        <ul>
                            <li v-for="(exam, index) in selectedResult.exams" :key="`${exam.name}-weight-${index}`">
                                {{ exam.name }} — {{ toBangla(exam.allocation) }}{{ selectedResult.mode === 'percentage' ? '%' : ' নম্বর' }}
                            </li>
                        </ul>
                        <p>প্রতিটি বিষয়ের কম্বাইন নম্বর হলো সংশ্লিষ্ট পরীক্ষাগুলোর রূপান্তরিত নম্বরের যোগফল। বিষয়ের গ্রেড প্রতিষ্ঠানের নির্ধারিত গ্রেডিং স্কেল অনুযায়ী হয়েছে।</p>
                        <p>GPA নিয়মিত বিষয়ের গ্রেড পয়েন্টের গড় থেকে হিসাব হয়; ঐচ্ছিক বিষয় থাকলে ২ পয়েন্টের বেশি অংশ বোনাস হিসেবে যোগ হয়। নিয়মিত বিষয়ে F বা নম্বর অসম্পূর্ণ থাকলে GPA ০ হয়।</p>
                        <p>মেধাক্রম মোট নম্বরের শতকরা হারে নির্ধারিত; অসম্পূর্ণ ফলাফলে মেধাক্রম দেওয়া হয় না।</p>
                    </section>
                    <h2 class="subheading">বিষয়ভিত্তিক নম্বর</h2>
                    <div class="content-card mark-list">
                        <div v-for="mark in selectedResult.marks" :key="mark.subject" class="mark-row">
                            <div>
                                <strong>{{ mark.subject || 'বিষয়' }}</strong>
                                <small>{{ mark.absent ? 'অনুপস্থিত' : `পূর্ণমান ${toBangla(mark.full_marks || '—')}` }}</small>
                                <small v-if="mark.incomplete" class="incomplete-mark">নম্বর অসম্পূর্ণ</small>
                                <small v-for="component in mark.components || []" :key="component.exam_name" class="component-mark">
                                    {{ component.exam_name }}:
                                    <template v-if="component.original_marks !== null">
                                        ({{ toBangla(component.original_marks) }} ÷ {{ toBangla(component.original_full_marks) }})
                                        × {{ toBangla(component.allocation) }}{{ selectedResult.mode === 'percentage' ? '%' : ' নম্বর' }} =
                                    </template>
                                    {{ component.obtained === null ? 'অসম্পূর্ণ' : toBangla(component.obtained) }}/{{ toBangla(component.full_marks) }}
                                </small>
                            </div>
                            <strong>{{ mark.absent ? '—' : toBangla(mark.score) }}</strong>
                            <span class="grade-pill">{{ mark.grade || '—' }}</span>
                        </div>
                        <p v-if="!selectedResult.marks.length" class="empty-inline">বিষয়ভিত্তিক নম্বর পাওয়া যায়নি।</p>
                    </div>
                </template>
                <template v-else>
                    <header class="page-heading">
                        <button class="back-button" aria-label="হোমে ফিরে যান" @click="showTab('home')"><i class="fa-solid fa-arrow-left"></i></button>
                        <div><h1>ফলাফল</h1><p>{{ student.name }}</p></div>
                    </header>
                    <button v-for="result in studentResults" :key="result.id" class="content-card result-list-item" @click="selectedResultId = result.id">
                        <span class="result-icon"><i class="fa-solid fa-trophy" aria-hidden="true"></i></span>
                        <span class="result-list-copy"><strong>{{ result.exam_name }}</strong><small>{{ formatDate(result.exam_date) }} · {{ result.grade || '—' }}</small></span>
                        <span class="list-gpa">{{ toBangla(Number(result.gpa || 0).toFixed(2)) }} ›</span>
                    </button>
                    <div v-if="!studentResults.length" class="empty-card">এখনো কোনো ফলাফল প্রকাশিত হয়নি।</div>
                </template>
            </section>

            <section v-else-if="activeTab === 'attendance'" class="screen">
                <header class="page-heading">
                    <button class="back-button" aria-label="হোমে ফিরে যান" @click="showTab('home')"><i class="fa-solid fa-arrow-left"></i></button>
                    <div><h1>হাজিরা</h1><p>{{ student.name }}</p></div>
                </header>
                <div class="month-selector">
                    <button
                        v-for="month in attendanceMonths"
                        :key="month.value"
                        :class="{ active: month.value === attendance.month }"
                        :disabled="month.value > todayDate.slice(0, 7)"
                        @click="changeAttendanceMonth(month.value)"
                    >
                        {{ month.label }}
                    </button>
                </div>
                <div class="content-card attendance-summary">
                    <div class="attendance-ring" :style="{ '--attendance': `${attendance.summary.percentage}%` }">
                        <strong>{{ toBangla(attendance.summary.percentage) }}%</strong>
                    </div>
                    <div class="attendance-summary-copy">
                        <h2>{{ attendance.month_label }}</h2>
                        <p>{{ toBangla(attendance.summary.working_days) }} কর্মদিবস · সব মাসে {{ toBangla(attendance.summary.percentage) }}%</p>
                    </div>
                    <div class="attendance-stat-cards">
                        <div><span>উপস্থিত</span><strong class="text-green">{{ toBangla(attendance.summary.present) }}</strong></div>
                        <div><span>অনুপস্থিত</span><strong class="text-red">{{ toBangla(attendance.summary.absent) }}</strong></div>
                        <div><span>দেরি</span><strong class="text-amber">{{ toBangla(attendance.summary.late) }}</strong></div>
                    </div>
                </div>
                <div class="content-card calendar-card">
                    <div class="weekdays"><span>শ</span><span>র</span><span>সো</span><span>ম</span><span>বু</span><span>বৃ</span><span>শু</span></div>
                    <div class="calendar-grid">
                        <span v-for="(day, index) in calendarDays" :key="`day-${index}`" class="calendar-day" :class="day ? statusClass(attendanceByDay[day]) : ''" :title="day ? statusLabel(attendanceByDay[day]) : ''">
                            {{ day ? toBangla(day) : '' }}
                        </span>
                    </div>
                    <div class="calendar-legend">
                        <span><i class="legend-present"></i>উপস্থিত</span><span><i class="legend-absent"></i>অনুপস্থিত</span><span><i class="legend-late"></i>দেরি</span>
                    </div>
                </div>
                <h2 class="subheading">এই মাসের হাজিরা</h2>
                <div class="content-card attendance-log">
                    <div v-for="day in attendance.days" :key="day.date" class="attendance-log-row">
                        <span>{{ formatDate(day.date) }}</span><strong :class="statusClass(day.status)">{{ statusLabel(day.status) }}</strong>
                    </div>
                    <p v-if="!attendance.days.length" class="empty-inline">এই মাসে হাজিরার তথ্য পাওয়া যায়নি।</p>
                </div>
            </section>

            <section v-else-if="activeTab === 'classRoutine'" class="screen">
                <header class="page-heading">
                    <button class="back-button" aria-label="হোমে ফিরে যান" @click="showTab('home')"><i class="fa-solid fa-arrow-left"></i></button>
                    <div><h1>ক্লাস রুটিন</h1><p>{{ student.class }}<template v-if="student.section"> · {{ student.section }}</template></p></div>
                </header>
                <div class="weekday-selector">
                    <button
                        v-for="day in weekdays"
                        :key="day.id"
                        :class="{ active: selectedRoutineDay === day.id }"
                        @click="selectedRoutineDay = day.id"
                    >
                        <strong>{{ day.short }}</strong>
                        <small v-if="day.id === currentTime.getDay()">আজ</small>
                    </button>
                </div>
                <div v-if="routineItems.length" class="content-card class-routine-card">
                    <template v-for="item in routineTimeline" :key="item.id">
                        <div v-if="item.type === 'break'" class="routine-break">
                            <i class="fa-solid fa-mug-hot"></i>
                            বিরতি · {{ formatTime(item.start_time) }} – {{ formatTime(item.end_time) }}
                        </div>
                        <article v-else class="class-period" :class="{ ongoing: isOngoingPeriod(item) }">
                            <div class="period-number">
                                <span>{{ toBangla(classRoutine.filter(period => Number(period.day_of_week) === Number(selectedRoutineDay)).findIndex(period => period.id === item.id) + 1) }}</span>
                                <small>পিরিয়ড</small>
                            </div>
                            <div class="period-copy">
                                <strong>{{ item.subject }}</strong>
                                <span>{{ formatTime(item.start_time) }} – {{ formatTime(item.end_time) }}</span>
                                <small>{{ item.teacher || 'শিক্ষক নির্ধারিত নেই' }}<template v-if="item.classroom"> · কক্ষ {{ item.classroom }}</template></small>
                            </div>
                            <span v-if="isOngoingPeriod(item)" class="ongoing-badge">এখন চলছে</span>
                        </article>
                    </template>
                </div>
                <div v-else class="empty-card">
                    <i class="fa-regular fa-calendar-xmark empty-icon"></i>
                    <strong>{{ weekdays.find(day => day.id === selectedRoutineDay)?.name }}-এর ক্লাস রুটিন নেই</strong>
                    <span>রুটিন প্রকাশ হলে এখানে দেখা যাবে।</span>
                </div>
            </section>

            <section v-else-if="activeTab === 'routine'" class="screen">
                <header class="page-heading">
                    <button class="back-button" aria-label="হোমে ফিরে যান" @click="showTab('home')"><i class="fa-solid fa-arrow-left"></i></button>
                    <div><h1>পরীক্ষার রুটিন</h1><p>{{ student.class }} · {{ student.section }}</p></div>
                </header>
                <div class="routine-legend" aria-label="রুটিনের রং নির্দেশিকা">
                    <span class="legend-completed">হয়ে গেছে</span>
                    <span class="legend-today">আজকের পরীক্ষা</span>
                    <span class="legend-ongoing">পরীক্ষাটি চলমান</span>
                    <span class="legend-next">➜ পরের পরীক্ষা</span>
                    <span class="legend-upcoming">পরবর্তী পরীক্ষাগুলো</span>
                </div>
                <article v-for="exam in examRoutine" :key="exam.id" class="exam-card">
                    <h2>{{ exam.name }}</h2>
                    <p class="exam-date-range">{{ formatDate(exam.start_date) }}<template v-if="exam.end_date !== exam.start_date"> — {{ formatDate(exam.end_date) }}</template></p>
                    <div
                        v-for="subject in exam.subjects"
                        :key="subject.id"
                        class="exam-row"
                        :class="{
                            'is-completed': getExamState(subject) === 'completed',
                            'is-upcoming': getExamState(subject) === 'upcoming',
                            'is-today': getExamState(subject) === 'today',
                            'is-ongoing': getExamState(subject) === 'ongoing',
                            'is-next': nextExamSubjectId === subject.id,
                        }"
                    >
                        <span class="exam-date">{{ formatDate(subject.date) }}</span>
                        <div>
                            <strong>{{ subject.name || 'বিষয়' }}</strong>
                            <small>{{ formatTime(subject.start_time) }}<template v-if="subject.end_time"> – {{ formatTime(subject.end_time) }}</template></small>
                            <small v-if="nextExamSubjectId === subject.id" class="next-marker">➜ পরের পরীক্ষা</small>
                            <small v-else-if="getExamState(subject) === 'ongoing'" class="ongoing-marker">পরীক্ষাটি চলমান</small>
                            <small v-else-if="getExamState(subject) === 'today'" class="today-marker">আজকের পরীক্ষা</small>
                        </div>
                        <span class="exam-marks">{{ toBangla(subject.full_marks) }}</span>
                    </div>
                    <p v-if="!exam.subjects.length" class="empty-inline">বিষয়ভিত্তিক সময়সূচি এখনো দেওয়া হয়নি।</p>
                </article>
                <div v-if="!examRoutine.length" class="empty-card">কোনো পরীক্ষার রুটিন নেই।</div>
            </section>

            <section v-else-if="activeTab === 'homework'" class="screen">
                <header class="page-heading">
                    <button class="back-button" aria-label="হোমে ফিরে যান" @click="showTab('home')"><i class="fa-solid fa-arrow-left"></i></button>
                    <div><h1>বাড়ির কাজ</h1><p>{{ student.class }} · {{ student.section }}</p></div>
                </header>
                <div class="homework-tabs">
                    <button :class="{ active: homeworkTab === 'todo' }" @click="homeworkTab = 'todo'">করতে হবে ({{ toBangla(todoHomework.length) }})</button>
                    <button :class="{ active: homeworkTab === 'completed' }" @click="homeworkTab = 'completed'">সম্পন্ন ও পুরোনো ({{ toBangla(completedHomework.length) }})</button>
                </div>
                <article v-for="item in visibleHomework" :key="item.id" class="content-card homework-card">
                    <div class="homework-card-top">
                        <span class="subject-chip">{{ item.subject || 'বিষয়' }}</span>
                        <strong class="due-chip" :class="{ overdue: !item.is_completed && item.due_date < todayDate }">
                            {{ item.is_completed ? 'সম্পন্ন' : item.due_date < todayDate ? 'জমার সময় পেরিয়েছে' : `জমা ${formatDate(item.due_date)}` }}
                        </strong>
                    </div>
                    <h2>{{ item.title }}</h2>
                    <p>{{ item.description }}</p>
                    <div class="homework-card-footer">
                        <small v-if="item.teacher">{{ item.teacher }} · দেওয়া {{ formatDate(item.due_date) }}</small>
                        <button :class="{ completed: item.is_completed }" @click="toggleHomework(item)">
                            <i :class="item.is_completed ? 'fa-solid fa-rotate-left' : 'fa-solid fa-check'"></i>
                            {{ item.is_completed ? 'আবার করতে হবে' : 'করেছি' }}
                        </button>
                    </div>
                </article>
                <div v-if="!visibleHomework.length" class="empty-card">
                    {{ homeworkTab === 'todo' ? 'এখন কোনো বাড়ির কাজ বাকি নেই।' : 'এখনো কোনো কাজ সম্পন্ন করা হয়নি।' }}
                </div>
            </section>

            <section v-else-if="activeTab === 'profile'" class="screen">
                <header class="page-heading">
                    <button class="back-button" aria-label="হোমে ফিরে যান" @click="showTab('home')"><i class="fa-solid fa-arrow-left"></i></button>
                    <div><h1>প্রোফাইল</h1><p>শিক্ষার্থীর তথ্য</p></div>
                </header>
                <div class="content-card profile-card">
                    <div class="profile-avatar">
                        <img v-if="student.photo" :src="student.photo" :alt="student.name">
                        <span v-else>{{ initials }}</span>
                    </div>
                    <h2>{{ student.name }}</h2>
                    <p>{{ student.institute.name }}</p>
                    <dl>
                        <div><dt>শিক্ষার্থী আইডি</dt><dd>{{ student.student_id }}</dd></div>
                        <div><dt>শ্রেণি</dt><dd>{{ student.class || '—' }}</dd></div>
                        <div><dt>শাখা</dt><dd>{{ student.section || '—' }}</dd></div>
                        <div><dt>রোল</dt><dd>{{ toBangla(student.roll || '—') }}</dd></div>
                        <div><dt>শিক্ষাবর্ষ</dt><dd>{{ toBangla(student.year || '—') }}</dd></div>
                    </dl>
                </div>
                <Link :href="route('logout')" method="post" as="button" class="content-card result-list-item menu-logout">
                    <span class="quick-icon rose"><i class="fa-solid fa-arrow-right-from-bracket"></i></span>
                    <span class="result-list-copy"><strong>লগআউট</strong><small>অ্যাকাউন্ট থেকে বের হও</small></span><i class="fa-solid fa-chevron-right list-gpa"></i>
                </Link>
            </section>

            <section v-else-if="activeTab === 'menu'" class="screen">
                <header class="page-heading">
                    <button class="back-button" aria-label="হোমে ফিরে যান" @click="showTab('home')"><i class="fa-solid fa-arrow-left"></i></button>
                    <div><h1>মেনু</h1><p>তোমার শিক্ষার্থী সেবা</p></div>
                </header>
                <button class="content-card result-list-item" @click="showTab('classRoutine')">
                    <span class="quick-icon blue"><i class="fa-regular fa-calendar-days"></i></span><span class="result-list-copy"><strong>ক্লাস রুটিন</strong><small>সপ্তাহের ক্লাসের সময়সূচি</small></span><i class="fa-solid fa-chevron-right list-gpa"></i>
                </button>
                <button class="content-card result-list-item" @click="showTab('routine')">
                    <span class="quick-icon rose"><i class="fa-regular fa-file-lines"></i></span><span class="result-list-copy"><strong>পরীক্ষার রুটিন</strong><small>আসন্ন ও সম্পন্ন পরীক্ষা</small></span><i class="fa-solid fa-chevron-right list-gpa"></i>
                </button>
                <button class="content-card result-list-item" @click="showTab('attendance')">
                    <span class="quick-icon green"><i class="fa-regular fa-square-check"></i></span><span class="result-list-copy"><strong>হাজিরা</strong><small>মাসিক উপস্থিতির হিসাব</small></span><i class="fa-solid fa-chevron-right list-gpa"></i>
                </button>
                <button class="content-card result-list-item" @click="showTab('notices')">
                    <span class="quick-icon amber"><i class="fa-regular fa-bell"></i></span><span class="result-list-copy"><strong>নোটিশ</strong><small>বিদ্যালয়ের ঘোষণা</small></span><i class="fa-solid fa-chevron-right list-gpa"></i>
                </button>
                <button class="content-card result-list-item" @click="showTab('profile')">
                    <span class="quick-icon blue"><i class="fa-regular fa-user"></i></span><span class="result-list-copy"><strong>প্রোফাইল</strong><small>শিক্ষার্থীর তথ্য</small></span><i class="fa-solid fa-chevron-right list-gpa"></i>
                </button>
            </section>

            <section v-else class="screen">
                <header class="page-heading"><div><h1>নোটিশ</h1><p>বিদ্যালয়ের সাম্প্রতিক ঘোষণা</p></div></header>
                <article v-for="notice in notices" :key="notice.id" class="content-card notice-card">
                    <div class="notice-heading"><span class="notice-dot" :class="notice.priority"></span><strong>{{ notice.title }}</strong></div>
                    <p>{{ notice.description }}</p>
                    <small>{{ formatDate(notice.publish_date) }}</small>
                </article>
                <div v-if="!notices.length" class="empty-card">এখনো কোনো নোটিশ নেই।</div>
            </section>

            <nav class="bottom-nav" aria-label="শিক্ষার্থী নেভিগেশন">
                <button :class="{ active: activeTab === 'home' }" @click="showTab('home')"><i class="fa-solid fa-house"></i>হোম</button>
                <button :class="{ active: activeTab === 'result' }" @click="showTab('result')"><i class="fa-solid fa-trophy"></i>ফলাফল</button>
                <button :class="{ active: activeTab === 'classRoutine' }" @click="showTab('classRoutine')"><i class="fa-regular fa-calendar-days"></i>রুটিন</button>
                <button :class="{ active: activeTab === 'homework' }" @click="showTab('homework')"><i class="fa-solid fa-book-open"></i>বাড়ির কাজ</button>
                <button :class="{ active: ['menu', 'notices', 'attendance', 'routine', 'profile'].includes(activeTab) }" @click="showTab('menu')"><i class="fa-solid fa-grip"></i>মেনু</button>
            </nav>
        </div>
    </main>
</template>

<style scoped>
.student-app {
    --bg: #f2f4fb;
    --card: #fff;
    --ink: #151b2d;
    --muted: #7b879a;
    --line: #e9edf3;
    min-height: 100vh;
    padding-bottom: 90px;
    background: var(--bg);
    color: var(--ink);
    color-scheme: light;
    font-family: 'Hind Siliguri', system-ui, sans-serif;
}
.student-shell { max-width: 760px; min-height: 100vh; margin: 0 auto; padding: 18px 28px 30px; }
.topbar { display: flex; align-items: center; gap: 12px; }
.school-mark { display: grid; width: 48px; height: 48px; flex: 0 0 48px; place-items: center; overflow: hidden; border-radius: 17px; background: var(--card); box-shadow: 0 4px 16px #14182b0c; font-size: 24px; }
.school-mark img { width: 100%; height: 100%; object-fit: contain; }
.school-title { display: grid; flex: 1; min-width: 0; }
.school-title strong { overflow: hidden; font-size: 19px; line-height: 1.15; text-overflow: ellipsis; white-space: nowrap; }
.school-title strong span { color: #d6001c; }
.school-title small { overflow: hidden; color: var(--muted); font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
.header-action { position: relative; display: grid; width: 52px; height: 52px; flex: 0 0 52px; place-items: center; border: 1px solid #e8ebf2; border-radius: 18px; background: var(--card); color: #c8102e; font-size: 20px; box-shadow: 0 2px 6px #1621390c; }
.beta-tag { margin-left: 6px; padding: 2px 9px; border-radius: 99px; background: #ffcf39; color: #624500; font-size: 11px; font-weight: 800; }
.notification-count { position: absolute; top: -5px; right: -5px; display: grid; min-width: 19px; height: 19px; place-items: center; padding: 0 4px; border-radius: 99px; background: #d20d32; color: #fff; font-size: 10px; }
.screen { padding: 20px 0 18px; animation: enter .2s ease-out; }
@keyframes enter { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: none; } }
.welcome-card { position: relative; display: flex; flex-wrap: wrap; align-items: center; gap: 15px; overflow: hidden; padding: 25px; border-radius: 28px; background: #0f766e; color: #fff; box-shadow: 0 18px 32px -16px #0f766e70; }
.welcome-orb { position: absolute; top: -55px; right: -40px; width: 160px; height: 160px; border-radius: 50%; background: #ffffff14; }
.student-avatar { z-index: 1; display: grid; width: 66px; height: 66px; flex: 0 0 66px; place-items: center; overflow: hidden; border: 1px solid #ffffff69; border-radius: 22px; background: #ffffff20; font-size: 23px; font-weight: 700; }
.student-avatar img { width: 100%; height: 100%; object-fit: cover; }
.welcome-copy { z-index: 1; min-width: 0; }
.welcome-copy p,.welcome-copy span { margin: 0; color: #ffffffd9; font-size: 13px; }
.welcome-copy h1 { max-width: 245px; margin: 1px 0; overflow: hidden; font-size: 20px; line-height: 1.3; text-overflow: ellipsis; white-space: nowrap; }
.student-tags { z-index: 1; display: flex; flex-wrap: wrap; gap: 7px; width: 100%; }
.student-tags span { padding: 4px 11px; border-radius: 99px; background: #00000029; font-size: 12px; }
.quick-grid { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: 9px; margin-top: 16px; }
.quick-card { position: relative; display: flex; min-width: 0; min-height: 130px; flex-direction: column; align-items: center; justify-content: center; gap: 9px; padding: 13px 5px 11px; border: 1px solid #ffffff; border-radius: 24px; background: var(--card); color: var(--ink); box-shadow: 0 8px 20px -14px #25324d50; }
.quick-card strong { min-height: 30px; font-size: 11px; line-height: 1.25; text-align: center; }
.quick-icon { display: grid; width: 54px; height: 54px; place-items: center; border-radius: 18px; font-size: 21px; }
.quick-icon.rose { background: #fff0f2; color: #d6001c; }.quick-icon.blue { background: #e8f4fc; color: #0876a8; }.quick-icon.green { background: #e9f8ee; color: #12823a; }.quick-icon.amber { background: #fff6df; color: #d18a00; }.quick-icon.orange { background: #fff0e3; color: #ea580c; }
.quick-badge { position: absolute; top: 9px; right: 9px; display: grid; min-width: 21px; height: 21px; place-items: center; padding: 0 5px; border-radius: 99px; background: #d6001c; color: #fff; font-size: 10px; font-weight: 700; }
.quick-badge.green-badge { background: #12823a; }
.section-heading { display: flex; align-items: end; justify-content: space-between; margin: 23px 0 9px; }
.section-heading h2,.subheading { margin: 0; font-size: 18px; font-weight: 700; }
.section-heading button { padding: 3px 0; border: 0; background: transparent; color: #0a6a1c; font-size: 13px; font-weight: 700; }
.result-banner { position: relative; width: 100%; overflow: hidden; padding: 22px; border: 0; border-radius: 25px; background: linear-gradient(125deg,#183c91,#326bd2); color: #fff; text-align: left; box-shadow: 0 18px 30px -16px #2550a299; }
.result-topline { display: flex; align-items: flex-start; justify-content: space-between; }
.pass-pill { padding: 4px 10px; border-radius: 99px; background: #ffffff26; font-size: 12px; font-weight: 700; }
.gpa { display: grid; justify-items: end; line-height: 1; }
.gpa small { color: #ffffffc9; font-size: 11px; }
.gpa strong { margin: 5px 0 2px; font-size: 34px; }
.result-banner h3 { margin: -4px 0 10px; font-size: 20px; }
.result-summary { display: flex; flex-wrap: wrap; gap: 12px; color: #ffffffe8; font-size: 12px; }
.content-card,.empty-card { border-radius: 23px; background: var(--card); box-shadow: 0 1px 2px #14182b0a,0 8px 24px -14px #14182b24; }
.empty-card { padding: 20px; color: var(--muted); font-size: 14px; text-align: center; }
.next-exam { display: flex; align-items: center; gap: 13px; padding: 14px; }
.date-tile { display: grid; width: 53px; height: 55px; flex: 0 0 53px; align-content: center; justify-items: center; border-radius: 17px; background: #fff0f2; color: #d6001c; }
.date-tile strong { font-size: 22px; line-height: 1.05; }.date-tile span { font-size: 11px; }
.next-exam-copy { display: grid; flex: 1; min-width: 0; }.next-exam-copy strong { overflow: hidden; font-size: 14px; text-overflow: ellipsis; white-space: nowrap; }.next-exam-copy span { color: var(--muted); font-size: 12px; }
.arrow { color: var(--muted); font-size: 28px; }
.notice-preview { display: flex; align-items: center; gap: 12px; margin-top: 8px; padding: 14px; }
.notice-preview div { display: grid; min-width: 0; }.notice-preview strong { overflow: hidden; font-size: 14px; text-overflow: ellipsis; white-space: nowrap; }.notice-preview small { color: var(--muted); font-size: 12px; }
.notice-dot { width: 11px; height: 11px; flex: 0 0 11px; border-radius: 50%; background: #f59e0b; }.notice-dot.high { background: #e11d48; }.notice-dot.low { background: #16a34a; }
.page-heading { display: flex; align-items: center; gap: 12px; margin-bottom: 17px; }
.page-heading h1 { margin: 0; font-size: 24px; line-height: 1.2; }.page-heading p { margin: 2px 0 0; color: var(--muted); font-size: 13px; }
.back-button { width: 42px; height: 42px; border: 0; border-radius: 15px; background: var(--card); color: #0a6a1c; font-size: 30px; line-height: 1; }
.result-detail { display: flex; align-items: center; gap: 14px; padding: 16px; }.result-detail strong { font-size: 16px; }.result-detail p { margin: 2px 0 6px; color: var(--muted); font-size: 12px; }.result-detail p.combined-exams { margin-top: 6px; line-height: 1.5; }
.calculation-guide { margin-top: 12px; padding: 16px; }.calculation-guide h2 { margin: 0 0 8px; font-size: 16px; }.calculation-guide p { margin: 7px 0; color: var(--muted); font-size: 12px; line-height: 1.6; }.calculation-guide strong { color: var(--ink); }.calculation-guide ul { margin: 4px 0 8px; padding-left: 20px; color: var(--muted); font-size: 12px; }
.gpa-circle { display: grid; width: 77px; height: 77px; flex: 0 0 77px; place-items: center; border: 6px solid #0a6a1c; border-radius: 50%; color: #0a6a1c; font-size: 20px; font-weight: 700; }
.grade-pill { display: inline-block; padding: 3px 10px; border-radius: 99px; background: #dcfce7; color: #166534; font-size: 12px; font-weight: 700; }
.stats-grid { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 8px; margin-top: 12px; }
.stats-grid div { display: grid; justify-items: center; padding: 11px 4px; border-radius: 17px; background: var(--card); box-shadow: 0 1px 2px #14182b0a; }
.stats-grid span { color: var(--muted); font-size: 11px; }.stats-grid strong { font-size: 20px; }.subheading { margin: 22px 0 9px; font-size: 17px; }
.mark-list { padding: 0 14px; }.mark-row { display: flex; align-items: center; gap: 10px; padding: 12px 0; border-bottom: 1px solid var(--line); }.mark-row:last-child { border-bottom: 0; }.mark-row div { display: grid; flex: 1; min-width: 0; }.mark-row div strong { overflow: hidden; font-size: 14px; text-overflow: ellipsis; white-space: nowrap; }.mark-row small { color: var(--muted); font-size: 11px; }.mark-row small.incomplete-mark { color: #b91c1c; }.mark-row small.component-mark { padding-left: 8px; }.mark-row>strong { font-size: 17px; }.empty-inline { margin: 0; padding: 16px 0; color: var(--muted); font-size: 13px; text-align: center; }
.result-list-item { display: flex; width: 100%; align-items: center; gap: 12px; margin-bottom: 9px; padding: 14px; border: 0; color: var(--ink); text-align: left; }.result-icon { display: grid; width: 42px; height: 42px; place-items: center; border-radius: 15px; background: #fff0f2; }.result-list-copy { display: grid; flex: 1; min-width: 0; }.result-list-copy strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.result-list-copy small { color: var(--muted); font-size: 12px; }.list-gpa { color: #0a6a1c; font-size: 14px; font-weight: 700; }
.menu-logout { text-decoration: none; }
.attendance-summary { display: flex; align-items: center; gap: 18px; padding: 17px; }.attendance-summary h2 { margin: 0; font-size: 17px; }.attendance-summary p { margin: 2px 0 0; color: var(--muted); font-size: 13px; }
.attendance-ring { display: grid; width: 86px; height: 86px; flex: 0 0 86px; place-items: center; border-radius: 50%; background: conic-gradient(#16a34a var(--attendance),var(--line) 0); transform: rotate(-90deg); }.attendance-ring strong { display: grid; width: 68px; height: 68px; place-items: center; border-radius: 50%; background: var(--card); font-size: 19px; transform: rotate(90deg); }
.attendance-stats { margin-bottom: 13px; }.text-green { color: #15803d; }.text-red { color: #dc2626; }.text-amber { color: #d97706; }
.calendar-card { padding: 15px; }.weekdays,.calendar-grid { display: grid; grid-template-columns: repeat(7,minmax(0,1fr)); gap: 6px; text-align: center; }.weekdays { margin-bottom: 8px; color: var(--muted); font-size: 12px; font-weight: 700; }.calendar-day { display: grid; aspect-ratio: 1; place-items: center; border-radius: 11px; color: var(--muted); font-size: 14px; font-weight: 700; }.calendar-day.present { background: #dcfce7; color: #166534; }.calendar-day.absent { background: #ef4444; color: #fff; }.calendar-day.late { background: #fde68a; color: #854d0e; }.calendar-day.holiday { background: #e2e8f0; }
.calendar-legend { display: flex; justify-content: center; gap: 12px; margin-top: 14px; color: var(--muted); font-size: 11px; }.calendar-legend span { display: flex; align-items: center; gap: 4px; }.calendar-legend i { width: 10px; height: 10px; border-radius: 3px; }.legend-present { background: #bbf7d0; }.legend-absent { background: #ef4444; }.legend-late { background: #fcd34d; }
.attendance-log { padding: 0 14px; }.attendance-log-row { display: flex; justify-content: space-between; padding: 11px 0; border-bottom: 1px solid var(--line); font-size: 13px; }.attendance-log-row:last-of-type { border: 0; }.attendance-log-row strong { padding: 2px 9px; border-radius: 99px; font-size: 11px; }.attendance-log-row strong.present { background: #dcfce7; color: #166534; }.attendance-log-row strong.absent { background: #fee2e2; color: #991b1b; }.attendance-log-row strong.late { background: #fef3c7; color: #92400e; }
.exam-card { margin-bottom: 12px; padding: 17px; border-radius: 23px; background: var(--card); box-shadow: 0 1px 2px #14182b0a,0 8px 24px -14px #14182b24; }.exam-card h2 { margin: 0; font-size: 17px; }.exam-date-range { margin: 2px 0 10px; color: var(--muted); font-size: 12px; }.routine-legend { display: flex; flex-wrap: wrap; gap: 6px; margin: -5px 0 12px; }.routine-legend span { padding: 4px 8px; border-radius: 99px; font-size: 10px; font-weight: 700; }.legend-completed { background: #fee2e2; color: #b91c1c; }.legend-today { background: #fef3c7; color: #92400e; }.legend-ongoing { background: #ede9fe; color: #6d28d9; }.legend-next { background: #dcfce7; color: #166534; }.legend-upcoming { background: #dbeafe; color: #1d4ed8; }.exam-row { display: flex; align-items: center; gap: 10px; padding: 11px 8px; border-top: 1px solid var(--line); }.exam-row.is-completed { border-radius: 10px; background: #fff1f2; }.exam-row.is-upcoming { background: #eff6ff; }.exam-row.is-today { border-radius: 10px; background: #fffbeb; box-shadow: inset 3px 0 #d97706; }.exam-row.is-ongoing { border-radius: 10px; background: #f5f3ff; box-shadow: inset 3px 0 #7c3aed; }.exam-row.is-next { border-radius: 10px; background: #ecfdf5; box-shadow: inset 3px 0 #16a34a; }.exam-date { width: 76px; flex: 0 0 76px; color: #0a6a1c; font-size: 11px; font-weight: 700; }.exam-row.is-completed .exam-date,.exam-row.is-completed div strong { color: #b91c1c; }.exam-row div { display: grid; flex: 1; min-width: 0; }.exam-row div strong { font-size: 13px; }.exam-row small { color: var(--muted); font-size: 11px; }.exam-row small.next-marker { color: #15803d; font-weight: 700; }.exam-row small.today-marker { color: #92400e; font-weight: 700; }.exam-row small.ongoing-marker { color: #6d28d9; font-weight: 700; }.exam-marks { color: var(--muted); font-size: 12px; }
.notice-card { margin-bottom: 10px; padding: 15px; }.notice-heading { display: flex; align-items: center; gap: 9px; }.notice-heading strong { font-size: 15px; }.notice-card p { margin: 8px 0; color: var(--muted); font-size: 13px; line-height: 1.55; white-space: pre-line; }.notice-card small { color: var(--muted); font-size: 11px; }
.bottom-nav { position: fixed; z-index: 10; right: 0; bottom: 0; left: 0; display: grid; max-width: 760px; grid-template-columns: repeat(5,1fr); margin: 0 auto; padding: 9px 5px max(10px,env(safe-area-inset-bottom)); border-top: 1px solid #e6e9f0; background: #ffffffed; backdrop-filter: blur(14px); }.bottom-nav button { position: relative; display: grid; justify-items: center; gap: 4px; padding: 3px 0; border: 0; background: transparent; color: #9aa5b7; font-family: inherit; font-size: 12px; font-weight: 700; }.bottom-nav button i { font-size: 21px; line-height: 1; }.bottom-nav button.active { color: #c8102e; }.bottom-nav button.active::before { position: absolute; top: -10px; width: 32px; height: 4px; border-radius: 0 0 4px 4px; background: #c8102e; content: ''; }
.bottom-nav { background: #ffffffed; }
.grade-pill { background: #dcfce7; color: #166534; }
.quick-icon.rose { background: #fff0f2; }.quick-icon.blue { background: #e8f4fc; }.quick-icon.green { background: #e9f8ee; }.quick-icon.amber { background: #fff6df; }.quick-icon.orange { background: #fff0e3; }
.date-tile { background: #fff0f2; }
.calendar-day.present { background: #dcfce7; color: #166534; }.calendar-day.absent { background: #ef4444; }.calendar-day.late { background: #fde68a; color: #854d0e; }.calendar-day.holiday { background: #e2e8f0; }
.routine-legend .legend-completed { background: #fee2e2; color: #b91c1c; }.routine-legend .legend-today { background: #fef3c7; color: #92400e; }.routine-legend .legend-ongoing { background: #ede9fe; color: #6d28d9; }.routine-legend .legend-next { background: #dcfce7; color: #166534; }.routine-legend .legend-upcoming { background: #dbeafe; color: #1d4ed8; }
.exam-row.is-completed { background: #fff1f2; }.exam-row.is-upcoming { background: #eff6ff; }.exam-row.is-today { background: #fffbeb; }.exam-row.is-ongoing { background: #f5f3ff; }.exam-row.is-next { background: #ecfdf5; }
.result-icon { background: #fff0f2; }
.attendance-log-row strong.present { background: #dcfce7; color: #166534; }.attendance-log-row strong.absent { background: #fee2e2; color: #991b1b; }.attendance-log-row strong.late { background: #fef3c7; color: #92400e; }
.topbar { margin-bottom: 24px; }.school-mark { border: 1px solid #e8ebf2; color: #c8102e; }.quick-card strong { min-height: 0; font-size: 13px; }.section-heading { align-items: center; }.section-heading h2,.subheading { font-size: 20px; }.section-heading button { color: #c8102e; font-size: 14px; }.content-card,.empty-card,.exam-card { box-shadow: 0 7px 22px -15px #29375060; }.page-heading { margin: 4px 0 22px; }.page-heading h1 { font-size: 26px; font-weight: 800; }.back-button { flex: 0 0 48px; width: 48px; height: 48px; border: 1px solid #e7eaf0; border-radius: 17px; box-shadow: 0 2px 6px #1621390c; color: #c8102e; font-size: 17px; }
.homework-preview { padding: 9px 16px; }.homework-preview-row { display: grid; width: 100%; grid-template-columns: minmax(0,1fr) auto; align-items: center; gap: 5px 12px; padding: 10px 0; border: 0; border-bottom: 1px solid var(--line); background: transparent; color: var(--ink); text-align: left; }.homework-preview-row:last-child { border-bottom: 0; }.homework-preview-row .subject-chip { grid-column: 1; }.homework-preview-row>strong { overflow: hidden; grid-column: 1; text-overflow: ellipsis; white-space: nowrap; }.homework-preview-row .due-chip { grid-row: 1 / span 2; grid-column: 2; }
.subject-chip { display: inline-flex; width: fit-content; max-width: 100%; overflow: hidden; padding: 4px 10px; border-radius: 99px; background: #e8fbfa; color: #08778a; font-size: 12px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }.due-chip { display: inline-flex; align-items: center; justify-content: center; max-width: 190px; padding: 5px 11px; border-radius: 99px; background: #e8edff; color: #3c4eb4; font-size: 12px; font-weight: 700; text-align: center; }.due-chip.overdue { background: #fee5e5; color: #ad2431; }
.month-selector,.weekday-selector { display: flex; gap: 9px; margin: 0 0 14px; overflow-x: auto; scrollbar-width: none; }.month-selector::-webkit-scrollbar,.weekday-selector::-webkit-scrollbar { display: none; }.month-selector button { flex: 1 0 auto; padding: 9px 13px; border: 1px solid #f1e8e9; border-radius: 99px; background: #fff; color: #465166; font-family: inherit; font-size: 14px; font-weight: 700; white-space: nowrap; }.month-selector button.active,.weekday-selector button.active { border-color: #c8102e; background: #c8102e; color: #fff; }.month-selector button:disabled { opacity: .5; }
.attendance-summary { flex-wrap: wrap; gap: 15px 20px; padding: 20px; }.attendance-ring { width: 110px; height: 110px; flex-basis: 110px; }.attendance-ring strong { width: 88px; height: 88px; font-size: 25px; }.attendance-summary-copy { flex: 1; min-width: 150px; }.attendance-summary-copy h2 { font-size: 21px; }.attendance-summary-copy p { font-size: 14px; }.attendance-stat-cards { display: grid; width: 100%; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 10px; }.attendance-stat-cards div { display: grid; justify-items: center; gap: 2px; padding: 12px 5px; border-radius: 16px; background: #f8fafc; }.attendance-stat-cards span { color: var(--muted); font-size: 13px; }.attendance-stat-cards strong { font-size: 21px; }
.weekday-selector { justify-content: space-between; }.weekday-selector button { display: grid; min-width: 72px; flex: 1 0 72px; gap: 1px; padding: 10px 6px; border: 1px solid #f1e8e9; border-radius: 17px; background: #fff; color: #4b5567; font-family: inherit; font-size: 14px; }.weekday-selector button strong { font-size: 14px; }.weekday-selector button small { font-size: 11px; }.class-routine-card { padding: 8px 20px; }.class-period { display: flex; align-items: center; gap: 15px; padding: 15px 0; border-bottom: 1px solid var(--line); }.class-period:last-child { border-bottom: 0; }.class-period.ongoing { margin: 3px -10px; padding: 14px 10px; border-radius: 15px; background: #effaf2; }.period-number { display: grid; width: 56px; height: 60px; flex: 0 0 56px; align-content: center; justify-items: center; border-radius: 16px; background: #e8fbfa; color: #08778a; }.period-number span { font-size: 22px; font-weight: 800; line-height: 1; }.period-number small { font-size: 11px; }.period-copy { display: grid; flex: 1; min-width: 0; gap: 1px; }.period-copy strong { font-size: 17px; }.period-copy span { color: #48566c; font-size: 14px; }.period-copy small { color: var(--muted); font-size: 12px; }.ongoing-badge { flex: 0 0 auto; padding: 5px 10px; border-radius: 99px; background: #dcfce7; color: #17643b; font-size: 11px; font-weight: 700; }.routine-break { margin: 7px 0; padding: 9px 12px; border-radius: 12px; background: #fff8e9; color: #ad5b13; font-size: 13px; font-weight: 700; text-align: center; }.empty-card { display: grid; justify-items: center; gap: 7px; }.empty-icon { color: #a7b1c1; font-size: 28px; }.empty-card strong { color: var(--ink); }.empty-card span { font-size: 12px; }
.homework-tabs { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 17px; }.homework-tabs button { padding: 12px 8px; border: 1px solid transparent; border-radius: 16px; background: transparent; color: #79869a; font-family: inherit; font-size: 15px; font-weight: 700; }.homework-tabs button.active { border-color: #edf0f5; background: #fff; color: #bd1633; box-shadow: 0 3px 8px #19243a10; }.homework-card { margin-bottom: 13px; padding: 18px 20px; }.homework-card-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }.homework-card h2 { margin: 8px 0 4px; font-size: 19px; }.homework-card>p { margin: 0 0 12px; color: #4e5b70; font-size: 14px; line-height: 1.65; white-space: pre-line; }.homework-card-footer { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding-top: 12px; border-top: 1px solid var(--line); }.homework-card-footer small { color: var(--muted); font-size: 12px; }.homework-card-footer button { display: inline-flex; align-items: center; gap: 6px; padding: 8px 13px; border: 0; border-radius: 13px; background: #dcfce7; color: #17643b; font-family: inherit; font-weight: 700; }.homework-card-footer button.completed { background: #eff2f7; color: #596579; }
.profile-card { display: grid; justify-items: center; padding: 24px; }.profile-avatar { display: grid; width: 82px; height: 82px; place-items: center; overflow: hidden; border-radius: 25px; background: #fff0f2; color: #c8102e; font-size: 24px; font-weight: 800; }.profile-avatar img { width: 100%; height: 100%; object-fit: cover; }.profile-card h2 { margin: 12px 0 0; font-size: 20px; }.profile-card>p { margin: 0; color: var(--muted); font-size: 13px; }.profile-card dl { width: 100%; margin: 19px 0 0; }.profile-card dl div { display: flex; justify-content: space-between; gap: 12px; padding: 11px 0; border-top: 1px solid var(--line); font-size: 14px; }.profile-card dt { color: var(--muted); }.profile-card dd { margin: 0; font-weight: 700; text-align: right; }
@media (max-width: 520px) { .student-shell { padding: 14px 16px 27px; }.topbar { gap: 9px; }.school-title strong { font-size: 15px; }.header-action { width: 42px; height: 42px; flex-basis: 42px; border-radius: 15px; }.quick-grid { gap: 8px; }.quick-card { min-height: 104px; border-radius: 19px; }.quick-icon { width: 45px; height: 45px; border-radius: 15px; font-size: 18px; }.quick-card strong { font-size: 11px; }.welcome-card { padding: 19px; }.welcome-copy h1 { max-width: 220px; font-size: 18px; }.student-avatar { width: 56px; height: 56px; flex-basis: 56px; border-radius: 18px; }.period-number { width: 48px; height: 54px; flex-basis: 48px; }.class-period { gap: 10px; }.period-copy strong { font-size: 15px; }.period-copy span { font-size: 12px; }.ongoing-badge { padding: 4px 7px; font-size: 10px; }.homework-card-footer { align-items: flex-start; }.homework-card-footer small { max-width: 58%; }.month-selector { gap: 6px; }.month-selector button { padding: 8px 10px; font-size: 12px; }.attendance-summary { gap: 12px; }.attendance-ring { width: 88px; height: 88px; flex-basis: 88px; }.attendance-ring strong { width: 70px; height: 70px; font-size: 21px; }.attendance-summary-copy h2 { font-size: 17px; }.attendance-summary-copy p { font-size: 12px; }.bottom-nav button { font-size: 10px; }.bottom-nav button i { font-size: 19px; } }
@media (prefers-reduced-motion: reduce) { .screen { animation: none; } }
</style>
