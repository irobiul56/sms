<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import TeacherMobileNavigation from '@/Components/TeacherMobileNavigation.vue';

const props = defineProps({
    teacherName: { type: String, required: true },
    classes: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] }
});

const initials = computed(() => {
    const words = props.teacherName.trim().split(/\s+/).filter(Boolean);
    return words.slice(0, 2).map(word => [...word][0]).join('') || 'শি';
});

const profileTags = computed(() => {
    const assignment = props.classes[0] || props.subjects[0];
    if (!assignment) return [];

    return [
        assignment.class,
        assignment.section && `শাখা ${assignment.section}`,
        assignment.academic_year
    ].filter(Boolean);
});

const canViewMarks = computed(() => props.subjects.some(assignment =>
    assignment.permissions?.includes('marks.view')
));
const canEnterMarks = computed(() => props.subjects.some(assignment =>
    assignment.permissions?.includes('marks.enter')
));
const canViewAttendance = computed(() => props.classes.some(assignment =>
    assignment.permissions?.includes('attendance.view')
        || assignment.permissions?.includes('attendance.manage')
));

const mobileActions = computed(() => [
    ...(canViewAttendance.value ? [{
        label: 'উপস্থিতি',
        icon: 'attendance',
        color: 'rose',
        routeName: 'attendance.index'
    }] : []),
    ...(canViewMarks.value ? [{
        label: 'নম্বর',
        icon: 'marks',
        color: 'blue',
        routeName: 'marks.index'
    }] : []),
    ...(canEnterMarks.value ? [{
        label: 'নম্বর দিন',
        icon: 'edit',
        color: 'green',
        routeName: 'marks.entry'
    }] : []),
    {
        label: 'আমার ক্লাস',
        icon: 'classes',
        color: 'amber',
        anchor: '#assigned-classes'
    },
    {
        label: 'বিষয়সমূহ',
        icon: 'subjects',
        color: 'violet',
        anchor: '#assigned-subjects'
    },
    {
        label: 'সব দায়িত্ব',
        icon: 'tasks',
        color: 'cyan',
        anchor: '#assignments'
    }
]);

const mobileRouteActions = computed(() => mobileActions.value.filter(action => action.routeName));
const mobileAnchorActions = computed(() => mobileActions.value.filter(action => action.anchor));
</script>

