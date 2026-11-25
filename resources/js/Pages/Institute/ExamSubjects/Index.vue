<!-- resources/js/Pages/Institute/ExamSubjects/Index.vue -->
<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import InstituteLayout from '@/Layouts/InstituteLayout.vue';
import { router } from '@inertiajs/vue3';

// Props
const props = defineProps({
    examSubjects: {
        type: Array,
        default: () => []
    },
    exams: {
        type: Array,
        default: () => []
    },
    subjects: {
        type: Array,
        default: () => []
    },
    classes: {
        type: Array,
        default: () => []
    },
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            total_subjects: 0,
            total_exams: 0
        })
    }
});

// State
const searchQuery = ref('');
const selectedExam = ref('');
const selectedClass = ref('');
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const showDetailsModal = ref(false);
const selectedExamSubject = ref(null);
const isSubmitting = ref(false);
const isDeleting = ref(false);

// Create Form
const createForm = useForm({
    exam_id: '',
    class_ids: [],
    institute_subject_ids: [],
    subject_marks: {},
    exam_date: '',
    start_time: '',
    end_time: '',
});

// Edit Form
const editForm = useForm({
    exam_id: '',
    class_id: '',
    institute_subject_id: '',
    exam_date: '',
    start_time: '',
    end_time: '',
    full_marks: '',
    pass_marks: ''
});

const classesForExam = (examId) => {
    const exam = props.exams.find(item => String(item.id) === String(examId));
    if (!exam) return [];

    return props.classes.filter(schoolClass =>
        (exam.class_ids || []).some(classId => String(classId) === String(schoolClass.id))
    );
};
const subjectsForExamClasses = (examId, classIds) => {
    if (!classIds.length) {
        return [];
    }

    return props.subjects.filter(subject =>
        (subject.class_ids || []).some(subjectClassId =>
            classIds.some(classId => String(subjectClassId) === String(classId))
        )
    );
};
const createClasses = computed(() => classesForExam(createForm.exam_id));
const createExam = computed(() => props.exams.find(item => String(item.id) === String(createForm.exam_id)));
const createAllowsMultipleSelections = computed(() => ['monthly', 'test'].includes(createExam.value?.type));
const createSubjects = computed(() => subjectsForExamClasses(createForm.exam_id, createForm.class_ids));
const selectedCreateSubjects = computed(() => createSubjects.value.filter(subject =>
    createForm.institute_subject_ids.some(subjectId => String(subjectId) === String(subject.id))
));
const editClasses = computed(() => classesForExam(editForm.exam_id));
const editSubjects = computed(() => subjectsForExamClasses(editForm.exam_id, editForm.class_id ? [editForm.class_id] : []));
const createSubjectError = computed(() => Object.entries(createForm.errors)
    .find(([key]) => key === 'institute_subject_ids' || key.startsWith('institute_subject_ids.'))?.[1]);
const subjectMarksError = computed(() => Object.entries(createForm.errors)
    .find(([key]) => key.startsWith('subject_marks.'))?.[1]);

watch(() => createForm.exam_id, () => {
    createForm.class_ids = [];
    createForm.institute_subject_ids = [];
}, { flush: 'sync' });
watch(() => createForm.class_ids.slice(), () => {
    const availableIds = createSubjects.value.map(subject => String(subject.id));
    createForm.institute_subject_ids = createForm.institute_subject_ids
        .filter(subjectId => availableIds.includes(String(subjectId)));
});
const updateCreateClasses = (event) => {
    createForm.class_ids = event.target.multiple
        ? Array.from(event.target.selectedOptions, option => option.value)
        : (event.target.value ? [event.target.value] : []);
};
const isCreateSubjectDisabled = (subjectId) =>
    !createAllowsMultipleSelections.value
    && createForm.institute_subject_ids.length > 0
    && !createForm.institute_subject_ids.some(id => String(id) === String(subjectId));
watch(() => createForm.institute_subject_ids.slice(), selectedIds => {
    const marks = {};
    for (const subjectId of selectedIds) {
        const subject = createSubjects.value.find(item => String(item.id) === String(subjectId));
        marks[subjectId] = createForm.subject_marks[subjectId] || {
            full_marks: subject?.full_marks ?? 100,
            pass_marks: subject?.pass_marks ?? 33,
        };
    }
    createForm.subject_marks = marks;
}, { deep: true, flush: 'sync' });
watch(() => editForm.exam_id, () => {
    editForm.class_id = '';
    editForm.institute_subject_id = '';
}, { flush: 'sync' });
watch(() => editForm.class_id, () => {
    if (!editSubjects.value.some(subject => String(subject.id) === String(editForm.institute_subject_id))) {
        editForm.institute_subject_id = '';
    }
});

