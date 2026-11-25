<!-- resources/js/Pages/Students/Show.vue -->
<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import InstituteLayout from '@/Layouts/InstituteLayout.vue';
import { router } from '@inertiajs/vue3';

// Props
const props = defineProps({
    student: {
        type: Object,
        required: true
    }
});

// State
const showDeleteModal = ref(false);
const isDeleting = ref(false);
const showGroupColumn = computed(() => props.student.academic_infos?.some(
    info => Boolean(info.class_group)
));

// Methods
const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'long',
        year: 'numeric'
    });
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

const getGenderLabel = (gender) => {
    const genders = {
        male: 'Male',
        female: 'Female',
        other: 'Other'
    };
    return genders[gender] || gender;
};

const getGroupLabel = (group) => {
    const groups = {
        science: 'Science (বিজ্ঞান)',
        commerce: 'Commerce (বাণিজ্য)',
        arts: 'Arts (মানবিক)',
        general: 'General (সাধারণ)'
    };
    return groups[group] || group;
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

const deleteStudent = () => {
    isDeleting.value = true;
    router.delete(route('students.destroy', props.student.id), {
        onSuccess: () => {
            showDeleteModal.value = false;
            isDeleting.value = false;
        },
        onError: () => {
            isDeleting.value = false;
        }
    });
};

const toggleStatus = () => {
    if (confirm(`Are you sure you want to ${props.student.status === 'active' ? 'deactivate' : 'activate'} this student?`)) {
        router.post(route('students.toggle-status', props.student.id));
    }
};
</script>

<template>
    <Head :title="`Student Details - ${student.name}`" />
    
    <InstituteLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-semibold text-gray-800">
                        <i class="fa-solid fa-user-graduate text-blue-600 mr-2"></i>
                        Student Details
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Complete information about the student
                    </p>
                </div>
                <div class="flex items-center space-x-3">
                    <Link
                        :href="route('students.index')"
                        class="inline-flex items-center px-4 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-medium rounded-lg transition"
                    >
                        <i class="fa-solid fa-arrow-left mr-2"></i>
                        Back
                    </Link>
                </div>
            </div>
        </template>

        <div class="space-y-6 w-full p-5">
            <!-- Profile Header -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-6">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                        <div class="flex items-center space-x-6">
                            <!-- Photo -->
                            <div class="flex-shrink-0 h-24 w-24 rounded-full overflow-hidden bg-gray-200">
                                <img 
                                    v-if="student.photo" 
                                    :src="student.photo" 
                                    alt="Student Photo"
                                    class="h-full w-full object-cover"
                                />
                                <div v-else class="h-full w-full flex items-center justify-center text-white text-3xl font-bold"
                                     :class="getGenderColor(student.gender)"
                                >
                                    {{ getInitials(student.name) }}
                                </div>
                            </div>
                            
                            <!-- Name & Info -->
                            <div>
                                <h3 class="text-2xl font-bold text-gray-900">{{ student.name }}</h3>
                                <p class="text-sm text-gray-500">{{ student.bn_name || '' }}</p>
                                <div class="flex flex-wrap items-center mt-2 gap-2">
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
                                    <span class="text-sm text-gray-500">|</span>
                                    <span class="text-sm text-gray-500">ID: {{ student.student_id }}</span>
                                    <span class="text-sm text-gray-500">|</span>
                                    <span class="text-sm text-gray-500">Roll: {{ student.roll_no }}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                @click="toggleStatus"
                                class="inline-flex items-center px-4 py-2 bg-yellow-50 hover:bg-yellow-100 text-yellow-700 text-sm font-medium rounded-lg transition"
                            >
                                <i :class="student.status === 'active' ? 'fa-solid fa-pause' : 'fa-solid fa-play'" class="mr-2"></i>
                                {{ student.status === 'active' ? 'Deactivate' : 'Activate' }}
                            </button>
                            <Link
                                :href="route('students.edit', student.id)"
                                class="inline-flex items-center px-4 py-2 bg-green-50 hover:bg-green-100 text-green-700 text-sm font-medium rounded-lg transition"
                            >
                                <i class="fa-solid fa-edit mr-2"></i>
                                Edit
                            </Link>
                            <button
                                @click="showDeleteModal = true"
                                class="inline-flex items-center px-4 py-2 bg-red-50 hover:bg-red-100 text-red-700 text-sm font-medium rounded-lg transition"
                            >
                                <i class="fa-solid fa-trash mr-2"></i>
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl shadow-sm p-4 text-center border border-gray-100">
                    <p class="text-xs text-gray-500">Class</p>
                    <p class="text-lg font-bold text-gray-800">{{ student.current_class?.name || 'N/A' }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 text-center border border-gray-100">
                    <p class="text-xs text-gray-500">Section</p>
                    <p class="text-lg font-bold text-gray-800">{{ student.current_section?.name || 'N/A' }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 text-center border border-gray-100">
                    <p class="text-xs text-gray-500">Academic Year</p>
                    <p class="text-lg font-bold text-gray-800">{{ student.current_academic_year?.session || 'N/A' }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 text-center border border-gray-100">
                    <p class="text-xs text-gray-500">Age</p>
                    <p class="text-lg font-bold text-gray-800">{{ student.age || 'N/A' }} years</p>
                </div>
            </div>

            <!-- Personal Information -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h4 class="text-lg font-semibold text-gray-800">
                        <i class="fa-solid fa-user text-blue-600 mr-2"></i>
                        Personal Information
                    </h4>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500">Student ID</p>
                            <p class="text-sm font-medium text-gray-900">{{ student.student_id }}</p>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500">Roll Number</p>
                            <p class="text-sm font-medium text-gray-900">{{ student.roll_no }}</p>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500">Registration No</p>
                            <p class="text-sm font-medium text-gray-900">{{ student.registration_no || 'N/A' }}</p>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500">Father's Name</p>
                            <p class="text-sm font-medium text-gray-900">{{ student.father_name }}</p>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500">Mother's Name</p>
                            <p class="text-sm font-medium text-gray-900">{{ student.mother_name }}</p>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500">Date of Birth</p>
                            <p class="text-sm font-medium text-gray-900">{{ formatDate(student.date_of_birth) }}</p>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500">Age</p>
                            <p class="text-sm font-medium text-gray-900">{{ student.age || 'N/A' }} years</p>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500">Gender</p>
                            <p class="text-sm font-medium text-gray-900">{{ getGenderLabel(student.gender) }}</p>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500">Religion</p>
                            <p class="text-sm font-medium text-gray-900">{{ student.religion || 'N/A' }}</p>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500">Blood Group</p>
                            <p class="text-sm font-medium text-gray-900">{{ student.blood_group || 'N/A' }}</p>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500">Phone</p>
                            <p class="text-sm font-medium text-gray-900">{{ student.phone || 'N/A' }}</p>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500">Email</p>
                            <p class="text-sm font-medium text-gray-900">{{ student.email || 'N/A' }}</p>
                        </div>
                        <div class="col-span-full p-3 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500">Address</p>
                            <p class="text-sm font-medium text-gray-900">{{ student.address }}</p>
                        </div>
                        <div class="col-span-full p-3 bg-gray-50 rounded-lg">
                            <p class="text-xs text-gray-500">Created At</p>
                            <p class="text-sm font-medium text-gray-900">{{ formatDate(student.created_at) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Academic Information -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h4 class="text-lg font-semibold text-gray-800">
                        <i class="fa-solid fa-graduation-cap text-green-600 mr-2"></i>
                        Academic Information
                    </h4>
                </div>
                <div class="p-6">
                    <div v-if="student.academic_infos && student.academic_infos.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Academic Year</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Section</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Roll</th>
                                    <th v-if="showGroupColumn" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Group</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Elective Subject</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Optional Subject</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Additional Subject</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fee</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Attendance</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Promoted</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="info in student.academic_infos" :key="info.id">
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ info.academic_year }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ info.class_name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ info.section_name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ info.roll_number }}</td>
                                    <td v-if="showGroupColumn" class="px-4 py-3 text-sm text-gray-900">{{ info.class_group ? (getGroupLabel(info.group || info.class_group)) : '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ info.elective_subject || 'N/A' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ info.optional_subject || 'N/A' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ info.additional_subject || 'N/A' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">৳ {{ info.monthly_fee || 0 }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ info.attendance_days || 0 }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <span 
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                            :class="info.is_promoted ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                                        >
                                            {{ info.is_promoted ? 'Yes' : 'No' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-center py-8 text-gray-500">
                        <i class="fa-solid fa-graduation-cap text-4xl text-gray-300 mb-3 block"></i>
                        <p>No academic information available</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <Link
               
                    class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition text-center border border-gray-100"
                >
                    <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center mx-auto mb-2">
                        <i class="fa-solid fa-pen-fancy text-blue-600 text-xl"></i>
                    </div>
                    <span class="text-sm font-medium text-gray-700">Enter Marks</span>
                </Link>
                <Link
                    
                    class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition text-center border border-gray-100"
                >
                    <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-2">
                        <i class="fa-solid fa-file-lines text-green-600 text-xl"></i>
                    </div>
                    <span class="text-sm font-medium text-gray-700">View Results</span>
                </Link>
                <Link
                   
                    class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition text-center border border-gray-100"
                >
                    <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center mx-auto mb-2">
                        <i class="fa-solid fa-clipboard-check text-purple-600 text-xl"></i>
                    </div>
                    <span class="text-sm font-medium text-gray-700">Attendance</span>
                </Link>
                <Link
                    
                    class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition text-center border border-gray-100"
                >
                    <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center mx-auto mb-2">
                        <i class="fa-solid fa-edit text-yellow-600 text-xl"></i>
                    </div>
                    <span class="text-sm font-medium text-gray-700">Edit Profile</span>
                </Link>
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
                                    Are you sure you want to delete <strong class="text-gray-900">{{ student.name }}</strong>?
                                </p>
                                
                                <div class="mt-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <p class="text-xs font-medium text-gray-700 mb-2">Student Details:</p>
                                    <div class="space-y-1 text-xs text-gray-600">
                                        <div class="flex justify-between">
                                            <span>Name:</span>
                                            <span class="font-medium">{{ student.name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>ID:</span>
                                            <span class="font-medium">{{ student.student_id }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Class:</span>
                                            <span class="font-medium">{{ student.current_class?.name || 'N/A' }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Status:</span>
                                            <span class="font-medium">{{ getStatusLabel(student.status) }}</span>
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

/* Hover effects */
.hover\:shadow-md:hover {
    transition: box-shadow 0.2s ease;
}

/* Status dot */
.w-1\.5 {
    width: 0.375rem;
    height: 0.375rem;
}
</style>