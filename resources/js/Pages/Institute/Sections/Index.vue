<!-- resources/js/Pages/Sections/Index.vue -->
<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import InstituteLayout from '@/Layouts/InstituteLayout.vue';
import { router } from '@inertiajs/vue3';

// Props
const props = defineProps({
    sections: {
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
            total_students: 0,
            full: 0,
            almost_full: 0,
            available: 0
        })
    }
});

// State
const searchQuery = ref('');
const selectedClass = ref('');
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const showDetailsModal = ref(false);
const selectedSection = ref(null);
const isSubmitting = ref(false);

// Create Form
const createForm = useForm({
    institute_class_id: '',
    name: '',
    room_number: '',
    capacity: 50,
    display_order: 0
});

// Edit Form
const editForm = useForm({
    institute_class_id: '',
    name: '',
    room_number: '',
    capacity: 50,
    display_order: 0
});

// Computed
const filteredSections = computed(() => {
    let filtered = props.sections;
    
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(section => {
            return section.name?.toLowerCase().includes(query) ||
                   section.class_name?.toLowerCase().includes(query) ||
                   section.room_number?.toLowerCase().includes(query);
        });
    }
    
    if (selectedClass.value) {
        filtered = filtered.filter(section => {
            return section.institute_class_id == selectedClass.value;
        });
    }
    
    return filtered;
});

// Stats cards
const displayStats = computed(() => [
    {
        label: 'Total Sections',
        value: props.stats.total,
        icon: 'fa-solid fa-grip-lines',
        color: 'blue'
    },
    {
        label: 'Total Students',
        value: props.stats.total_students,
        icon: 'fa-solid fa-users',
        color: 'green'
    },
    {
        label: 'Full Sections',
        value: props.stats.full,
        icon: 'fa-solid fa-circle-xmark',
        color: 'red'
    },
    {
        label: 'Available',
        value: props.stats.available,
        icon: 'fa-solid fa-circle-check',
        color: 'green'
    }
]);

// Methods
const openCreateModal = () => {
    createForm.reset();
    createForm.clearErrors();
    if (props.classes.length === 1) {
        createForm.institute_class_id = props.classes[0].id;
    }
    showCreateModal.value = true;
};

const openEditModal = (section) => {
    selectedSection.value = section;
    editForm.institute_class_id = section.institute_class_id;
    editForm.name = section.name;
    editForm.room_number = section.room_number || '';
    editForm.capacity = section.capacity || 50;
    editForm.display_order = section.display_order || 0;
    editForm.clearErrors();
    showEditModal.value = true;
};

const openDeleteModal = (section) => {
    selectedSection.value = section;
    showDeleteModal.value = true;
};

const openDetailsModal = (section) => {
    selectedSection.value = section;
    showDetailsModal.value = true;
};

