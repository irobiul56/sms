<!-- resources/js/Pages/Marks/Create.vue -->
<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import InstituteLayout from '@/Layouts/InstituteLayout.vue';
import axios from 'axios';

// Props
const props = defineProps({
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
const isLoaded = ref(false);
const isLoading = ref(false);
const isSavingAll = ref(false);
const studentsData = ref([]);
const selectedStudents = ref([]);
const bulkAction = ref('');
const totalSaved = ref(0);
const errorMessage = ref('');
const successMessage = ref('');
const showMobileFilter = ref(false);
const isMobile = ref(false);
let mobileMediaQuery;
const updateMobileState = () => {
    isMobile.value = mobileMediaQuery.matches;
};

// Filter Form
const filterForm = useForm({
    exam_id: '',
    institute_class_id: '',
    section_id: '',
    institute_subject_id: ''
});

// Computed
const filteredSections = computed(() => {
    if (!filterForm.institute_class_id) {
        return props.sections;
    }
    return props.sections.filter(s => s.institute_class_id == filterForm.institute_class_id);
});
const filteredExams = computed(() => {
    return props.exams;
});
const displayExamName = (name) => {
    const characters = Array.from(name || '');
    if (!isMobile.value || characters.length <= 25) return name;
    return `${characters.slice(0, 24).join('')}…`;
};
const selectedExam = computed(() => props.exams.find(
    exam => String(exam.id) === String(filterForm.exam_id)
));
const selectedSubject = computed(() => props.subjects.find(
    subject => String(subject.id) === String(filterForm.institute_subject_id)
));
const hasPracticalMarks = computed(() => Number(selectedSubject.value?.practical_marks) > 0);
const hasMcqMarks = computed(() => Number(selectedSubject.value?.mcq_marks) > 0);
const hasOtherMarkComponents = computed(() => hasPracticalMarks.value || hasMcqMarks.value);
const selectedExamSubjectLimits = computed(() =>
    selectedExam.value?.subject_limits_by_class?.[filterForm.institute_class_id]?.[filterForm.institute_subject_id]
    ?? selectedExam.value?.subject_limits?.[filterForm.institute_subject_id]
    ?? {}
);
const selectedExamFullMarks = computed(() => {
    const examFullMarks = selectedExamSubjectLimits.value.full_marks;
    if (examFullMarks === null || examFullMarks === undefined || examFullMarks === '') {
        return Number(selectedSubject.value?.full_marks ?? 100);
    }
    return Number(examFullMarks);
});
const selectedExamPassMarks = computed(() => {
    const examPassMarks = selectedExamSubjectLimits.value.pass_marks;
    if (examPassMarks === null || examPassMarks === undefined || examPassMarks === '') {
        return Number(selectedSubject.value?.pass_marks ?? 0);
    }
    return Number(examPassMarks);
});
const theoryMaximum = computed(() => {
    const theoryLimit = Number(selectedSubject.value?.theory_marks) || 0;
    if (hasOtherMarkComponents.value) {
        return ((theoryLimit > 0 ? Math.min(theoryLimit, selectedExamFullMarks.value) : selectedExamFullMarks.value) || selectedExamFullMarks.value || 0);
    }
    return selectedExamFullMarks.value;
});
const practicalMaximum = computed(() => Math.min(
    Number(selectedSubject.value?.practical_marks) || 0,
    selectedExamFullMarks.value || Number.MAX_SAFE_INTEGER
));
const mcqMaximum = computed(() => Math.min(
    Number(selectedSubject.value?.mcq_marks) || 0,
    selectedExamFullMarks.value || Number.MAX_SAFE_INTEGER
));
const filteredClasses = computed(() => {
    if (!selectedExam.value) return [];
    const examClassIds = (selectedExam.value.class_ids || [selectedExam.value.class_id])
        .filter(Boolean)
        .map(String);
    return props.classes.filter(classItem => examClassIds.includes(String(classItem.id)));
});
const filteredSubjects = computed(() => {
    if (!filterForm.institute_class_id || !selectedExam.value) return [];
    const subjectsForClass = props.subjects.filter(subject =>
        subject.class_ids?.some(classId => String(classId) === String(filterForm.institute_class_id))
    );
    const examSubjectIds = selectedExam.value.subject_ids_by_class?.[filterForm.institute_class_id]
        || selectedExam.value.subject_ids
        || [];
    if (examSubjectIds.length === 0) return [];

    const examSubjectIdSet = new Set(examSubjectIds.map(String));
    return subjectsForClass.filter(subject => examSubjectIdSet.has(String(subject.id)));
});

onMounted(() => {
    mobileMediaQuery = window.matchMedia('(max-width: 639px)');
    updateMobileState();
    mobileMediaQuery.addEventListener('change', updateMobileState);
});
onBeforeUnmount(() => {
    mobileMediaQuery?.removeEventListener('change', updateMobileState);
});
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

const isFiltersValid = computed(() => {
    return filterForm.exam_id && 
           filterForm.institute_class_id && 
           filterForm.section_id && 
           filterForm.institute_subject_id;
});

watch(() => filterForm.exam_id, () => {
    filterForm.institute_class_id = '';
    filterForm.section_id = '';
    filterForm.institute_subject_id = '';
    isLoaded.value = false;
    studentsData.value = [];
});
watch(() => filterForm.institute_class_id, () => {
    filterForm.section_id = '';
    filterForm.institute_subject_id = '';
    isLoaded.value = false;
    studentsData.value = [];
});
watch(
    () => filterForm.institute_subject_id,
    () => {
        isLoaded.value = false;
        studentsData.value = [];
    }
);

// Methods
const loadStudents = async () => {
    if (!isFiltersValid.value) {
        errorMessage.value = 'Please select all fields';
        setTimeout(() => errorMessage.value = '', 3000);
        return;
    }

    isLoading.value = true;
    isLoaded.value = false;
    errorMessage.value = '';

    try {
        const response = await axios.get(route('marks.get-students'), {
            params: {
                class_id: filterForm.institute_class_id,
                section_id: filterForm.section_id,
                exam_id: filterForm.exam_id,
                subject_id: filterForm.institute_subject_id
            }
        });

        if (response.data.success) {
            const students = [...(response.data.students || [])].sort((first, second) => {
                const firstRoll = String(first.roll_no ?? '');
                const secondRoll = String(second.roll_no ?? '');
                return firstRoll.localeCompare(secondRoll, undefined, {
                    numeric: true,
                    sensitivity: 'base'
                });
            });
            const existingMarks = response.data.existing_marks || [];

            if (students.length === 0) {
                errorMessage.value = 'No students found';
                setTimeout(() => errorMessage.value = '', 3000);
                isLoading.value = false;
                return;
            }

            studentsData.value = students.map(student => {
                const existing = existingMarks.find(m => m.student_id === student.id);
                const studentEntry = {
                    id: student.id,
                    student_id: student.student_id,
                    name: student.name,
                    roll_no: student.roll_no,
                    gender: student.gender || 'male',
                    theory_marks: existing?.theory_marks ?? '',
                    practical_marks: existing?.practical_marks ?? '',
                    mcq_marks: existing?.mcq_marks ?? '',
                    total_marks: existing?.total_marks || 0,
                    grade: existing?.grade || '',
                    is_absent: existing?.is_absent || false,
                    is_reexamine: existing?.is_reexamine || false,
                    is_saved: !!existing,
                    mark_id: existing?.id || null,
                    is_saving: false,
                };
                if (existing) {
                    studentEntry.grade = studentEntry.is_absent
                        ? 'Absent'
                        : calculateGrade(Number(studentEntry.total_marks) || 0, studentEntry);
                }
                return studentEntry;
            });

            totalSaved.value = studentsData.value.filter(s => s.is_saved).length;
            isLoaded.value = true;
            selectedStudents.value = [];
            successMessage.value = `Loaded ${students.length} students`;
            setTimeout(() => successMessage.value = '', 3000);
        }
    } catch (error) {
        console.error('Load error:', error);
        errorMessage.value = error.response?.data?.message || 'Failed to load students';
        setTimeout(() => errorMessage.value = '', 3000);
    } finally {
        isLoading.value = false;
    }
};

const calculateTotal = (student) => {
    if (student.is_absent) {
        student.total_marks = 0;
        student.grade = 'Absent';
        return;
    }

    const theory = parseFloat(student.theory_marks) || 0;
    const practical = hasPracticalMarks.value ? parseFloat(student.practical_marks) || 0 : 0;
    const mcq = hasMcqMarks.value ? parseFloat(student.mcq_marks) || 0 : 0;
    const total = theory + practical + mcq;
    
    student.total_marks = total;
    student.grade = calculateGrade(total, student);
};

const markStudentDirty = (student) => {
    student.is_saved = false;
    totalSaved.value = studentsData.value.filter(item => item.is_saved).length;
};

const toggleAbsent = (student) => {
    student.is_absent = !student.is_absent;
    if (student.is_absent) {
        student.theory_marks = 0;
        student.practical_marks = 0;
        student.mcq_marks = 0;
    }
    markStudentDirty(student);
    calculateTotal(student);
};

const toggleReexamine = (student) => {
    student.is_reexamine = !student.is_reexamine;
    markStudentDirty(student);
};

const calculateGrade = (total, student) => {
    const subject = props.subjects.find(s => s.id == filterForm.institute_subject_id);
    const fullMarks = selectedExamFullMarks.value;
    const passMarks = selectedExamPassMarks.value;

    if (hasOtherMarkComponents.value) {
        for (const { key, maximum, score } of componentPassFields) {
            const max = Number(subject?.[maximum]) || 0;
            if (max <= 0 || (fullMarks > 0 && max > fullMarks)) continue;
            const achieved = parseFloat(student[score]) || 0;
            const configuredPassValue = subject?.[key];
            const required = configuredPassValue === null || configuredPassValue === undefined || configuredPassValue === ''
                ? max * 0.33
                : Number(configuredPassValue);
            if (achieved + 0.000001 < required) return 'F';
        }
    }

    if (total + 0.000001 < passMarks || fullMarks <= 0) return 'F';
    const percentage = Math.min(100, (total / fullMarks) * 100);
    return (props.grades.find(grade => percentage + 0.000001 >= Number(grade.marks_from))
        || defaultGrades.find(grade => percentage + 0.000001 >= grade.marks_from))?.name || 'F';
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

const getGradeOutcome = (grade) => {
    if (!grade) return 'Enter marks';
    if (grade === 'Absent') return 'Absent';
    return grade === 'F' ? 'Fail' : 'Pass';
};

const getGradePercentage = (student) => {
    if (!student.grade || student.is_absent || selectedExamFullMarks.value <= 0) return null;
    return ((Number(student.total_marks) / selectedExamFullMarks.value) * 100).toFixed(1);
};

const saveStudentMark = async (student, showSuccess = true) => {
    if (!student.is_absent) {
        const theory = parseFloat(student.theory_marks) || 0;
        const practical = hasPracticalMarks.value ? parseFloat(student.practical_marks) || 0 : 0;
        const mcq = hasMcqMarks.value ? parseFloat(student.mcq_marks) || 0 : 0;
        const total = theory + practical + mcq;

        const scoreLimits = [
            ['Theory', theory, theoryMaximum.value],
            ...(hasPracticalMarks.value ? [['Practical', practical, practicalMaximum.value]] : []),
            ...(hasMcqMarks.value ? [['MCQ', mcq, mcqMaximum.value]] : []),
        ];
        const exceededLimit = scoreLimits.find(([, score, maximum]) => maximum > 0 && score > maximum);
        if (exceededLimit) {
            errorMessage.value = `${exceededLimit[0]} marks cannot exceed ${exceededLimit[2]}.`;
            return false;
        }
        if (total > selectedExamFullMarks.value) {
            errorMessage.value = `Total marks cannot exceed ${selectedExamFullMarks.value}.`;
            return false;
        }

        if (theory === 0 && practical === 0 && mcq === 0) {
            errorMessage.value = 'Please enter at least one mark';
            setTimeout(() => errorMessage.value = '', 3000);
            return false;
        }
    }

    const data = {
        exam_id: filterForm.exam_id,
        student_id: student.id,
        institute_subject_id: filterForm.institute_subject_id,
        institute_class_id: filterForm.institute_class_id,
        section_id: filterForm.section_id,
        teacher_id: props.teachers[0]?.id,
        theory_marks: parseFloat(student.theory_marks) || 0,
        practical_marks: hasPracticalMarks.value ? parseFloat(student.practical_marks) || 0 : null,
        mcq_marks: hasMcqMarks.value ? parseFloat(student.mcq_marks) || 0 : null,
        is_absent: student.is_absent || false,
        is_reexamine: student.is_reexamine || false
    };

    if (!data.teacher_id) {
        errorMessage.value = 'No active teacher is available to record these marks.';
        return false;
    }

    student.is_saving = true;
    errorMessage.value = '';
    try {
        const response = await axios.post(route('marks.store'), data, {
            headers: { Accept: 'application/json' },
        });
        student.is_saved = true;
        student.mark_id = response.data.mark?.id ?? student.mark_id;
        totalSaved.value = studentsData.value.filter(item => item.is_saved).length;
        if (showSuccess) {
            successMessage.value = 'Marks saved successfully.';
            setTimeout(() => successMessage.value = '', 2500);
        }
        return true;
    } catch (error) {
        console.error('Save error:', error);
        const validationMessage = Object.values(error.response?.data?.errors ?? {}).flat()[0];
        errorMessage.value = validationMessage || error.response?.data?.message || 'Failed to save marks. Please try again.';
        return false;
    } finally {
        student.is_saving = false;
    }
};

const saveAllMarks = async () => {
    if (isSavingAll.value) return;
    const unsaved = studentsData.value.filter(s => !s.is_saved);
    if (unsaved.length === 0) {
        errorMessage.value = 'All marks are already saved!';
        setTimeout(() => errorMessage.value = '', 3000);
        return;
    }

    if (!confirm(`You have ${unsaved.length} unsaved entries. Save all?`)) {
        return;
    }

    isSavingAll.value = true;
    let saved = 0;
    let failed = 0;
    try {
        for (const student of unsaved) {
            if (await saveStudentMark(student, false)) saved++;
            else failed++;
        }
        if (failed === 0) {
            successMessage.value = `All ${saved} marks saved successfully.`;
            errorMessage.value = '';
        } else {
            errorMessage.value = `${saved} marks saved; ${failed} could not be saved. Check each highlighted entry and try again.`;
        }
        setTimeout(() => {
            successMessage.value = '';
            errorMessage.value = '';
        }, 4000);
    } finally {
        isSavingAll.value = false;
    }
};

const toggleSelectAll = (event) => {
    const checked = event.target.checked;
    if (checked) {
        selectedStudents.value = studentsData.value.map(s => s.id);
    } else {
        selectedStudents.value = [];
    }
};

const toggleSelectStudent = (studentId) => {
    const index = selectedStudents.value.indexOf(studentId);
    if (index > -1) {
        selectedStudents.value.splice(index, 1);
    } else {
        selectedStudents.value.push(studentId);
    }
};

const applyBulkAction = (action) => {
    if (selectedStudents.value.length === 0) {
        errorMessage.value = 'Please select at least one student';
        setTimeout(() => errorMessage.value = '', 3000);
        return;
    }

    if (action === 'mark_absent') {
        selectedStudents.value.forEach(id => {
            const student = studentsData.value.find(s => s.id === id);
            if (student) {
                student.is_absent = true;
                student.theory_marks = 0;
                student.practical_marks = 0;
                student.mcq_marks = 0;
                student.total_marks = 0;
                student.grade = 'Absent';
                student.is_saved = false;
            }
        });
        successMessage.value = `${selectedStudents.value.length} marked absent`;
        setTimeout(() => successMessage.value = '', 3000);
    } else if (action === 'clear') {
        selectedStudents.value.forEach(id => {
            const student = studentsData.value.find(s => s.id === id);
            if (student) {
                student.theory_marks = '';
                student.practical_marks = '';
                student.mcq_marks = '';
                student.total_marks = 0;
                student.grade = '';
                student.is_absent = false;
                student.is_reexamine = false;
                student.is_saved = false;
                student.mark_id = null;
            }
        });
        successMessage.value = `${selectedStudents.value.length} cleared`;
        setTimeout(() => successMessage.value = '', 3000);
    }

    selectedStudents.value = [];
    totalSaved.value = studentsData.value.filter(s => s.is_saved).length;
};

const resetStudentMarks = (student) => {
    student.theory_marks = '';
    student.practical_marks = '';
    student.mcq_marks = '';
    student.total_marks = 0;
    student.grade = '';
    student.is_absent = false;
    student.is_reexamine = false;
    student.is_saved = false;
    student.mark_id = null;
};

const resetForm = () => {
    filterForm.reset();
    studentsData.value = [];
    isLoaded.value = false;
    selectedStudents.value = [];
    totalSaved.value = 0;
    errorMessage.value = '';
    successMessage.value = '';
};
</script>

<template>
    <Head title="Enter Marks" />
    
    <InstituteLayout>
        <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-6 py-4 sm:py-6">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <!-- Header -->
                <div class="px-4 sm:px-6 py-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h2 class="text-xl sm:text-2xl font-bold text-gray-800 flex items-center">
                                <i class="fas fa-pen-fancy text-blue-600 mr-2"></i>
                                Enter Marks
                            </h2>
                            <p class="text-xs sm:text-sm text-gray-600 mt-0.5">
                                Select Exam > Class > Section > Subject
                            </p>
                        </div>
                        <Link :href="route('marks.index')" class="text-gray-400 hover:text-gray-600 self-start sm:self-center">
                            <i class="fas fa-times text-xl sm:text-2xl"></i>
                        </Link>
                    </div>
                </div>

                <div class="p-3 sm:p-4 lg:p-6 space-y-4 sm:space-y-6">
                    <!-- Messages -->
                    <div v-if="errorMessage" class="bg-red-50 border border-red-200 text-red-700 px-3 sm:px-4 py-2 sm:py-3 rounded-lg text-sm">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        {{ errorMessage }}
                    </div>
                    <div v-if="successMessage" class="bg-green-50 border border-green-200 text-green-700 px-3 sm:px-4 py-2 sm:py-3 rounded-lg text-sm">
                        <i class="fas fa-check-circle mr-2"></i>
                        {{ successMessage }}
                    </div>

                    <!-- Mobile Filter Toggle -->
                    <button 
                        @click="showMobileFilter = !showMobileFilter"
                        class="w-full sm:hidden flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200"
                    >
                        <span class="font-medium text-gray-700">
                            <i class="fas fa-filter mr-2"></i>
                            Filters
                        </span>
                        <i :class="showMobileFilter ? 'fas fa-chevron-up' : 'fas fa-chevron-down'" class="text-gray-400"></i>
                    </button>

                    <!-- Filters -->
                    <div :class="[
                        'bg-gray-50 p-3 sm:p-4 rounded-lg border border-gray-200 transition-all duration-300',
                        showMobileFilter ? 'block' : 'hidden sm:block'
                    ]">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">
                                    Exam <span class="text-red-500">*</span>
                                </label>
                                <select 
                                    v-model="filterForm.exam_id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm"
                                >
                                    <option value="">Select Exam</option>
                                    <option 
                                        v-for="exam in filteredExams" 
                                        :key="exam.id" 
                                        :value="exam.id"
                                    >
                                        {{ displayExamName(exam.name) }} — ({{ exam.academic_year }})
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">
                                    Class <span class="text-red-500">*</span>
                                </label>
                                <select 
                                    v-model="filterForm.institute_class_id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm"
                                    :disabled="!filterForm.exam_id"
                                >
                                    <option value="">Select Class</option>
                                    <option 
                                        v-for="cls in filteredClasses" 
                                        :key="cls.id" 
                                        :value="cls.id"
                                    >
                                        {{ cls.name }}{{ cls.group ? ` — ${cls.group}` : '' }}
                                    </option>
                                </select>
                                <p v-if="!filterForm.exam_id" class="mt-1 text-xs text-gray-400">
                                    Select exam first
                                </p>
                            </div>

                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">
                                    Section <span class="text-red-500">*</span>
                                </label>
                                <select 
                                    v-model="filterForm.section_id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm"
                                    :disabled="!filterForm.institute_class_id"
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
                                <p v-if="!filterForm.institute_class_id" class="mt-1 text-xs text-gray-400">
                                    Select class first
                                </p>
                            </div>

                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">
                                    Subject <span class="text-red-500">*</span>
                                </label>
                                <select 
                                    v-model="filterForm.institute_subject_id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm"
                                    :disabled="!filterForm.institute_class_id"
                                >
                                    <option value="">Select Subject</option>
                                    <option 
                                        v-for="subject in filteredSubjects" 
                                        :key="subject.id" 
                                        :value="subject.id"
                                    >
                                        {{ subject.name }}
                                    </option>
                                </select>
                                <p v-if="filterForm.institute_class_id && filterForm.exam_id && filteredSubjects.length === 0" class="mt-1 text-xs text-gray-400">
                                    No subjects are assigned to this class and exam. Assign class subjects and exam subjects first.
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 sm:mt-4 flex flex-col sm:flex-row gap-2 sm:gap-3">
                            <button 
                                @click="loadStudents"
                                class="w-full sm:w-auto px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium text-sm disabled:opacity-50"
                                :disabled="!isFiltersValid || isLoading"
                            >
                                <i v-if="isLoading" class="fas fa-spinner fa-spin mr-2"></i>
                                <i v-else class="fas fa-search mr-2"></i>
                                {{ isLoading ? 'Loading...' : 'Load Students' }}
                            </button>
                            <button 
                                @click="resetForm"
                                type="button"
                                aria-label="Reset filters and loaded students"
                                title="Reset"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-300 sm:w-auto"
                            >
                                <i class="fas fa-undo" aria-hidden="true"></i>
                                <span class="hidden sm:inline">Reset</span>
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="selectedSubject"
                        class="rounded-lg border border-blue-200 bg-blue-50 p-3 sm:p-4"
                        role="status"
                        aria-live="polite"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-blue-900">
                                    <i class="fas fa-scale-balanced mr-2" aria-hidden="true"></i>
                                    {{ selectedExam?.name }} · {{ selectedSubject.name }} grading
                                </p>
                                <p class="mt-1 text-xs text-blue-800">
                                    Grade is calculated live from the exam full marks. Configured theory, MCQ, or practical pass marks also apply.
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2 text-sm">
                                <span class="rounded-full bg-white px-3 py-1 font-semibold text-gray-800 ring-1 ring-blue-200">
                                    Full: {{ selectedExamFullMarks }}
                                </span>
                                <span class="rounded-full bg-white px-3 py-1 font-semibold text-gray-800 ring-1 ring-blue-200">
                                    Pass: {{ selectedExamPassMarks }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Students List -->
                    <div v-if="isLoaded">
                        <!-- Mobile Stats -->
                        <div class="flex flex-wrap items-center justify-between gap-2 p-3 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="flex items-center space-x-3 text-xs sm:text-sm">
                                <span class="text-gray-600">
                                    <i class="fas fa-users text-blue-600 mr-1"></i>
                                    {{ studentsData.length }}
                                </span>
                                <span class="text-gray-600">
                                    <i class="fas fa-check-circle text-green-600 mr-1"></i>
                                    {{ totalSaved }} saved
                                </span>
                                <span class="text-gray-600">
                                    <i class="fas fa-clock text-yellow-600 mr-1"></i>
                                    {{ studentsData.length - totalSaved }} pending
                                </span>
                            </div>
                            <button 
                                @click="saveAllMarks"
                                class="px-3 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-xs sm:text-sm font-medium"
                                :disabled="isSavingAll"
                            >
                                <i :class="isSavingAll ? 'fas fa-spinner fa-spin' : 'fas fa-save'" class="mr-1"></i>
                                {{ isSavingAll ? 'Saving...' : 'Save All' }}
                            </button>
                        </div>

                        <!-- Mobile Card View -->
                        <div class="sm:hidden space-y-3 mt-3">
                            <div 
                                v-for="student in studentsData" 
                                :key="student.id"
                                class="bg-white border rounded-lg p-3 shadow-sm"
                                :class="{ 'bg-red-50 border-red-200': student.is_absent, 'bg-yellow-50 border-yellow-200': student.is_reexamine && !student.is_absent }"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center space-x-2">
                                        <input 
                                            type="checkbox" 
                                            :checked="selectedStudents.includes(student.id)"
                                            @change="toggleSelectStudent(student.id)"
                                            class="rounded border-gray-300"
                                        >
                                        <span class="font-medium text-gray-900">Roll: {{ student.roll_no }}</span>
                                    </div>
                                    <span v-if="student.is_saved" class="text-green-600 text-xs">
                                        <i class="fas fa-check-circle"></i> Saved
                                    </span>
                                </div>
                                
                                <p class="text-sm text-gray-700 font-medium mb-2">{{ student.name }}</p>
                                
                                <div
                                    class="mb-2 grid gap-2"
                                    :class="hasPracticalMarks && hasMcqMarks ? 'grid-cols-3' : (hasPracticalMarks || hasMcqMarks ? 'grid-cols-2' : 'grid-cols-1')"
                                >
                                    <div>
                                        <label class="text-xs text-gray-500">Theory</label>
                                        <input 
                                            type="number" 
                                            v-model="student.theory_marks"
                                            @input="markStudentDirty(student); calculateTotal(student)"
                                            @blur="calculateTotal(student)"
                                            :disabled="student.is_absent"
                                            :max="theoryMaximum"
                                            class="w-full px-2 py-1 border rounded text-sm"
                                            :class="{ 'bg-gray-100': student.is_absent }"
                                            placeholder="0"
                                            step="0.01"
                                        >
                                    </div>
                                    <div v-if="hasPracticalMarks">
                                        <label class="text-xs text-gray-500">Practical</label>
                                        <input 
                                            type="number" 
                                            v-model="student.practical_marks"
                                            @input="markStudentDirty(student); calculateTotal(student)"
                                            @blur="calculateTotal(student)"
                                            :disabled="student.is_absent"
                                            :max="practicalMaximum"
                                            class="w-full px-2 py-1 border rounded text-sm"
                                            :class="{ 'bg-gray-100': student.is_absent }"
                                            placeholder="0"
                                            step="0.01"
                                        >
                                    </div>
                                    <div v-if="hasMcqMarks">
                                        <label class="text-xs text-gray-500">MCQ</label>
                                        <input 
                                            type="number" 
                                            v-model="student.mcq_marks"
                                            @input="markStudentDirty(student); calculateTotal(student)"
                                            @blur="calculateTotal(student)"
                                            :disabled="student.is_absent"
                                            :max="mcqMaximum"
                                            class="w-full px-2 py-1 border rounded text-sm"
                                            :class="{ 'bg-gray-100': student.is_absent }"
                                            placeholder="0"
                                            step="0.01"
                                        >
                                    </div>
                                </div>
                                
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-bold">
                                            Total: {{ student.total_marks || 0 }} / {{ selectedExamFullMarks }}
                                        </span>
                                        <span 
                                            class="px-2 py-0.5 text-xs font-semibold rounded-full"
                                            :class="getGradeColor(student.grade || '')"
                                        >
                                            {{ student.grade || 'Enter marks' }}
                                            <span v-if="student.grade"> · {{ getGradeOutcome(student.grade) }}</span>
                                        </span>
                                        <span v-if="getGradePercentage(student) !== null" class="text-xs text-gray-500">
                                            {{ getGradePercentage(student) }}%
                                        </span>
                                    </div>
                                    <div class="flex items-center space-x-1">
                                        <button 
                                            @click="toggleAbsent(student)"
                                            class="p-1.5 rounded-lg text-xs"
                                            :class="student.is_absent ? 'text-red-600 bg-red-50' : 'text-gray-400'"
                                            title="Absent"
                                        >
                                            <i class="fas fa-user-slash"></i>
                                        </button>
                                        <button 
                                            @click="toggleReexamine(student)"
                                            class="p-1.5 rounded-lg text-xs"
                                            :class="student.is_reexamine ? 'text-yellow-600 bg-yellow-50' : 'text-gray-400'"
                                            title="Re-examine"
                                            aria-label="Toggle re-examine"
                                        >
                                            <i class="fas fa-rotate-right"></i>
                                        </button>
                                        <button
                                            type="button"
                                            @click="resetStudentMarks(student)"
                                            class="rounded-lg p-1.5 text-xs text-gray-500 hover:bg-gray-100"
                                            title="Reset this student's marks"
                                            :aria-label="`Reset marks for ${student.name}`"
                                        >
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        <button 
                                            @click="saveStudentMark(student)"
                                            class="px-3 py-1 rounded-lg text-xs font-medium"
                                            :class="student.is_saving ? 'bg-gray-200 text-gray-500' : 'bg-blue-600 text-white hover:bg-blue-700'"
                                            :disabled="student.is_saving"
                                        >
                                            <i v-if="student.is_saving" class="fas fa-spinner fa-spin mr-1"></i>
                                            {{ student.is_saving ? 'Saving...' : (student.is_saved ? 'Saved' : 'Save') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Desktop Table View -->
                        <div class="hidden sm:block overflow-x-auto border rounded-lg mt-3">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                            <input type="checkbox" @change="toggleSelectAll" class="rounded border-gray-300">
                                        </th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Roll</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Theory</th>
                                        <th v-if="hasPracticalMarks" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Practical</th>
                                        <th v-if="hasMcqMarks" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">MCQ</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total / Full</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Live Grade</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr 
                                        v-for="student in studentsData" 
                                        :key="student.id"
                                        :class="{ 'bg-red-50': student.is_absent, 'bg-yellow-50': student.is_reexamine && !student.is_absent }"
                                    >
                                        <td class="px-3 py-2">
                                            <input 
                                                type="checkbox" 
                                                :checked="selectedStudents.includes(student.id)"
                                                @change="toggleSelectStudent(student.id)"
                                                class="rounded border-gray-300"
                                            >
                                        </td>
                                        <td class="px-3 py-2 text-sm font-medium text-gray-900">{{ student.roll_no }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-900">{{ student.name }}</td>
                                        <td class="px-3 py-2">
                                            <input 
                                                type="number" 
                                                v-model="student.theory_marks"
                                                @input="markStudentDirty(student); calculateTotal(student)"
                                                @blur="calculateTotal(student)"
                                                :disabled="student.is_absent"
                                                :max="theoryMaximum"
                                                class="w-16 px-2 py-1 border rounded text-sm"
                                                :class="{ 'bg-gray-100': student.is_absent }"
                                                placeholder="0"
                                                step="0.01"
                                            >
                                        </td>
                                        <td v-if="hasPracticalMarks" class="px-3 py-2">
                                            <input 
                                                type="number" 
                                                v-model="student.practical_marks"
                                                @input="markStudentDirty(student); calculateTotal(student)"
                                                @blur="calculateTotal(student)"
                                                :disabled="student.is_absent"
                                                :max="practicalMaximum"
                                                class="w-16 px-2 py-1 border rounded text-sm"
                                                :class="{ 'bg-gray-100': student.is_absent }"
                                                placeholder="0"
                                                step="0.01"
                                            >
                                        </td>
                                        <td v-if="hasMcqMarks" class="px-3 py-2">
                                            <input 
                                                type="number" 
                                                v-model="student.mcq_marks"
                                                @input="markStudentDirty(student); calculateTotal(student)"
                                                @blur="calculateTotal(student)"
                                                :disabled="student.is_absent"
                                                :max="mcqMaximum"
                                                class="w-16 px-2 py-1 border rounded text-sm"
                                                :class="{ 'bg-gray-100': student.is_absent }"
                                                placeholder="0"
                                                step="0.01"
                                            >
                                        </td>
                                        <td class="px-3 py-2 text-sm font-bold text-center">
                                            {{ student.total_marks || 0 }} / {{ selectedExamFullMarks }}
                                            <div v-if="getGradePercentage(student) !== null" class="text-xs font-normal text-gray-500">
                                                {{ getGradePercentage(student) }}%
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <span 
                                                class="px-2 py-0.5 text-xs font-semibold rounded-full"
                                                :class="getGradeColor(student.grade || '')"
                                            >
                                                {{ student.grade || 'Enter marks' }}
                                            </span>
                                            <div v-if="student.grade" class="mt-1 text-xs text-gray-500">
                                                {{ getGradeOutcome(student.grade) }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex flex-wrap items-center gap-1">
                                                <button 
                                                    @click="saveStudentMark(student)"
                                                    class="px-2 py-1 rounded text-xs font-medium"
                                                    :class="student.is_saving ? 'bg-gray-200 text-gray-500' : 'bg-blue-600 text-white hover:bg-blue-700'"
                                                    :disabled="student.is_saving"
                                                >
                                                    <i v-if="student.is_saving" class="fas fa-spinner fa-spin mr-1"></i>
                                                    {{ student.is_saving ? 'Saving...' : (student.is_saved ? 'Saved' : 'Save') }}
                                                </button>
                                                <button 
                                                    @click="toggleAbsent(student)"
                                                    class="p-1 rounded text-xs"
                                                    :class="student.is_absent ? 'text-red-600 bg-red-50' : 'text-gray-400'"
                                                    title="Absent"
                                                >
                                                    <i class="fas fa-user-slash"></i>
                                                </button>
                                                <button 
                                                    @click="toggleReexamine(student)"
                                                    class="p-1 rounded text-xs"
                                                    :class="student.is_reexamine ? 'text-yellow-600 bg-yellow-50' : 'text-gray-400'"
                                                    title="Re-examine"
                                                >
                                                    <i class="fas fa-rotate-right"></i>
                                                </button>
                                                <button 
                                                    @click="resetStudentMarks(student)"
                                                    class="p-1 rounded text-xs text-gray-400"
                                                    title="Reset"
                                                >
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Bulk Actions (Desktop) -->
                        <div class="hidden sm:flex flex-wrap items-center justify-between gap-3 mt-3 bg-gray-50 p-3 rounded-lg border border-gray-200">
                            <div class="flex items-center space-x-3">
                                <select 
                                    v-model="bulkAction"
                                    class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">Bulk Action</option>
                                    <option value="mark_absent">Mark as Absent</option>
                                    <option value="clear">Clear All</option>
                                </select>
                                <button 
                                    @click="applyBulkAction(bulkAction)"
                                    class="px-3 py-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm font-medium"
                                    :disabled="selectedStudents.length === 0"
                                >
                                    Apply to {{ selectedStudents.length }}
                                </button>
                            </div>
                            <button 
                                @click="saveAllMarks"
                                class="px-4 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm font-medium"
                                :disabled="isSavingAll"
                            >
                                <i :class="isSavingAll ? 'fas fa-spinner fa-spin' : 'fas fa-save'" class="mr-1"></i>
                                {{ isSavingAll ? 'Saving...' : 'Save All' }}
                            </button>
                        </div>

                        <!-- Bulk Actions (Mobile) -->
                        <div class="sm:hidden flex flex-wrap items-center gap-2 mt-3">
                            <select 
                                v-model="bulkAction"
                                class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">Bulk Action</option>
                                <option value="mark_absent">Mark as Absent</option>
                                <option value="clear">Clear All</option>
                            </select>
                            <button 
                                @click="applyBulkAction(bulkAction)"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm font-medium"
                                :disabled="selectedStudents.length === 0"
                            >
                                Apply
                            </button>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div v-if="isLoading" class="text-center py-12">
                        <i class="fas fa-spinner fa-spin text-4xl text-blue-600 mb-4 block"></i>
                        <p class="text-gray-500 font-medium">Loading students...</p>
                    </div>

                    <!-- No Students Found -->
                    <div v-else-if="isFiltersValid && !isLoaded && !isLoading" class="text-center py-12">
                        <i class="fas fa-users text-4xl text-gray-300 mb-4 block"></i>
                        <p class="text-gray-500 font-medium">No students loaded</p>
                        <p class="text-sm text-gray-400 mt-1">Click "Load Students" to continue</p>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-2 sm:gap-3 pt-4 sm:pt-6 border-t border-gray-200">
                        <Link
                            :href="route('marks.index')"
                            class="text-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium text-sm"
                        >
                            <i class="fas fa-times mr-2"></i>
                            Cancel
                        </Link>
                        <button 
                            @click="resetForm"
                            class="px-4 py-2 bg-yellow-100 text-yellow-700 rounded-lg hover:bg-yellow-200 transition font-medium text-sm"
                        >
                            <i class="fas fa-undo mr-2"></i>
                            Reset
                        </button>
                        <button 
                            @click="saveAllMarks"
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium text-sm"
                            :disabled="!isLoaded || isSavingAll"
                        >
                            <i :class="isSavingAll ? 'fas fa-spinner fa-spin' : 'fas fa-check-circle'" class="mr-2"></i>
                            {{ isSavingAll ? 'Saving...' : 'Submit All' }}
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

/* Input focus effects */
input:focus, select:focus {
    outline: none;
}

/* Button transitions */
button {
    transition: all 0.15s ease;
}

/* Row colors */
.bg-red-50 {
    background-color: #fef2f2;
}

.bg-yellow-50 {
    background-color: #fefce8;
}

/* Mobile card hover */
.sm\:hidden .bg-white:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

/* Select disabled state */
select:disabled {
    background-color: #f3f4f6;
    cursor: not-allowed;
}

/* Responsive table */
@media (max-width: 640px) {
    .overflow-x-auto {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .grid-cols-3 {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* Animation for mobile filter */
.transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 300ms;
}

/* Better touch targets on mobile */
@media (max-width: 640px) {
    button, select, input[type="checkbox"] {
        min-height: 44px;
    }
    
    input[type="checkbox"] {
        min-height: auto;
        width: 18px;
        height: 18px;
    }
    
    .p-1 {
        padding: 0.5rem;
    }
}
</style>