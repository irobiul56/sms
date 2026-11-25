<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const emit = defineEmits(['toggle-sidebar']);
defineProps({
    showSidebarToggle: { type: Boolean, default: true },
});
const page = usePage();
const showMobileMenu = ref(false);
const openMenu = ref('');
const searchQuery = ref('');
const notifications = ref([]);
const notificationsLoading = ref(false);
const notificationsError = ref('');
const isFullscreen = ref(false);

const user = computed(() => page.props.auth?.user ?? {});
const userName = computed(() => user.value.name || 'School Admin');
const userEmail = computed(() => user.value.email || '');
const userInitials = computed(() => userName.value
    .split(/\s+/)
    .filter(Boolean)
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase());
const currentRoute = computed(() => route().current() || '');

const navigationItems = [
    { label: 'School Dashboard', route: 'institute.dashboard', icon: 'fa-solid fa-school' },
    { label: 'Question Making Dashboard', route: 'userdashboard', icon: 'fa-solid fa-file-circle-question' },
    { label: 'Students', route: 'students.index', icon: 'fa-solid fa-user-graduate' },
    { label: 'Teachers', route: 'teachers.index', icon: 'fa-solid fa-chalkboard-user' },
    { label: 'Exams', route: 'exams.index', icon: 'fa-solid fa-pen-to-square' },
    { label: 'Marks Entry', route: 'marks.entry', icon: 'fa-solid fa-pen-fancy' },
    { label: 'Results', route: 'results.index', icon: 'fa-solid fa-file-lines' },
    { label: 'Settings', route: 'profile.edit', icon: 'fa-solid fa-gear' },
];

const searchResults = computed(() => {
    const query = searchQuery.value.trim().toLocaleLowerCase();
    return query
        ? navigationItems.filter((item) => item.label.toLocaleLowerCase().includes(query)).slice(0, 5)
        : [];
});
const isActive = (routeName) => currentRoute.value === routeName
    || currentRoute.value.startsWith(`${routeName}.`);
const notificationDestination = (notification) => {
    if (notification.title?.includes('Result')) return 'results.publish';
    if (notification.title?.includes('Exam')) return 'exams.index';
    if (notification.title?.includes('Attendance')) return 'attendance.index';
    return 'marks.entry';
};
const toggleMenu = (name) => {
    openMenu.value = openMenu.value === name ? '' : name;
};
const navigateToFirstSearchResult = () => {
    if (searchResults.value.length) {
        window.location.assign(route(searchResults.value[0].route));
    }
};
const closeMenus = () => {
    openMenu.value = '';
    showMobileMenu.value = false;
};

const loadNotifications = async () => {
    notificationsLoading.value = true;
    notificationsError.value = '';

    try {
        const response = await fetch(route('institute.dashboard.notifications'), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) throw new Error('Notifications could not be loaded.');

        const payload = await response.json();
        if (!payload.success || !Array.isArray(payload.data)) {
            throw new Error('The notification response was invalid.');
        }
        notifications.value = payload.data;
    } catch (error) {
        notificationsError.value = error instanceof Error
            ? error.message
            : 'Notifications could not be loaded.';
    } finally {
        notificationsLoading.value = false;
    }
};

const toggleNotifications = async () => {
    const opening = openMenu.value !== 'notifications';
    toggleMenu('notifications');
    if (opening && notifications.value.length === 0) await loadNotifications();
};

const toggleFullscreen = async () => {
    try {
        if (document.fullscreenElement) {
            await document.exitFullscreen();
        } else {
            await document.documentElement.requestFullscreen();
        }
    } catch (error) {
        console.error('Unable to toggle fullscreen mode.', error);
    }
};
const syncFullscreenState = () => {
    isFullscreen.value = Boolean(document.fullscreenElement);
};
const closeOnEscape = (event) => {
    if (event.key === 'Escape') closeMenus();
};

