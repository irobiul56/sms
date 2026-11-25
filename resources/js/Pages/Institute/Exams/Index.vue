<!-- resources/js/Pages/Exams/Index.vue -->
<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import InstituteLayout from '@/Layouts/InstituteLayout.vue';
import { router } from '@inertiajs/vue3';

// Props
const props = defineProps({
    exams: {
        type: Array,
        default: () => []
    },
    academicYears: {
        type: Array,
        default: () => []
    },
    classes: {
        type: Array,
        default: () => []
    },
    typeOptions: {
        type: Array,
        default: () => []
    },
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            upcoming: 0,
            ongoing: 0,
            completed: 0,
            published: 0
        })
    }
});

// State
const searchQuery = ref('');
const selectedType = ref('');
const selectedStatus = ref('');
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const showDetailsModal = ref(false);
const selectedExam = ref(null);
const isSubmitting = ref(false);

// Create Form
const createForm = useForm({
    academic_year_id: '',
    class_ids: [],
    name: '',
    bn_name: '',
    type: '',
    start_date: '',
    end_date: '',
    note: '',
    is_published: false
});

// Edit Form
const editForm = useForm({
    academic_year_id: '',
    class_ids: [],
    name: '',
    bn_name: '',
    type: '',
    start_date: '',
    end_date: '',
    note: '',
    is_published: false
});

// Computed
const filteredExams = computed(() => {
    let filtered = props.exams;
    
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(exam => {
            return exam.name?.toLowerCase().includes(query) ||
                   exam.bn_name?.toLowerCase().includes(query) ||
                   exam.class_name?.toLowerCase().includes(query);
        });
    }
    
    if (selectedType.value) {
        filtered = filtered.filter(exam => exam.type === selectedType.value);
    }
    
    if (selectedStatus.value) {
        filtered = filtered.filter(exam => exam.status_label === selectedStatus.value);
    }
    
    return filtered;
});

// Stats cards
const displayStats = computed(() => [
    {
        label: 'Total Exams',
        value: props.stats.total,
        icon: 'fa-solid fa-clipboard-list',
        color: 'blue',
        bgColor: 'bg-blue-100',
        textColor: 'text-blue-600'
    },
    {
        label: 'Upcoming',
        value: props.stats.upcoming,
        icon: 'fa-solid fa-clock',
        color: 'indigo',
        bgColor: 'bg-indigo-100',
        textColor: 'text-indigo-600'
    },
    {
        label: 'Ongoing',
        value: props.stats.ongoing,
        icon: 'fa-solid fa-play',
        color: 'yellow',
        bgColor: 'bg-yellow-100',
        textColor: 'text-yellow-600'
    },
    {
        label: 'Published',
        value: props.stats.published,
        icon: 'fa-solid fa-check-circle',
        color: 'green',
        bgColor: 'bg-green-100',
        textColor: 'text-green-600'
    }
]);

// Methods
const getClassGroupLabel = (group) => {
    const labels = {
        science: 'Science (বিজ্ঞান)',
        commerce: 'Commerce (বাণিজ্য)',
        arts: 'Arts (মানবিক)',
        general: 'General (সাধারণ)',
    };
    return labels[group] || '';
};

const openCreateModal = () => {
    createForm.reset();
    createForm.clearErrors();
    createForm.class_ids = [];
    const currentYear = props.academicYears.find(y => y.is_current);
    if (currentYear) {
        createForm.academic_year_id = currentYear.id;
    }
    showCreateModal.value = true;
};

const openEditModal = (exam) => {
    selectedExam.value = exam;
    editForm.academic_year_id = exam.academic_year.id;
    editForm.class_ids = exam.class_ids || [exam.institute_class_id].filter(Boolean);
    editForm.name = exam.name;
    editForm.bn_name = exam.bn_name || '';
    editForm.type = exam.type;
    editForm.start_date = exam.start_date;
    editForm.end_date = exam.end_date;
    editForm.note = exam.note || '';
    editForm.is_published = exam.is_published;
    editForm.clearErrors();
    showEditModal.value = true;
};

