<!-- resources/js/Pages/Students/Create.vue -->
<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import InstituteLayout from '@/Layouts/InstituteLayout.vue';

// Props
const props = defineProps({
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
    electiveSubjects: {
        type: Array,
        default: () => []
    },
    optionalSubjects: {
        type: Array,
        default: () => []
    },
    additionalSubjects: {
        type: Array,
        default: () => []
    }
});

// State
const photoPreview = ref(null);
const photoFile = ref(null);
const isSubmitting = ref(false);

// Form
const form = useForm({
    // Personal Information
    student_id: '',
    name: '',
    bn_name: '',
    father_name: '',
    mother_name: '',
    date_of_birth: '',
    gender: '',
    religion: '',
    blood_group: '',
    phone: '',
    email: '',
    password: '',
    password_confirmation: '',
    address: '',
    status: 'active',
    registration_no: '',
    photo: null, // For file upload
    
    // Academic Information
    academic_year_id: '',
    institute_class_id: '',
    section_id: '',
    roll_number: '',
    monthly_fee: 0,
    group: '',
    elective_subject_id: '',
    optional_subject_id: '',
    additional_subject_id: ''
});

// Computed
const currentYear = computed(() => {
    return props.academicYears.find(y => y.is_current);
});
const availableElectiveSubjects = computed(() => props.electiveSubjects.filter(
    subject => String(subject.class_id) === String(form.institute_class_id)
));
const availableOptionalSubjects = computed(() => props.optionalSubjects.filter(
    subject => String(subject.class_id) === String(form.institute_class_id)
));
const availableAdditionalSubjects = computed(() => props.additionalSubjects.filter(
    subject => String(subject.class_id) === String(form.institute_class_id)
));
const availableSections = computed(() => props.sections.filter(
    section => String(section.institute_class_id) === String(form.institute_class_id)
));
const selectedClass = computed(() => props.classes.find(
    item => String(item.id) === String(form.institute_class_id)
));
const selectedClassGroup = computed(() => selectedClass.value?.group || '');
const groupLabel = (group) => ({
    science: 'Science (বিজ্ঞান)',
    commerce: 'Commerce (বাণিজ্য)',
    arts: 'Arts (মানবিক)',
    general: 'General (সাধারণ)',
}[group] || group);

watch(() => form.institute_class_id, (classId, previousClassId) => {
    if (classId !== previousClassId) {
        form.section_id = '';
        form.elective_subject_id = '';
        form.optional_subject_id = '';
        form.additional_subject_id = '';
        form.group = selectedClassGroup.value;
    }
});

// Methods
const handlePhotoChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        // Validate file type
        if (!file.type.startsWith('image/')) {
            alert('Please select an image file');
            return;
        }
        
        // Validate file size (max 2MB)
        if (file.size > 2 * 1024 * 1024) {
            alert('File size should be less than 2MB');
            return;
        }
        
        photoFile.value = file;
        form.photo = file;
        
        // Create preview
        const reader = new FileReader();
        reader.onload = (e) => {
            photoPreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const removePhoto = () => {
    photoPreview.value = null;
    photoFile.value = null;
    form.photo = null;
    // Reset file input
    const fileInput = document.getElementById('photo_input');
    if (fileInput) {
        fileInput.value = '';
    }
};

const submit = () => {
    isSubmitting.value = true;

    form.post(route('students.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            removePhoto();
        },
        onFinish: () => {
            isSubmitting.value = false;
        }
    });
};

// Helper function to get initial
const getInitials = (name) => {
    if (!name) return 'S';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
};
</script>

