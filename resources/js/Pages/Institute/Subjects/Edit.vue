<!-- resources/js/Pages/Subjects/Edit.vue -->
<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import InstituteLayout from '@/Layouts/InstituteLayout.vue';
import { router } from '@inertiajs/vue3';

// Props
const props = defineProps({
    subject: {
        type: Object,
        required: true
    },
    classes: {
        type: Array,
        default: () => []
    }
});

// State
const isSubmitting = ref(false);
const selectedClasses = ref([]);
const showDeleteModal = ref(false);
const isDeleting = ref(false);

// Form
const form = useForm({
    // Basic Information
    name: props.subject.name || '',
    bn_name: props.subject.bn_name || '',
    code: props.subject.code || '',
    type: props.subject.type || 'compulsory',
    
    // Marks Configuration
    full_marks: props.subject.full_marks || '',
    pass_marks: props.subject.pass_marks || '',
    theory_marks: props.subject.theory_marks || '',
    theory_pass_marks: props.subject.theory_pass_marks ?? '',
    practical_marks: props.subject.practical_marks || '',
    practical_pass_marks: props.subject.practical_pass_marks ?? '',
    mcq_marks: props.subject.mcq_marks || '',
    mcq_pass_marks: props.subject.mcq_pass_marks ?? '',
    
    // Additional
    display_order: props.subject.display_order || 0,
    has_practical: props.subject.has_practical || false,
    
    // Class Assignment
    class_ids: props.subject.assigned_class_ids || [],
    group_names: { ...(props.subject.group_names || {}) }
});

// Type options
const typeOptions = [
    { value: 'compulsory', label: 'Compulsory (বাধ্যতামূলক)', icon: 'fa-solid fa-star', color: 'blue' },
    { value: 'optional', label: 'Optional (ঐচ্ছিক)', icon: 'fa-solid fa-circle-check', color: 'green' },
    { value: 'elective', label: 'Elective (ইলেকটিভ)', icon: 'fa-solid fa-book-open', color: 'purple' },
    { value: 'additional', label: 'Additional (অতিরিক্ত)', icon: 'fa-solid fa-plus-circle', color: 'yellow' }
];

// Computed
const totalMarks = computed(() => {
    let total = 0;
    if (form.full_marks) {
        total = parseInt(form.full_marks) || 0;
    }
    return total;
});

// Methods
const toggleClass = (classId) => {
    const index = selectedClasses.value.indexOf(classId);
    if (index > -1) {
        selectedClasses.value.splice(index, 1);
    } else {
        selectedClasses.value.push(classId);
    }
    form.class_ids = selectedClasses.value;
};

const selectAllClasses = () => {
    if (selectedClasses.value.length === props.classes.length) {
        selectedClasses.value = [];
    } else {
        selectedClasses.value = props.classes.map(c => c.id);
    }
    form.class_ids = selectedClasses.value;
};

