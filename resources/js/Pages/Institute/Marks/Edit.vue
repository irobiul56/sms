<!-- resources/js/Pages/Marks/Edit.vue -->
<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import InstituteLayout from '@/Layouts/InstituteLayout.vue';
import { router } from '@inertiajs/vue3';

// Props
const props = defineProps({
    mark: {
        type: Object,
        required: true
    },
    exams: {
        type: Array,
        default: () => []
    },
    classes: {
        type: Array,
        default: () => []
    },
    sections: {
        type: Array,
        default: () => []
    },
    subjects: {
        type: Array,
        default: () => []
    },
    students: {
        type: Array,
        default: () => []
    },
    teachers: {
        type: Array,
        default: () => []
    },
    grades: {
        type: Array,
        default: () => []
    }
});

// State
const isSubmitting = ref(false);
const showDeleteModal = ref(false);
const isDeleting = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

// Form
const form = useForm({
    exam_id: props.mark.exam_id || '',
    student_id: props.mark.student_id || '',
    institute_subject_id: props.mark.institute_subject_id || '',
    institute_class_id: props.mark.institute_class_id || '',
    section_id: props.mark.section_id || '',
    teacher_id: props.mark.teacher_id || '',
    theory_marks: props.mark.theory_marks ?? '',
    practical_marks: props.mark.practical_marks ?? '',
    mcq_marks: props.mark.mcq_marks ?? '',
    remarks: props.mark.remarks || '',
    is_absent: props.mark.is_absent || false,
    is_reexamine: props.mark.is_reexamine || false
});

// Computed
const filteredSections = computed(() => {
    if (!form.institute_class_id) {
        return props.sections;
    }
    return props.sections.filter(s => s.institute_class_id == form.institute_class_id);
});

const filteredStudents = computed(() => {
    if (!form.institute_class_id) {
        return props.students;
    }
    return props.students;
});
const selectedExam = computed(() => props.exams.find(exam => String(exam.id) === String(form.exam_id)));
const selectedSubject = computed(() => props.subjects.find(subject => String(subject.id) === String(form.institute_subject_id)));
const examSubjectLimits = computed(() =>
    selectedExam.value?.subject_limits_by_class?.[form.institute_class_id]?.[form.institute_subject_id]
    ?? selectedExam.value?.subject_limits?.[form.institute_subject_id]
    ?? {}
);
const examFullMarks = computed(() => {
    const examFull = examSubjectLimits.value.full_marks;
    if (examFull === null || examFull === undefined || examFull === '') {
        return Number(selectedSubject.value?.full_marks ?? 100);
    }
    return Number(examFull);
});
const examPassMarks = computed(() => {
    const examPass = examSubjectLimits.value.pass_marks;
    if (examPass === null || examPass === undefined || examPass === '') {
        return Number(selectedSubject.value?.pass_marks ?? 0);
    }
    return Number(examPass);
});
const hasPracticalMarks = computed(() => Number(selectedSubject.value?.practical_marks) > 0);
const hasMcqMarks = computed(() => Number(selectedSubject.value?.mcq_marks) > 0);
const componentPassFields = [
    { key: 'theory_pass_marks', maximum: 'theory_marks', score: 'theory_marks' },
    { key: 'mcq_pass_marks', maximum: 'mcq_marks', score: 'mcq_marks' },
    { key: 'practical_pass_marks', maximum: 'practical_marks', score: 'practical_marks' },
];
const defaultGrades = [
    { name: 'A+', marks_from: 80 },
    { name: 'A', marks_from: 70 },
    { name: 'A-', marks_from: 60 },
    { name: 'B', marks_from: 50 },
    { name: 'C', marks_from: 40 },
    { name: 'D', marks_from: 33 },
    { name: 'F', marks_from: 0 },
];
const theoryMaximum = computed(() => {
    const theoryLimit = Number(selectedSubject.value?.theory_marks) || 0;
    if (hasPracticalMarks.value || hasMcqMarks.value) {
        return theoryLimit > 0 ? Math.min(theoryLimit, examFullMarks.value) : examFullMarks.value;
    }
    return examFullMarks.value;
});

const totalMarks = computed(() => {
    if (form.is_absent) {
        return 0;
    }
    const theory = parseFloat(form.theory_marks) || 0;
    const practical = parseFloat(form.practical_marks) || 0;
    const mcq = parseFloat(form.mcq_marks) || 0;
    return theory + practical + mcq;
});

