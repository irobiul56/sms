<!-- resources/js/Pages/Marks/Index.vue -->
<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import InstituteLayout from '@/Layouts/InstituteLayout.vue';
import { router } from '@inertiajs/vue3';

// Props
const props = defineProps({
    marks: {
        type: Array,
        default: () => []
    },
    exams: {
        type: Array,
        default: () => []
    },
    classes: {
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
    sections: {
        type: Array,
        default: () => []
    },
    teachers: {
        type: Array,
        default: () => []
    },
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            passed: 0,
            failed: 0,
            absent: 0,
            reexamine: 0
        })
    }
});

// State
const searchQuery = ref('');
const selectedExam = ref('');
const selectedClass = ref('');
const selectedSubject = ref('');
const selectedStatus = ref('');
const showDeleteModal = ref(false);
const selectedMark = ref(null);
const isDeleting = ref(false);

// Computed
const filteredMarks = computed(() => {
    let filtered = props.marks;
    
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(mark => {
            return mark.student_name?.toLowerCase().includes(query) ||
                   mark.subject_name?.toLowerCase().includes(query) ||
                   mark.exam_name?.toLowerCase().includes(query) ||
                   mark.roll_no?.toLowerCase().includes(query);
        });
    }
    
    if (selectedExam.value) {
        filtered = filtered.filter(mark => mark.exam_id == selectedExam.value);
    }
    
    if (selectedClass.value) {
        filtered = filtered.filter(mark => mark.class_id == selectedClass.value);
    }
    
    if (selectedSubject.value) {
        filtered = filtered.filter(mark => mark.subject_id == selectedSubject.value);
    }
    
    if (selectedStatus.value) {
        if (selectedStatus.value === 'passed') {
            filtered = filtered.filter(mark => mark.grade !== 'F' && mark.grade !== null);
        } else if (selectedStatus.value === 'failed') {
            filtered = filtered.filter(mark => mark.grade === 'F');
        } else if (selectedStatus.value === 'absent') {
            filtered = filtered.filter(mark => mark.is_absent === true);
        } else if (selectedStatus.value === 'reexamine') {
            filtered = filtered.filter(mark => mark.is_reexamine === true);
        }
    }
    
    return filtered;
});

// Stats cards
const displayStats = computed(() => [
    {
        label: 'Total Marks',
        value: props.stats.total,
        icon: 'fa-solid fa-pen-fancy',
        color: 'blue',
        bgColor: 'bg-blue-100',
        textColor: 'text-blue-600'
    },
    {
        label: 'Passed',
        value: props.stats.passed,
        icon: 'fa-solid fa-check-circle',
        color: 'green',
        bgColor: 'bg-green-100',
        textColor: 'text-green-600'
    },
    {
        label: 'Failed',
        value: props.stats.failed,
        icon: 'fa-solid fa-times-circle',
        color: 'red',
        bgColor: 'bg-red-100',
        textColor: 'text-red-600'
    },
    {
        label: 'Absent',
        value: props.stats.absent,
        icon: 'fa-solid fa-user-slash',
        color: 'gray',
        bgColor: 'bg-gray-100',
        textColor: 'text-gray-600'
    }
]);

// Methods
const openDeleteModal = (mark) => {
    selectedMark.value = mark;
    showDeleteModal.value = true;
};

const deleteMark = () => {
    if (!selectedMark.value) return;
    
    isDeleting.value = true;
    router.delete(route('marks.destroy', selectedMark.value.id), {
        onSuccess: () => {
            showDeleteModal.value = false;
            selectedMark.value = null;
            isDeleting.value = false;
        },
        onError: () => {
            isDeleting.value = false;
        }
    });
};