const createSection = () => {
    isSubmitting.value = true;
    createForm.post(route('sections.store'), {
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

const updateSection = () => {
    isSubmitting.value = true;
    editForm.put(route('sections.update', selectedSection.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            isSubmitting.value = false;
            selectedSection.value = null;
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
};

const deleteSection = () => {
    router.delete(route('sections.destroy', selectedSection.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            selectedSection.value = null;
        }
    });
};

const getCapacityStatusBadge = (status) => {
    const classes = {
        'Full': 'bg-red-100 text-red-800',
        'Almost Full': 'bg-yellow-100 text-yellow-800',
        'Half Full': 'bg-blue-100 text-blue-800',
        'Available': 'bg-green-100 text-green-800'
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
};

const closeModal = (modalType) => {
    if (modalType === 'create') {
        showCreateModal.value = false;
        createForm.reset();
    } else if (modalType === 'edit') {
        showEditModal.value = false;
        editForm.reset();
        selectedSection.value = null;
    } else if (modalType === 'delete') {
        showDeleteModal.value = false;
        selectedSection.value = null;
    } else if (modalType === 'details') {
        showDetailsModal.value = false;
        selectedSection.value = null;
    }
};
</script>

<template>
    <Head title="Section Management" />
    
    <InstituteLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-semibold text-gray-800">
                        <i class="fa-solid fa-grip-lines text-blue-600 mr-2"></i>
                        Section Management
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Manage sections for all classes
                    </p>
                </div>
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
                        'border-l-red-500': stat.color === 'red',
                        'border-l-yellow-500': stat.color === 'yellow'
                    }"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ stat.label }}</p>
                            <p class="text-2xl font-bold text-gray-800 mt-1">{{ stat.value }}</p>
                        </div>
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center"
                             :class="{
                                 'bg-blue-100 text-blue-600': stat.color === 'blue',
                                 'bg-green-100 text-green-600': stat.color === 'green',
                                 'bg-red-100 text-red-600': stat.color === 'red',
                                 'bg-yellow-100 text-yellow-600': stat.color === 'yellow'
                             }"
                        >
                            <i :class="[stat.icon, 'text-lg']"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search & Filter -->
            <div class="bg-white rounded-xl shadow-sm p-4">
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="flex-1 relative">
                        <i class="fa-solid fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input
                            type="text"
                            v-model="searchQuery"
                            placeholder="Search sections by name, class, or room..."
                            class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                        />
                        <div v-if="searchQuery" class="absolute right-3 top-1/2 transform -translate-y-1/2">
                            <button @click="searchQuery = ''" class="text-gray-400 hover:text-gray-600">
                                <i class="fa-solid fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="sm:w-48">
                        <select
                            v-model="selectedClass"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                        >
                            <option value="">All Classes</option>
                            <option 
                                v-for="cls in classes" 
                                :key="cls.id" 
                                :value="cls.id"
                            >
                                {{ cls.name }}{{ cls.group ? ` — ${cls.group}` : '' }}
                            </option>
                        </select>
                    </div>

                    <button
                        @click="openCreateModal"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition whitespace-nowrap"
                    >
                        <i class="fa-solid fa-plus mr-2"></i>
                        Add Section
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <i class="fa-solid fa-hashtag"></i>
                                        <span>SL</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <i class="fa-solid fa-grip-lines"></i>
                                        <span>Section</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <i class="fa-solid fa-school"></i>
                                        <span>Class</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <i class="fa-solid fa-door-open"></i>
                                        <span>Room</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <i class="fa-solid fa-users"></i>
                                        <span>Students</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <i class="fa-solid fa-chair"></i>
                                        <span>Capacity</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <i class="fa-solid fa-circle"></i>
                                        <span>Status</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <i class="fa-solid fa-cog"></i>
                                        <span>Actions</span>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-if="filteredSections.length === 0">
                                <td colspan="8" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="fa-solid fa-grip-lines text-5xl text-gray-300 mb-4"></i>
                                        <p class="text-gray-500 font-medium">No sections found</p>
                                        <p class="text-sm text-gray-400 mt-1">Try adjusting your search or add a new section</p>
                                        <button
                                            @click="openCreateModal"
                                            class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition"
                                        >
                                            <i class="fa-solid fa-plus mr-2"></i>
                                            Add Your First Section
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr 
                                v-for="(section, index) in filteredSections" 
                                :key="section.id"
                                class="hover:bg-gray-50 transition-colors duration-150"
                            >
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ index + 1 }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-9 w-9 rounded-full bg-blue-100 flex items-center justify-center">
                                            <i class="fa-solid fa-grip-lines text-blue-600 text-sm"></i>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-gray-900">{{ section.name }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    {{ section.class_name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    {{ section.room_number || '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    {{ section.student_count || 0 }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    {{ section.capacity || 50 }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span 
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                        :class="getCapacityStatusBadge(section.capacity_status)"
                                    >
                                        {{ section.capacity_status }}
                                        <span class="ml-1 text-xs">
                                            ({{ section.available_seats }} left)
                                        </span>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        <button
                                            @click="openDetailsModal(section)"
                                            class="p-1.5 text-blue-600 hover:text-blue-900 hover:bg-blue-50 rounded-lg transition"
                                            title="View Details"
                                        >
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                        <button
                                            @click="openEditModal(section)"
                                            class="p-1.5 text-green-600 hover:text-green-900 hover:bg-green-50 rounded-lg transition"
                                            title="Edit"
                                        >
                                            <i class="fa-solid fa-edit"></i>
                                        </button>
                                        <button
                                            @click="openDeleteModal(section)"
                                            class="p-1.5 text-red-600 hover:text-red-900 hover:bg-red-50 rounded-lg transition"
                                            title="Delete"
                                        >
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Table Footer -->
                <div class="px-6 py-3 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
                    <div class="text-sm text-gray-500">
                        Showing <span class="font-medium">{{ filteredSections.length }}</span> of 
                        <span class="font-medium">{{ props.sections.length }}</span> sections
                    </div>
                    <div class="text-xs text-gray-400">
                        <i class="fa-regular fa-clock mr-1"></i>
                        Last updated: {{ formatDate(new Date()) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- CREATE MODAL -->
        <!-- ============================================ -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" @click="closeModal('create')">
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
                                    Add New Section
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">Create a new section for a class</p>
                            </div>
                            <button @click="closeModal('create')" class="text-gray-400 hover:text-gray-600 transition">
                                <i class="fa-solid fa-times text-xl"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Form -->
                    <form @submit.prevent="createSection" class="p-6">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Class Selection -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Class <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="createForm.institute_class_id"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                        :class="{ 'border-red-500': createForm.errors.institute_class_id }"
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
                                    <p v-if="createForm.errors.institute_class_id" class="mt-1 text-sm text-red-600">
                                        {{ createForm.errors.institute_class_id }}
                                    </p>
                                </div>

                                <!-- Section Name -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Section Name <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        v-model="createForm.name"
                                        placeholder="e.g., A, B, C"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                        :class="{ 'border-red-500': createForm.errors.name }"
                                    />
                                    <p v-if="createForm.errors.name" class="mt-1 text-sm text-red-600">
                                        {{ createForm.errors.name }}
                                    </p>
                                </div>

                                <!-- Room Number -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Room Number
                                    </label>
                                    <input
                                        type="text"
                                        v-model="createForm.room_number"
                                        placeholder="e.g., Room 101"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    />
                                </div>

                                <!-- Capacity -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Capacity
                                    </label>
                                    <input
                                        type="number"
                                        v-model="createForm.capacity"
                                        placeholder="50"
                                        min="1"
                                        max="500"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    />
                                    <p class="mt-1 text-xs text-gray-400">Default: 50 students</p>
                                </div>

                                <!-- Display Order -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Display Order
                                    </label>
                                    <input
                                        type="number"
                                        v-model="createForm.display_order"
                                        placeholder="0"
                                        min="0"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end space-x-3 pt-6 mt-6 border-t border-gray-200">
                            <button
                                type="button"
                                @click="closeModal('create')"
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
                                {{ isSubmitting ? 'Creating...' : 'Create Section' }}
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
                <div class="fixed inset-0 transition-opacity" @click="closeModal('edit')">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-xl shadow-2xl sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <!-- Header -->
                    <div class="px-6 py-4 bg-gradient-to-r from-green-50 to-emerald-50 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">
                                    <i class="fa-solid fa-edit text-green-600 mr-2"></i>
                                    Edit Section
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">Update section information</p>
                            </div>
                            <button @click="closeModal('edit')" class="text-gray-400 hover:text-gray-600 transition">
                                <i class="fa-solid fa-times text-xl"></i>
                            </button>
                        </div>
                    </div>

                    <form @submit.prevent="updateSection" class="p-6">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Class <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="editForm.institute_class_id"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                        :class="{ 'border-red-500': editForm.errors.institute_class_id }"
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
                                    <p v-if="editForm.errors.institute_class_id" class="mt-1 text-sm text-red-600">
                                        {{ editForm.errors.institute_class_id }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Section Name <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        v-model="editForm.name"
                                        placeholder="e.g., A, B, C"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                        :class="{ 'border-red-500': editForm.errors.name }"
                                    />
                                    <p v-if="editForm.errors.name" class="mt-1 text-sm text-red-600">
                                        {{ editForm.errors.name }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Room Number
                                    </label>
                                    <input
                                        type="text"
                                        v-model="editForm.room_number"
                                        placeholder="e.g., Room 101"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Capacity
                                    </label>
                                    <input
                                        type="number"
                                        v-model="editForm.capacity"
                                        placeholder="50"
                                        min="1"
                                        max="500"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Display Order
                                    </label>
                                    <input
                                        type="number"
                                        v-model="editForm.display_order"
                                        placeholder="0"
                                        min="0"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end space-x-3 pt-6 mt-6 border-t border-gray-200">
                            <button
                                type="button"
                                @click="closeModal('edit')"
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
                                {{ isSubmitting ? 'Updating...' : 'Update Section' }}
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
                <div class="fixed inset-0 transition-opacity" @click="closeModal('delete')">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-xl shadow-2xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="px-6 py-4 bg-gradient-to-r from-red-50 to-rose-50 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">
                                    <i class="fa-solid fa-trash text-red-600 mr-2"></i>
                                    Delete Section
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">This action cannot be undone</p>
                            </div>
                            <button @click="closeModal('delete')" class="text-gray-400 hover:text-gray-600 transition">
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
                                    Are you sure you want to delete section <strong class="text-gray-900">{{ selectedSection?.name }}</strong>?
                                </p>
                                
                                <div class="mt-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <p class="text-xs font-medium text-gray-700 mb-2">Section Details:</p>
                                    <div class="space-y-1 text-xs text-gray-600">
                                        <div class="flex justify-between">
                                            <span>Name:</span>
                                            <span class="font-medium">{{ selectedSection?.name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Class:</span>
                                            <span class="font-medium">{{ selectedSection?.class_name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Students:</span>
                                            <span class="font-medium text-red-600">{{ selectedSection?.student_count || 0 }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="selectedSection?.student_count > 0" class="mt-3 p-3 bg-red-50 rounded-lg border border-red-200">
                                    <p class="text-xs text-red-700 flex items-center">
                                        <i class="fa-solid fa-users mr-2"></i>
                                        <span>This section has <strong>{{ selectedSection?.student_count }}</strong> enrolled students. You cannot delete it.</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-end space-x-3">
                        <button
                            @click="closeModal('delete')"
                            class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium"
                        >
                            Cancel
                        </button>
                        <button
                            @click="deleteSection"
                            class="px-6 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium disabled:opacity-50"
                            :disabled="selectedSection?.student_count > 0"
                        >
                            <i class="fa-solid fa-trash mr-2"></i>
                            Delete Section
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- DETAILS MODAL -->
        <!-- ============================================ -->
        <div v-if="showDetailsModal && selectedSection" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" @click="closeModal('details')">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-xl shadow-2xl sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="px-6 py-4 bg-gradient-to-r from-indigo-50 to-purple-50 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">
                                    <i class="fa-solid fa-info-circle text-indigo-600 mr-2"></i>
                                    Section Details
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">Complete information about this section</p>
                            </div>
                            <button @click="closeModal('details')" class="text-gray-400 hover:text-gray-600 transition">
                                <i class="fa-solid fa-times text-xl"></i>
                            </button>
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Section Name</p>
                                <p class="text-sm font-medium text-gray-900">{{ selectedSection.name }}</p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Class</p>
                                <p class="text-sm font-medium text-gray-900">{{ selectedSection.class_name }}</p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Room Number</p>
                                <p class="text-sm font-medium text-gray-900">{{ selectedSection.room_number || '—' }}</p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Capacity</p>
                                <p class="text-sm font-medium text-gray-900">{{ selectedSection.capacity || 50 }}</p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Total Students</p>
                                <p class="text-sm font-bold text-blue-600">{{ selectedSection.student_count || 0 }}</p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Available Seats</p>
                                <p class="text-sm font-bold text-green-600">{{ selectedSection.available_seats || 0 }}</p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Status</p>
                                <p class="text-sm font-medium">
                                    <span 
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                        :class="getCapacityStatusBadge(selectedSection.capacity_status)"
                                    >
                                        {{ selectedSection.capacity_status }}
                                    </span>
                                </p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-xs text-gray-500">Display Order</p>
                                <p class="text-sm font-medium text-gray-900">{{ selectedSection.display_order || 0 }}</p>
                            </div>
                        </div>

                        <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                            <p class="text-xs text-blue-700">
                                <i class="fa-solid fa-circle-info mr-1"></i>
                                This section has {{ selectedSection.student_count || 0 }} students enrolled out of {{ selectedSection.capacity || 50 }} capacity.
                            </p>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-end space-x-3">
                        <button
                            @click="closeModal('details')"
                            class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium"
                        >
                            Close
                        </button>
                        <button
                            @click="openEditModal(selectedSection); closeModal('details')"
                            class="px-6 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium"
                        >
                            <i class="fa-solid fa-edit mr-2"></i>
                            Edit Section
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

/* Modal animations */
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

/* Table row hover */
.hover\:bg-gray-50:hover {
    transition: background-color 0.15s ease;
}

/* Input focus effects */
input:focus, select:focus {
    outline: none;
}

/* Button transitions */
button {
    transition: all 0.15s ease;
}

/* Stats card hover */
.hover\:shadow-md:hover {
    transition: box-shadow 0.2s ease;
}
</style>