const calculatedGrade = computed(() => {
    if (form.is_absent) {
        return 'Absent';
    }
    const total = totalMarks.value;

    if (hasPracticalMarks.value || hasMcqMarks.value) {
        for (const { key, maximum, score } of componentPassFields) {
            const max = Number(selectedSubject.value?.[maximum]) || 0;
            if (max <= 0 || (examFullMarks.value > 0 && max > examFullMarks.value)) continue;
            const configuredPass = selectedSubject.value?.[key];
            const required = configuredPass === null || configuredPass === undefined
                ? max * 0.33
                : Number(configuredPass);
            if ((Number(form[score]) || 0) + 0.000001 < required) return 'F';
        }
    }

    if (total + 0.000001 < examPassMarks.value) return 'F';
    const percentage = Math.min(100, (total / examFullMarks.value) * 100);
    return (props.grades.find(grade => percentage + 0.000001 >= Number(grade.marks_from))
        || defaultGrades.find(grade => percentage + 0.000001 >= grade.marks_from))?.name || 'F';
});

// Methods
const submit = () => {
    isSubmitting.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    // Update marks
    form.put(route('marks.update', props.mark.id), {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            successMessage.value = 'Marks updated successfully!';
            setTimeout(() => successMessage.value = '', 3000);
        },
        onError: (errors) => {
            isSubmitting.value = false;
            errorMessage.value = 'Failed to update marks. Please check the form.';
            setTimeout(() => errorMessage.value = '', 3000);
        }
    });
};

const deleteMark = () => {
    isDeleting.value = true;
    router.delete(route('marks.destroy', props.mark.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            isDeleting.value = false;
        },
        onError: () => {
            isDeleting.value = false;
            errorMessage.value = 'Failed to delete marks. Please try again.';
            setTimeout(() => errorMessage.value = '', 3000);
        }
    });
};

const getGradeColor = (grade) => {
    const colors = {
        'A+': 'bg-green-100 text-green-800',
        'A': 'bg-blue-100 text-blue-800',
        'A-': 'bg-blue-100 text-blue-800',
        'B': 'bg-yellow-100 text-yellow-800',
        'C': 'bg-yellow-100 text-yellow-800',
        'D': 'bg-orange-100 text-orange-800',
        'F': 'bg-red-100 text-red-800',
        'Absent': 'bg-red-100 text-red-800',
        'Re-examine': 'bg-purple-100 text-purple-800'
    };
    return colors[grade] || 'bg-gray-100 text-gray-800';
};

