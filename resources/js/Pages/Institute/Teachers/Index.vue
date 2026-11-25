<!-- resources/js/Pages/Teachers/Index.vue -->
<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import InstituteLayout from '@/Layouts/InstituteLayout.vue';
import { router } from '@inertiajs/vue3';

// Props
const props = defineProps({
    teachers: {
        type: Array,
        default: () => []
    },
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            active: 0,
            inactive: 0,
            retired: 0,
            transferred: 0,
            male: 0,
            female: 0
        })
    }
});

// State
const searchQuery = ref('');
const selectedStatus = ref('');
const showDeleteModal = ref(false);
const selectedTeacher = ref(null);
const isDeleting = ref(false);

// Computed
const filteredTeachers = computed(() => {
    let filtered = props.teachers;
    
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(teacher => {
            return teacher.name?.toLowerCase().includes(query) ||
                   teacher.bn_name?.toLowerCase().includes(query) ||
                   teacher.employee_id?.toLowerCase().includes(query) ||
                   teacher.designation?.toLowerCase().includes(query) ||
                   teacher.phone?.toLowerCase().includes(query) ||
                   teacher.email?.toLowerCase().includes(query);
        });
    }
    
    if (selectedStatus.value) {
        filtered = filtered.filter(teacher => {
            return teacher.status === selectedStatus.value;
        });
    }
    
    return filtered;
});

// Stats cards
const displayStats = computed(() => [
    {
        label: 'Total Teachers',
        value: props.stats.total,
        icon: 'fa-solid fa-chalkboard-user',
        color: 'blue'
    },
    {
        label: 'Active',
        value: props.stats.active,
        icon: 'fa-solid fa-user-check',
        color: 'green'
    },
    {
        label: 'Male',
        value: props.stats.male,
        icon: 'fa-solid fa-mars',
        color: 'indigo'
    },
    {
        label: 'Female',
        value: props.stats.female,
        icon: 'fa-solid fa-venus',
        color: 'pink'
    }
]);

// Methods
const openDeleteModal = (teacher) => {
    selectedTeacher.value = teacher;
    showDeleteModal.value = true;
};

const deleteTeacher = () => {
    if (!selectedTeacher.value) return;
    
    isDeleting.value = true;
    router.delete(route('teachers.destroy', selectedTeacher.value.id), {
        onSuccess: () => {
            showDeleteModal.value = false;
            selectedTeacher.value = null;
            isDeleting.value = false;
        },
        onError: () => {
            isDeleting.value = false;
        }
    });
};

const toggleStatus = (teacher) => {
    if (confirm(`Are you sure you want to ${teacher.status === 'active' ? 'deactivate' : 'activate'} this teacher?`)) {
        router.post(route('teachers.toggle-status', teacher.id));
    }
};

