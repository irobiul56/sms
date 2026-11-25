<!-- resources/js/Pages/Teachers/Create.vue -->
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
    classPermissionOptions: {
        type: Object,
        default: () => ({})
    }
});

// State
const photoPreview = ref(null);
const photoFile = ref(null);
const isSubmitting = ref(false);
const selectedClasses = ref([]);
const selectedSections = ref({});

// Form
const form = useForm({
    // Personal Information
    employee_id: '',
    name: '',
    bn_name: '',
    designation: '',
    phone: '',
    email: '',
    account_email: '',
    password: '',
    password_confirmation: '',
    gender: '',
    date_of_birth: '',
    joining_date: '',
    qualification: '',
    address: '',
    photo: null,
    status: 'active',
    
    // Class Assignment
    class_ids: [],
    section_ids: {},
    class_permissions: {},
    academic_year_id: ''
});

// Gender options
const genderOptions = [
    { value: 'male', label: 'Male', icon: 'fa-solid fa-mars' },
    { value: 'female', label: 'Female', icon: 'fa-solid fa-venus' },
    { value: 'other', label: 'Other', icon: 'fa-solid fa-genderless' }
];

const statusOptions = [
    { value: 'active', label: 'Active', color: 'green' },
    { value: 'inactive', label: 'Inactive', color: 'gray' },
    { value: 'retired', label: 'Retired', color: 'yellow' },
    { value: 'transferred', label: 'Transferred', color: 'blue' }
];

// Computed
const currentAcademicYear = computed(() => {
    return props.academicYears.find(y => y.is_current);
});

// Methods
const handlePhotoChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        if (!file.type.startsWith('image/')) {
            alert('Please select an image file');
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            alert('File size should be less than 2MB');
            return;
        }
        
        photoFile.value = file;
        form.photo = file;
        
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
    const fileInput = document.getElementById('photo_input');
    if (fileInput) {
        fileInput.value = '';
    }
};

// Get sections for a specific class
const getSectionsForClass = (classId) => {
    return props.sections.filter(section => section.institute_class_id === classId);
};

const toggleClass = (classId) => {
    const index = selectedClasses.value.indexOf(classId);
    if (index > -1) {
        selectedClasses.value.splice(index, 1);
        delete selectedSections.value[classId];
    } else {
        selectedClasses.value.push(classId);
        const sections = getSectionsForClass(classId);
        // Auto-select if only one section
        if (sections.length === 1) {
            selectedSections.value[classId] = sections[0].id;
        } else {
            selectedSections.value[classId] = '';
        }
        form.class_permissions[classId] = [];
    }
    form.class_ids = selectedClasses.value;
    form.section_ids = selectedSections.value;
};

const selectAllClasses = () => {
    if (selectedClasses.value.length === props.classes.length) {
        selectedClasses.value = [];
        selectedSections.value = {};
    } else {
        selectedClasses.value = props.classes.map(c => c.id);
        props.classes.forEach(cls => {
            const sections = getSectionsForClass(cls.id);
            if (sections.length === 1) {
                selectedSections.value[cls.id] = sections[0].id;
            } else {
                selectedSections.value[cls.id] = '';
            }
            form.class_permissions[cls.id] = form.class_permissions[cls.id] || [];
        });
        if (selectedClasses.value.length === 0) {
            form.class_permissions = {};
        }
    }
    form.class_ids = selectedClasses.value;
    form.section_ids = selectedSections.value;
};