const getStatusBadgeClass = (status) => {
    const classes = {
        active: 'bg-green-100 text-green-800',
        inactive: 'bg-gray-100 text-gray-800',
        passed: 'bg-blue-100 text-blue-800',
        transferred: 'bg-yellow-100 text-yellow-800'
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};

const getStatusLabel = (status) => {
    const labels = {
        active: 'Active',
        inactive: 'Inactive',
        passed: 'Passed',
        transferred: 'Transferred'
    };
    return labels[status] || status;
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
};
</script>

<template>
    <Head title="Edit Marks" />
    
    <InstituteLayout>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <!-- Header -->
                <div class="px-6 py-4 bg-gradient-to-r from-green-50 to-emerald-50 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800">
                                <i class="fas fa-edit text-green-600 mr-2"></i>
                                Edit Marks
                            </h2>
                            <p class="text-sm text-gray-600 mt-1">
                                Update marks for student
                            </p>
                        </div>
                        <div class="flex items-center space-x-3">
                            <button
                                @click="showDeleteModal = true"
                                class="inline-flex items-center px-4 py-2 bg-red-50 hover:bg-red-100 text-red-700 text-sm font-medium rounded-lg transition"
                            >
                                <i class="fas fa-trash mr-2"></i>
                                Delete
                            </button>
                            <Link
                                :href="route('marks.index')"
                                class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-medium rounded-lg transition"
                            >
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back
                            </Link>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <!-- Messages -->
                    <div v-if="errorMessage" class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        {{ errorMessage }}
                    </div>
                    <div v-if="successMessage" class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                        <i class="fas fa-check-circle mr-2"></i>
                        {{ successMessage }}
                    </div>

                    <!-- Mark Info Header -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200 grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <p class="text-xs text-gray-500">Student</p>
                            <p class="text-sm font-medium text-gray-900">{{ mark.student_name }}</p>
                            <p class="text-xs text-gray-500">ID: {{ mark.student_id }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Exam</p>
                            <p class="text-sm font-medium text-gray-900">{{ mark.exam_name }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Subject</p>
                            <p class="text-sm font-medium text-gray-900">{{ mark.subject_name }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Current Marks</p>
                            <p class="text-sm font-bold text-gray-900">
                                {{ mark.total_marks }} 
                                <span class="text-xs font-normal text-gray-500">({{ mark.grade_label }})</span>
                            </p>
                        </div>
                    </div>

                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Exam -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Exam <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="form.exam_id"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.exam_id }"
                                >
                                    <option value="">Select Exam</option>
                                    <option 
                                        v-for="exam in exams" 
                                        :key="exam.id" 
                                        :value="exam.id"
                                    >
                                        {{ exam.name }} ({{ exam.class_name }} - {{ exam.academic_year }})
                                    </option>
                                </select>
                                <p v-if="form.errors.exam_id" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.exam_id }}
                                </p>
                            </div>

                            <!-- Subject -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Subject <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="form.institute_subject_id"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.institute_subject_id }"
                                >
                                    <option value="">Select Subject</option>
                                    <option 
                                        v-for="subject in subjects" 
                                        :key="subject.id" 
                                        :value="subject.id"
                                    >
                                        {{ subject.name }} ({{ subject.code }})
                                    </option>
                                </select>
                                <p v-if="form.errors.institute_subject_id" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.institute_subject_id }}
                                </p>
                            </div>

                            <!-- Class -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Class <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="form.institute_class_id"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.institute_class_id }"
                                >
                                    <option value="">Select Class</option>
                                    <option 
                                        v-for="cls in classes" 
                                        :key="cls.id" 
                                        :value="cls.id"
                                    >
                                        {{ cls.name }}{{ cls.group ? ` — ${cls.group}` : '' }}
                                    </option>
                                </select>
                                <p v-if="form.errors.institute_class_id" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.institute_class_id }}
                                </p>
                            </div>

                            <!-- Section -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Section <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="form.section_id"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.section_id }"
                                    :disabled="!form.institute_class_id"
                                >
                                    <option value="">Select Section</option>
                                    <option 
                                        v-for="section in filteredSections" 
                                        :key="section.id" 
                                        :value="section.id"
                                    >
                                        {{ section.name }}
                                    </option>
                                </select>
                                <p v-if="form.errors.section_id" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.section_id }}
                                </p>
                            </div>

                            <!-- Student -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Student <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="form.student_id"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.student_id }"
                                    :disabled="!form.institute_class_id"
                                >
                                    <option value="">Select Student</option>
                                    <option 
                                        v-for="student in filteredStudents" 
                                        :key="student.id" 
                                        :value="student.id"
                                    >
                                        {{ student.name }} ({{ student.student_id }} - Roll: {{ student.roll_no }})
                                    </option>
                                </select>
                                <p v-if="form.errors.student_id" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.student_id }}
                                </p>
                            </div>

                            <!-- Teacher -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Teacher <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="form.teacher_id"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.teacher_id }"
                                >
                                    <option value="">Select Teacher</option>
                                    <option 
                                        v-for="teacher in teachers" 
                                        :key="teacher.id" 
                                        :value="teacher.id"
                                    >
                                        {{ teacher.name }}
                                    </option>
                                </select>
                                <p v-if="form.errors.teacher_id" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.teacher_id }}
                                </p>
                            </div>

                            <!-- Theory Marks -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Theory Marks
                                </label>
                                <input
                                    type="number"
                                    v-model="form.theory_marks"
                                    placeholder="e.g., 80"
                                    min="0"
                                    step="0.01"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.theory_marks }"
                                    :disabled="form.is_absent"
                                    :max="theoryMaximum"
                                />
                                <p v-if="form.errors.theory_marks" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.theory_marks }}
                                </p>
                            </div>

                            <!-- Practical Marks -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Practical Marks
                                </label>
                                <input
                                    type="number"
                                    v-model="form.practical_marks"
                                    placeholder="e.g., 20"
                                    min="0"
                                    step="0.01"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.practical_marks }"
                                    :disabled="form.is_absent"
                                    :max="selectedSubject?.practical_marks"
                                />
                                <p v-if="form.errors.practical_marks" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.practical_marks }}
                                </p>
                            </div>

                            <!-- MCQ Marks -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    MCQ Marks
                                </label>
                                <input
                                    type="number"
                                    v-model="form.mcq_marks"
                                    placeholder="e.g., 30"
                                    min="0"
                                    step="0.01"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.mcq_marks }"
                                    :disabled="form.is_absent"
                                    :max="selectedSubject?.mcq_marks"
                                />
                                <p v-if="form.errors.mcq_marks" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.mcq_marks }}
                                </p>
                            </div>

                            <!-- Marks Summary -->
                            <div class="md:col-span-2 p-4 bg-blue-50 rounded-lg border border-blue-200">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-blue-800">Marks Summary</p>
                                        <p class="text-xs text-blue-600 mt-1">
                                            Theory: {{ form.theory_marks || 0 }} | 
                                            Practical: {{ form.practical_marks || 0 }} | 
                                            MCQ: {{ form.mcq_marks || 0 }} ·
                                            Full: {{ examFullMarks }} · Pass: {{ examPassMarks }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium text-blue-800">Total: {{ totalMarks }}</p>
                                        <p class="text-xs text-blue-600">
                                            Grade: 
                                            <span 
                                                class="px-2 py-0.5 text-xs font-semibold rounded-full"
                                                :class="getGradeColor(calculatedGrade)"
                                            >
                                                {{ calculatedGrade }}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Remarks -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Remarks
                                </label>
                                <textarea
                                    v-model="form.remarks"
                                    rows="2"
                                    placeholder="Enter any remarks"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.remarks }"
                                ></textarea>
                                <p v-if="form.errors.remarks" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.remarks }}
                                </p>
                            </div>

                            <!-- Status Toggles -->
                            <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="flex items-center">
                                        <input
                                            type="checkbox"
                                            v-model="form.is_absent"
                                            class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                            @change="() => {
                                                if (form.is_absent) {
                                                    form.theory_marks = 0;
                                                    form.practical_marks = 0;
                                                    form.mcq_marks = 0;
                                                }
                                            }"
                                        />
                                        <span class="ml-2 text-sm text-gray-700">Absent</span>
                                    </label>
                                    <p class="text-xs text-gray-400 mt-1">Mark as absent (all marks will be 0)</p>
                                </div>
                                <div>
                                    <label class="flex items-center">
                                        <input
                                            type="checkbox"
                                            v-model="form.is_reexamine"
                                            class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                        />
                                        <span class="ml-2 text-sm text-gray-700">Re-examine</span>
                                    </label>
                                    <p class="text-xs text-gray-400 mt-1">Mark for re-examination</p>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex items-center justify-end space-x-3 pt-6 mt-6 border-t border-gray-200">
                            <Link
                                :href="route('marks.index')"
                                class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium"
                            >
                                Cancel
                            </Link>
                            <button
                                type="reset"
                                class="px-6 py-2.5 bg-yellow-100 text-yellow-700 rounded-lg hover:bg-yellow-200 transition font-medium"
                            >
                                <i class="fas fa-undo mr-2"></i>
                                Reset
                            </button>
                            <button
                                type="submit"
                                class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium disabled:opacity-50"
                                :disabled="form.processing || isSubmitting"
                            >
                                <i v-if="form.processing || isSubmitting" class="fas fa-spinner fa-spin mr-2"></i>
                                <i v-else class="fas fa-save mr-2"></i>
                                {{ (form.processing || isSubmitting) ? 'Updating...' : 'Update Marks' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div v-if="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" @click="showDeleteModal = false">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-xl shadow-2xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <!-- Header -->
                    <div class="px-6 py-4 bg-gradient-to-r from-red-50 to-rose-50 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">
                                    <i class="fas fa-trash text-red-600 mr-2"></i>
                                    Delete Marks
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">This action cannot be undone</p>
                            </div>
                            <button @click="showDeleteModal = false" class="text-gray-400 hover:text-gray-600 transition">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0 w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-700">
                                    Are you sure you want to delete marks for <strong class="text-gray-900">{{ mark.student_name }}</strong>?
                                </p>
                                <div class="mt-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <p class="text-xs font-medium text-gray-700 mb-2">Marks Details:</p>
                                    <div class="space-y-1 text-xs text-gray-600">
                                        <div class="flex justify-between">
                                            <span>Student:</span>
                                            <span class="font-medium">{{ mark.student_name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Exam:</span>
                                            <span class="font-medium">{{ mark.exam_name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Subject:</span>
                                            <span class="font-medium">{{ mark.subject_name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Total Marks:</span>
                                            <span class="font-medium">{{ mark.total_marks }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Grade:</span>
                                            <span class="font-medium">{{ mark.grade_label }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                                    <p class="text-xs text-yellow-800 flex items-center">
                                        <i class="fas fa-circle-info mr-2"></i>
                                        <span>This will permanently delete these marks.</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-end space-x-3">
                        <button
                            @click="showDeleteModal = false"
                            class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium"
                            :disabled="isDeleting"
                        >
                            Cancel
                        </button>
                        <button
                            @click="deleteMark"
                            class="px-6 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium disabled:opacity-50"
                            :disabled="isDeleting"
                        >
                            <i v-if="isDeleting" class="fas fa-spinner fa-spin mr-2"></i>
                            <i v-else class="fas fa-trash mr-2"></i>
                            {{ isDeleting ? 'Deleting...' : 'Delete Marks' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </InstituteLayout>
</template>

<style scoped>
/* Custom scrollbar */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

/* Modal animation */
.fixed {
    animation: fadeIn 0.2s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* Input focus effects */
input:focus, select:focus, textarea:focus {
    outline: none;
}

/* Button transitions */
button {
    transition: all 0.15s ease;
}

/* Select disabled state */
select:disabled {
    background-color: #f3f4f6;
    cursor: not-allowed;
}
</style>