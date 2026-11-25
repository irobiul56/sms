<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import InstituteNavbar from '@/Components/InstituteNavbar.vue';
import InstituteSidebar from '../Components/InstituteSidebar.vue';
import TeacherMobileNavigation from '@/Components/TeacherMobileNavigation.vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const props = defineProps({
    showSidebar: { type: Boolean, default: true },
});
const isTeacher = computed(() => String(page.props.auth?.user?.usertype || '').toLowerCase() === 'teacher');
const isCollapsed = ref(false);
const isMobile = ref(false);
const isMobileSidebarOpen = ref(false);
const sidebarStorageKey = computed(() => `institute-sidebar-collapsed:${page.props.auth?.user?.id ?? 'default'}`);
let mobileQuery;
let previousBodyOverflow = null;

const updateViewport = (event) => {
    isMobile.value = event.matches;
    closeMobileSidebar();
};

const toggleSidebar = () => {
    if (isMobile.value) {
      setMobileSidebarOpen(!isMobileSidebarOpen.value);
      return;
    }
    isCollapsed.value = !isCollapsed.value;
    try {
        window.localStorage.setItem(sidebarStorageKey.value, String(isCollapsed.value));
    } catch (error) {
        console.error('Unable to save the institute sidebar preference.', error);
    }
};

const setMobileSidebarOpen = (open) => {
    if (open && isMobile.value) {
        if (previousBodyOverflow === null) {
            previousBodyOverflow = document.body.style.overflow;
        }
        document.body.style.overflow = 'hidden';
    } else if (previousBodyOverflow !== null) {
        document.body.style.overflow = previousBodyOverflow;
        previousBodyOverflow = null;
    }
    isMobileSidebarOpen.value = open;
};

const closeMobileSidebar = () => {
    setMobileSidebarOpen(false);
};

const closeOnEscape = (event) => {
    if (event.key === 'Escape') closeMobileSidebar();
};

onMounted(() => {
    try {
        isCollapsed.value = window.localStorage.getItem(sidebarStorageKey.value) === 'true';
    } catch (error) {
        console.error('Unable to load the institute sidebar preference.', error);
    }
    mobileQuery = window.matchMedia('(max-width: 767px)');
    isMobile.value = mobileQuery.matches;
    mobileQuery.addEventListener('change', updateViewport);
    document.addEventListener('keydown', closeOnEscape);
});

onUnmounted(() => {
    mobileQuery?.removeEventListener('change', updateViewport);
    document.removeEventListener('keydown', closeOnEscape);
    if (previousBodyOverflow !== null) {
        document.body.style.overflow = previousBodyOverflow;
    }
});
</script>

<template>
    <div class="institute-layout" :class="{ 'teacher-layout': isTeacher }">
        <TeacherMobileNavigation v-if="isTeacher" />
        <InstituteNavbar
            :class="isTeacher ? 'hidden md:block' : ''"
            :show-sidebar-toggle="props.showSidebar"
            @toggle-sidebar="toggleSidebar"
        />
        <div class="flex min-h-[calc(100vh-4rem)] min-w-0">
            <button
                v-if="props.showSidebar && isMobileSidebarOpen"
                type="button"
                aria-label="Close sidebar"
                class="fixed inset-0 z-40 bg-gray-900/50"
                @click="closeMobileSidebar"
            />
            <InstituteSidebar
                v-if="props.showSidebar"
                :class="isTeacher ? 'hidden md:flex' : ''"
                :is-collapsed="isCollapsed"
                :is-mobile="isMobile"
                :is-mobile-open="isMobileSidebarOpen"
                @close-mobile="closeMobileSidebar"
                @toggle-collapse="toggleSidebar"
            />
            <div class="min-w-0 flex-1">
                <div :class="{ 'teacher-mobile-content': isTeacher }">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.teacher-layout {
    min-height: 100vh;
}

@media (max-width: 767px) {
    .teacher-layout {
        background: #0e111c;
    }

    .teacher-mobile-content {
        min-height: calc(100vh - 68px);
        padding-bottom: 76px;
    }
}
</style>
