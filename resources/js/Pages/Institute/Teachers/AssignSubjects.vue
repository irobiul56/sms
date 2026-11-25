<!-- resources/js/Pages/TeacherSubjects/Index.vue -->
<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import InstituteLayout from '@/Layouts/InstituteLayout.vue';
import { router } from '@inertiajs/vue3';

// Props
const props = defineProps({
    teachers: {
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
    sections: {
        type: Array,
        default: () => []
    },
    academicYears: {
        type: Array,
        default: () => []
    },
    subjectPermissionOptions: {
        type: Object,
        default: () => ({})
    },
    stats: {
        type: Object,
        default: () => ({
            total_teachers: 0,
            total_assignments: 0,
            total_primary: 0,
            total_active: 0
        })
    }
});

// State
const searchQuery = ref('');
const selectedTeacher = ref(null);
const showAssignModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const showTeacherDetailModal = ref(false);
const selectedAssignment = ref(null);
const isSubmitting = ref(false);
const expandedTeacher = ref(null);

// Forms
const assignForm = useForm({
    teacher_id: '',
    subject_id: '',
    class_id: '',
    section_id: '',
    academic_year_id: '',
    is_primary: false,
    is_active: true,
    permissions: [],
    display_order: 0,
    note: ''
});

const editForm = useForm({
    is_primary: false,
    is_active: true,
    permissions: [],
    display_order: 0,
    note: ''
});

// Computed
const filteredTeachers = computed(() => {
    if (!searchQuery.value) return props.teachers;
    const query = searchQuery.value.toLowerCase();
    return props.teachers.filter(teacher => {
        return teacher.name?.toLowerCase().includes(query) ||
               teacher.bn_name?.toLowerCase().includes(query) ||
               teacher.employee_id?.toLowerCase().includes(query) ||
               teacher.designation?.toLowerCase().includes(query);
    });
});

const filteredSections = computed(() => {
    if (!assignForm.class_id) {
        return props.sections;
    }
    return props.sections.filter(s => s.institute_class_id == assignForm.class_id);
});
const filteredSubjects = computed(() => props.subjects.filter(subject =>
    (subject.class_ids || []).some(classId => String(classId) === String(assignForm.class_id))
));

// Stats cards
const displayStats = computed(() => [
    {
        label: 'Total Teachers',
        value: props.stats.total_teachers,
        icon: 'fa-solid fa-chalkboard-user',
        color: 'blue'
    },
    {
        label: 'Total Assignments',
        value: props.stats.total_assignments,
        icon: 'fa-solid fa-tasks',
        color: 'green'
    },
    {
        label: 'Primary Subjects',
        value: props.stats.total_primary,
        icon: 'fa-solid fa-star',
        color: 'yellow'
    },
    {
        label: 'Active Assignments',
        value: props.stats.total_active,
        icon: 'fa-solid fa-check-circle',
        color: 'purple'
    }
]);

// Watch for class change to reset section
watch(() => assignForm.class_id, () => {
    assignForm.section_id = '';
    assignForm.subject_id = '';
});

// Methods
const openAssignModal = (teacher = null) => {
    assignForm.reset();
    assignForm.clearErrors();
    if (teacher) {
        assignForm.teacher_id = teacher.id;
        selectedTeacher.value = teacher;
    }
    const currentYear = props.academicYears.find(y => y.is_current);
    if (currentYear) {
        assignForm.academic_year_id = currentYear.id;
    }
    showAssignModal.value = true;
};

const openEditModal = (assignment) => {
    selectedAssignment.value = assignment;
    editForm.is_primary = assignment.is_primary;
    editForm.is_active = assignment.is_active;
    editForm.permissions = assignment.permissions || [];
    editForm.display_order = assignment.display_order || 0;
    editForm.note = assignment.note || '';
    editForm.clearErrors();
    showEditModal.value = true;
};

const openDeleteModal = (assignment) => {
    selectedAssignment.value = assignment;
    showDeleteModal.value = true;
};

const openTeacherDetail = (teacher) => {
    selectedTeacher.value = teacher;
    showTeacherDetailModal.value = true;
};

const toggleExpand = (teacherId) => {
    if (expandedTeacher.value === teacherId) {
        expandedTeacher.value = null;
    } else {
        expandedTeacher.value = teacherId;
    }
};

const assignSubject = () => {
    isSubmitting.value = true;
    assignForm.post(route('teacher-subjects.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showAssignModal.value = false;
            isSubmitting.value = false;
            assignForm.reset();
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
};

const updateAssignment = () => {
    isSubmitting.value = true;
    editForm.put(route('teacher-subjects.update', selectedAssignment.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            isSubmitting.value = false;
            selectedAssignment.value = null;
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
};

const deleteAssignment = () => {
    router.delete(route('teacher-subjects.destroy', selectedAssignment.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            selectedAssignment.value = null;
        }
    });
};

const toggleStatus = (assignment) => {
    router.post(route('teacher-subjects.toggle-status', assignment.id));
};

const resetFilters = () => {
    searchQuery.value = '';
};

const getSubjectName = (subjectId) => {
    const subject = props.subjects.find(s => s.id === subjectId);
    return subject ? subject.name : 'N/A';
};

const getClassName = (classId) => {
    const cls = props.classes.find(c => c.id === classId);
    return cls ? cls.name : 'N/A';
};

const getSectionName = (sectionId) => {
    if (!sectionId) return 'All';
    const section = props.sections.find(s => s.id === sectionId);
    return section ? section.name : 'N/A';
};

const getAcademicYearSession = (yearId) => {
    const year = props.academicYears.find(y => y.id === yearId);
    return year ? year.session : 'N/A';
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
    <Head title="Teacher Subjects Assignment" />
    
    <InstituteLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-semibold text-gray-800">
                        <i class="fa-solid fa-user-tie text-blue-600 mr-2"></i>
                        Teacher Subjects Assignment
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Manage subjects assigned to teachers
                    </p>
                </div>
                <button
                    @click="openAssignModal()"
                    class="inline-flex items-center px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition shadow-sm hover:shadow-md"
                >
                    <i class="fa-solid fa-plus mr-2"></i>
                    Assign Subject
                </button>
            </div>
        </template>

        <div class="space-y-6 w-full p-5">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div 
                    v-for="stat in displayStats" 
                    :key="stat.label"
                    class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow border-l-4"
                    :class="{
                        'border-l-blue-500': stat.color === 'blue',
                        'border-l-green-500': stat.color === 'green',
                        'border-l-yellow-500': stat.color === 'yellow',
                        'border-l-purple-500': stat.color === 'purple'
                    }"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ stat.label }}</p>
                            <p class="text-2xl font-bold text-gray-800 mt-1">{{ stat.value }}</p>
                        </div>
                        <div class="w-12 h-12 rounded-lg flex items-center justify-center"
                             :class="{
                                 'bg-blue-100 text-blue-600': stat.color === 'blue',
                                 'bg-green-100 text-green-600': stat.color === 'green',
                                 'bg-yellow-100 text-yellow-600': stat.color === 'yellow',
                                 'bg-purple-100 text-purple-600': stat.color === 'purple'
                             }"
                        >
                            <i :class="[stat.icon, 'text-2xl']"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search & Filter -->
            <div class="bg-white rounded-xl shadow-sm p-4">
                <div class="flex flex-col lg:flex-row gap-4">
                    <div class="flex-1 relative">
                        <i class="fa-solid fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input
                            type="text"
                            v-model="searchQuery"
                            placeholder="Search teachers by name, employee ID, or designation..."
                            class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                        />
                        <div v-if="searchQuery" class="absolute right-3 top-1/2 transform -translate-y-1/2">
                            <button @click="searchQuery = ''" class="text-gray-400 hover:text-gray-600">
                                <i class="fa-solid fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button
                        @click="resetFilters"
                        class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition whitespace-nowrap"
                    >
                        <i class="fa-solid fa-undo mr-1"></i>
                        Reset
                    </button>
                        <button
                        @click="openAssignModal()"
                        class="inline-flex items-center px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition shadow-sm hover:shadow-md"
                    >
                        <i class="fa-solid fa-plus mr-2"></i>
                        Assign Subject
                    </button>
                </div>

                <div class="mt-3 flex flex-wrap items-center justify-between text-xs text-gray-500 border-t border-gray-100 pt-3">
                    <span>
                        Showing <span class="font-medium text-gray-700">{{ filteredTeachers.length }}</span> of 
                        <span class="font-medium text-gray-700">{{ props.teachers.length }}</span> teachers
                    </span>
                    <span>
                        <i class="fa-regular fa-clock mr-1"></i>
                        Updated: {{ formatDate(new Date()) }}
                    </span>
                </div>
            </div>

            <!-- Teachers Grid -->
            <div class="grid grid-cols-1 gap-4">
                <div 
                    v-for="teacher in filteredTeachers" 
                    :key="teacher.id"
                    class="bg-white rounded-xl shadow-sm overflow-hidden hover:shadow-md transition"
                >
                    <!-- Teacher Header -->
                    <div class="p-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="h-12 w-12 rounded-full overflow-hidden bg-gray-200">
                                        <img 
                                            v-if="teacher.photo" 
                                            :src="teacher.photo" 
                                            alt="Teacher Photo"
                                            class="h-full w-full object-cover"
                                        />
                                        <div v-else class="h-full w-full flex items-center justify-center text-gray-500 text-xl font-bold">
                                            {{ teacher.name?.charAt(0) || 'T' }}
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">{{ teacher.name }}</h3>
                                    <p class="text-sm text-gray-500">{{ teacher.designation }}</p>
                                    <div class="flex flex-wrap items-center gap-2 mt-1">
                                        <span class="text-xs text-gray-500">ID: {{ teacher.employee_id }}</span>
                                        <span 
                                            class="px-2 py-0.5 text-xs font-medium rounded-full"
                                            :class="teacher.status_badge_class"
                                        >
                                            {{ teacher.status_label }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="flex items-center space-x-3 text-sm text-gray-500">
                                    <span>
                                        <i class="fa-solid fa-book mr-1"></i>
                                        {{ teacher.total_assignments }} subjects
                                    </span>
                                    <span class="text-yellow-600">
                                        <i class="fa-solid fa-star mr-1"></i>
                                        {{ teacher.primary_count }} primary
                                    </span>
                                    <span class="text-green-600">
                                        <i class="fa-solid fa-check-circle mr-1"></i>
                                        {{ teacher.active_count }} active
                                    </span>
                                </div>
                                <button
                                    @click="openAssignModal(teacher)"
                                    class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg transition"
                                >
                                    <i class="fa-solid fa-plus mr-1"></i>
                                    Assign
                                </button>
                                <button
                                    @click="toggleExpand(teacher.id)"
                                    class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs font-medium rounded-lg transition"
                                >
                                    <i :class="expandedTeacher === teacher.id ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down'"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Assigned Subjects List (Expandable) -->
                    <div v-if="expandedTeacher === teacher.id" class="p-4">
                        <div v-if="teacher.assignments && teacher.assignments.length > 0" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Section</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Academic Year</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr 
                                        v-for="(assignment, index) in teacher.assignments" 
                                        :key="assignment.id"
                                        class="hover:bg-gray-50 transition"
                                        :class="{ 'bg-yellow-50': assignment.is_primary }"
                                    >
                                        <td class="px-3 py-2 text-sm text-gray-500">{{ index + 1 }}</td>
                                        <td class="px-3 py-2">
                                            <div class="flex items-center">
                                                <div class="w-6 h-6 rounded-full bg-purple-100 flex items-center justify-center">
                                                    <i class="fa-solid fa-book text-purple-600 text-xs"></i>
                                                </div>
                                                <span class="ml-2 text-sm text-gray-900">{{ assignment.subject_name }}</span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-600">{{ assignment.class_name }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-600">{{ assignment.section_name }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-600">{{ assignment.academic_year_session }}</td>
                                        <td class="px-3 py-2">
                                            <span 
                                                class="px-2 py-0.5 text-xs font-semibold rounded-full"
                                                :class="assignment.is_primary ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'"
                                            >
                                                {{ assignment.is_primary ? 'Primary' : 'Secondary' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <span 
                                                class="px-2 py-0.5 text-xs font-semibold rounded-full cursor-pointer"
                                                :class="assignment.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                                                @click="toggleStatus(assignment)"
                                            >
                                                {{ assignment.is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex items-center space-x-1">
                                                <button
                                                    @click="openEditModal(assignment)"
                                                    class="p-1 text-green-600 hover:text-green-900 hover:bg-green-50 rounded-lg transition"
                                                    title="Edit"
                                                >
                                                    <i class="fa-solid fa-edit text-xs"></i>
                                                </button>
                                                <button
                                                    @click="openDeleteModal(assignment)"
                                                    class="p-1 text-red-600 hover:text-red-900 hover:bg-red-50 rounded-lg transition"
                                                    title="Delete"
                                                >
                                                    <i class="fa-solid fa-trash text-xs"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-center py-6 text-gray-500">
                            <i class="fa-solid fa-book text-3xl text-gray-300 mb-2 block"></i>
                            <p>No subjects assigned yet</p>
                            <button
                                @click="openAssignModal(teacher)"
                                class="mt-2 text-blue-600 hover:text-blue-800 font-medium text-sm"
                            >
                                Assign first subject
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- ASSIGN MODAL -->
        <!-- ============================================ -->
        <div v-if="showAssignModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" @click="showAssignModal = false">
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
                                    Assign Subject to Teacher
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    {{ selectedTeacher ? selectedTeacher.name : 'Select a teacher' }}
                                </p>
                            </div>
                            <button @click="showAssignModal = false" class="text-gray-400 hover:text-gray-600 transition">
                                <i class="fa-solid fa-times text-xl"></i>
                            </button>
                        </div>
                    </div>

                    <form @submit.prevent="assignSubject" class="p-6">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Teacher Selection -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Teacher <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="assignForm.teacher_id"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                        :class="{ 'border-red-500': assignForm.errors.teacher_id }"
                                    >
                                        <option value="">Select Teacher</option>
                                        <option 
                                            v-for="teacher in teachers" 
                                            :key="teacher.id" 
                                            :value="teacher.id"
                                        >
                                            {{ teacher.name }} ({{ teacher.employee_id }})
                                        </option>
                                    </select>
                                    <p v-if="assignForm.errors.teacher_id" class="mt-1 text-sm text-red-600">
                                        {{ assignForm.errors.teacher_id }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Academic Year <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="assignForm.academic_year_id"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                        :class="{ 'border-red-500': assignForm.errors.academic_year_id }"
                                    >
                                        <option value="">Select Academic Year</option>
                                        <option 
                                            v-for="year in academicYears" 
                                            :key="year.id" 
                                            :value="year.id"
                                        >
                                            {{ year.session }} {{ year.is_current ? '(Current)' : '' }}
                                        </option>
                                    </select>
                                    <p v-if="assignForm.errors.academic_year_id" class="mt-1 text-sm text-red-600">
                                        {{ assignForm.errors.academic_year_id }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Subject <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="assignForm.subject_id"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                        :class="{ 'border-red-500': assignForm.errors.subject_id }"
                                        :disabled="!assignForm.class_id"
                                    >
                                        <option value="">{{ assignForm.class_id ? 'Select Subject' : 'Select a class first' }}</option>
                                        <option 
                                            v-for="subject in filteredSubjects"
                                            :key="subject.id" 
                                            :value="subject.id"
                                        >
                                            {{ subject.name }} ({{ subject.code }})
                                        </option>
                                    </select>
                                    <p v-if="assignForm.class_id && !filteredSubjects.length" class="mt-1 text-xs text-gray-500">
                                        No subjects are assigned to this class.
                                    </p>
                                    <p v-if="assignForm.errors.subject_id" class="mt-1 text-sm text-red-600">
                                        {{ assignForm.errors.subject_id }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Class <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="assignForm.class_id"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                        :class="{ 'border-red-500': assignForm.errors.class_id }"
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
                                    <p v-if="assignForm.errors.class_id" class="mt-1 text-sm text-red-600">
                                        {{ assignForm.errors.class_id }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Section
                                    </label>
                                    <select
                                        v-model="assignForm.section_id"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                        :disabled="!assignForm.class_id"
                                    >
                                        <option value="">All Sections</option>
                                        <option 
                                            v-for="section in filteredSections" 
                                            :key="section.id" 
                                            :value="section.id"
                                        >
                                            {{ section.name }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Note
                                    </label>
                                    <textarea
                                        v-model="assignForm.note"
                                        rows="2"
                                        placeholder="Enter any note about this assignment"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    ></textarea>
                                </div>

                                <fieldset class="rounded-lg border border-blue-100 bg-blue-50 p-4">
                                    <legend class="px-1 text-sm font-semibold text-gray-800">Subject permissions</legend>
                                    <p class="mb-3 text-xs text-gray-600">These permissions apply only to this subject, class, section, and academic year.</p>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <label
                                            v-for="(label, permission) in subjectPermissionOptions"
                                            :key="permission"
                                            class="inline-flex items-center gap-2 text-sm text-gray-700"
                                        >
                                            <input
                                                type="checkbox"
                                                :value="permission"
                                                v-model="assignForm.permissions"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            />
                                            {{ label }}
                                        </label>
                                    </div>
                                </fieldset>
                            </div>

                            <div class="flex flex-wrap items-center gap-4">
                                <label class="flex items-center">
                                    <input
                                        type="checkbox"
                                        v-model="assignForm.is_primary"
                                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">
                                        <i class="fa-solid fa-star text-yellow-500 mr-1"></i>
                                        Primary Teacher
                                    </span>
                                </label>
                                <label class="flex items-center">
                                    <input
                                        type="checkbox"
                                        v-model="assignForm.is_active"
                                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">Active</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex items-center justify-end space-x-3 pt-6 mt-6 border-t border-gray-200">
                            <button
                                type="button"
                                @click="showAssignModal = false"
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
                                    Edit Assignment
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">Update assignment details</p>
                            </div>
                            <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-600 transition">
                                <i class="fa-solid fa-times text-xl"></i>
                            </button>
                        </div>
                    </div>

                    <form @submit.prevent="updateAssignment" class="p-6">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Note
                                </label>
                                <textarea
                                    v-model="editForm.note"
                                    rows="2"
                                    placeholder="Enter any note about this assignment"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                ></textarea>
                            </div>

                            <fieldset class="rounded-lg border border-blue-100 bg-blue-50 p-4">
                                <legend class="px-1 text-sm font-semibold text-gray-800">Subject permissions</legend>
                                <p class="mb-3 text-xs text-gray-600">These permissions apply only to this subject assignment.</p>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label
                                        v-for="(label, permission) in subjectPermissionOptions"
                                        :key="permission"
                                        class="inline-flex items-center gap-2 text-sm text-gray-700"
                                    >
                                        <input
                                            type="checkbox"
                                            :value="permission"
                                            v-model="editForm.permissions"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        />
                                        {{ label }}
                                    </label>
                                </div>
                            </fieldset>

                            <div class="flex flex-wrap items-center gap-4">
                                <label class="flex items-center">
                                    <input
                                        type="checkbox"
                                        v-model="editForm.is_primary"
                                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">
                                        <i class="fa-solid fa-star text-yellow-500 mr-1"></i>
                                        Primary Teacher
                                    </span>
                                </label>
                                <label class="flex items-center">
                                    <input
                                        type="checkbox"
                                        v-model="editForm.is_active"
                                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">Active</span>
                                </label>
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
                                {{ isSubmitting ? 'Updating...' : 'Update Assignment' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- DELETE MODAL -->
        <!-- ============================================ -->
        <div v-if="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" @click="showDeleteModal = false">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-xl shadow-2xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="px-6 py-4 bg-gradient-to-r from-red-50 to-rose-50 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">
                                    <i class="fa-solid fa-trash text-red-600 mr-2"></i>
                                    Remove Assignment
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">This action cannot be undone</p>
                            </div>
                            <button @click="showDeleteModal = false" class="text-gray-400 hover:text-gray-600 transition">
                                <i class="fa-solid fa-times text-xl"></i>
                            </button>
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0 w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                                <i class="fa-solid fa-exclamation-triangle text-red-600 text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-700">
                                    Are you sure you want to remove this subject assignment?
                                </p>
                                <div class="mt-3 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                                    <p class="text-xs text-yellow-800 flex items-center">
                                        <i class="fa-solid fa-circle-info mr-2"></i>
                                        <span>This will permanently remove this subject from the teacher's assigned subjects.</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-end space-x-3">
                        <button
                            @click="showDeleteModal = false"
                            class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium"
                        >
                            Cancel
                        </button>
                        <button
                            @click="deleteAssignment"
                            class="px-6 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium"
                        >
                            <i class="fa-solid fa-trash mr-2"></i>
                            Remove
                        </button>
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

.hover\:shadow-md:hover {
    transition: box-shadow 0.2s ease;
}

button {
    transition: all 0.15s ease;
}

.cursor-pointer {
    cursor: pointer;
}
</style>