const openDeleteModal = (exam) => {
    selectedExam.value = exam;
    showDeleteModal.value = true;
};

const openDetailsModal = (exam) => {
    selectedExam.value = exam;
    showDetailsModal.value = true;
};

const createExam = () => {
    isSubmitting.value = true;
    createForm.post(route('exams.store'), {
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

const updateExam = () => {
    isSubmitting.value = true;
    editForm.put(route('exams.update', selectedExam.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            isSubmitting.value = false;
            selectedExam.value = null;
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
};

const deleteExam = () => {
    router.delete(route('exams.destroy', selectedExam.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            selectedExam.value = null;
        }
    });
};

const togglePublish = (exam) => {
    router.post(route('exams.toggle-publish', exam.id));
};

const resetFilters = () => {
    searchQuery.value = '';
    selectedType.value = '';
    selectedStatus.value = '';
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
};

const getStatusIcon = (status) => {
    const icons = {
        'Upcoming': 'fa-regular fa-clock',
        'Ongoing': 'fa-solid fa-spinner fa-spin',
        'Completed': 'fa-regular fa-circle-check',
        'Published': 'fa-regular fa-eye'
    };
    return icons[status] || 'fa-regular fa-circle';
};

const getStatusColor = (status) => {
    const colors = {
        'Upcoming': 'text-blue-600',
        'Ongoing': 'text-yellow-600',
        'Completed': 'text-gray-600',
        'Published': 'text-green-600'
    };
    return colors[status] || 'text-gray-600';
};

const getTypeIcon = (type) => {
    const icons = {
        'monthly': 'fa-regular fa-calendar',
        'term': 'fa-regular fa-calendar-alt',
        'half-yearly': 'fa-regular fa-calendar-check',
        'final': 'fa-solid fa-award',
        'test': 'fa-regular fa-pen-to-square',
        'pre-test': 'fa-regular fa-pen-to-square'
    };
    return icons[type] || 'fa-regular fa-file-lines';
};
</script>

<template>
    <Head title="Exam Management" />
    
    <InstituteLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-semibold text-gray-800">
                        <i class="fa-solid fa-clipboard-list text-blue-600 mr-2"></i>
                        Exam Management
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Manage all examinations for your institute
                    </p>
                </div>
                <button @click="openCreateModal"
                    class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition"
                >
                    <i class="fa-solid fa-plus mr-2"></i>
                    Create  Exam
                </button>
            </div>
        </template>

        <div class="space-y-6 w-full p-5">
        <!-- Search & Filter -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="flex flex-col lg:flex-row gap-4">
                <div class="flex-1 relative">
                    <i class="fa-solid fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input
                        type="text"
                        v-model="searchQuery"
                        placeholder="Search exams by name, Bangla name, or class..."
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
                        v-model="selectedType"
                        class="px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm min-w-[150px] bg-white"
                    >
                        <option value="">All Types</option>
                        <option 
                            v-for="type in typeOptions" 
                            :key="type.value" 
                            :value="type.value"
                        >
                            {{ type.label }}
                        </option>
                    </select>

                    <select
                        v-model="selectedStatus"
                        class="px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm min-w-[150px] bg-white"
                    >
                        <option value="">All Status</option>
                        <option value="Upcoming">Upcoming</option>
                        <option value="Ongoing">Ongoing</option>
                        <option value="Completed">Completed</option>
                        <option value="Published">Published</option>
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
                        @click="openCreateModal"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700"
                    >
                        <i class="fa-solid fa-plus"></i>
                        Create Exam
                    </button>
                </div>
            </div>

            <div class="mt-3 flex flex-wrap items-center justify-between text-xs text-gray-500 border-t border-gray-100 pt-3">
                <span>
                    Showing <span class="font-medium text-gray-700">{{ filteredExams.length }}</span> of 
                    <span class="font-medium text-gray-700">{{ props.exams.length }}</span> exams
                </span>
                <span>
                    <i class="fa-regular fa-clock mr-1"></i>
                    Updated: {{ formatDate(new Date()) }}
                </span>
            </div>

            
        </div>

        <!-- Exam Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Empty State -->
            <div v-if="filteredExams.length === 0" class="col-span-full">
                <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                    <div class="flex flex-col items-center justify-center">
                        <i class="fa-solid fa-clipboard-list text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 font-medium text-lg">No exams found</p>
                        <p class="text-sm text-gray-400 mt-1">
                            {{ searchQuery || selectedType || selectedStatus ? 'Try adjusting your filters' : 'Create your first exam' }}
                        </p>
                        <button
                            v-if="!searchQuery && !selectedType && !selectedStatus"
                            @click="openCreateModal"
                            class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition"
                        >
                            <i class="fa-solid fa-plus mr-2"></i>
                            Create Your First Exam
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

            <!-- Exam Card -->
            <div 
                v-for="exam in filteredExams" 
                :key="exam.id"
                class="bg-white rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden group border border-gray-100"
            >
                <!-- Card Header -->
                <div class="p-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <span 
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="exam.type_badge_class"
                                >
                                    <i :class="[getTypeIcon(exam.type), 'mr-1 text-xs']"></i>
                                    {{ exam.type_label }}
                                </span>
                                <span 
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="exam.status_badge_class"
                                >
                                    <i :class="[getStatusIcon(exam.status_label), 'mr-1 text-xs', getStatusColor(exam.status_label)]"></i>
                                    {{ exam.status_label }}
                                </span>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 group-hover:text-blue-600 transition-colors">
                                {{ exam.name }}
                            </h3>
                            <p class="text-sm text-gray-500">{{ exam.bn_name || '' }}</p>
                        </div>
                        <button
                            @click="togglePublish(exam)"
                            class="p-2 rounded-lg hover:bg-gray-100 transition"
                            :title="exam.is_published ? 'Unpublish' : 'Publish'"
                        >
                            <i 
                                :class="[
                                    exam.is_published ? 'fa-solid fa-eye text-green-600' : 'fa-solid fa-eye-slash text-gray-400',
                                    'text-lg'
                                ]"
                            ></i>
                        </button>
                    </div>
                </div>

                <!-- Card Body -->
                <div class="p-5 space-y-4">
                    <!-- Class & Academic Year -->
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-school text-gray-400"></i>
                            <span class="text-gray-600">{{ exam.class_name }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <i class="fa-regular fa-calendar text-gray-400"></i>
                            <span class="text-gray-600">{{ exam.academic_year.session }}</span>
                        </div>
                    </div>

                    <!-- Date Range -->
                    <div class="flex items-center justify-between text-sm bg-gray-50 rounded-lg p-3">
                        <div class="flex items-center gap-2">
                            <i class="fa-regular fa-calendar-check text-blue-500"></i>
                            <span class="text-gray-700 font-medium">{{ formatDate(exam.start_date) }}</span>
                        </div>
                        <span class="text-gray-400">→</span>
                        <div class="flex items-center gap-2">
                            <i class="fa-regular fa-calendar-check text-red-500"></i>
                            <span class="text-gray-700 font-medium">{{ formatDate(exam.end_date) }}</span>
                        </div>
                    </div>

                    <!-- Note -->
                    <div v-if="exam.note" class="text-sm text-gray-500 bg-yellow-50 rounded-lg p-2.5 border border-yellow-100">
                        <i class="fa-solid fa-circle-info text-yellow-600 mr-1.5"></i>
                        {{ exam.note }}
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-2 pt-2">
                        <div class="text-center p-2 bg-blue-50 rounded-lg">
                            <p class="text-xs text-gray-500">Subjects</p>
                            <p class="text-lg font-bold text-blue-600">0</p>
                        </div>
                        <div class="text-center p-2 bg-green-50 rounded-lg">
                            <p class="text-xs text-gray-500">Students</p>
                            <p class="text-lg font-bold text-green-600">0</p>
                        </div>
                        <div class="text-center p-2 bg-purple-50 rounded-lg">
                            <p class="text-xs text-gray-500">Results</p>
                            <p class="text-lg font-bold text-purple-600">0</p>
                        </div>
                    </div>
                </div>

                <!-- Card Footer -->
                <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <button
                            @click="openDetailsModal(exam)"
                            class="text-blue-600 hover:text-blue-800 text-sm font-medium transition"
                        >
                            <i class="fa-regular fa-eye mr-1"></i>
                            Details
                        </button>
                    </div>
                    <div class="flex items-center gap-1">
                        <button
                            @click="openEditModal(exam)"
                            class="p-1.5 text-green-600 hover:text-green-900 hover:bg-green-50 rounded-lg transition"
                            title="Edit"
                        >
                            <i class="fa-solid fa-edit"></i>
                        </button>
                        <button
                            @click="openDeleteModal(exam)"
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
                                    Create New Exam
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">Create a new examination</p>
                            </div>
                            <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600 transition">
                                <i class="fa-solid fa-times text-xl"></i>
                            </button>
                        </div>
                    </div>

                    <form @submit.prevent="createExam" class="p-6">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Academic Year <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="createForm.academic_year_id"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                        :class="{ 'border-red-500': createForm.errors.academic_year_id }"
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
                                    <p v-if="createForm.errors.academic_year_id" class="mt-1 text-sm text-red-600">
                                        {{ createForm.errors.academic_year_id }}
                                    </p>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Classes <span class="text-red-500">*</span>
                                    </label>
                                    <div class="grid max-h-40 grid-cols-2 gap-2 overflow-y-auto rounded-lg border border-gray-300 p-3 sm:grid-cols-3">
                                        <label v-for="cls in classes" :key="cls.id" class="flex items-center gap-2 text-sm text-gray-700">
                                            <input v-model="createForm.class_ids" type="checkbox" :value="cls.id" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span>
                                                {{ cls.name }}
                                                <span v-if="cls.group" class="block text-xs font-medium text-purple-700">
                                                    {{ getClassGroupLabel(cls.group) }}
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                    <p v-if="createForm.errors.class_ids || createForm.errors['class_ids.0']" class="mt-1 text-sm text-red-600">
                                        {{ createForm.errors.class_ids || createForm.errors['class_ids.0'] }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Exam Name <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        v-model="createForm.name"
                                        placeholder="e.g., Half Yearly Examination"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                        :class="{ 'border-red-500': createForm.errors.name }"
                                    />
                                    <p v-if="createForm.errors.name" class="mt-1 text-sm text-red-600">
                                        {{ createForm.errors.name }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Exam Name (Bangla)
                                    </label>
                                    <input
                                        type="text"
                                        v-model="createForm.bn_name"
                                        placeholder="e.g., অর্ধ-বার্ষিক পরীক্ষা"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Exam Type <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="createForm.type"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                        :class="{ 'border-red-500': createForm.errors.type }"
                                    >
                                        <option value="">Select Type</option>
                                        <option 
                                            v-for="type in typeOptions" 
                                            :key="type.value" 
                                            :value="type.value"
                                        >
                                            {{ type.label }}
                                        </option>
                                    </select>
                                    <p v-if="createForm.errors.type" class="mt-1 text-sm text-red-600">
                                        {{ createForm.errors.type }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Publish Status
                                    </label>
                                    <div class="flex items-center pt-2">
                                        <input
                                            type="checkbox"
                                            id="create_is_published"
                                            v-model="createForm.is_published"
                                            class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                        />
                                        <label for="create_is_published" class="ml-2 text-sm text-gray-700 cursor-pointer">
                                            Publish immediately
                                        </label>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Start Date <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="date"
                                        v-model="createForm.start_date"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                        :class="{ 'border-red-500': createForm.errors.start_date }"
                                    />
                                    <p v-if="createForm.errors.start_date" class="mt-1 text-sm text-red-600">
                                        {{ createForm.errors.start_date }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        End Date <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="date"
                                        v-model="createForm.end_date"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                        :class="{ 'border-red-500': createForm.errors.end_date }"
                                    />
                                    <p v-if="createForm.errors.end_date" class="mt-1 text-sm text-red-600">
                                        {{ createForm.errors.end_date }}
                                    </p>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Note / Instructions
                                    </label>
                                    <textarea
                                        v-model="createForm.note"
                                        rows="2"
                                        placeholder="Enter any special instructions for this exam"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                        :class="{ 'border-red-500': createForm.errors.note }"
                                    ></textarea>
                                    <p v-if="createForm.errors.note" class="mt-1 text-sm text-red-600">
                                        {{ createForm.errors.note }}
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
                                {{ isSubmitting ? 'Creating...' : 'Create Exam' }}
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
                                    Edit Exam
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">Update exam information</p>
                            </div>
                            <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-600 transition">
                                <i class="fa-solid fa-times text-xl"></i>
                            </button>
                        </div>
                    </div>

                    <form @submit.prevent="updateExam" class="p-6">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Academic Year <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="editForm.academic_year_id"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                        :class="{ 'border-red-500': editForm.errors.academic_year_id }"
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
                                    <p v-if="editForm.errors.academic_year_id" class="mt-1 text-sm text-red-600">
                                        {{ editForm.errors.academic_year_id }}
                                    </p>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Classes <span class="text-red-500">*</span>
                                    </label>
                                    <div class="grid max-h-40 grid-cols-2 gap-2 overflow-y-auto rounded-lg border border-gray-300 p-3 sm:grid-cols-3">
                                        <label v-for="cls in classes" :key="cls.id" class="flex items-center gap-2 text-sm text-gray-700">
                                            <input v-model="editForm.class_ids" type="checkbox" :value="cls.id" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span>
                                                {{ cls.name }}
                                                <span v-if="cls.group" class="block text-xs font-medium text-purple-700">
                                                    {{ getClassGroupLabel(cls.group) }}
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                    <p v-if="editForm.errors.class_ids || editForm.errors['class_ids.0']" class="mt-1 text-sm text-red-600">
                                        {{ editForm.errors.class_ids || editForm.errors['class_ids.0'] }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Exam Name <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        v-model="editForm.name"
                                        placeholder="e.g., Half Yearly Examination"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                        :class="{ 'border-red-500': editForm.errors.name }"
                                    />
                                    <p v-if="editForm.errors.name" class="mt-1 text-sm text-red-600">
                                        {{ editForm.errors.name }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Exam Name (Bangla)
                                    </label>
                                    <input
                                        type="text"
                                        v-model="editForm.bn_name"
                                        placeholder="e.g., অর্ধ-বার্ষিক পরীক্ষা"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Exam Type <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="editForm.type"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                        :class="{ 'border-red-500': editForm.errors.type }"
                                    >
                                        <option value="">Select Type</option>
                                        <option 
                                            v-for="type in typeOptions" 
                                            :key="type.value" 
                                            :value="type.value"
                                        >
                                            {{ type.label }}
                                        </option>
                                    </select>
                                    <p v-if="editForm.errors.type" class="mt-1 text-sm text-red-600">
                                        {{ editForm.errors.type }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Publish Status
                                    </label>
                                    <div class="flex items-center pt-2">
                                        <input
                                            type="checkbox"
                                            id="edit_is_published"
                                            v-model="editForm.is_published"
                                            class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                        />
                                        <label for="edit_is_published" class="ml-2 text-sm text-gray-700 cursor-pointer">
                                            Published
                                        </label>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Start Date <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="date"
                                        v-model="editForm.start_date"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                        :class="{ 'border-red-500': editForm.errors.start_date }"
                                    />
                                    <p v-if="editForm.errors.start_date" class="mt-1 text-sm text-red-600">
                                        {{ editForm.errors.start_date }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        End Date <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="date"
                                        v-model="editForm.end_date"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                        :class="{ 'border-red-500': editForm.errors.end_date }"
                                    />
                                    <p v-if="editForm.errors.end_date" class="mt-1 text-sm text-red-600">
                                        {{ editForm.errors.end_date }}
                                    </p>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Note / Instructions
                                    </label>
                                    <textarea
                                        v-model="editForm.note"
                                        rows="2"
                                        placeholder="Enter any special instructions for this exam"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                        :class="{ 'border-red-500': editForm.errors.note }"
                                    ></textarea>
                                    <p v-if="editForm.errors.note" class="mt-1 text-sm text-red-600">
                                        {{ editForm.errors.note }}
                                    </p>
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
                                {{ isSubmitting ? 'Updating...' : 'Update Exam' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- DETAILS MODAL -->
        <!-- ============================================ -->
        <div v-if="showDetailsModal && selectedExam" class="fixed inset-0 z-50 overflow-y-auto">
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
                                    Exam Details
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">Complete information about the exam</p>
                            </div>
                            <button @click="showDetailsModal = false" class="text-gray-400 hover:text-gray-600 transition">
                                <i class="fa-solid fa-times text-xl"></i>
                            </button>
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Exam Name</p>
                                <p class="text-sm font-medium text-gray-900">{{ selectedExam.name }}</p>
                                <p v-if="selectedExam.bn_name" class="text-xs text-gray-500">{{ selectedExam.bn_name }}</p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Type</p>
                                <p class="text-sm font-medium">
                                    <span 
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                        :class="selectedExam.type_badge_class"
                                    >
                                        {{ selectedExam.type_label }}
                                    </span>
                                </p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Class</p>
                                <p class="text-sm font-medium text-gray-900">{{ selectedExam.class_name }}</p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Academic Year</p>
                                <p class="text-sm font-medium text-gray-900">{{ selectedExam.academic_year.session }}</p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Start Date</p>
                                <p class="text-sm font-medium text-gray-900">{{ formatDate(selectedExam.start_date) }}</p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">End Date</p>
                                <p class="text-sm font-medium text-gray-900">{{ formatDate(selectedExam.end_date) }}</p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Status</p>
                                <p class="text-sm font-medium">
                                    <span 
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                        :class="selectedExam.status_badge_class"
                                    >
                                        <i :class="[getStatusIcon(selectedExam.status_label), 'mr-1 text-xs', getStatusColor(selectedExam.status_label)]"></i>
                                        {{ selectedExam.status_label }}
                                    </span>
                                </p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Published</p>
                                <p class="text-sm font-medium">
                                    <span 
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                        :class="selectedExam.is_published ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                                    >
                                        {{ selectedExam.is_published ? 'Yes' : 'No' }}
                                    </span>
                                </p>
                            </div>
                            <div class="col-span-2 p-3 bg-gray-50 rounded-lg" v-if="selectedExam.note">
                                <p class="text-xs text-gray-500">Note / Instructions</p>
                                <p class="text-sm font-medium text-gray-900">{{ selectedExam.note }}</p>
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
                            @click="showDetailsModal = false; openEditModal(selectedExam)"
                            class="px-6 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium"
                        >
                            <i class="fa-solid fa-edit mr-2"></i>
                            Edit Exam
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
                                    Delete Exam
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
                                    Are you sure you want to delete <strong class="text-gray-900">{{ selectedExam?.name }}</strong>?
                                </p>
                                <div class="mt-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <p class="text-xs font-medium text-gray-700 mb-2">Exam Details:</p>
                                    <div class="space-y-1 text-xs text-gray-600">
                                        <div class="flex justify-between">
                                            <span>Name:</span>
                                            <span class="font-medium">{{ selectedExam?.name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Type:</span>
                                            <span class="font-medium">{{ selectedExam?.type_label }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Class:</span>
                                            <span class="font-medium">{{ selectedExam?.class_name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Status:</span>
                                            <span class="font-medium">{{ selectedExam?.status_label }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                                    <p class="text-xs text-yellow-800 flex items-center">
                                        <i class="fa-solid fa-circle-info mr-2"></i>
                                        <span>This will permanently delete the exam and all associated data.</span>
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
                            @click="deleteExam"
                            class="px-6 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium"
                        >
                            <i class="fa-solid fa-trash mr-2"></i>
                            Delete Exam
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

.hover\:shadow-lg:hover {
    transition: box-shadow 0.3s ease;
}

.group:hover .group-hover\:text-blue-600 {
    transition: color 0.3s ease;
}

button {
    transition: all 0.15s ease;
}
</style>