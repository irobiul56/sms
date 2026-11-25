<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    name: { type: String, default: null },
});
const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const isTeacher = computed(() => String(user.value.usertype || '').toLowerCase() === 'teacher');
const permissions = computed(() => page.props.teacherPermissions ?? {});
const userName = computed(() => props.name || user.value.name || 'শিক্ষক');
const initials = computed(() => userName.value
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map(word => [...word][0])
    .join('') || 'শি');
const currentRoute = computed(() => route().current() || '');

const navigationItems = computed(() => [
    { label: 'হোম', route: 'teacher.dashboard', icon: 'home' },
    ...(
        permissions.value['attendance.view'] || permissions.value['attendance.manage']
            ? [{ label: 'উপস্থিতি', route: 'attendance.index', icon: 'attendance' }]
            : []
    ),
    ...(permissions.value['marks.view']
        ? [{ label: 'নম্বর', route: 'marks.index', icon: 'marks' }]
        : []),
    ...(permissions.value['marks.enter']
        ? [{ label: 'নম্বর দিন', route: 'marks.entry', icon: 'edit' }]
        : []),
    ...(permissions.value['homework.manage']
        ? [{ label: 'বাড়ির কাজ', route: 'homework.index', icon: 'homework' }]
        : []),
]);

const isActive = (routeName) => {
    const routePrefix = routeName.split('.').slice(0, -1).join('.');
    return currentRoute.value === routeName
        || currentRoute.value.startsWith(`${routePrefix}.`);
};
</script>

<template>
    <div v-if="isTeacher" class="teacher-mobile-navigation">
        <header class="teacher-mobile-header">
            <Link :href="route('teacher.dashboard')" class="teacher-mobile-brand">
                <span class="teacher-brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M12 2.8 21 8v8l-9 5.2L3 16V8l9-5.2Z" stroke="currentColor" stroke-width="1.7" />
                        <path d="m12 6 5.2 6-5.2 6-5.2-6L12 6Z" stroke="currentColor" stroke-width="1.7" />
                    </svg>
                </span>
                <span class="teacher-brand-copy">
                    <strong>শিক্ষক ড্যাশবোর্ড</strong>
                    <small>{{ userName }}</small>
                </span>
            </Link>

            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="teacher-logout"
                aria-label="লগ আউট"
                title="লগ আউট"
            >
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M10 17l5-5-5-5M15 12H3m9-8h6a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </Link>
        </header>

        <nav class="teacher-bottom-navigation" aria-label="শিক্ষক নেভিগেশন">
            <Link
                v-for="item in navigationItems"
                :key="item.route"
                :href="route(item.route)"
                class="teacher-bottom-item"
                :class="{ 'teacher-bottom-item-active': isActive(item.route) }"
                :aria-current="isActive(item.route) ? 'page' : undefined"
            >
                <svg v-if="item.icon === 'home'" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M3 10.5 12 3l9 7.5V21h-6v-7H9v7H3V10.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <svg v-else-if="item.icon === 'attendance'" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M8 3v3m8-3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v12H4V7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <svg v-else-if="item.icon === 'edit'" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="m14 5 5 5M4 20l4.5-1 10-10a2.1 2.1 0 0 0-3-3l-10 10L4 20Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <svg v-else viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M7 3h10l3 3v15H4V3h3Zm3 7h4m-4 4h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <span>{{ item.label }}</span>
            </Link>
        </nav>
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap');
</style>

<style scoped>
.teacher-mobile-navigation {
    display: none;
    color: #f7f8fc;
    font-family: 'Hind Siliguri', sans-serif;
}

.teacher-mobile-header {
    position: sticky;
    z-index: 30;
    top: 0;
    display: flex;
    min-height: 68px;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 16px;
    background: #0e111c;
}

.teacher-mobile-brand {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 12px;
    color: inherit;
    text-decoration: none;
}

.teacher-brand-mark,
.teacher-logout {
    display: grid;
    flex: 0 0 44px;
    width: 44px;
    height: 44px;
    place-items: center;
    border: 0;
    border-radius: 15px;
    background: #171d2d;
    color: #16c768;
}

.teacher-brand-mark svg {
    width: 28px;
    height: 28px;
}

.teacher-brand-copy {
    display: grid;
    min-width: 0;
    line-height: 1.2;
}

.teacher-brand-copy strong {
    overflow: hidden;
    font-size: 15px;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.teacher-brand-copy small {
    overflow: hidden;
    margin-top: 3px;
    color: #a0a8bc;
    font-size: 11px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.teacher-logout svg {
    width: 21px;
    height: 21px;
}

.teacher-bottom-navigation {
    position: fixed;
    z-index: 50;
    right: 0;
    bottom: 0;
    left: 0;
    display: grid;
    grid-auto-columns: minmax(0, 1fr);
    grid-auto-flow: column;
    padding: 8px 8px calc(8px + env(safe-area-inset-bottom));
    border-top: 1px solid rgb(255 255 255 / 8%);
    background: rgb(25 27 43 / 94%);
    backdrop-filter: blur(16px);
}

.teacher-bottom-item {
    display: flex;
    min-width: 0;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    color: #8d95aa;
    font-size: 10px;
    text-decoration: none;
}

.teacher-bottom-item svg {
    width: 21px;
    height: 21px;
}

.teacher-bottom-item-active {
    color: #13c866;
}

.teacher-mobile-navigation a:focus-visible {
    outline: 2px solid #21cb70;
    outline-offset: 3px;
}

@media (max-width: 767px) {
    .teacher-mobile-navigation {
        display: block;
    }
}

@media print {
    .teacher-mobile-navigation {
        display: none !important;
    }
}
</style>
