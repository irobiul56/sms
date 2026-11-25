<script setup>
import { Head, Link } from '@inertiajs/vue3';
import InstituteLayout from '@/Layouts/InstituteLayout.vue';

const props = defineProps({
    mark: {
        type: Object,
        required: true
    }
});

const details = [
    ['Exam', props.mark.exam_name],
    ['Student', props.mark.student_name],
    ['Student ID', props.mark.student_id],
    ['Roll number', props.mark.roll_no],
    ['Class', props.mark.class_name],
    ['Section', props.mark.section_name],
    ['Subject', props.mark.subject_name],
    ['Teacher', props.mark.teacher_name],
    ['Theory marks', props.mark.theory_marks],
    ['Practical marks', props.mark.practical_marks],
    ['MCQ marks', props.mark.mcq_marks],
    ['Total marks', `${props.mark.total_marks} / ${props.mark.full_marks}`],
    ['Grade', props.mark.grade_label || props.mark.grade],
    ['Status', props.mark.status_label],
    ['Remarks', props.mark.remarks || '—']
];
</script>

<template>
    <Head title="Mark details" />

    <InstituteLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-800">Mark details</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ props.mark.student_name }} · {{ props.mark.exam_name }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <Link :href="route('marks.edit', props.mark.id)" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        Edit marks
                    </Link>
                    <Link :href="route('marks.index')" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                        Back to marks
                    </Link>
                </div>
            </div>
        </template>

        <div class="p-4 sm:p-6">
            <div class="mx-auto max-w-4xl rounded-lg bg-white p-5 shadow-sm sm:p-8">
                <div class="mb-6 flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-5">
                    <div>
                        <p class="text-sm text-gray-500">Result</p>
                        <p class="mt-1 text-3xl font-bold text-gray-900">{{ props.mark.total_marks }} / {{ props.mark.full_marks }}</p>
                        <p class="mt-1 text-xs text-gray-500">Pass marks: {{ props.mark.pass_marks }}</p>
                    </div>
                    <span :class="props.mark.status_badge_class || 'bg-gray-100 text-gray-700'" class="rounded-full px-3 py-1 text-sm font-medium">
                        {{ props.mark.status_label || props.mark.grade_label || props.mark.grade }}
                    </span>
                </div>
                <dl class="grid gap-x-8 sm:grid-cols-2">
                    <div v-for="detail in details" :key="detail[0]" class="border-b border-gray-100 py-3">
                        <dt class="text-sm text-gray-500">{{ detail[0] }}</dt>
                        <dd class="mt-1 font-medium text-gray-900">{{ detail[1] ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </InstituteLayout>
</template>
