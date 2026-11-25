<!-- resources/js/Pages/Students/Index.vue -->
<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import InstituteLayout from '@/Layouts/InstituteLayout.vue';
import { router } from '@inertiajs/vue3';

// Props
const props = defineProps({
    students: {
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
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            active: 0,
            inactive: 0,
            passed: 0,
            transferred: 0,
            male: 0,
            female: 0
        })
    }
});

// State
const searchQuery = ref('');
const selectedClass = ref('');
const selectedSection = ref('');
const selectedStatus = ref('');
const selectedAcademicYear = ref('');
const showDeleteModal = ref(false);
const selectedStudent = ref(null);
const isDeleting = ref(false);
const showGroupColumn = computed(() => props.students.some(
    student => Boolean(student.current_class?.group)
));
const availableSections = computed(() => props.sections.filter(
    section => !selectedClass.value || String(section.institute_class_id) === String(selectedClass.value)
));
const getClassLabel = (cls) => cls.group
    ? `${cls.name} (${cls.group_label || cls.group})`
    : cls.name;

// Computed
const filteredStudents = computed(() => {
    let filtered = props.students;
    
    // Filter by search query
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(student => {
            return student.name?.toLowerCase().includes(query) ||
                   student.bn_name?.toLowerCase().includes(query) ||
                   student.student_id?.toLowerCase().includes(query) ||
                   student.roll_no?.toLowerCase().includes(query) ||
                   student.father_name?.toLowerCase().includes(query) ||
                   student.mother_name?.toLowerCase().includes(query) ||
                   student.phone?.toLowerCase().includes(query) ||
                   student.email?.toLowerCase().includes(query);
        });
    }
    
    // Filter by class
    if (selectedClass.value) {
        filtered = filtered.filter(student => {
            return student.current_class?.id == selectedClass.value;
        });
    }
    
    // Filter by section
    if (selectedSection.value) {
        filtered = filtered.filter(student => {
            return student.current_section?.id == selectedSection.value;
        });
    }
    
    // Filter by status
    if (selectedStatus.value) {
        filtered = filtered.filter(student => {
            return student.status === selectedStatus.value;
        });
    }
    
    // Filter by academic year
    if (selectedAcademicYear.value) {
        filtered = filtered.filter(student => {
            return student.current_academic_year?.id == selectedAcademicYear.value;
        });
    }
    
    return filtered;
});

const printUrl = computed(() => {
    const query = new URLSearchParams();
    const filters = {
        search: searchQuery.value,
        class_id: selectedClass.value,
        section_id: selectedSection.value,
        status: selectedStatus.value,
        academic_year_id: selectedAcademicYear.value,
    };

    Object.entries(filters).forEach(([key, value]) => {
        if (value) query.set(key, value);
    });

    const url = route('students.print');
    const queryString = query.toString();
    return queryString ? `${url}?${queryString}` : url;
});

// Stats cards
const displayStats = computed(() => [
    {
        label: 'Total Students',
        value: props.stats.total,
        icon: 'fa-solid fa-users',
        color: 'blue',
        bgColor: 'bg-blue-100',
        textColor: 'text-blue-600',
        change: '+12.5%',
        changeType: 'increase'
    },
    {
        label: 'Active Students',
        value: props.stats.active,
        icon: 'fa-solid fa-user-check',
        color: 'green',
        bgColor: 'bg-green-100',
        textColor: 'text-green-600',
        change: '+5.2%',
        changeType: 'increase'
    },
    {
        label: 'Male',
        value: props.stats.male,
        icon: 'fa-solid fa-mars',
        color: 'indigo',
        bgColor: 'bg-indigo-100',
        textColor: 'text-indigo-600',
        change: '52%',
        changeType: 'neutral'
    },
    {
        label: 'Female',
        value: props.stats.female,
        icon: 'fa-solid fa-venus',
        color: 'pink',
        bgColor: 'bg-pink-100',
        textColor: 'text-pink-600',
        change: '48%',
        changeType: 'neutral'
    }
]);