// Computed
// Get unique classes for filter
const uniqueClasses = computed(() => {
    const assignedClassIds = new Set(props.examSubjects.map(es => es.class_id).filter(Boolean).map(String));
    return props.classes.filter(schoolClass => assignedClassIds.has(String(schoolClass.id)));
});

const routineExamSubjects = computed(() => props.examSubjects
    .filter(es => String(es.class_id) === String(selectedClass.value)
        && String(es.exam_id) === String(selectedExam.value))
    .slice()
    .sort((a, b) => {
        const dateOrder = String(a.exam_date || '').localeCompare(String(b.exam_date || ''));
        return dateOrder || String(a.start_time || '').localeCompare(String(b.start_time || ''));
    }));
const filteredExamSubjects = computed(() => {
    let filtered = props.examSubjects;
    
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(es => {
            return es.exam_name?.toLowerCase().includes(query) ||
                   es.subject_name?.toLowerCase().includes(query) ||
                   es.subject_code?.toLowerCase().includes(query) ||
                   es.class_name?.toLowerCase().includes(query);
        });
    }
    
    if (selectedExam.value) {
        filtered = filtered.filter(es => es.exam_id == selectedExam.value);
    }
    
    if (selectedClass.value) {
        filtered = filtered.filter(es => String(es.class_id) === String(selectedClass.value));
    }
    
    return filtered;
});

// Stats cards
const displayStats = computed(() => [
    {
        label: 'Total Assignments',
        value: props.stats.total,
        icon: 'fa-solid fa-tasks',
        color: 'blue',
        bgColor: 'bg-blue-100',
        textColor: 'text-blue-600'
    },
    {
        label: 'Total Exams',
        value: props.stats.total_exams,
        icon: 'fa-solid fa-clipboard-list',
        color: 'green',
        bgColor: 'bg-green-100',
        textColor: 'text-green-600'
    },
    {
        label: 'Total Subjects',
        value: props.stats.total_subjects,
        icon: 'fa-solid fa-book',
        color: 'purple',
        bgColor: 'bg-purple-100',
        textColor: 'text-purple-600'
    }
]);

// Methods
const openCreateModal = () => {
    createForm.reset();
    createForm.clearErrors();
    showCreateModal.value = true;
};

const openEditModal = (examSubject) => {
    selectedExamSubject.value = examSubject;
    editForm.exam_id = examSubject.exam_id;
    editForm.class_id = examSubject.class_id || '';
    editForm.institute_subject_id = examSubject.institute_subject_id;
    editForm.exam_date = examSubject.exam_date;
    editForm.start_time = examSubject.start_time;
    editForm.end_time = examSubject.end_time;
    editForm.full_marks = examSubject.full_marks;
    editForm.pass_marks = examSubject.pass_marks;
    editForm.clearErrors();
    showEditModal.value = true;
};

const openDeleteModal = (examSubject) => {
    selectedExamSubject.value = examSubject;
    showDeleteModal.value = true;
};

const openDetailsModal = (examSubject) => {
    selectedExamSubject.value = examSubject;
    showDetailsModal.value = true;
};