<template>
    <Head title="Teacher dashboard" />

    <div class="teacher-dashboard">
        <main class="mobile-dashboard md:hidden">
            <TeacherMobileNavigation :name="teacherName" />

            <section class="teacher-card" aria-label="শিক্ষকের পরিচিতি">
                <div class="teacher-identity">
                    <div class="teacher-avatar">{{ initials }}</div>
                    <div class="teacher-intro">
                        <p class="greeting">শুভ সকাল <span aria-hidden="true">👋</span></p>
                        <h1>{{ teacherName }}</h1>
                        <p class="teacher-role">শিক্ষক অ্যাকাউন্ট</p>
                    </div>
                </div>
                <div v-if="profileTags.length" class="profile-tags">
                    <span v-for="tag in profileTags" :key="tag">{{ tag }}</span>
                </div>
                <div v-else class="profile-tags">
                    <span>{{ classes.length }}টি ক্লাস</span>
                    <span>{{ subjects.length }}টি বিষয়</span>
                </div>
            </section>

            <section class="quick-actions" aria-label="দ্রুত কাজ">
                <Link
                    v-for="action in mobileRouteActions"
                    :key="action.label"
                    :href="route(action.routeName)"
                    class="action-card"
                >
                    <span class="action-icon" :class="`icon-${action.color}`">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <template v-if="action.icon === 'attendance'">
                                <path d="M8 3v3m8-3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v12H4V7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="m8 14 2 2 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </template>
                            <template v-else-if="action.icon === 'marks'">
                                <path d="M7 3h10l3 3v15H4V3h3Zm3 7h4m-4 4h4m-4 4h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </template>
                            <template v-else-if="action.icon === 'edit'">
                                <path d="m14 5 5 5M4 20l4.5-1 10-10a2.1 2.1 0 0 0-3-3l-10 10L4 20Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </template>
                            <template v-else-if="action.icon === 'classes'">
                                <path d="M4 19V8l8-5 8 5v11h-6v-6h-4v6H4Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </template>
                            <template v-else-if="action.icon === 'subjects'">
                                <path d="M5 4h14v16H5zM8 8h8m-8 4h8m-8 4h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </template>
                            <template v-else>
                                <path d="M4 5h16M4 12h16M4 19h16M8 3v4m8 3v4m-8 3v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </template>
                        </svg>
                    </span>
                    <span class="action-label">{{ action.label }}</span>
                </Link>
                <a
                    v-for="action in mobileAnchorActions"
                    :key="action.label"
                    :href="action.anchor"
                    class="action-card"
                >
                    <span class="action-icon" :class="`icon-${action.color}`">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <template v-if="action.icon === 'classes'">
                                <path d="M4 19V8l8-5 8 5v11h-6v-6h-4v6H4Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </template>
                            <template v-else-if="action.icon === 'subjects'">
                                <path d="M5 4h14v16H5zM8 8h8m-8 4h8m-8 4h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </template>
                            <template v-else>
                                <path d="M4 5h16M4 12h16M4 19h16M8 3v4m8 3v4m-8 3v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </template>
                        </svg>
                    </span>
                    <span class="action-label">{{ action.label }}</span>
                </a>
            </section>

            <section id="assignments" class="assignments-section">
                <div class="section-heading">
                    <div>
                        <h2>আপনার দায়িত্ব</h2>
                        <p>ক্লাস ও বিষয় এক নজরে</p>
                    </div>
                    <span class="assignment-count">{{ classes.length + subjects.length }}</span>
                </div>

                <div class="assignment-panels">
                    <article id="assigned-classes" class="assignment-panel">
                        <div class="panel-heading">
                            <span class="panel-icon icon-amber">
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M4 19V8l8-5 8 5v11h-6v-6h-4v6H4Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                            <h3>আমার ক্লাস</h3>
                            <span class="panel-count">{{ classes.length }}</span>
                        </div>
                        <p v-if="classes.length === 0" class="empty-state">এখনও কোনো ক্লাস যুক্ত হয়নি।</p>
                        <ul v-else class="assignment-list">
                            <li v-for="(assignment, index) in classes" :key="`${assignment.class}-${assignment.section}-${index}`">
                                <span class="list-marker"></span>
                                <div>
                                    <p class="assignment-title">{{ assignment.class }}</p>
                                    <p class="assignment-detail">
                                        <span v-if="assignment.section">শাখা {{ assignment.section }}</span>
                                        <span v-if="assignment.section && assignment.academic_year"> · </span>
                                        <span v-if="assignment.academic_year">{{ assignment.academic_year }}</span>
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </article>

                    <article id="assigned-subjects" class="assignment-panel">
                        <div class="panel-heading">
                            <span class="panel-icon icon-violet">
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M5 4h14v16H5zM8 8h8m-8 4h8m-8 4h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                            <h3>বিষয়সমূহ</h3>
                            <span class="panel-count">{{ subjects.length }}</span>
                        </div>
                        <p v-if="subjects.length === 0" class="empty-state">এখনও কোনো বিষয় যুক্ত হয়নি।</p>
                        <ul v-else class="assignment-list">
                            <li v-for="(assignment, index) in subjects" :key="`${assignment.subject}-${assignment.class}-${index}`">
                                <span class="list-marker marker-violet"></span>
                                <div>
                                    <p class="assignment-title">{{ assignment.subject }} · {{ assignment.class }}</p>
                                    <p class="assignment-detail">
                                        <span v-if="assignment.section">শাখা {{ assignment.section }}</span>
                                        <span v-if="assignment.section && assignment.academic_year"> · </span>
                                        <span v-if="assignment.academic_year">{{ assignment.academic_year }}</span>
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </article>
                </div>
            </section>

        </main>

        <main class="desktop-dashboard hidden md:block min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-5xl">
                <header class="mb-8 flex items-center justify-between rounded-xl bg-white p-6 shadow-sm">
                    <div>
                        <p class="text-sm text-slate-500">Teacher dashboard</p>
                        <h1 class="mt-1 text-2xl font-semibold text-slate-900">{{ teacherName }}</h1>
                    </div>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200"
                    >
                        Log out
                    </Link>
                </header>

                <nav class="mb-6 flex flex-wrap gap-3">
                    <Link
                        v-if="canViewMarks"
                        :href="route('marks.index')"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    >
                        View marks
                    </Link>
                    <Link
                        v-if="canEnterMarks"
                        :href="route('marks.entry')"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        Enter marks
                    </Link>
                    <Link
                        v-if="canViewAttendance"
                        :href="route('attendance.index')"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                    >
                        Attendance
                    </Link>
                </nav>

                <section class="grid gap-6 lg:grid-cols-2">
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <h2 class="mb-4 text-lg font-semibold text-slate-900">Assigned classes</h2>
                        <p v-if="classes.length === 0" class="text-sm text-slate-500">No class assignments yet.</p>
                        <ul v-else class="divide-y divide-slate-100">
                            <li v-for="(assignment, index) in classes" :key="index" class="py-3">
                                <p class="font-medium text-slate-800">{{ assignment.class }}</p>
                                <p class="text-sm text-slate-500">
                                    <span v-if="assignment.section">{{ assignment.section }} · </span>
                                    {{ assignment.academic_year }}
                                </p>
                            </li>
                        </ul>
                    </div>

                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <h2 class="mb-4 text-lg font-semibold text-slate-900">Assigned subjects</h2>
                        <p v-if="subjects.length === 0" class="text-sm text-slate-500">No subject assignments yet.</p>
                        <ul v-else class="divide-y divide-slate-100">
                            <li v-for="(assignment, index) in subjects" :key="index" class="py-3">
                                <p class="font-medium text-slate-800">{{ assignment.subject }} · {{ assignment.class }}</p>
                                <p class="text-sm text-slate-500">
                                    <span v-if="assignment.section">{{ assignment.section }} · </span>
                                    {{ assignment.academic_year }}
                                </p>
                            </li>
                        </ul>
                    </div>
                </section>
            </div>
        </main>
    </div>