const submit = () => {
    isSubmitting.value = true;
    form.class_ids = selectedClasses.value;
    form.section_ids = selectedSections.value;
    form.academic_year_id = form.academic_year_id || currentAcademicYear.value?.id || '';
    
    form.post(route('teachers.store'), {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
};

// Auto-select section when class is selected and only one section exists
watch(selectedClasses, (newClasses) => {
    newClasses.forEach(classId => {
        const sections = getSectionsForClass(classId);
        if (sections.length === 1 && !selectedSections.value[classId]) {
            selectedSections.value[classId] = sections[0].id;
            form.section_ids = selectedSections.value;
        }
    });
}, { deep: true });
</script>

<template>
    <Head title="Add New Teacher" />
    
    <InstituteLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-semibold text-gray-800">
                        <i class="fa-solid fa-chalkboard-user text-blue-600 mr-2"></i>
                        Add New Teacher
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Create a new teacher with personal information and class assignments
                    </p>
                </div>
                <Link
                    :href="route('teachers.index')"
                    class="inline-flex items-center px-4 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-medium rounded-lg transition"
                >
                    <i class="fa-solid fa-arrow-left mr-2"></i>
                    Back to List
                </Link>
            </div>
        </template>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <form @submit.prevent="submit" class="p-6">
                <!-- Progress Steps -->
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center flex-1">
                        <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-bold">1</div>
                        <div class="flex-1 h-1 bg-blue-600 mx-2"></div>
                        <div class="w-8 h-8 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center text-sm font-bold">2</div>
                        <div class="flex-1 h-1 bg-gray-200 mx-2"></div>
                        <div class="w-8 h-8 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center text-sm font-bold">3</div>
                    </div>
                    <div class="text-sm text-gray-500 ml-4">Step 1 of 3</div>
                </div>

                <!-- Step 1: Basic Information -->
                <div class="space-y-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">
                            <i class="fa-solid fa-info-circle text-blue-600 mr-2"></i>
                            Basic Information
                        </h3>

                        <!-- Photo Upload -->
                        <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">
                                <i class="fa-solid fa-camera text-blue-600 mr-2"></i>
                                Teacher Photo
                            </h4>
                            <div class="flex items-center space-x-6">
                                <div class="flex-shrink-0">
                                    <div class="h-24 w-24 rounded-full overflow-hidden bg-gray-200 border-2 border-dashed border-gray-300 flex items-center justify-center">
                                        <img 
                                            v-if="photoPreview" 
                                            :src="photoPreview" 
                                            alt="Teacher Photo"
                                            class="h-full w-full object-cover"
                                        />
                                        <div v-else class="text-center">
                                            <i class="fa-solid fa-user text-3xl text-gray-400"></i>
                                            <p class="text-xs text-gray-400 mt-1">No Photo</p>
                                        </div>
                                    </div>
                                </div>
                                
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

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Employee ID <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    v-model="form.employee_id"
                                    placeholder="e.g., EMP-2024-001"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.employee_id }"
                                />
                                <p v-if="form.errors.employee_id" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.employee_id }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Name (English) <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    v-model="form.name"
                                    placeholder="e.g., Md. Abdur Rahman"
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
                                    placeholder="e.g., মো. আব্দুর রহমান"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Designation <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    v-model="form.designation"
                                    placeholder="e.g., Senior Teacher"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.designation }"
                                />
                                <p v-if="form.errors.designation" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.designation }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Phone <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    v-model="form.phone"
                                    placeholder="e.g., 01712345678"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.phone }"
                                />
                                <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.phone }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Email <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="email"
                                    v-model="form.email"
                                    placeholder="e.g., teacher@school.edu"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.email }"
                                />
                                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.email }}
                                </p>
                            </div>

                            <div class="md:col-span-2 rounded-lg border border-blue-100 bg-blue-50 p-4">
                                <h4 class="mb-3 text-sm font-semibold text-gray-800">Teacher login credentials</h4>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">Login email <span class="text-red-500">*</span></label>
                                        <input
                                            type="email"
                                            v-model="form.account_email"
                                            autocomplete="off"
                                            class="w-full rounded-lg border border-gray-300 px-4 py-2.5"
                                            :class="{ 'border-red-500': form.errors.account_email }"
                                        />
                                        <p v-if="form.errors.account_email" class="mt-1 text-sm text-red-600">{{ form.errors.account_email }}</p>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">Password <span class="text-red-500">*</span></label>
                                        <input
                                            type="password"
                                            v-model="form.password"
                                            autocomplete="new-password"
                                            class="w-full rounded-lg border border-gray-300 px-4 py-2.5"
                                            :class="{ 'border-red-500': form.errors.password }"
                                        />
                                        <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">Confirm password <span class="text-red-500">*</span></label>
                                        <input
                                            type="password"
                                            v-model="form.password_confirmation"
                                            autocomplete="new-password"
                                            class="w-full rounded-lg border border-gray-300 px-4 py-2.5"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Gender <span class="text-red-500">*</span>
                                </label>
                                <div class="grid grid-cols-3 gap-2">
                                    <button
                                        v-for="gender in genderOptions"
                                        :key="gender.value"
                                        type="button"
                                        @click="form.gender = gender.value"
                                        class="p-2.5 border-2 rounded-lg text-sm font-medium transition flex items-center justify-center space-x-2"
                                        :class="[
                                            form.gender === gender.value 
                                                ? 'border-blue-500 bg-blue-50 text-blue-700' 
                                                : 'border-gray-200 hover:border-gray-300 text-gray-600'
                                        ]"
                                    >
                                        <i :class="[gender.icon, form.gender === gender.value ? 'text-blue-600' : 'text-gray-400']"></i>
                                        <span>{{ gender.label }}</span>
                                    </button>
                                </div>
                                <p v-if="form.errors.gender" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.gender }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Date of Birth
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
                                    Joining Date <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="date"
                                    v-model="form.joining_date"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.joining_date }"
                                />
                                <p v-if="form.errors.joining_date" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.joining_date }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Status <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="form.status"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.status }"
                                >
                                    <option v-for="status in statusOptions" :key="status.value" :value="status.value">
                                        {{ status.label }}
                                    </option>
                                </select>
                                <p v-if="form.errors.status" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.status }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Qualification
                                </label>
                                <textarea
                                    v-model="form.qualification"
                                    rows="2"
                                    placeholder="e.g., MSc in Mathematics, B.Ed"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.qualification }"
                                ></textarea>
                                <p v-if="form.errors.qualification" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.qualification }}
                                </p>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Address
                                </label>
                                <textarea
                                    v-model="form.address"
                                    rows="2"
                                    placeholder="Enter full address"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.address }"
                                ></textarea>
                                <p v-if="form.errors.address" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.address }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Class Assignment -->
                    <div class="pt-6 border-t border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">
                            <i class="fa-solid fa-chalkboard text-purple-600 mr-2"></i>
                            Class Assignment
                        </h3>
                        <p class="mb-4 text-sm text-gray-600">
                            Assign this teacher to the classes and, optionally, sections they teach. Leave section unselected to assign the whole class.
                        </p>

                        <div class="space-y-4">
                            <!-- Academic Year -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Academic Year <span v-if="selectedClasses.length" class="text-red-500">*</span>
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
                                <p v-if="!academicYears.length" class="mt-1 text-sm text-amber-700">
                                    Create an academic year before assigning classes to a teacher.
                                </p>
                            </div>

                            <!-- Class Selection -->
                            <div v-if="classes.length > 0" class="space-y-4">
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center">
                                        <input
                                            type="checkbox"
                                            :checked="selectedClasses.length === classes.length"
                                            @change="selectAllClasses"
                                            class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                        />
                                        <label class="ml-2 text-sm font-medium text-gray-700">
                                            Select All Classes
                                        </label>
                                    </div>
                                    <span class="text-xs text-gray-500">
                                        {{ selectedClasses.length }} of {{ classes.length }} selected
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 gap-3">
                                    <div
                                        v-for="cls in classes"
                                        :key="cls.id"
                                        class="p-4 border-2 rounded-lg transition"
                                        :class="[
                                            selectedClasses.includes(cls.id)
                                                ? 'border-blue-500 bg-blue-50'
                                                : 'border-gray-200 hover:border-gray-300'
                                        ]"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-3">
                                                <input
                                                    type="checkbox"
                                                    :checked="selectedClasses.includes(cls.id)"
                                                    @change="toggleClass(cls.id)"
                                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                                    @click.stop
                                                />
                                                <div>
                                                    <p class="text-sm font-medium text-gray-700">                                                    {{ cls.name }}{{ cls.group ? ` — ${cls.group}` : '' }}</p>
                                                    <p class="text-xs text-gray-500">Class {{ cls.numeric_value }}</p>
                                                </div>
                                            </div>
                                            
                                            <!-- Section Selection - Show only sections for this class -->
                                            <div v-if="selectedClasses.includes(cls.id)" class="flex items-center space-x-2">
                                                <label class="text-xs text-gray-600">Section:</label>
                                                <select
                                                    v-model="selectedSections[cls.id]"
                                                    @change="form.section_ids = selectedSections"
                                                    class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                                >
                                                <option value="">Whole class (no section)</option>
                                                    <!-- FIX: Only show sections belonging to this class -->
                                                    <option 
                                                        v-for="section in sections.filter(s => s.institute_class_id === cls.id)" 
                                                        :key="section.id" 
                                                        :value="section.id"
                                                    >
                                                        {{ section.name }}
                                                    </option>
                                                </select>
                                            </div>
                                        </div>
                                        <div v-if="selectedClasses.includes(cls.id)" class="mt-4 border-t border-blue-100 pt-3">
                                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-600">Class permissions</p>
                                            <label
                                                v-for="(label, permission) in classPermissionOptions"
                                                :key="permission"
                                                class="mr-5 inline-flex items-center gap-2 text-sm text-gray-700"
                                            >
                                                <input
                                                    type="checkbox"
                                                    :value="permission"
                                                    v-model="form.class_permissions[cls.id]"
                                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                />
                                                {{ label }}
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <p v-if="form.errors.class_ids" class="text-sm text-red-600">
                                    {{ form.errors.class_ids }}
                                </p>
                                <p v-if="form.errors.section_ids" class="text-sm text-red-600">
                                    {{ form.errors.section_ids }}
                                </p>
                            </div>

                            <div v-else class="text-center py-8 text-gray-500">
                                <i class="fa-solid fa-school text-4xl text-gray-300 mb-3 block"></i>
                                <p>No classes available</p>
                                <Link
                                    :href="route('classes.index')"
                                    class="inline-block mt-2 text-blue-600 hover:text-blue-800 font-medium text-sm"
                                >
                                    Create a class first
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-3 pt-6 mt-6 border-t border-gray-200">
                    <Link
                        :href="route('teachers.index')"
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
                        :disabled="form.processing || isSubmitting"
                    >
                        <i v-if="form.processing || isSubmitting" class="fa-solid fa-spinner fa-spin mr-2"></i>
                        <i v-else class="fa-solid fa-save mr-2"></i>
                        {{ (form.processing || isSubmitting) ? 'Creating...' : 'Create Teacher' }}
                    </button>
                </div>
            </form>
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
input:focus, select:focus, textarea:focus {
    outline: none;
}

/* Button transitions */
button {
    transition: all 0.15s ease;
}

/* Class card hover */
.class-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

/* Checkbox custom style */
input[type="checkbox"] {
    cursor: pointer;
}

/* Animation for step indicators */
.w-8 {
    transition: all 0.3s ease;
}
</style>