const createExamSubject = () => {
    isSubmitting.value = true;
    createForm.post(route('exam-subjects.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            isSubmitting.value = false;
            createForm.reset();
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
};

const updateExamSubject = () => {
    isSubmitting.value = true;
    editForm.put(route('exam-subjects.update', selectedExamSubject.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            isSubmitting.value = false;
            selectedExamSubject.value = null;
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
};

const deleteExamSubject = () => {
    if (isDeleting.value || !selectedExamSubject.value) return;

    const examSubjectId = selectedExamSubject.value.id;
    isDeleting.value = true;
    router.delete(route('exam-subjects.destroy', examSubjectId), {
        preserveScroll: true,
        onSuccess: (page) => {
            const assignmentRemains = page.props.examSubjects?.some(
                examSubject => String(examSubject.id) === String(examSubjectId)
            );
            if (page.props.flash?.type === 'success' || !assignmentRemains) {
                showDeleteModal.value = false;
                selectedExamSubject.value = null;
            }
        },
        onFinish: () => {
            isDeleting.value = false;
        }
    });
};

const resetFilters = () => {
    searchQuery.value = '';
    selectedExam.value = '';
    selectedClass.value = '';
};

const printRoutine = () => {
    const url = new URL(route('exam-subjects.routine-print'), window.location.origin);
    url.searchParams.set('class_id', selectedClass.value);
    url.searchParams.set('exam_id', selectedExam.value);
    window.open(url.toString(), '_blank', 'noopener,noreferrer');
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
};

const formatTime = (time) => {
    if (!time) return 'N/A';
    const value = String(time);
    const timePart = value.match(/(?:T|\s)(\d{2}:\d{2})/)?.[1] || value.slice(0, 5);
    const date = new Date(`2000-01-01T${timePart}`);
    if (Number.isNaN(date.getTime())) return 'N/A';

    return date.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });
};

// ✅ New method to format duration
const formatDuration = (minutes) => {
    if (!minutes) return 'N/A';
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    
    let duration = '';
    if (hours > 0) {
        duration += hours + 'h ';
    }
    if (mins > 0) {
        duration += mins + 'm';
    }
    if (duration === '') {
        duration = '0m';
    }
    return duration.trim();
};

const getStatusColor = (status) => {
    const colors = {
        'Upcoming': 'bg-blue-100 text-blue-800 border-blue-200',
        'Ongoing': 'bg-yellow-100 text-yellow-800 border-yellow-200',
        'Completed': 'bg-gray-100 text-gray-800 border-gray-200',
        'Published': 'bg-green-100 text-green-800 border-green-200'
    };
    return colors[status] || 'bg-gray-100 text-gray-800 border-gray-200';
};
</script>

<template>
    <Head title="Exam Subjects Management" />
    
    <InstituteLayout>
        

        <div class="space-y-6 w-full p-5">
            <div
                v-if="$page.props.flash?.message"
                class="rounded-lg px-4 py-3 text-sm"
                :class="$page.props.flash.type === 'error' ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700'"
            >
                {{ $page.props.flash.message }}
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                <div 
                    v-for="stat in displayStats" 
                    :key="stat.label"
                    class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow border-l-4"
                    :class="{
                        'border-l-blue-500': stat.color === 'blue',
                        'border-l-green-500': stat.color === 'green',
                        'border-l-purple-500': stat.color === 'purple'
                    }"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ stat.label }}</p>
                            <p class="text-2xl font-bold text-gray-800 mt-1">{{ stat.value }}</p>
                        </div>
                        <div class="w-12 h-12 rounded-lg flex items-center justify-center"
                             :class="stat.bgColor"
                        >
                            <i :class="[stat.icon, 'text-2xl', stat.textColor]"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search & Filter -->
            <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                <div class="flex flex-col lg:flex-row gap-4">
                    <div class="flex-1 relative">
                        <i class="fa-solid fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input
                            type="text"
                            v-model="searchQuery"
                            placeholder="Search by exam, subject, class, or code..."
                            class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                        />
                        <div v-if="searchQuery" class="absolute right-3 top-1/2 transform -translate-y-1/2">
                            <button @click="searchQuery = ''" class="text-gray-400 hover:text-gray-600">
                                <i class="fa-solid fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex flex-wrap gap-3">
                        <select
                            v-model="selectedExam"
                            class="px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm min-w-[200px] bg-white"
                        >
                            <option value="">All Exams</option>
                            <option 
                                v-for="exam in exams" 
                                :key="exam.id" 
                                :value="exam.id"
                            >
                                {{ exam.name }} - {{ exam.academic_year }}
                            </option>
                        </select>

                        <select
                            v-model="selectedClass"
                            class="px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm min-w-[150px] bg-white"
                        >
                            <option value="">All Classes</option>
                            <option 
                                v-for="cls in uniqueClasses" 
                                :key="cls.id" 
                                :value="cls.id"
                            >
                                {{ cls.name }}
                            </option>
                        </select>

                        <button
                            @click="resetFilters"
                            class="px-3 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition whitespace-nowrap"
                        >
                            <i class="fa-solid fa-undo mr-1"></i>
                            Reset
                        </button>

                        <button
                            type="button"
                            @click="printRoutine"
                            :disabled="!selectedClass || !selectedExam || !routineExamSubjects.length"
                            class="inline-flex items-center px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white text-sm font-medium rounded-lg transition whitespace-nowrap"
                        >
                            <i class="fa-solid fa-print mr-2"></i>
                            Print Routine
                        </button>

                        <button
                        @click="openCreateModal"
                        class="inline-flex items-center px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition shadow-sm hover:shadow-md"
                    >
                        <i class="fa-solid fa-plus mr-2"></i>
                        Assign Subject
                    </button>
                    </div>
                </div>
                <p v-if="!selectedExam || !selectedClass" class="mt-2 text-xs text-gray-500">
                    Select an exam and class to customize and print its routine in a new tab.
                </p>

                <div class="mt-3 flex flex-wrap items-center justify-between text-xs text-gray-500 border-t border-gray-100 pt-3">
                    <span>
                        Showing <span class="font-medium text-gray-700">{{ filteredExamSubjects.length }}</span> of 
                        <span class="font-medium text-gray-700">{{ props.examSubjects.length }}</span> assignments
                    </span>
                    <span>
                        <i class="fa-regular fa-clock mr-1"></i>
                        Updated: {{ formatDate(new Date()) }}
                    </span>
                </div>
            </div>

            <!-- Exam Subjects Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Empty State -->
                <div v-if="filteredExamSubjects.length === 0" class="col-span-full">
                    <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <i class="fa-solid fa-tasks text-6xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500 font-medium text-lg">No subjects assigned</p>
                            <p class="text-sm text-gray-400 mt-1">
                                {{ searchQuery || selectedExam || selectedClass ? 'Try adjusting your filters' : 'Assign your first subject to an exam' }}
                            </p>
                            <button
                                v-if="!searchQuery && !selectedExam && !selectedClass"
                                @click="openCreateModal"
                                class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition"
                            >
                                <i class="fa-solid fa-plus mr-2"></i>
                                Assign Subject
                            </button>
                            <button
                                v-else
                                @click="resetFilters"
                                class="mt-4 inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-medium rounded-lg transition"
                            >
                                <i class="fa-solid fa-undo mr-2"></i>
                                Reset Filters
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Exam Subject Card -->
                <div 
                    v-for="examSubject in filteredExamSubjects" 
                    :key="examSubject.id"
                    class="bg-white rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden group border border-gray-100"
                >
                    <!-- Card Header -->
                    <div class="p-4 border-b border-gray-100 bg-gradient-to-r from-blue-50 to-indigo-50">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <span 
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                        :class="examSubject.exam_status_badge_class || 'bg-gray-100 text-gray-800'"
                                    >
                                        <i class="fa-regular fa-circle mr-1 text-xs"></i>
                                        {{ examSubject.exam_status || 'N/A' }}
                                    </span>
                                    <span 
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800"
                                    >
                                        <i class="fa-solid fa-school mr-1 text-xs"></i>
                                        {{ examSubject.class_name }}
                                    </span>
                                </div>
                                <h3 class="text-base font-semibold text-gray-900 group-hover:text-blue-600 transition-colors">
                                    {{ examSubject.exam_name }}
                                </h3>
                                <p class="text-xs text-gray-500">{{ examSubject.exam_type || '' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="p-4 space-y-3">
                        <!-- Subject Info -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center">
                                    <i class="fa-solid fa-book text-purple-600 text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ examSubject.subject_name }}</p>
                                    <p class="text-xs text-gray-500">Code: {{ examSubject.subject_code }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Schedule -->
                        <div class="grid grid-cols-2 gap-2 text-sm bg-gray-50 rounded-lg p-2.5">
                            <div class="flex items-center gap-1.5">
                                <i class="fa-regular fa-calendar text-blue-500 text-xs"></i>
                                <span class="text-gray-700">{{ examSubject.formatted_date }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-regular fa-clock text-green-500 text-xs"></i>
                                <span class="text-gray-700">{{ examSubject.time_range }}</span>
                            </div>
                        </div>

                        <!-- Marks -->
                        <div class="flex items-center justify-between text-sm">
                            <div class="flex items-center gap-4">
                                <div>
                                    <span class="text-xs text-gray-500">Full Marks</span>
                                    <p class="font-bold text-gray-900">{{ examSubject.full_marks }}</p>
                                </div>
                                <div class="w-px h-8 bg-gray-200"></div>
                                <div>
                                    <span class="text-xs text-gray-500">Pass Marks</span>
                                    <p class="font-bold text-green-600">{{ examSubject.pass_marks }}</p>
                                </div>
                            </div>
                            <div>
                             <span class="text-xs text-gray-400 flex items-center gap-1">Duration</span>
                            <i class="fa-regular fa-hourglass-half mr-1"></i>
                            <p>{{ examSubject.duration || formatDuration(examSubject.duration_minutes) }}</p>
                            </div>
                        </div>

                        <!-- Class Info -->
                        <div class="flex items-center justify-between text-xs text-gray-500 pt-1 border-t border-gray-100">
                            <div class="flex items-center gap-1">
                                <i class="fa-solid fa-school text-indigo-500"></i>
                                <span>Class: {{ examSubject.class_name }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Card Footer -->
                    <div class="px-4 py-2.5 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                        <button
                            @click="openDetailsModal(examSubject)"
                            class="text-blue-600 hover:text-blue-800 text-sm font-medium transition"
                        >
                            <i class="fa-regular fa-eye mr-1"></i>
                            Details
                        </button>
                        <div class="flex items-center gap-1">
                            <button
                                @click="openEditModal(examSubject)"
                                class="p-1.5 text-green-600 hover:text-green-900 hover:bg-green-50 rounded-lg transition"
                                title="Edit"
                            >
                                <i class="fa-solid fa-edit"></i>
                            </button>
                            <button
                                @click="openDeleteModal(examSubject)"
                                class="p-1.5 text-red-600 hover:text-red-900 hover:bg-red-50 rounded-lg transition"
                                title="Delete"
                            >
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- CREATE MODAL -->
            <!-- ============================================ -->
            <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 transition-opacity" @click="showCreateModal = false">
                        <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                    </div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                    <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-xl shadow-2xl sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                        <!-- Header -->
                        <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800">
                                        <i class="fa-solid fa-plus-circle text-blue-600 mr-2"></i>
                                        Assign Subject to Exam
                                    </h3>
                                    <p class="text-xs text-gray-500 mt-0.5">Assign a subject to an examination</p>
                                </div>
                                <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600 transition">
                                    <i class="fa-solid fa-times text-xl"></i>
                                </button>
                            </div>
                        </div>

                        <form @submit.prevent="createExamSubject" class="p-6">
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Exam <span class="text-red-500">*</span>
                                        </label>
                                        <select
                                            v-model="createForm.exam_id"
                                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                            :class="{ 'border-red-500': createForm.errors.exam_id }"
                                        >
                                            <option value="">Select Exam</option>
                                            <option 
                                                v-for="exam in exams" 
                                                :key="exam.id" 
                                                :value="exam.id"
                                            >
                                                {{ exam.name }} - {{ exam.academic_year }}
                                            </option>
                                        </select>
                                        <p v-if="createForm.errors.exam_id" class="mt-1 text-sm text-red-600">
                                            {{ createForm.errors.exam_id }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Class{{ createAllowsMultipleSelections ? 'es' : '' }} <span class="text-red-500">*</span>
                                        </label>
                                        <select
                                            :value="createAllowsMultipleSelections ? createForm.class_ids : (createForm.class_ids[0] || '')"
                                            @change="updateCreateClasses"
                                            :multiple="createAllowsMultipleSelections"
                                            :disabled="!createForm.exam_id"
                                            required
                                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition disabled:bg-gray-100"
                                            :class="{ 'border-red-500': createForm.errors.class_ids || createForm.errors.class_id }"
                                        >
                                            <option v-if="!createAllowsMultipleSelections" value="">Select Class</option>
                                            <option v-for="schoolClass in createClasses" :key="schoolClass.id" :value="schoolClass.id">
                                                {{ schoolClass.name }}{{ schoolClass.group ? ` — ${schoolClass.group}` : '' }}
                                            </option>
                                        </select>
                                        <p v-if="createForm.errors.class_ids || createForm.errors.class_id" class="mt-1 text-sm text-red-600">
                                            {{ createForm.errors.class_ids || createForm.errors.class_id }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Subjects <span class="text-red-500">*</span>
                                        </label>
                                        <div
                                            class="max-h-40 overflow-y-auto rounded-lg border border-gray-300 p-3"
                                            :class="{ 'border-red-500': createSubjectError }"
                                        >
                                            <p v-if="!createForm.class_ids.length" class="text-sm text-gray-500">
                                                Select an exam and class to see available subjects.
                                            </p>
                                            <p v-else-if="!createSubjects.length" class="text-sm text-gray-500">
                                                No subjects are assigned to this class.
                                            </p>
                                            <label
                                                v-for="subject in createSubjects"
                                                :key="subject.id"
                                                class="flex cursor-pointer items-center gap-2 py-1 text-sm text-gray-700"
                                            >
                                                <input
                                                    v-model="createForm.institute_subject_ids"
                                                    type="checkbox"
                                                    :value="subject.id"
                                                    :disabled="isCreateSubjectDisabled(subject.id)"
                                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                />
                                                <span>{{ subject.name }} ({{ subject.code }})</span>
                                            </label>
                                        </div>
                                        <p v-if="createSubjectError" class="mt-1 text-sm text-red-600">
                                            {{ createSubjectError }}
                                        </p>
                                        <p v-else class="mt-1 text-xs text-gray-500">
                                            {{ createAllowsMultipleSelections ? 'Select one or more subjects.' : 'Select one subject.' }}
                                        </p>
                                        <div v-if="selectedCreateSubjects.length" class="mt-3 space-y-3">
                                            <div
                                                v-for="subject in selectedCreateSubjects"
                                                :key="subject.id"
                                                class="rounded-lg border border-gray-200 bg-gray-50 p-3"
                                            >
                                                <p class="mb-2 text-sm font-medium text-gray-700">{{ subject.name }} marks</p>
                                                <div class="grid grid-cols-2 gap-3">
                                                    <label class="text-xs text-gray-600">
                                                        Full Marks
                                                        <input
                                                            v-model="createForm.subject_marks[subject.id].full_marks"
                                                            type="number"
                                                            min="1"
                                                            class="mt-1 w-full rounded-md border-gray-300 text-sm"
                                                        />
                                                        <span v-if="createForm.errors[`subject_marks.${subject.id}.full_marks`]" class="mt-1 block text-red-600">
                                                            {{ createForm.errors[`subject_marks.${subject.id}.full_marks`] }}
                                                        </span>
                                                    </label>
                                                    <label class="text-xs text-gray-600">
                                                        Pass Marks
                                                        <input
                                                            v-model="createForm.subject_marks[subject.id].pass_marks"
                                                            type="number"
                                                            min="0"
                                                            :max="Number(createForm.subject_marks[subject.id].full_marks) - 1"
                                                            class="mt-1 w-full rounded-md border-gray-300 text-sm"
                                                        />
                                                        <span v-if="createForm.errors[`subject_marks.${subject.id}.pass_marks`]" class="mt-1 block text-red-600">
                                                            {{ createForm.errors[`subject_marks.${subject.id}.pass_marks`] }}
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                            <p v-if="subjectMarksError && !selectedCreateSubjects.some(subject => createForm.errors[`subject_marks.${subject.id}.full_marks`] || createForm.errors[`subject_marks.${subject.id}.pass_marks`])" class="text-sm text-red-600">
                                                {{ subjectMarksError }}
                                            </p>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Exam Date <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="date"
                                            v-model="createForm.exam_date"
                                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                            :class="{ 'border-red-500': createForm.errors.exam_date }"
                                        />
                                        <p v-if="createForm.errors.exam_date" class="mt-1 text-sm text-red-600">
                                            {{ createForm.errors.exam_date }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Start Time <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="time"
                                            v-model="createForm.start_time"
                                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                            :class="{ 'border-red-500': createForm.errors.start_time }"
                                        />
                                        <p v-if="createForm.errors.start_time" class="mt-1 text-sm text-red-600">
                                            {{ createForm.errors.start_time }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            End Time <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="time"
                                            v-model="createForm.end_time"
                                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                            :class="{ 'border-red-500': createForm.errors.end_time }"
                                        />
                                        <p v-if="createForm.errors.end_time" class="mt-1 text-sm text-red-600">
                                            {{ createForm.errors.end_time }}
                                        </p>
                                    </div>

                                </div>
                            </div>

                            <div class="flex items-center justify-end space-x-3 pt-6 mt-6 border-t border-gray-200">
                                <button
                                    type="button"
                                    @click="showCreateModal = false"
                                    class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium disabled:opacity-50"
                                    :disabled="isSubmitting"
                                >
                                    <i v-if="isSubmitting" class="fa-solid fa-spinner fa-spin mr-2"></i>
                                    <i v-else class="fa-solid fa-save mr-2"></i>
                                    {{ isSubmitting ? 'Assigning...' : 'Assign Subject' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- EDIT MODAL -->
            <!-- ============================================ -->
            <div v-if="showEditModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 transition-opacity" @click="showEditModal = false">
                        <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                    </div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                    <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-xl shadow-2xl sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                        <div class="px-6 py-4 bg-gradient-to-r from-green-50 to-emerald-50 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800">
                                        <i class="fa-solid fa-edit text-green-600 mr-2"></i>
                                        Edit Exam Subject
                                    </h3>
                                    <p class="text-xs text-gray-500 mt-0.5">Update exam subject information</p>
                                </div>
                                <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-600 transition">
                                    <i class="fa-solid fa-times text-xl"></i>
                                </button>
                            </div>
                        </div>

                        <form @submit.prevent="updateExamSubject" class="p-6">
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Exam <span class="text-red-500">*</span>
                                        </label>
                                        <select
                                            v-model="editForm.exam_id"
                                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                            :class="{ 'border-red-500': editForm.errors.exam_id }"
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
                                        <p v-if="editForm.errors.exam_id" class="mt-1 text-sm text-red-600">
                                            {{ editForm.errors.exam_id }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Class <span class="text-red-500">*</span>
                                        </label>
                                        <select
                                            v-model="editForm.class_id"
                                            :disabled="!editForm.exam_id"
                                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition disabled:bg-gray-100"
                                            :class="{ 'border-red-500': editForm.errors.class_id }"
                                        >
                                            <option value="">Select Class</option>
                                            <option v-for="schoolClass in editClasses" :key="schoolClass.id" :value="schoolClass.id">
                                                {{ schoolClass.name }}
                                            </option>
                                        </select>
                                        <p v-if="editForm.errors.class_id" class="mt-1 text-sm text-red-600">
                                            {{ editForm.errors.class_id }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Subject <span class="text-red-500">*</span>
                                        </label>
                                        <select
                                            v-model="editForm.institute_subject_id"
                                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                            :class="{ 'border-red-500': editForm.errors.institute_subject_id }"
                                        >
                                            <option value="">Select Subject</option>
                                            <option 
                                                v-for="subject in editSubjects" 
                                                :key="subject.id" 
                                                :value="subject.id"
                                            >
                                                {{ subject.name }} ({{ subject.code }})
                                            </option>
                                        </select>
                                        <p v-if="editForm.errors.institute_subject_id" class="mt-1 text-sm text-red-600">
                                            {{ editForm.errors.institute_subject_id }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Exam Date <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="date"
                                            v-model="editForm.exam_date"
                                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                            :class="{ 'border-red-500': editForm.errors.exam_date }"
                                        />
                                        <p v-if="editForm.errors.exam_date" class="mt-1 text-sm text-red-600">
                                            {{ editForm.errors.exam_date }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Start Time <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="time"
                                            v-model="editForm.start_time"
                                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                            :class="{ 'border-red-500': editForm.errors.start_time }"
                                        />
                                        <p v-if="editForm.errors.start_time" class="mt-1 text-sm text-red-600">
                                            {{ editForm.errors.start_time }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            End Time <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="time"
                                            v-model="editForm.end_time"
                                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                            :class="{ 'border-red-500': editForm.errors.end_time }"
                                        />
                                        <p v-if="editForm.errors.end_time" class="mt-1 text-sm text-red-600">
                                            {{ editForm.errors.end_time }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Full Marks <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="number"
                                            v-model="editForm.full_marks"
                                            placeholder="100"
                                            min="1"
                                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                            :class="{ 'border-red-500': editForm.errors.full_marks }"
                                        />
                                        <p v-if="editForm.errors.full_marks" class="mt-1 text-sm text-red-600">
                                            {{ editForm.errors.full_marks }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Pass Marks <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="number"
                                            v-model="editForm.pass_marks"
                                            placeholder="33"
                                            min="0"
                                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                            :class="{ 'border-red-500': editForm.errors.pass_marks }"
                                        />
                                        <p v-if="editForm.errors.pass_marks" class="mt-1 text-sm text-red-600">
                                            {{ editForm.errors.pass_marks }}
                                        </p>
                                        <p class="mt-1 text-xs text-gray-400">Must be less than Full Marks</p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-end space-x-3 pt-6 mt-6 border-t border-gray-200">
                                <button
                                    type="button"
                                    @click="showEditModal = false"
                                    class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium disabled:opacity-50"
                                    :disabled="isSubmitting"
                                >
                                    <i v-if="isSubmitting" class="fa-solid fa-spinner fa-spin mr-2"></i>
                                    <i v-else class="fa-solid fa-save mr-2"></i>
                                    {{ isSubmitting ? 'Updating...' : 'Update Subject' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- DETAILS MODAL -->
            <!-- ============================================ -->
            <div v-if="showDetailsModal && selectedExamSubject" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 transition-opacity" @click="showDetailsModal = false">
                        <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                    </div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                    <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-xl shadow-2xl sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                        <div class="px-6 py-4 bg-gradient-to-r from-indigo-50 to-purple-50 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800">
                                        <i class="fa-solid fa-info-circle text-indigo-600 mr-2"></i>
                                        Assignment Details
                                    </h3>
                                    <p class="text-xs text-gray-500 mt-0.5">Complete information about the exam subject</p>
                                </div>
                                <button @click="showDetailsModal = false" class="text-gray-400 hover:text-gray-600 transition">
                                    <i class="fa-solid fa-times text-xl"></i>
                                </button>
                            </div>
                        </div>

                        <div class="p-6">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="p-3 bg-gray-50 rounded-lg">
                                    <p class="text-xs text-gray-500">Exam</p>
                                    <p class="text-sm font-medium text-gray-900">{{ selectedExamSubject.exam_name }}</p>
                                    <p class="text-xs text-gray-500">{{ selectedExamSubject.exam_type }}</p>
                                </div>
                                <div class="p-3 bg-gray-50 rounded-lg">
                                    <p class="text-xs text-gray-500">Class</p>
                                    <p class="text-sm font-medium text-gray-900">{{ selectedExamSubject.class_name }}</p>
                                </div>
                                <div class="p-3 bg-gray-50 rounded-lg">
                                    <p class="text-xs text-gray-500">Subject</p>
                                    <p class="text-sm font-medium text-gray-900">{{ selectedExamSubject.subject_name }}</p>
                                    <p class="text-xs text-gray-500">Code: {{ selectedExamSubject.subject_code }}</p>
                                </div>
                                <div class="p-3 bg-gray-50 rounded-lg">
                                    <p class="text-xs text-gray-500">Date</p>
                                    <p class="text-sm font-medium text-gray-900">{{ selectedExamSubject.formatted_date }}</p>
                                </div>
                                <div class="p-3 bg-gray-50 rounded-lg">
                                    <p class="text-xs text-gray-500">Time</p>
                                    <p class="text-sm font-medium text-gray-900">{{ selectedExamSubject.time_range }}</p>
                                </div>
                                <div class="p-3 bg-gray-50 rounded-lg">
                                    <p class="text-xs text-gray-500">Full Marks</p>
                                    <p class="text-sm font-bold text-gray-900">{{ selectedExamSubject.full_marks }}</p>
                                </div>
                                <div class="p-3 bg-gray-50 rounded-lg">
                                    <p class="text-xs text-gray-500">Pass Marks</p>
                                    <p class="text-sm font-bold text-green-600">{{ selectedExamSubject.pass_marks }}</p>
                                </div>
                                <div class="col-span-2 p-3 bg-gray-50 rounded-lg" v-if="selectedExamSubject.exam_status">
                                    <p class="text-xs text-gray-500">Exam Status</p>
                                    <p class="text-sm font-medium">
                                        <span 
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                            :class="selectedExamSubject.exam_status_badge_class || 'bg-gray-100 text-gray-800'"
                                        >
                                            {{ selectedExamSubject.exam_status }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-end space-x-3">
                            <button
                                @click="showDetailsModal = false"
                                class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium"
                            >
                                Close
                            </button>
                            <button
                                @click="showDetailsModal = false; openEditModal(selectedExamSubject)"
                                class="px-6 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium"
                            >
                                <i class="fa-solid fa-edit mr-2"></i>
                                Edit
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- DELETE MODAL -->
            <!-- ============================================ -->
            <div v-if="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 transition-opacity" @click="!isDeleting && (showDeleteModal = false)">
                        <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                    </div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                    <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-xl shadow-2xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                        <div class="px-6 py-4 bg-gradient-to-r from-red-50 to-rose-50 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800">
                                        <i class="fa-solid fa-trash text-red-600 mr-2"></i>
                                        Delete Exam Subject
                                    </h3>
                                    <p class="text-xs text-gray-500 mt-0.5">This action cannot be undone</p>
                                </div>
                                <button
                                    @click="showDeleteModal = false"
                                    :disabled="isDeleting"
                                    class="text-gray-400 hover:text-gray-600 transition disabled:opacity-50"
                                >
                                    <i class="fa-solid fa-times text-xl"></i>
                                </button>
                            </div>
                        </div>

                        <div class="p-6">
                            <div
                                v-if="$page.props.flash?.type === 'error'"
                                class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"
                            >
                                {{ $page.props.flash.message }}
                            </div>
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0 w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                                    <i class="fa-solid fa-exclamation-triangle text-red-600 text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-700">
                                        Are you sure you want to delete <strong class="text-gray-900">{{ selectedExamSubject?.subject_name }}</strong> from <strong>{{ selectedExamSubject?.exam_name }}</strong>?
                                    </p>
                                    <div class="mt-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                        <p class="text-xs font-medium text-gray-700 mb-2">Assignment Details:</p>
                                        <div class="space-y-1 text-xs text-gray-600">
                                            <div class="flex justify-between">
                                                <span>Exam:</span>
                                                <span class="font-medium">{{ selectedExamSubject?.exam_name }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Subject:</span>
                                                <span class="font-medium">{{ selectedExamSubject?.subject_name }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Class:</span>
                                                <span class="font-medium">{{ selectedExamSubject?.class_name }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Date:</span>
                                                <span class="font-medium">{{ selectedExamSubject?.formatted_date }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Time:</span>
                                                <span class="font-medium">{{ selectedExamSubject?.time_range }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Marks:</span>
                                                <span class="font-medium">{{ selectedExamSubject?.full_marks }} (Pass: {{ selectedExamSubject?.pass_marks }})</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                                        <p class="text-xs text-yellow-800 flex items-center">
                                            <i class="fa-solid fa-circle-info mr-2"></i>
                                            <span>This will permanently remove this subject from the exam.</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-end space-x-3">
                            <button
                                @click="showDeleteModal = false"
                                :disabled="isDeleting"
                                class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium"
                            >
                                Cancel
                            </button>
                            <button
                                @click="deleteExamSubject"
                                :disabled="isDeleting"
                                class="px-6 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <i :class="isDeleting ? 'fa-solid fa-spinner fa-spin mr-2' : 'fa-solid fa-trash mr-2'"></i>
                                {{ isDeleting ? 'Deleting...' : 'Delete' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </InstituteLayout>
</template>

<style scoped>
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

/* Card hover effects */
.hover\:shadow-lg:hover {
    transition: box-shadow 0.3s ease;
}

.group:hover .group-hover\:text-blue-600 {
    transition: color 0.3s ease;
}

/* Button transitions */
button {
    transition: all 0.15s ease;
}

/* Stats card hover */
.hover\:shadow-md:hover {
    transition: box-shadow 0.2s ease;
}

/* Select focus */
select:focus {
    outline: none;
    ring: 2px solid #3b82f6;
}

/* Input focus */
input:focus {
    outline: none;
    ring: 2px solid #3b82f6;
}

</style>