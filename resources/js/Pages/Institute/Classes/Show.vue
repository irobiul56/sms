<script setup>
import { Head, Link } from '@inertiajs/vue3';
import InstituteLayout from '@/Layouts/InstituteLayout.vue';

const props = defineProps({
    class: {
        type: Object,
        required: true
    }
});
</script>

<template>
    <Head :title="`${props.class.name} details`" />

    <InstituteLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-800">{{ props.class.name }}</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ props.class.bn_name || props.class.group_label || 'Class details' }}</p>
                </div>
                <Link :href="route('classes.index')" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                    Back to classes
                </Link>
            </div>
        </template>

        <div class="space-y-6 p-4 sm:p-6">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Students</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ props.class.student_count }}</p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Sections</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ props.class.section_count }}</p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Group</p>
                    <p class="mt-2 text-xl font-semibold text-gray-900">{{ props.class.group_label || 'General' }}</p>
                </div>
            </div>

            <section class="rounded-lg bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-800">Sections</h2>
                <div v-if="props.class.sections?.length" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="section in props.class.sections" :key="section.id" class="rounded-md border border-gray-200 p-4">
                        <p class="font-medium text-gray-900">{{ section.name }}</p>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ section.room_number || 'No room assigned' }}
                            <span v-if="section.capacity"> · Capacity {{ section.capacity }}</span>
                        </p>
                    </div>
                </div>
                <p v-else class="mt-3 text-sm text-gray-500">No sections have been added.</p>
            </section>

            <section class="rounded-lg bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-800">Students</h2>
                <div v-if="props.class.students?.length" class="mt-4 divide-y divide-gray-100">
                    <div v-for="student in props.class.students" :key="student.id" class="flex items-center justify-between py-3">
                        <span class="font-medium text-gray-800">{{ student.name }}</span>
                        <span class="text-sm text-gray-500">{{ student.roll_no || student.student_id || 'No ID' }}</span>
                    </div>
                </div>
                <p v-else class="mt-3 text-sm text-gray-500">No students have been enrolled.</p>
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-lg bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-800">Subjects</h2>
                    <ul v-if="props.class.subjects?.length" class="mt-3 space-y-2">
                        <li v-for="subject in props.class.subjects" :key="subject.id" class="text-sm text-gray-700">
                            {{ subject.name }}<span v-if="subject.code" class="ml-2 text-gray-400">{{ subject.code }}</span>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-gray-500">No subjects are assigned.</p>
                </section>
                <section class="rounded-lg bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-800">Teachers</h2>
                    <ul v-if="props.class.teachers?.length" class="mt-3 space-y-2">
                        <li v-for="teacher in props.class.teachers" :key="teacher.id" class="text-sm text-gray-700">
                            {{ teacher.name }}
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-gray-500">No teachers are assigned.</p>
                </section>
            </div>
        </div>
    </InstituteLayout>
</template>