const submit = () => {
    isSubmitting.value = true;
    form.class_ids = selectedClasses.value;
    
    form.put(route('subjects.update', props.subject.id), {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
};

const openDeleteModal = () => {
    showDeleteModal.value = true;
};

const deleteSubject = () => {
    isDeleting.value = true;
    router.delete(route('subjects.destroy', props.subject.id), {
        onSuccess: () => {
            showDeleteModal.value = false;
            isDeleting.value = false;
        },
        onError: () => {
            isDeleting.value = false;
        }
    });
};

const getTypeBadgeClass = (type) => {
    const classes = {
        compulsory: 'bg-blue-100 text-blue-800',
        optional: 'bg-green-100 text-green-800',
        elective: 'bg-purple-100 text-purple-800',
        additional: 'bg-yellow-100 text-yellow-800'
    };
    return classes[type] || 'bg-gray-100 text-gray-800';
};

const getTypeIcon = (type) => {
    const icons = {
        compulsory: 'fa-solid fa-star',
        optional: 'fa-solid fa-circle-check',
        elective: 'fa-solid fa-book-open',
        additional: 'fa-solid fa-plus-circle'
    };
    return icons[type] || 'fa-solid fa-book';
};

const getTypeLabel = (type) => {
    const labels = {
        compulsory: 'Compulsory',
        optional: 'Optional',
        elective: 'Elective',
        additional: 'Additional'
    };
    return labels[type] || type;
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
};

const getClassGroupLabel = (group) => {
    const labels = {
        science: 'Science (বিজ্ঞান)',
        commerce: 'Commerce (বাণিজ্য)',
        arts: 'Arts (মানবিক)',
        general: 'General (সাধারণ)'
    };
    return labels[group] || '';
};

onMounted(() => {
    selectedClasses.value = props.subject.assigned_class_ids || [];
});
</script>

<template>
    <Head :title="`Edit Subject - ${subject.name}`" />
    
    <InstituteLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-semibold text-gray-800">
                        <i class="fa-solid fa-book text-blue-600 mr-2"></i>
                        Edit Subject
                    </h2>
                    <div class="flex items-center mt-1 space-x-3">
                        <p class="text-sm text-gray-500">Update subject information</p>
                        <span 
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                            :class="getTypeBadgeClass(subject.type)"
                        >
                            <i :class="[getTypeIcon(subject.type), 'mr-1 text-xs']"></i>
                            {{ getTypeLabel(subject.type) }}
                        </span>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <button
                        @click="openDeleteModal"
                        class="inline-flex items-center px-4 py-2.5 bg-red-50 hover:bg-red-100 text-red-700 text-sm font-medium rounded-lg transition"
                    >
                        <i class="fa-solid fa-trash mr-2"></i>
                        Delete
                    </button>
                    <Link
                        :href="route('subjects.index')"
                        class="inline-flex items-center px-4 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-medium rounded-lg transition"
                    >
                        <i class="fa-solid fa-arrow-left mr-2"></i>
                        Back
                    </Link>
                </div>
            </div>
        </template>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <form @submit.prevent="submit" class="p-6">
                <!-- Subject Info Header -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs text-gray-500">Subject Code</p>
                        <p class="text-sm font-medium text-gray-900">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                {{ subject.code }}
                            </span>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Created At</p>
                        <p class="text-sm font-medium text-gray-900">{{ formatDate(subject.created_at) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Last Updated</p>
                        <p class="text-sm font-medium text-gray-900">{{ formatDate(subject.updated_at) }}</p>
                    </div>
                </div>

                <!-- Progress Steps -->
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center flex-1">
                        <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-bold">1</div>
                        <div class="flex-1 h-1 bg-blue-600 mx-2"></div>
                        <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-bold">2</div>
                        <div class="flex-1 h-1 bg-blue-600 mx-2"></div>
                        <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-bold">3</div>
                    </div>
                    <div class="text-sm text-gray-500 ml-4">Step 3 of 3</div>
                </div>

                <!-- Step 1: Basic Information -->
                <div class="space-y-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">
                            <i class="fa-solid fa-info-circle text-blue-600 mr-2"></i>
                            Basic Information
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Subject Name (English) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Subject Name (English) <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    v-model="form.name"
                                    placeholder="e.g., Mathematics"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.name }"
                                />
                                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.name }}
                                </p>
                            </div>

                            <!-- Subject Name (Bangla) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Subject Name (Bangla)
                                </label>
                                <input
                                    type="text"
                                    v-model="form.bn_name"
                                    placeholder="e.g., গণিত"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                />
                            </div>

                            <!-- Subject Code -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Subject Code <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    v-model="form.code"
                                    placeholder="e.g., MATH-101"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.code }"
                                />
                                <p v-if="form.errors.code" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.code }}
                                </p>
                                <p class="mt-1 text-xs text-gray-400">Unique code for the subject</p>
                            </div>

                            <!-- Subject Type -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Subject Type <span class="text-red-500">*</span>
                                </label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        v-for="type in typeOptions"
                                        :key="type.value"
                                        type="button"
                                        @click="form.type = type.value"
                                        class="p-2.5 border-2 rounded-lg text-sm font-medium transition flex items-center justify-center space-x-2"
                                        :class="[
                                            form.type === type.value 
                                                ? 'border-blue-500 bg-blue-50 text-blue-700' 
                                                : 'border-gray-200 hover:border-gray-300 text-gray-600'
                                        ]"
                                    >
                                        <i :class="[type.icon, form.type === type.value ? 'text-blue-600' : 'text-gray-400']"></i>
                                        <span>{{ type.label }}</span>
                                    </button>
                                </div>
                                <p v-if="form.errors.type" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.type }}
                                </p>
                            </div>

                            <!-- Display Order -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Display Order
                                </label>
                                <input
                                    type="number"
                                    v-model="form.display_order"
                                    placeholder="0"
                                    min="0"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                />
                                <p class="mt-1 text-xs text-gray-400">Lower number appears first</p>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Marks Configuration -->
                    <div class="pt-6 border-t border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">
                            <i class="fa-solid fa-chart-bar text-green-600 mr-2"></i>
                            Marks Configuration
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Full Marks -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Full Marks <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="number"
                                    v-model="form.full_marks"
                                    placeholder="e.g., 100"
                                    min="1"
                                    max="1000"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.full_marks }"
                                />
                                <p v-if="form.errors.full_marks" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.full_marks }}
                                </p>
                            </div>

                            <!-- Pass Marks -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Pass Marks <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="number"
                                    v-model="form.pass_marks"
                                    placeholder="e.g., 33"
                                    min="0"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.pass_marks }"
                                />
                                <p v-if="form.errors.pass_marks" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.pass_marks }}
                                </p>
                                <p class="mt-1 text-xs text-gray-400">Must be less than Full Marks</p>
                            </div>

                            <!-- Theory Marks -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Theory Marks
                                </label>
                                <input
                                    type="number"
                                    v-model="form.theory_marks"
                                    placeholder="e.g., 80"
                                    min="0"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                />
                                <p class="mt-1 text-xs text-gray-400">Optional if subject has theory</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Theory Pass Marks
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    v-model="form.theory_pass_marks"
                                    :max="form.theory_marks || undefined"
                                    min="0"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.theory_pass_marks }"
                                />
                                <p v-if="form.errors.theory_pass_marks" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.theory_pass_marks }}
                                </p>
                            </div>

                            <!-- Practical Marks -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Practical Marks
                                </label>
                                <input
                                    type="number"
                                    v-model="form.practical_marks"
                                    placeholder="e.g., 20"
                                    min="0"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                />
                                <p class="mt-1 text-xs text-gray-400">Optional if subject has practical</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Practical Pass Marks
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    v-model="form.practical_pass_marks"
                                    :max="form.practical_marks || undefined"
                                    min="0"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.practical_pass_marks }"
                                />
                                <p v-if="form.errors.practical_pass_marks" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.practical_pass_marks }}
                                </p>
                            </div>

                            <!-- MCQ Marks -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    MCQ Marks
                                </label>
                                <input
                                    type="number"
                                    v-model="form.mcq_marks"
                                    placeholder="e.g., 30"
                                    min="0"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                />
                                <p class="mt-1 text-xs text-gray-400">Optional if subject has MCQ</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    MCQ Pass Marks
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    v-model="form.mcq_pass_marks"
                                    :max="form.mcq_marks || undefined"
                                    min="0"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                    :class="{ 'border-red-500': form.errors.mcq_pass_marks }"
                                />
                                <p v-if="form.errors.mcq_pass_marks" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.mcq_pass_marks }}
                                </p>
                            </div>

                            <!-- Has Practical -->
                            <div class="flex items-center pt-6">
                                <input
                                    type="checkbox"
                                    id="has_practical"
                                    v-model="form.has_practical"
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                />
                                <label for="has_practical" class="ml-2 text-sm text-gray-700 cursor-pointer">
                                    <i class="fa-solid fa-flask text-purple-600 mr-1"></i>
                                    Has Practical
                                </label>
                            </div>
                        </div>

                        <!-- Marks Summary -->
                        <div v-if="form.full_marks" class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-blue-800">Marks Summary</p>
                                    <p class="text-xs text-blue-600 mt-1">
                                        Full Marks: {{ form.full_marks || 0 }} | 
                                        Pass Marks: {{ form.pass_marks || 0 }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium text-blue-800">Total: {{ totalMarks }}</p>
                                    <p class="text-xs text-blue-600">
                                        Theory: {{ form.theory_marks || 0 }} | 
                                        Practical: {{ form.practical_marks || 0 }} | 
                                        MCQ: {{ form.mcq_marks || 0 }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Class Assignment -->
                    <div class="pt-6 border-t border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">
                            <i class="fa-solid fa-chalkboard text-purple-600 mr-2"></i>
                            Assign to Classes
                        </h3>

                        <div v-if="classes.length > 0" class="space-y-4">
                            <!-- Select All -->
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

                            <!-- Class List -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <div
                                    v-for="cls in classes"
                                    :key="cls.id"
                                    @click="toggleClass(cls.id)"
                                    class="p-3 border-2 rounded-lg cursor-pointer transition flex items-center space-x-3"
                                    :class="[
                                        selectedClasses.includes(cls.id)
                                            ? 'border-blue-500 bg-blue-50'
                                            : 'border-gray-200 hover:border-gray-300'
                                    ]"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="selectedClasses.includes(cls.id)"
                                        @change="toggleClass(cls.id)"
                                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                        @click.stop
                                    />
                                    <div>
                                        <p class="text-sm font-medium text-gray-700">{{ cls.name }}</p>
                                        <p class="text-xs text-gray-500">Class {{ cls.numeric_value }}</p>
                                        <p v-if="cls.group" class="mt-1 text-xs font-medium text-purple-700">
                                            <i class="fa-solid fa-layer-group mr-1"></i>{{ getClassGroupLabel(cls.group) }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <p v-if="form.errors.class_ids" class="text-sm text-red-600">
                                {{ form.errors.class_ids }}
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

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-3 pt-6 mt-6 border-t border-gray-200">
                    <Link
                        :href="route('subjects.index')"
                        class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium"
                    >
                        Cancel
                    </Link>
                    <button
                        type="reset"
                        class="px-6 py-2.5 bg-yellow-100 text-yellow-700 rounded-lg hover:bg-yellow-200 transition font-medium"
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
                        {{ (form.processing || isSubmitting) ? 'Updating...' : 'Update Subject' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Delete Modal -->
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
                                    <i class="fa-solid fa-trash text-red-600 mr-2"></i>
                                    Delete Subject
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
                                    Are you sure you want to delete <strong class="text-gray-900">{{ subject.name }}</strong>?
                                </p>
                                
                                <div class="mt-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <p class="text-xs font-medium text-gray-700 mb-2">Subject Details:</p>
                                    <div class="space-y-1 text-xs text-gray-600">
                                        <div class="flex justify-between">
                                            <span>Name:</span>
                                            <span class="font-medium">{{ subject.name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Code:</span>
                                            <span class="font-medium">{{ subject.code }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Type:</span>
                                            <span class="font-medium">{{ subject.type_label }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Full Marks:</span>
                                            <span class="font-medium">{{ subject.full_marks }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Classes:</span>
                                            <span class="font-medium">{{ subject.assigned_class_ids?.length || 0 }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                                    <p class="text-xs text-yellow-800 flex items-center">
                                        <i class="fa-solid fa-circle-info mr-2"></i>
                                        <span>This will permanently delete the subject and remove it from all classes.</span>
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
                            @click="deleteSubject"
                            class="px-6 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium disabled:opacity-50"
                            :disabled="isDeleting"
                        >
                            <i v-if="isDeleting" class="fa-solid fa-spinner fa-spin mr-2"></i>
                            <i v-else class="fa-solid fa-trash mr-2"></i>
                            {{ isDeleting ? 'Deleting...' : 'Delete Subject' }}
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

/* Input focus effects */
input:focus, select:focus {
    outline: none;
}

/* Button transitions */
button {
    transition: all 0.15s ease;
}

/* Type button hover */
.type-btn:hover {
    transform: translateY(-1px);
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

/* Marks summary animation */
.bg-blue-50 {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>