// Additional stats for quick view
const additionalStats = computed(() => [
    {
        label: 'Inactive',
        value: props.stats.inactive || 0,
        color: 'gray',
        icon: 'fa-solid fa-user-slash'
    },
    {
        label: 'Passed',
        value: props.stats.passed || 0,
        color: 'blue',
        icon: 'fa-solid fa-user-graduate'
    },
    {
        label: 'Transferred',
        value: props.stats.transferred || 0,
        color: 'yellow',
        icon: 'fa-solid fa-user-arrows'
    }
]);

// Methods
const openDeleteModal = (student) => {
    selectedStudent.value = student;
    showDeleteModal.value = true;
};

const deleteStudent = () => {
    if (!selectedStudent.value) return;
    
    isDeleting.value = true;
    router.delete(route('students.destroy', selectedStudent.value.id), {
        onSuccess: () => {
            showDeleteModal.value = false;
            selectedStudent.value = null;
            isDeleting.value = false;
        },
        onError: () => {
            isDeleting.value = false;
        }
    });
};

const toggleStatus = (student) => {
    if (confirm(`Are you sure you want to ${student.status === 'active' ? 'deactivate' : 'activate'} this student?`)) {
        router.post(route('students.toggle-status', student.id));
    }
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

const resetFilters = () => {
    searchQuery.value = '';
    selectedClass.value = '';
    selectedSection.value = '';
    selectedStatus.value = '';
    selectedAcademicYear.value = '';
};

const getInitials = (name) => {
    if (!name) return 'S';
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

const getChangeIcon = (type) => {
    if (type === 'increase') return 'fa-arrow-up';
    if (type === 'decrease') return 'fa-arrow-down';
    return 'fa-minus';
};

const getChangeColor = (type) => {
    if (type === 'increase') return 'text-green-600';
    if (type === 'decrease') return 'text-red-600';
    return 'text-gray-500';
};
</script>

<template>
    <Head title="Student Management" />
    
    <InstituteLayout>
        <main class="min-w-0 flex-1">
        <div class="w-full p-5 pb-0">
            <!-- Header with Add and Print actions -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 rounded-xl bg-white p-5 shadow-sm">
                <div>
                    <h2 class="text-2xl font-semibold text-gray-800">
                        <i class="fa-solid fa-user-graduate text-blue-600 mr-2"></i>
                        Student Management
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Manage all students and their academic information
                    </p>
                </div>
                <div class="flex items-center space-x-3">
                    <Link
                        :href="route('students.import')"
                        class="inline-flex items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition shadow-sm"
                    >
                        <i class="fa-solid fa-file-import mr-2"></i>
                        Bulk Import
                    </Link>
                    <a
                        :href="printUrl"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center px-4 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium rounded-lg transition shadow-sm"
                    >
                        <i class="fa-solid fa-print mr-2"></i>
                        Print Students
                    </a>
                    <Link
                        :href="route('students.create')"
                        class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white text-sm font-medium rounded-lg transition shadow-sm hover:shadow-md"
                    >
                        <i class="fa-solid fa-user-plus mr-2"></i>
                        Add Student
                    </Link>
                </div>
            </div>
        </div>

        <!-- Main Content -->
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
                        'border-l-indigo-500': stat.color === 'indigo',
                        'border-l-pink-500': stat.color === 'pink'
                    }"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ stat.label }}</p>
                            <p class="text-2xl font-bold text-gray-800 mt-1">{{ stat.value }}</p>
                            <div v-if="stat.change" class="flex items-center mt-2">
                                <span 
                                    class="text-xs font-medium"
                                    :class="getChangeColor(stat.changeType)"
                                >
                                    <i :class="['fa-solid', getChangeIcon(stat.changeType), 'mr-1']"></i>
                                    {{ stat.change }}
                                </span>
                                <span class="text-xs text-gray-400 ml-2">vs last month</span>
                            </div>
                        </div>
                        <div class="w-12 h-12 rounded-lg flex items-center justify-center"
                             :class="stat.bgColor"
                        >
                            <i :class="[stat.icon, 'text-2xl', stat.textColor]"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Stats -->
            <div class="grid grid-cols-3 gap-3">
                <div 
                    v-for="stat in additionalStats" 
                    :key="stat.label"
                    class="bg-white rounded-lg shadow-sm p-3 text-center border border-gray-100 hover:shadow-md transition"
                >
                    <div class="flex items-center justify-center space-x-2">
                        <i :class="[stat.icon, 'text-' + stat.color + '-500']"></i>
                        <p class="text-sm text-gray-600">{{ stat.label }}</p>
                    </div>
                    <p class="text-xl font-bold text-gray-800">{{ stat.value }}</p>
                </div>
            </div>

            <!-- Search & Filter -->
            <div class="bg-white rounded-xl shadow-sm p-4">
                <div class="flex flex-col lg:flex-row gap-4">
                    <!-- Search -->
                    <div class="flex-1 relative">
                        <i class="fa-solid fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input
                            type="text"
                            v-model="searchQuery"
                            placeholder="Search students by name, ID, roll, father/mother name, phone, email..."
                            class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                        />
                        <div v-if="searchQuery" class="absolute right-3 top-1/2 transform -translate-y-1/2">
                            <button @click="searchQuery = ''" class="text-gray-400 hover:text-gray-600">
                                <i class="fa-solid fa-times"></i>
                            </button>
                        </div>
                        <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-xs text-gray-400 hidden lg:block">
                            <kbd class="px-1.5 py-0.5 bg-gray-100 rounded border border-gray-200 text-xs">⌘</kbd>
                        </div>
                    </div>
                    
                    <!-- Filters -->
                    <div class="flex flex-wrap gap-3">
                        <select
                            v-model="selectedClass"
                            class="px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm min-w-[130px] bg-white"
                        >
                            <option value="">All Classes</option>
                            <option 
                                v-for="cls in classes" 
                                :key="cls.id" 
                                :value="cls.id"
                            >
                                {{ getClassLabel(cls) }}
                            </option>
                        </select>

                        <select
                            v-model="selectedSection"
                            class="px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm min-w-[130px] bg-white"
                        >
                            <option value="">All Sections</option>
                            <option 
                                v-for="section in availableSections"
                                :key="section.id" 
                                :value="section.id"
                            >
                                {{ section.name }}
                            </option>
                        </select>

                        <select
                            v-model="selectedStatus"
                            class="px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm min-w-[130px] bg-white"
                        >
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="passed">Passed</option>
                            <option value="transferred">Transferred</option>
                        </select>

                        <select
                            v-model="selectedAcademicYear"
                            class="px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm min-w-[140px] bg-white"
                        >
                            <option value="">All Years</option>
                            <option 
                                v-for="year in academicYears" 
                                :key="year.id" 
                                :value="year.id"
                            >
                                {{ year.session }} {{ year.is_current ? '(Current)' : '' }}
                            </option>
                        </select>

                        <button
                            @click="resetFilters"
                            class="px-3 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition whitespace-nowrap"
                            title="Reset all filters"
                        >
                            <i class="fa-solid fa-undo mr-1"></i>
                            Reset
                        </button>
                    </div>
                </div>

                <!-- Filter Results Info -->
                <div class="mt-3 flex flex-wrap items-center justify-between text-xs text-gray-500 border-t border-gray-100 pt-3">
                    <div class="flex items-center space-x-4">
                        <span>
                            Showing <span class="font-medium text-gray-700">{{ filteredStudents.length }}</span> of 
                            <span class="font-medium text-gray-700">{{ props.students.length }}</span> students
                        </span>
                        <span v-if="searchQuery || selectedClass || selectedSection || selectedStatus || selectedAcademicYear" class="text-blue-600">
                            <i class="fa-solid fa-filter mr-1"></i>
                            Filtered
                        </span>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span>
                            <i class="fa-regular fa-clock mr-1"></i>
                            Updated: {{ formatDate(new Date()) }}
                        </span>
                        <button 
                            @click="resetFilters" 
                            class="text-blue-600 hover:text-blue-800 transition"
                            v-if="searchQuery || selectedClass || selectedSection || selectedStatus || selectedAcademicYear"
                        >
                            <i class="fa-solid fa-times-circle mr-1"></i>
                            Clear Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Students Table -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12">
                                    <div class="flex items-center space-x-1">
                                        <i class="fa-solid fa-hashtag"></i>
                                        <span>SL</span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[180px]">
                                    <div class="flex items-center space-x-1">
                                        <i class="fa-solid fa-user"></i>
                                        <span>Student</span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <i class="fa-solid fa-id-card"></i>
                                        <span>ID & Roll</span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <i class="fa-solid fa-school"></i>
                                        <span>Class & Section</span>
                                    </div>
                                </th>
                                <th v-if="showGroupColumn" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <i class="fa-solid fa-layer-group"></i>
                                        <span>Group</span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <i class="fa-solid fa-phone"></i>
                                        <span>Contact</span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <i class="fa-solid fa-circle"></i>
                                        <span>Status</span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-36">
                                    <div class="flex items-center space-x-1">
                                        <i class="fa-solid fa-cog"></i>
                                        <span>Actions</span>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <!-- Empty State -->
                            <tr v-if="filteredStudents.length === 0">
                                <td :colspan="showGroupColumn ? 8 : 7" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="w-24 h-24 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                                            <i class="fa-solid fa-user-graduate text-4xl text-gray-300"></i>
                                        </div>
                                        <p class="text-gray-500 font-medium">No students found</p>
                                        <p class="text-sm text-gray-400 mt-1">
                                            {{ searchQuery || selectedClass || selectedSection || selectedStatus || selectedAcademicYear ? 'Try adjusting your filters' : 'Add your first student' }}
                                        </p>
                                        <div class="mt-4 flex items-center space-x-3">
                                            <button
                                                v-if="!searchQuery && !selectedClass && !selectedSection && !selectedStatus && !selectedAcademicYear"
                                                @click="$inertia.visit(route('students.create'))"
                                                class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition"
                                            >
                                                <i class="fa-solid fa-user-plus mr-2"></i>
                                                Add Your First Student
                                            </button>
                                            <button
                                                v-else
                                                @click="resetFilters"
                                                class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-medium rounded-lg transition"
                                            >
                                                <i class="fa-solid fa-undo mr-2"></i>
                                                Reset Filters
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <!-- Student Rows -->
                            <tr 
                                v-for="(student, index) in filteredStudents" 
                                :key="student.id"
                                class="hover:bg-gray-50 transition-colors duration-150 group"
                            >
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    {{ index + 1 }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-full overflow-hidden">
                                            <img 
                                                v-if="student.photo" 
                                                :src="student.photo" 
                                                alt="Student Photo"
                                                class="h-full w-full object-cover"
                                            />
                                            <div v-else class="h-full w-full flex items-center justify-center text-white font-medium text-sm"
                                                 :class="getGenderColor(student.gender)"
                                            >
                                                {{ getInitials(student.name) }}
                                            </div>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-gray-900">{{ student.name }}</p>
                                            <p class="text-xs text-gray-500">{{ student.bn_name || '' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <p class="text-sm text-gray-900">{{ student.student_id }}</p>
                                    <p class="text-xs text-gray-500">Roll: {{ student.roll_no }}</p>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <p class="text-sm text-gray-900">{{ student.current_class?.name || 'N/A' }}</p>
                                    <p class="text-xs text-gray-500">Section: {{ student.current_section?.name || 'N/A' }}</p>
                                </td>
                                <td v-if="showGroupColumn" class="px-4 py-3 whitespace-nowrap">
                                    <p v-if="student.current_class?.group" class="text-sm text-gray-900">
                                        {{ student.group_label || student.group || student.current_class.group }}
                                    </p>
                                    <p v-else class="text-sm text-gray-400">—</p>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <p class="text-sm text-gray-600">{{ student.phone || 'N/A' }}</p>
                                    <p class="text-xs text-gray-500 truncate max-w-[120px]" :title="student.email || ''">
                                        {{ student.email || '' }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span 
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                        :class="getStatusBadgeClass(student.status)"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full mr-1.5"
                                              :class="{
                                                  'bg-green-500': student.status === 'active',
                                                  'bg-gray-500': student.status === 'inactive',
                                                  'bg-blue-500': student.status === 'passed',
                                                  'bg-yellow-500': student.status === 'transferred'
                                              }"
                                        ></span>
                                        {{ getStatusLabel(student.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-1">
                                        <Link
                                            :href="route('students.show', student.id)"
                                            class="p-1.5 text-blue-600 hover:text-blue-900 hover:bg-blue-50 rounded-lg transition"
                                            title="View Details"
                                        >
                                            <i class="fa-solid fa-eye"></i>
                                        </Link>
                                        <Link
                                            :href="route('students.edit', student.id)"
                                            class="p-1.5 text-green-600 hover:text-green-900 hover:bg-green-50 rounded-lg transition"
                                            title="Edit"
                                        >
                                            <i class="fa-solid fa-edit"></i>
                                        </Link>
                                        <button
                                            @click="toggleStatus(student)"
                                            class="p-1.5 text-yellow-600 hover:text-yellow-900 hover:bg-yellow-50 rounded-lg transition"
                                            :title="student.status === 'active' ? 'Deactivate' : 'Activate'"
                                        >
                                            <i :class="student.status === 'active' ? 'fa-solid fa-pause' : 'fa-solid fa-play'"></i>
                                        </button>
                                        <button
                                            @click="openDeleteModal(student)"
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
                        Showing <span class="font-medium">{{ filteredStudents.length }}</span> of 
                        <span class="font-medium">{{ props.students.length }}</span> students
                    </div>
                    <div class="flex items-center space-x-4 text-xs text-gray-400">
                        <span>
                            <i class="fa-regular fa-clock mr-1"></i>
                            Updated: {{ formatDate(new Date()) }}
                        </span>
                        <Link
                            :href="route('students.index')"
                            class="text-blue-600 hover:text-blue-800 transition"
                        >
                            <i class="fa-solid fa-refresh mr-1"></i>
                            Refresh
                        </Link>
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
                    <!-- Header -->
                    <div class="px-6 py-4 bg-gradient-to-r from-red-50 to-rose-50 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">
                                    <i class="fa-solid fa-user-slash text-red-600 mr-2"></i>
                                    Delete Student
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">This action cannot be undone</p>
                            </div>
                            <button @click="showDeleteModal = false" class="text-gray-400 hover:text-gray-600 transition">
                                <i class="fa-solid fa-times text-xl"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="p-6">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0 w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                                <i class="fa-solid fa-exclamation-triangle text-red-600 text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-700">
                                    Are you sure you want to delete <strong class="text-gray-900">{{ selectedStudent?.name }}</strong>?
                                </p>
                                
                                <div class="mt-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <p class="text-xs font-medium text-gray-700 mb-2">Student Details:</p>
                                    <div class="space-y-1 text-xs text-gray-600">
                                        <div class="flex justify-between">
                                            <span>Name:</span>
                                            <span class="font-medium">{{ selectedStudent?.name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>ID:</span>
                                            <span class="font-medium">{{ selectedStudent?.student_id }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Class:</span>
                                            <span class="font-medium">{{ selectedStudent?.current_class?.name || 'N/A' }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Status:</span>
                                            <span class="font-medium">{{ getStatusLabel(selectedStudent?.status) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Phone:</span>
                                            <span class="font-medium">{{ selectedStudent?.phone || 'N/A' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                                    <p class="text-xs text-yellow-800 flex items-center">
                                        <i class="fa-solid fa-circle-info mr-2"></i>
                                        <span>This will permanently delete the student and all their academic information.</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-end space-x-3">
                        <button
                            @click="showDeleteModal = false"
                            class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium"
                            :disabled="isDeleting"
                        >
                            Cancel
                        </button>
                        <button
                            @click="deleteStudent"
                            class="px-6 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium disabled:opacity-50"
                            :disabled="isDeleting"
                        >
                            <i v-if="isDeleting" class="fa-solid fa-spinner fa-spin mr-2"></i>
                            <i v-else class="fa-solid fa-trash mr-2"></i>
                            {{ isDeleting ? 'Deleting...' : 'Delete Student' }}
                        </button>
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

/* Select box styling */
select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
}

/* Truncate email */
.truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Keyboard shortcut */
kbd {
    font-family: inherit;
    font-size: 0.65rem;
    padding: 0.1rem 0.4rem;
    border-radius: 0.25rem;
}

/* Dropdown animation */
.absolute {
    animation: slideDown 0.2s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Status dot animation */
.w-1\.5 {
    width: 0.375rem;
    height: 0.375rem;
}

/* Gradient text for header */
.bg-gradient-to-r {
    background-size: 200% 200%;
    animation: gradientShift 3s ease-in-out infinite;
}

@keyframes gradientShift {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}
</style>