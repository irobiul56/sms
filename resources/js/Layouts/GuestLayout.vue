<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    wide: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0"
    >
        <div>
            <Link href="https://learnandteach.app">
                <ApplicationLogo class="h-20 w-20 fill-current text-gray-500" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-6 py-6 shadow-md sm:rounded-lg"
            :class="wide ? 'sm:max-w-4xl sm:px-8 sm:py-8' : 'sm:max-w-md sm:py-4'"
        >
            <slot />
        </div>
    </div>
</template>
