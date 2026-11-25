<script setup>
import { computed, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import InstituteLayout from '@/Layouts/InstituteLayout.vue';

const props = defineProps({
    teachers: { type: Array, default: () => [] },
    selectedTeacherId: { type: Number, default: null },
    classAssignments: { type: Array, default: () => [] },
    subjectAssignments: { type: Array, default: () => [] },
    classPermissionOptions: { type: Object, default: () => ({}) },
    subjectPermissionOptions: { type: Object, default: () => ({}) },
});

const selectedTeacher = computed(() => props.teachers.find(
    teacher => teacher.id === props.selectedTeacherId
));

const form = useForm({
    class_assignments: props.classAssignments.map(assignment => ({
        id: assignment.id,
        permissions: [...assignment.permissions],
    })),
    subject_assignments: props.subjectAssignments.map(assignment => ({
        id: assignment.id,
        permissions: [...assignment.permissions],
    })),
});

watch(
    () => [props.selectedTeacherId, props.classAssignments, props.subjectAssignments],
    () => {
        form.defaults({
            class_assignments: props.classAssignments.map(assignment => ({
                id: assignment.id,
                permissions: [...assignment.permissions],
            })),
            subject_assignments: props.subjectAssignments.map(assignment => ({
                id: assignment.id,
                permissions: [...assignment.permissions],
            })),
        });
        form.reset();
        form.clearErrors();
    },
    { deep: true },
);

const selectTeacher = (event) => {
    router.get(route('teacher-permissions.index'), {
        teacher_id: event.target.value,
    }, {
        preserveScroll: true,
        preserveState: false,
        replace: true,
    });
};

const savePermissions = () => {
    if (!selectedTeacher.value) return;

    form.put(route('teacher-permissions.update', selectedTeacher.value.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Roles & Permissions" />

    <InstituteLayout>
        <template #header>
            <div>
                <h1 class="text-2xl font-semibold text-gray-800">Roles &amp; Permissions</h1>
                <p class="mt-1 text-sm text-gray-500">
                    Choose what each teacher can do for their assigned classes and subjects.
                </p>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6">
            <section class="rounded-xl bg-white p-5 shadow-sm">
                <label for="permission-teacher" class="mb-2 block text-sm font-medium text-gray-700">
                    Teacher
                </label>
                <select
                    id="permission-teacher"
                    :value="selectedTeacherId || ''"
                    :disabled="teachers.length === 0"
                    class="w-full max-w-xl rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                    @change="selectTeacher"
                >
                    <option value="" disabled>Select a teacher</option>
                    <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">
                        {{ teacher.name }} · {{ teacher.employee_id }}
                    </option>
                </select>
                <p v-if="selectedTeacher" class="mt-2 text-sm text-gray-500">
                    {{ selectedTeacher.designation }} ·
                    <span :class="selectedTeacher.status === 'active' ? 'text-green-700' : 'text-amber-700'">
                        {{ selectedTeacher.status }}
                    </span>
                    <span v-if="!selectedTeacher.has_login" class="text-amber-700">
                        · Login account not set up
                    </span>
                </p>
                <p v-if="teachers.length === 0" class="text-sm text-gray-500">
                    No teachers are registered in this institute yet.
                </p>
            </section>

            <template v-if="selectedTeacher">
                <section class="rounded-xl bg-white p-5 shadow-sm">
                    <div class="mb-4">
                        <h2 class="text-lg font-semibold text-gray-800">Class role permissions</h2>
                        <p class="text-sm text-gray-500">
                            These apply to the assigned class/section and academic year.
                        </p>
                    </div>

                    <div v-if="classAssignments.length" class="space-y-4">
                        <article
                            v-for="(assignment, index) in classAssignments"
                            :key="assignment.id"
                            class="rounded-lg border border-gray-200 p-4"
                        >
                            <div class="mb-3">
                                <h3 class="font-medium text-gray-800">
                                    {{ assignment.class_name }} · {{ assignment.section_name }}
                                </h3>
                                <p class="text-sm text-gray-500">{{ assignment.academic_year }}</p>
                            </div>
                            <div class="flex flex-wrap gap-x-6 gap-y-3">
                                <label
                                    v-for="(label, permission) in classPermissionOptions"
                                    :key="permission"
                                    class="inline-flex items-center gap-2 text-sm text-gray-700"
                                >
                                    <input
                                        v-model="form.class_assignments[index].permissions"
                                        type="checkbox"
                                        :value="permission"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    {{ label }}
                                </label>
                            </div>
                        </article>
                    </div>
                    <p v-else class="rounded-lg bg-gray-50 p-4 text-sm text-gray-500">
                        No class assignment. Assign a class to this teacher first.
                    </p>
                </section>

                <section class="rounded-xl bg-white p-5 shadow-sm">
                    <div class="mb-4">
                        <h2 class="text-lg font-semibold text-gray-800">Subject role permissions</h2>
                        <p class="text-sm text-gray-500">
                            Marks permissions only apply to the assigned subject, class/section, and academic year.
                        </p>
                    </div>

                    <div v-if="subjectAssignments.length" class="space-y-4">
                        <article
                            v-for="(assignment, index) in subjectAssignments"
                            :key="assignment.id"
                            class="rounded-lg border border-gray-200 p-4"
                            :class="assignment.is_active ? '' : 'bg-gray-50 opacity-70'"
                        >
                            <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <h3 class="font-medium text-gray-800">
                                        {{ assignment.subject_name }} · {{ assignment.class_name }}
                                    </h3>
                                    <p class="text-sm text-gray-500">
                                        {{ assignment.section_name }} · {{ assignment.academic_year }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="assignment.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600'"
                                >
                                    {{ assignment.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <div class="flex flex-wrap gap-x-6 gap-y-3">
                                <label
                                    v-for="(label, permission) in subjectPermissionOptions"
                                    :key="permission"
                                    class="inline-flex items-center gap-2 text-sm text-gray-700"
                                >
                                    <input
                                        v-model="form.subject_assignments[index].permissions"
                                        type="checkbox"
                                        :value="permission"
                                        :disabled="!assignment.is_active"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    {{ label }}
                                </label>
                            </div>
                        </article>
                    </div>
                    <p v-else class="rounded-lg bg-gray-50 p-4 text-sm text-gray-500">
                        No subject assignment. Assign a subject to this teacher first.
                    </p>
                </section>

                <div class="flex flex-col-reverse items-stretch justify-between gap-3 sm:flex-row sm:items-center">
                    <Link
                        :href="route('teacher-subjects.index')"
                        class="rounded-lg border border-gray-300 px-5 py-2.5 text-center text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Manage teacher subject assignments
                    </Link>
                    <button
                        type="button"
                        :disabled="form.processing"
                        class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                        @click="savePermissions"
                    >
                        {{ form.processing ? 'Saving…' : 'Save permissions' }}
                    </button>
                </div>
                <p v-if="form.errors.class_assignments || form.errors.subject_assignments" class="text-sm text-red-600">
                    {{ form.errors.class_assignments || form.errors.subject_assignments }}
                </p>
            </template>
        </div>
    </InstituteLayout>
</template>