const resetFilters = () => {
    searchQuery.value = '';
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

const getInitials = (name) => {
    if (!name) return 'T';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
};

const getGenderColor = (gender) => {
    const colors = {
        male: 'bg-blue-500',
        female: 'bg-pink-500',
        other: 'bg-purple-500'
    };
    return colors[gender] || 'bg-gray-500';
};
</script>

<template>
    <Head title="Teacher Management" />
    
    <InstituteLayout>
        <main class="min-w-0 flex-1">
            <div class="w-full p-5 pb-0">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 rounded-xl bg-white p-5 shadow-sm">
                <div>
                    <h2 class="text-2xl font-semibold text-gray-800">
                        <i class="fa-solid fa-chalkboard-user text-blue-600 mr-2"></i>
                        Teacher Management
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Manage all teachers and their class assignments
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <Link
                        :href="route('teachers.import')"
                        class="inline-flex items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition shadow-sm"
                    >
                        <i class="fa-solid fa-file-import mr-2"></i>
                        Bulk Import
                    </Link>
                    <Link
                        :href="route('teachers.create')"
                        class="inline-flex items-center px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition shadow-sm hover:shadow-md"
                    >
                        <i class="fa-solid fa-user-plus mr-2"></i>
                        Add Teacher
                    </Link>
                </div>
            </div>
            </div>

        <div class="space-y-6 w-full p-5">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div 
                v-for="stat in displayStats" 
                :key="stat.label"
                class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow border-l-4"
                :class="{
                    'border-l-blue-500': stat.color === 'blue',
                    'border-l-green-500': stat.color === 'green',
                    'border-l-indigo-500': stat.color === 'indigo',
                    'border-l-pink-500': stat.color === 'pink'
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
                             'bg-indigo-100 text-indigo-600': stat.color === 'indigo',
                             'bg-pink-100 text-pink-600': stat.color === 'pink'
                         }"
                    >
                        <i :class="[stat.icon, 'text-2xl']"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Stats -->
        <div class="grid grid-cols-4 gap-3 mb-6">
            <div class="bg-white rounded-lg shadow-sm p-3 text-center border border-gray-100">
                <p class="text-xs text-gray-500">Inactive</p>
                <p class="text-xl font-bold text-gray-700">{{ props.stats.inactive || 0 }}</p>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-3 text-center border border-gray-100">
                <p class="text-xs text-gray-500">Retired</p>
                <p class="text-xl font-bold text-yellow-600">{{ props.stats.retired || 0 }}</p>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-3 text-center border border-gray-100">
                <p class="text-xs text-gray-500">Transferred</p>
                <p class="text-xl font-bold text-blue-600">{{ props.stats.transferred || 0 }}</p>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-3 text-center border border-gray-100">
                <p class="text-xs text-gray-500">Total</p>
                <p class="text-xl font-bold text-gray-800">{{ props.stats.total || 0 }}</p>
            </div>
        </div>

        <!-- Search & Filter -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="flex flex-col lg:flex-row gap-4">
                <!-- Search -->
                <div class="flex-1 relative">
                    <i class="fa-solid fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input
                        type="text"
                        v-model="searchQuery"
                        placeholder="Search teachers by name, ID, designation, phone, email..."
                        class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                    />
                    <div v-if="searchQuery" class="absolute right-3 top-1/2 transform -translate-y-1/2">
                        <button @click="searchQuery = ''" class="text-gray-400 hover:text-gray-600">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="flex flex-wrap gap-3">
                    <select
                        v-model="selectedStatus"
                        class="px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm min-w-[150px] bg-white"
                    >
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="retired">Retired</option>
                        <option value="transferred">Transferred</option>
                    </select>

                    <button
                        @click="resetFilters"
                        class="px-3 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition whitespace-nowrap"
                    >
                        <i class="fa-solid fa-undo mr-1"></i>
                        Reset
                    </button>
                </div>
            </div>

            <!-- Filter Results Info -->
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

        <!-- Teachers Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12">
                                SL
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[180px]">
                                Teacher
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ID
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Designation
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Contact
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Classes
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <!-- Empty State -->
                        <tr v-if="filteredTeachers.length === 0">
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="fa-solid fa-chalkboard-user text-5xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-500 font-medium">No teachers found</p>
                                    <p class="text-sm text-gray-400 mt-1">
                                        {{ searchQuery || selectedStatus ? 'Try adjusting your filters' : 'Add your first teacher' }}
                                    </p>
                                    <button
                                        v-if="!searchQuery && !selectedStatus"
                                        @click="$inertia.visit(route('teachers.create'))"
                                        class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition"
                                    >
                                        <i class="fa-solid fa-user-plus mr-2"></i>
                                        Add Your First Teacher
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
                            </td>
                        </tr>

                        <!-- Teacher Rows -->
                        <tr 
                            v-for="(teacher, index) in filteredTeachers" 
                            :key="teacher.id"
                            class="hover:bg-gray-50 transition-colors duration-150 group"
                        >
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                {{ index + 1 }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 rounded-full overflow-hidden">
                                        <img 
                                            v-if="teacher.photo" 
                                            :src="teacher.photo" 
                                            alt="Teacher Photo"
                                            class="h-full w-full object-cover"
                                        />
                                        <div v-else class="h-full w-full flex items-center justify-center text-white font-medium text-sm"
                                             :class="getGenderColor(teacher.gender)"
                                        >
                                            {{ getInitials(teacher.name) }}
                                        </div>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900">{{ teacher.name }}</p>
                                        <p class="text-xs text-gray-500">{{ teacher.bn_name || '' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    {{ teacher.employee_id }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                {{ teacher.designation }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <p class="text-sm text-gray-600">{{ teacher.phone }}</p>
                                <p class="text-xs text-gray-500 truncate max-w-[120px]">{{ teacher.email }}</p>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div v-if="teacher.classes && teacher.classes.length > 0" class="flex flex-wrap gap-1">
                                    <span 
                                        v-for="cls in teacher.classes.slice(0, 2)" 
                                        :key="cls.id"
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200"
                                    >
                                        {{ cls.name }}
                                    </span>
                                    <span 
                                        v-if="teacher.classes.length > 2"
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600"
                                    >
                                        +{{ teacher.classes.length - 2 }} more
                                    </span>
                                </div>
                                <span v-else class="text-xs text-gray-400">Not assigned</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span 
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="teacher.status_badge_class"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full mr-1.5"
                                          :class="{
                                              'bg-green-500': teacher.status === 'active',
                                              'bg-gray-500': teacher.status === 'inactive',
                                              'bg-yellow-500': teacher.status === 'retired',
                                              'bg-blue-500': teacher.status === 'transferred'
                                          }"
                                    ></span>
                                    {{ teacher.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center space-x-1">
                                    <Link
                                        :href="route('teachers.edit', teacher.id)"
                                        class="p-1.5 text-green-600 hover:text-green-900 hover:bg-green-50 rounded-lg transition"
                                        title="Edit"
                                    >
                                        <i class="fa-solid fa-edit"></i>
                                    </Link>
                                    <button
                                        @click="toggleStatus(teacher)"
                                        class="p-1.5 text-yellow-600 hover:text-yellow-900 hover:bg-yellow-50 rounded-lg transition"
                                        :title="teacher.status === 'active' ? 'Deactivate' : 'Activate'"
                                    >
                                        <i :class="teacher.status === 'active' ? 'fa-solid fa-pause' : 'fa-solid fa-play'"></i>
                                    </button>
                                    <button
                                        @click="openDeleteModal(teacher)"
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
            <div class="px-6 py-3 bg-gray-50 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
                <div class="text-sm text-gray-500">
                    Showing <span class="font-medium">{{ filteredTeachers.length }}</span> of 
                    <span class="font-medium">{{ props.teachers.length }}</span> teachers
                </div>
                <div class="flex items-center space-x-4 text-xs text-gray-400">
                    <span>
                        <i class="fa-regular fa-clock mr-1"></i>
                        Updated: {{ formatDate(new Date()) }}
                    </span>
                    <Link
                        :href="route('teachers.index')"
                        class="text-blue-600 hover:text-blue-800 transition"
                    >
                        <i class="fa-solid fa-refresh mr-1"></i>
                        Refresh
                    </Link>
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
                    <div class="px-6 py-4 bg-gradient-to-r from-red-50 to-rose-50 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">
                                    <i class="fa-solid fa-user-slash text-red-600 mr-2"></i>
                                    Delete Teacher
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
                                    Are you sure you want to delete <strong class="text-gray-900">{{ selectedTeacher?.name }}</strong>?
                                </p>
                                
                                <div class="mt-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <p class="text-xs font-medium text-gray-700 mb-2">Teacher Details:</p>
                                    <div class="space-y-1 text-xs text-gray-600">
                                        <div class="flex justify-between">
                                            <span>Name:</span>
                                            <span class="font-medium">{{ selectedTeacher?.name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Employee ID:</span>
                                            <span class="font-medium">{{ selectedTeacher?.employee_id }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Designation:</span>
                                            <span class="font-medium">{{ selectedTeacher?.designation }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Status:</span>
                                            <span class="font-medium">{{ selectedTeacher?.status_label }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                                    <p class="text-xs text-yellow-800 flex items-center">
                                        <i class="fa-solid fa-circle-info mr-2"></i>
                                        <span>This will permanently delete the teacher and remove all class assignments.</span>
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
                            @click="deleteTeacher"
                            class="px-6 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium disabled:opacity-50"
                            :disabled="isDeleting"
                        >
                            <i v-if="isDeleting" class="fa-solid fa-spinner fa-spin mr-2"></i>
                            <i v-else class="fa-solid fa-trash mr-2"></i>
                            {{ isDeleting ? 'Deleting...' : 'Delete Teacher' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </div>
        </main>
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

/* Status dot */
.w-1\.5 {
    width: 0.375rem;
    height: 0.375rem;
}

/* Truncate email */
.truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Class badge hover */
.bg-blue-50:hover {
    background-color: #dbeafe;
}
</style>