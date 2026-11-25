<script setup>
import { Head, Link } from '@inertiajs/vue3';
import InstituteLayout from '@/Layouts/InstituteLayout.vue';

const props = defineProps({
    teacher: {
        type: Object,
        required: true
    },
    assignments: {
        type: Array,
        default: () => []
    }
});
</script>

<template>
    <Head :title="`${props.teacher.name} subject assignments`" />

    <InstituteLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-800">{{ props.teacher.name }}</h1>
                    <p class="mt-1 text-sm text-gray-500">Subject assignments</p>
                </div>
                <Link :href="route('teacher-subjects.index')" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                    Back to assignments
                </Link>
            </div>
        </template>

        <div class="p-4 sm:p-6">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div v-if="props.assignments.length" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Subject</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Class / Section</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Academic year</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="assignment in props.assignments" :key="assignment.id">
                                <td class="px-5 py-4">
                                    <p class="font-medium text-gray-900">{{ assignment.subject_name }}</p>
                                    <p class="text-sm text-gray-500">{{ assignment.subject_code }}</p>
                                </td>
                                <td class="px-5 py-4 text-sm text-gray-700">
                                    {{ assignment.class_name }} / {{ assignment.section_name }}
                                </td>
                                <td class="px-5 py-4 text-sm text-gray-700">{{ assignment.academic_year_session }}</td>
                                <td class="px-5 py-4">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="assignment.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'">
                                        {{ assignment.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                    <span v-if="assignment.is_primary" class="ml-2 text-xs font-medium text-amber-700">Primary</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="p-8 text-center text-sm text-gray-500">This teacher has no subject assignments.</p>
            </div>
        </div>
    </InstituteLayout>
</template>