const resetFilters = () => {
    searchQuery.value = '';
    selectedExam.value = '';
    selectedClass.value = '';
    selectedSubject.value = '';
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
</script>

<template>
    <Head title="Marks Management" />
    
    <InstituteLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-semibold text-gray-800">
                        <i class="fa-solid fa-pen-fancy text-blue-600 mr-2"></i>
                        Marks Management
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Manage all student marks and results
                    </p>
                </div>
                <Link
                    :href="route('marks.entry')"
                    class="inline-flex items-center px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition shadow-sm hover:shadow-md"
                >
                    <i class="fa-solid fa-plus mr-2"></i>
                    Enter Marks
                </Link>
            </div>
        </template>

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
                    'border-l-red-500': stat.color === 'red',
                    'border-l-gray-500': stat.color === 'gray'
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

        <!-- Additional Stats -->
        <div class="grid grid-cols-2 gap-3 mb-6">
            <div class="bg-white rounded-lg shadow-sm p-3 text-center border border-gray-100">
                <p class="text-xs text-gray-500">Re-examine</p>
                <p class="text-xl font-bold text-yellow-600">{{ props.stats.reexamine || 0 }}</p>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-3 text-center border border-gray-100">
                <p class="text-xs text-gray-500">Total Entries</p>
                <p class="text-xl font-bold text-gray-800">{{ props.stats.total || 0 }}</p>
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
                        placeholder="Search by student name, roll, subject, or exam..."
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
                        class="px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm min-w-[150px] bg-white"
                    >
                        <option value="">All Exams</option>
                        <option 
                            v-for="exam in exams" 
                            :key="exam.id" 
                            :value="exam.id"
                        >
                            {{ exam.name }}
                        </option>
                    </select>

                    <select
                        v-model="selectedClass"
                        class="px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm min-w-[150px] bg-white"
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

                    <select
                        v-model="selectedSubject"
                        class="px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm min-w-[150px] bg-white"
                    >
                        <option value="">All Subjects</option>
                        <option 
                            v-for="subject in subjects" 
                            :key="subject.id" 
                            :value="subject.id"
                        >
                            {{ subject.name }}
                        </option>
                    </select>

                    <select
                        v-model="selectedStatus"
                        class="px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm min-w-[150px] bg-white"
                    >
                        <option value="">All Status</option>
                        <option value="passed">Passed</option>
                        <option value="failed">Failed</option>
                        <option value="absent">Absent</option>
                        <option value="reexamine">Re-examine</option>
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

            <div class="mt-3 flex flex-wrap items-center justify-between text-xs text-gray-500 border-t border-gray-100 pt-3">
                <span>
                    Showing <span class="font-medium text-gray-700">{{ filteredMarks.length }}</span> of 
                    <span class="font-medium text-gray-700">{{ props.marks.length }}</span> entries
                </span>
                <span>
                    <i class="fa-regular fa-clock mr-1"></i>
                    Updated: {{ formatDate(new Date()) }}
                </span>
            </div>
        </div>

        <!-- Marks Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12">SL</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exam</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marks</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-if="filteredMarks.length === 0">
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="fa-solid fa-pen-fancy text-5xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-500 font-medium">No marks found</p>
                                    <p class="text-sm text-gray-400 mt-1">
                                        {{ searchQuery || selectedExam || selectedClass || selectedSubject || selectedStatus ? 'Try adjusting your filters' : 'Enter marks for students' }}
                                    </p>
                                    <Link
                                        v-if="!searchQuery && !selectedExam && !selectedClass && !selectedSubject && !selectedStatus"
                                        :href="route('marks.entry')"
                                        class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition"
                                    >
                                        <i class="fa-solid fa-plus mr-2"></i>
                                        Enter Marks
                                    </Link>
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
                        <tr 
                            v-for="(mark, index) in filteredMarks" 
                            :key="mark.id"
                            class="hover:bg-gray-50 transition-colors duration-150"
                        >
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ index + 1 }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-9 w-9 rounded-full bg-blue-100 flex items-center justify-center">
                                        <span class="text-blue-600 font-medium text-sm">
                                            {{ mark.student_name?.charAt(0) || 'S' }}
                                        </span>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900">{{ mark.student_name }}</p>
                                        <p class="text-xs text-gray-500">Roll: {{ mark.roll_no }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                {{ mark.exam_name }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                {{ mark.subject_name }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ mark.total_marks }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    T: {{ mark.theory_marks || 0 }} | P: {{ mark.practical_marks || 0 }} | M: {{ mark.mcq_marks || 0 }}
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span 
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="{
                                        'bg-green-100 text-green-800': mark.grade === 'A+',
                                        'bg-blue-100 text-blue-800': mark.grade === 'A' || mark.grade === 'A-',
                                        'bg-yellow-100 text-yellow-800': mark.grade === 'B' || mark.grade === 'C',
                                        'bg-orange-100 text-orange-800': mark.grade === 'D',
                                        'bg-red-100 text-red-800': mark.grade === 'F',
                                        'bg-gray-100 text-gray-800': !mark.grade
                                    }"
                                >
                                    {{ mark.grade_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span 
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="mark.status_badge_class"
                                >
                                    {{ mark.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center space-x-1">
                                    <Link
                                        :href="route('marks.show', mark.id)"
                                        class="p-1.5 text-blue-600 hover:text-blue-900 hover:bg-blue-50 rounded-lg transition"
                                        title="View"
                                    >
                                        <i class="fa-solid fa-eye"></i>
                                    </Link>
                                    <Link
                                        v-if="mark.can_update"
                                        :href="route('marks.edit', mark.id)"
                                        class="p-1.5 text-green-600 hover:text-green-900 hover:bg-green-50 rounded-lg transition"
                                        title="Edit"
                                    >
                                        <i class="fa-solid fa-edit"></i>
                                    </Link>
                                    <button
                                        v-if="mark.can_delete"
                                        @click="openDeleteModal(mark)"
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

            <div class="px-6 py-3 bg-gray-50 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
                <div class="text-sm text-gray-500">
                    Showing <span class="font-medium">{{ filteredMarks.length }}</span> of 
                    <span class="font-medium">{{ props.marks.length }}</span> entries
                </div>
                <div class="flex items-center space-x-4 text-xs text-gray-400">
                    <span>
                        <i class="fa-regular fa-clock mr-1"></i>
                        Updated: {{ formatDate(new Date()) }}
                    </span>
                    <Link
                        :href="route('marks.index')"
                        class="text-blue-600 hover:text-blue-800 transition"
                    >
                        <i class="fa-solid fa-refresh mr-1"></i>
                        Refresh
                    </Link>
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
                                    Delete Marks
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
                                    Are you sure you want to delete marks for <strong class="text-gray-900">{{ selectedMark?.student_name }}</strong>?
                                </p>
                                <div class="mt-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <p class="text-xs font-medium text-gray-700 mb-2">Marks Details:</p>
                                    <div class="space-y-1 text-xs text-gray-600">
                                        <div class="flex justify-between">
                                            <span>Student:</span>
                                            <span class="font-medium">{{ selectedMark?.student_name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Exam:</span>
                                            <span class="font-medium">{{ selectedMark?.exam_name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Subject:</span>
                                            <span class="font-medium">{{ selectedMark?.subject_name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Total Marks:</span>
                                            <span class="font-medium">{{ selectedMark?.total_marks }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Grade:</span>
                                            <span class="font-medium">{{ selectedMark?.grade_label }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                                    <p class="text-xs text-yellow-800 flex items-center">
                                        <i class="fa-solid fa-circle-info mr-2"></i>
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
                            <i v-if="isDeleting" class="fa-solid fa-spinner fa-spin mr-2"></i>
                            <i v-else class="fa-solid fa-trash mr-2"></i>
                            {{ isDeleting ? 'Deleting...' : 'Delete Marks' }}
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

.hover\:bg-gray-50:hover {
    transition: background-color 0.15s ease;
}

input:focus, select:focus {
    outline: none;
}

button {
    transition: all 0.15s ease;
}

.hover\:shadow-md:hover {
    transition: box-shadow 0.2s ease;
}
</style>