onMounted(() => {
    document.addEventListener('fullscreenchange', syncFullscreenState);
    document.addEventListener('keydown', closeOnEscape);
});
onUnmounted(() => {
    document.removeEventListener('fullscreenchange', syncFullscreenState);
    document.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>
    <header class="sticky top-0 z-50 border-b border-gray-200 bg-white shadow-sm">
        <div class="flex min-h-16 items-center justify-between gap-3 px-3 sm:px-5">
            <div class="flex min-w-0 items-center gap-2">
                <button v-if="showSidebarToggle" type="button" @click="emit('toggle-sidebar')" aria-label="Toggle sidebar"
                    class="rounded-lg p-2 text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <Link :href="route('institute.dashboard')" class="hidden items-center gap-2 sm:flex">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 text-white">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </span>
                    <span class="hidden whitespace-nowrap font-bold text-gray-800 md:inline">School RMS</span>
                </Link>

                <nav class="hidden items-center gap-1 xl:flex" aria-label="Dashboard navigation">
                    <Link v-for="item in navigationItems.slice(0, 2)" :key="item.route"
                        :href="route(item.route)"
                        :aria-current="isActive(item.route) ? 'page' : undefined"
                        class="inline-flex items-center gap-2 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                        :class="isActive(item.route) ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'">
                        <i :class="item.icon"></i>
                        {{ item.label }}
                    </Link>
                </nav>
            </div>

            <div class="flex items-center gap-1 sm:gap-2">
                <div class="relative hidden 2xl:block">
                    <input v-model="searchQuery" type="search" placeholder="Search pages..."
                        aria-label="Search navigation pages" @keydown.enter.prevent="navigateToFirstSearchResult"
                        class="w-44 rounded-lg border-gray-300 py-2 pl-9 pr-3 text-sm focus:border-blue-500 focus:ring-blue-500 lg:w-56">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-xs text-gray-400"></i>
                    <div v-if="searchResults.length"
                        class="absolute right-0 top-full z-50 mt-2 w-72 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg">
                        <Link v-for="item in searchResults" :key="item.route" :href="route(item.route)"
                            @click="searchQuery = ''"
                            class="flex items-center gap-3 px-4 py-3 text-sm text-gray-700 hover:bg-gray-50">
                            <i :class="item.icon" class="w-4 text-gray-400"></i>{{ item.label }}
                        </Link>
                    </div>
                </div>

                <div class="relative">
                    <button type="button" @click="toggleNotifications" aria-label="Open notifications"
                        class="relative rounded-lg p-2 text-gray-600 hover:bg-gray-100">
                        <i class="fa-regular fa-bell text-lg"></i>
                        <span v-if="notifications.length"
                            class="absolute right-1 top-1 h-2 w-2 rounded-full bg-red-500"></span>
                    </button>
                    <section v-if="openMenu === 'notifications'"
                        class="absolute right-0 z-50 mt-2 w-80 max-w-[calc(100vw-1.5rem)] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl"
                        aria-label="Notifications">
                        <div class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800">Notifications</div>
                        <div v-if="notificationsLoading" class="p-4 text-sm text-gray-500">Loading notifications…</div>
                        <p v-else-if="notificationsError" class="p-4 text-sm text-red-600">{{ notificationsError }}</p>
                        <p v-else-if="!notifications.length" class="p-4 text-sm text-gray-500">No new notifications.</p>
                        <Link v-for="notification in notifications" :key="notification.id"
                            :href="route(notificationDestination(notification))"
                            @click="closeMenus"
                            class="block border-b border-gray-100 px-4 py-3 hover:bg-gray-50">
                            <p class="text-sm font-medium text-gray-800">{{ notification.title }}</p>
                            <p class="mt-1 text-xs text-gray-600">{{ notification.message }}</p>
                            <p class="mt-1 text-xs text-gray-400">{{ notification.time }}</p>
                        </Link>
                    </section>
                </div>

                <div class="relative hidden sm:block">
                    <button type="button" @click="toggleMenu('quick-actions')" aria-label="Open quick actions"
                        class="rounded-lg p-2 text-gray-600 hover:bg-gray-100">
                        <i class="fa-solid fa-plus text-lg"></i>
                    </button>
                    <section v-if="openMenu === 'quick-actions'"
                        class="absolute right-0 z-50 mt-2 w-56 rounded-xl border border-gray-200 bg-white p-2 shadow-xl"
                        aria-label="Quick actions">
                        <Link v-for="item in [
                            { label: 'Add Student', route: 'students.create' },
                            { label: 'Enter Marks', route: 'marks.entry' },
                            { label: 'View Exams', route: 'exams.index' },
                            { label: 'View Results', route: 'results.index' },
                        ]" :key="item.route" :href="route(item.route)" @click="closeMenus"
                            class="block rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            {{ item.label }}
                        </Link>
                    </section>
                </div>

                <div class="relative">
                    <button type="button" @click="toggleMenu('profile')" aria-label="Open profile menu"
                        class="flex items-center gap-2 rounded-lg p-1.5 hover:bg-gray-100">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">
                            {{ userInitials }}
                        </span>
                        <span class="hidden max-w-36 truncate text-left text-sm font-medium text-gray-700 lg:block">{{ userName }}</span>
                        <i class="fa-solid fa-chevron-down hidden text-xs text-gray-400 lg:block"></i>
                    </button>
                    <section v-if="openMenu === 'profile'"
                        class="absolute right-0 z-50 mt-2 w-60 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl">
                        <div class="border-b border-gray-100 px-4 py-3">
                            <p class="truncate text-sm font-semibold text-gray-800">{{ userName }}</p>
                            <p class="truncate text-xs text-gray-500">{{ userEmail }}</p>
                        </div>
                        <Link :href="route('profile.edit')" @click="closeMenus"
                            class="block px-4 py-3 text-sm text-gray-700 hover:bg-gray-50">Profile settings</Link>
                        <Link :href="route('contactus')" @click="closeMenus"
                            class="block px-4 py-3 text-sm text-gray-700 hover:bg-gray-50">Help & support</Link>
                        <Link :href="route('logout')" method="post" as="button" @click="closeMenus"
                            class="w-full border-t border-gray-100 px-4 py-3 text-left text-sm text-red-600 hover:bg-red-50">Log out</Link>
                    </section>
                </div>

                <button type="button" @click="toggleFullscreen" :aria-label="isFullscreen ? 'Exit fullscreen' : 'Enter fullscreen'"
                    class="hidden rounded-lg p-2 text-gray-600 hover:bg-gray-100 lg:block">
                    <i :class="isFullscreen ? 'fa-solid fa-compress' : 'fa-solid fa-expand'"></i>
                </button>
                <button type="button" @click="showMobileMenu = !showMobileMenu" aria-label="Toggle navigation menu"
                    class="rounded-lg p-2 text-gray-600 hover:bg-gray-100 xl:hidden">
                    <i :class="showMobileMenu ? 'fa-solid fa-xmark' : 'fa-solid fa-ellipsis-vertical'"></i>
                </button>
            </div>
        </div>

        <nav v-if="showMobileMenu" class="border-t border-gray-100 bg-white p-2 xl:hidden" aria-label="Mobile navigation">
            <div class="grid gap-1 sm:grid-cols-2">
                <Link v-for="item in navigationItems" :key="item.route" :href="route(item.route)"
                    @click="closeMenus"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium"
                    :class="isActive(item.route) ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50'">
                    <i :class="item.icon" class="w-5 text-center"></i>{{ item.label }}
                </Link>
                <Link :href="route('contactus')" @click="closeMenus"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <i class="fa-solid fa-circle-question w-5 text-center"></i>Help & support
                </Link>
            </div>
        </nav>
    </header>
</template>