</template>

<style scoped>
.mobile-dashboard {
    min-height: 100vh;
    padding: 16px 16px calc(92px + env(safe-area-inset-bottom));
    background: #0e111c;
    color: #f7f8fc;
    font-family: 'Hind Siliguri', sans-serif;
}

.teacher-identity,
.panel-heading {
    display: flex;
    align-items: center;
}

.section-heading {
    justify-content: space-between;
}

.teacher-card {
    position: relative;
    overflow: hidden;
    min-height: 152px;
    padding: 20px;
    border-radius: 28px;
    background:
        radial-gradient(circle at 88% 110%, rgb(91 225 128 / 28%) 0 31%, transparent 31.5%),
        radial-gradient(circle at 78% 12%, rgb(6 76 31 / 42%) 0 39%, transparent 39.5%),
        linear-gradient(115deg, #006b29, #159642);
}

.teacher-identity {
    gap: 14px;
}

.teacher-avatar {
    display: grid;
    width: 70px;
    height: 70px;
    flex: 0 0 70px;
    place-items: center;
    border: 1px solid rgb(255 255 255 / 27%);
    border-radius: 22px;
    background: rgb(255 255 255 / 15%);
    font-size: 21px;
    font-weight: 700;
}

.teacher-intro {
    min-width: 0;
}

.greeting,
.teacher-role {
    color: rgb(255 255 255 / 86%);
    font-size: 12px;
}

.teacher-intro h1 {
    overflow: hidden;
    margin: 1px 0;
    font-size: 19px;
    font-weight: 700;
    line-height: 1.35;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.profile-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 15px;
}

.profile-tags span {
    padding: 4px 11px;
    border-radius: 999px;
    background: rgb(0 76 30 / 48%);
    color: #fff;
    font-size: 11px;
    font-weight: 600;
}

.quick-actions {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    margin-top: 16px;
}

.action-card {
    display: flex;
    min-width: 0;
    min-height: 94px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 9px 3px 7px;
    border-radius: 22px;
    background: #171d2c;
    color: #f3f5fb;
    text-align: center;
    text-decoration: none;
    transition: transform 150ms ease, background 150ms ease;
}

.action-card:active {
    transform: scale(.96);
    background: #20283a;
}

.action-icon,
.panel-icon {
    display: grid;
    width: 48px;
    height: 48px;
    flex: 0 0 48px;
    place-items: center;
    border-radius: 16px;
}

.action-icon svg,
.panel-icon svg {
    width: 24px;
    height: 24px;
}

.action-label {
    font-size: 12px;
    font-weight: 600;
    line-height: 1.25;
}

.icon-rose {
    background: #fff0f2;
    color: #f43f5e;
}

.icon-blue {
    background: #eaf5ff;
    color: #1688ef;
}

.icon-green {
    background: #e8fff3;
    color: #08a65a;
}

.icon-amber {
    background: #fff7e7;
    color: #f59e0b;
}

.icon-violet {
    background: #f8edff;
    color: #c026d3;
}

.icon-cyan {
    background: #e8faff;
    color: #0891b2;
}

.assignments-section {
    scroll-margin-top: 14px;
    margin-top: 24px;
}

.section-heading {
    margin-bottom: 12px;
}

.section-heading h2 {
    font-size: 17px;
    font-weight: 700;
}

.section-heading p {
    margin-top: 1px;
    color: #9da5b8;
    font-size: 11px;
}

.assignment-count,
.panel-count {
    display: grid;
    min-width: 26px;
    height: 26px;
    place-items: center;
    padding: 0 7px;
    border-radius: 999px;
    background: #183825;
    color: #56e88f;
    font-size: 11px;
    font-weight: 700;
}

.assignment-panels {
    display: grid;
    gap: 10px;
}

.assignment-panel {
    scroll-margin-top: 14px;
    padding: 14px;
    border: 1px solid rgb(255 255 255 / 5%);
    border-radius: 20px;
    background: #171d2c;
}

.panel-heading {
    gap: 9px;
}

.panel-icon {
    width: 36px;
    height: 36px;
    flex-basis: 36px;
    border-radius: 12px;
}

.panel-icon svg {
    width: 19px;
    height: 19px;
}

.panel-heading h3 {
    flex: 1;
    font-size: 13px;
    font-weight: 600;
}

.panel-count {
    min-width: 23px;
    height: 23px;
    background: #242c3d;
    color: #c1c8d8;
}

.assignment-list {
    margin-top: 9px;
}

.assignment-list li {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    padding: 9px 2px;
    border-top: 1px solid rgb(255 255 255 / 6%);
}

.list-marker {
    width: 7px;
    height: 7px;
    flex: 0 0 7px;
    margin-top: 7px;
    border-radius: 50%;
    background: #f5ad35;
}

.marker-violet {
    background: #c26cf3;
}

.assignment-title {
    color: #edf0f7;
    font-size: 12px;
    font-weight: 600;
}

.assignment-detail,
.empty-state {
    margin-top: 2px;
    color: #9da5b8;
    font-size: 11px;
}

.empty-state {
    padding-top: 10px;
}

@media (max-width: 359px) {
    .mobile-dashboard {
        padding-right: 11px;
        padding-left: 11px;
    }

    .quick-actions {
        gap: 7px;
    }

    .action-card {
        border-radius: 18px;
    }

    .action-label {
        font-size: 11px;
    }
}
</style>