<template>
    <Head title="Add New Student" />
    
    <InstituteLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-semibold text-gray-800">
                        <i class="fa-solid fa-user-plus text-blue-600 mr-2"></i>
                        Add New Student
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Create a new student with personal and academic information
                    </p>
                </div>
                <Link
                    :href="route('students.index')"
                    class="inline-flex items-center px-4 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-medium rounded-lg transition"
                >
                    <i class="fa-solid fa-arrow-left mr-2"></i>
                    Back to List
                </Link>
            </div>
        </template>

        <div class="bg-white w-full rounded-xl shadow-sm overflow-hidden">
            <form @submit.prevent="submit" class="p-6" enctype="multipart/form-data">
                <!-- Student Photo Upload -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">
                        <i class="fa-solid fa-camera text-blue-600 mr-2"></i>
                        Student Photo
                    </h4>
                    <div class="flex items-center space-x-6">
                        <!-- Photo Preview -->
                        <div class="flex-shrink-0">
                            <div class="h-24 w-24 rounded-full overflow-hidden bg-gray-200 border-2 border-dashed border-gray-300 flex items-center justify-center">
                                <img 
                                    v-if="photoPreview" 
                                    :src="photoPreview" 
                                    alt="Student Photo"
                                    class="h-full w-full object-cover"
                                />
                                <div v-else class="text-center">
                                    <i class="fa-solid fa-user text-3xl text-gray-400"></i>
                                    <p class="text-xs text-gray-400 mt-1">No Photo</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Upload Controls -->
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-3">
                                <label 
                                    for="photo_input"
                                    class="cursor-pointer inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition"
                                >
                                    <i class="fa-solid fa-upload mr-2"></i>
                                    Choose Photo
                                    <input
                                        id="photo_input"
                                        type="file"
                                        accept="image/*"
                                        @change="handlePhotoChange"
                                        class="hidden"
                                    />
                                </label>
                                
                                <button
                                    v-if="photoPreview"
                                    type="button"
                                    @click="removePhoto"
                                    class="inline-flex items-center px-4 py-2 bg-red-100 hover:bg-red-200 text-red-700 text-sm font-medium rounded-lg transition"
                                >
                                    <i class="fa-solid fa-trash mr-2"></i>
                                    Remove
                                </button>
                            </div>
                            
                            <div class="mt-2 text-xs text-gray-500">
                                <p>Supported formats: JPG, PNG, GIF, WebP</p>
                                <p>Maximum file size: 2MB</p>
                                <p v-if="photoFile" class="text-green-600">
                                    <i class="fa-solid fa-check-circle mr-1"></i>
                                    Selected: {{ photoFile.name }} ({{ (photoFile.size / 1024).toFixed(1) }} KB)
                                </p>
                            </div>
                            
                            <p v-if="form.errors.photo" class="mt-1 text-sm text-red-600">
                                {{ form.errors.photo }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Personal Information -->
                <div class="space-y-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">
                            <i class="fa-solid fa-user text-blue-600 mr-2"></i>
                            Personal Information
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Student ID <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    v-model="form.student_id"
                                    placeholder="e.g., 2024-001"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.student_id }"
                                />
                                <p v-if="form.errors.student_id" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.student_id }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Name (English) <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    v-model="form.name"
                                    placeholder="e.g., Md. Rahat Ahmed"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.name }"
                                />
                                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.name }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Name (Bangla)
                                </label>
                                <input
                                    type="text"
                                    v-model="form.bn_name"
                                    placeholder="e.g., মো. রাহাত আহমেদ"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Father's Name <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    v-model="form.father_name"
                                    placeholder="e.g., Md. Abdur Rahman"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.father_name }"
                                />
                                <p v-if="form.errors.father_name" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.father_name }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Mother's Name <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    v-model="form.mother_name"
                                    placeholder="e.g., Mrs. Fatema Khatun"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.mother_name }"
                                />
                                <p v-if="form.errors.mother_name" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.mother_name }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Date of Birth <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="date"
                                    v-model="form.date_of_birth"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.date_of_birth }"
                                />
                                <p v-if="form.errors.date_of_birth" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.date_of_birth }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Gender <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="form.gender"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.gender }"
                                >
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                                <p v-if="form.errors.gender" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.gender }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Religion
                                </label>
                                <input
                                    type="text"
                                    v-model="form.religion"
                                    placeholder="e.g., Islam, Hinduism, Christianity"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Blood Group
                                </label>
                                <select
                                    v-model="form.blood_group"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                >
                                    <option value="">Select Blood Group</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Phone
                                </label>
                                <input
                                    type="text"
                                    v-model="form.phone"
                                    placeholder="e.g., 01712345678"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Email
                                </label>
                                <input
                                    type="email"
                                    v-model="form.email"
                                    placeholder="e.g., student@email.com"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                />
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Address <span class="text-red-500">*</span>
                                </label>
                                <textarea
                                    v-model="form.address"
                                    rows="3"
                                    placeholder="Enter full address"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.address }"
                                ></textarea>
                                <p v-if="form.errors.address" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.address }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Registration No (SSC/HSC)
                                </label>
                                <input
                                    type="text"
                                    v-model="form.registration_no"
                                    placeholder="e.g., 123456"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Status
                                </label>
                                <select
                                    v-model="form.status"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                >
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="passed">Passed</option>
                                    <option value="transferred">Transferred</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-blue-100 bg-blue-50 p-4">
                        <h3 class="font-semibold text-gray-800">Student login</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            The student will sign in with their Student ID and this password. Share it privately.
                        </p>
                        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label for="student_password" class="block text-sm font-medium text-gray-700 mb-1">
                                    Initial password <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id="student_password"
                                    v-model="form.password"
                                    type="password"
                                    autocomplete="new-password"
                                    minlength="8"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5"
                                    :class="{ 'border-red-500': form.errors.password }"
                                />
                                <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.password }}
                                </p>
                            </div>
                            <div>
                                <label for="student_password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                                    Confirm password <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id="student_password_confirmation"
                                    v-model="form.password_confirmation"
                                    type="password"
                                    autocomplete="new-password"
                                    minlength="8"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5"
                                    :class="{ 'border-red-500': form.errors.password }"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Academic Information -->
                    <div class="pt-6 border-t border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">
                            <i class="fa-solid fa-graduation-cap text-green-600 mr-2"></i>
                            Academic Information
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Academic Year <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="form.academic_year_id"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.academic_year_id }"
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
                                <p v-if="form.errors.academic_year_id" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.academic_year_id }}
                                </p>
                            </div>

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

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Section <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="form.section_id"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.section_id }"
                                >
                                    <option value="">Select Section</option>
                                    <option 
                                        v-for="section in availableSections"
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

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Roll Number <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    v-model="form.roll_number"
                                    placeholder="e.g., 01"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.roll_number }"
                                />
                                <p v-if="form.errors.roll_number" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.roll_number }}
                                </p>
                            </div>

                            <div v-if="selectedClassGroup">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Group
                                </label>
                                <select
                                    v-model="form.group"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                >
                                    <option :value="selectedClassGroup">{{ groupLabel(selectedClassGroup) }}</option>
                                </select>
                            </div>

                            <div v-if="availableElectiveSubjects.length">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Elective Subject <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="form.elective_subject_id"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.elective_subject_id }"
                                >
                                    <option value="">Choose one elective subject</option>
                                    <option
                                        v-for="subject in availableElectiveSubjects"
                                        :key="subject.id"
                                        :value="subject.id"
                                    >
                                        {{ subject.name }} ({{ subject.code }})
                                    </option>
                                </select>
                                <p class="mt-1 text-xs text-gray-500">Choose the student's elective during enrollment.</p>
                                <p v-if="form.errors.elective_subject_id" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.elective_subject_id }}
                                </p>
                            </div>

                            <div v-if="availableOptionalSubjects.length">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Optional Subject (৪র্থ বিষয়) <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="form.optional_subject_id"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.optional_subject_id }"
                                >
                                    <option value="">Choose one optional subject</option>
                                    <option v-for="subject in availableOptionalSubjects" :key="subject.id" :value="subject.id">
                                        {{ subject.name }} ({{ subject.code }})
                                    </option>
                                </select>
                                <p class="mt-1 text-xs text-gray-500">The result GPA uses the board 4th-subject bonus (GP above 2).</p>
                                <p v-if="form.errors.optional_subject_id" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.optional_subject_id }}
                                </p>
                            </div>

                            <div v-if="availableAdditionalSubjects.length">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Additional Subject <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="form.additional_subject_id"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.additional_subject_id }"
                                >
                                    <option value="">Choose one additional subject</option>
                                    <option v-for="subject in availableAdditionalSubjects" :key="subject.id" :value="subject.id">
                                        {{ subject.name }} ({{ subject.code }})
                                    </option>
                                </select>
                                <p class="mt-1 text-xs text-gray-500">This subject counts as a regular subject in GPA.</p>
                                <p v-if="form.errors.additional_subject_id" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.additional_subject_id }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Monthly Fee
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">৳</span>
                                    <input
                                        type="number"
                                        v-model="form.monthly_fee"
                                        placeholder="0.00"
                                        class="w-full pl-8 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-3 pt-6 mt-6 border-t border-gray-200">
                    <Link
                        :href="route('students.index')"
                        class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium"
                    >
                        Cancel
                    </Link>
                    <button
                        type="reset"
                        class="px-6 py-2.5 bg-yellow-100 text-yellow-700 rounded-lg hover:bg-yellow-200 transition font-medium"
                        @click="removePhoto"
                    >
                        <i class="fa-solid fa-undo mr-2"></i>
                        Reset
                    </button>
                    <button
                        type="submit"
                        class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        <i v-if="form.processing" class="fa-solid fa-spinner fa-spin mr-2"></i>
                        <i v-else class="fa-solid fa-save mr-2"></i>
                        {{ form.processing ? 'Creating...' : 'Create Student' }}
                    </button>
                </div>
            </form>
        </div>
    </InstituteLayout>
</template>