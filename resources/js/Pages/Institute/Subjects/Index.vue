<!-- resources/js/Pages/Subjects/Index.vue -->
<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import InstituteLayout from '@/Layouts/InstituteLayout.vue';
import { router } from '@inertiajs/vue3';

// Props
const props = defineProps({
    subjects: {
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
            compulsory: 0,
            optional: 0,
            elective: 0,
            additional: 0,
            with_practical: 0
        })
    }
});

// State
const searchQuery = ref('');
const selectedType = ref('');
const selectedClass = ref('');
const showDeleteModal = ref(false);
const selectedSubject = ref(null);
const isDeleting = ref(false);
const showPaperGroupModal = ref(false);
const paperGroupClassId = ref('');
const paperGroupName = ref('');
const oldPaperGroupName = ref('');
const selectedPaperIds = ref([]);
const paperGroupErrors = ref({});
const isSavingPaperGroup = ref(false);

// Computed
const filteredSubjects = computed(() => {
    let filtered = props.subjects;
    
    // Filter by search query
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(subject => {
            return subject.name?.toLowerCase().includes(query) ||
                   subject.bn_name?.toLowerCase().includes(query) ||
                   subject.code?.toLowerCase().includes(query);
        });
    }
    
    // Filter by type
    if (selectedType.value) {
        filtered = filtered.filter(subject => {
            return subject.type === selectedType.value;
        });
    }
    
    // Filter by class
    if (selectedClass.value) {
        filtered = filtered.filter(subject => {
            return subject.classes?.some(cls => cls.id === selectedClass.value);
        });
    }
    
    return filtered;
});

const subjectsForPaperGroupClass = computed(() => props.subjects
    .filter((subject) => subject.classes?.some((cls) => String(cls.id) === String(paperGroupClassId.value))));
const existingPaperGroups = computed(() => {
    const groups = new Map();
    subjectsForPaperGroupClass.value.forEach((subject) => {
        subject.classes
            ?.filter((cls) => String(cls.id) === String(paperGroupClassId.value)
                && cls.pivot?.is_group_based
                && cls.pivot?.group_name)
            .forEach((cls) => {
                if (!groups.has(cls.pivot.group_name)) {
                    groups.set(cls.pivot.group_name, []);
                }
                groups.get(cls.pivot.group_name).push(subject.id);
            });
    });

    return [...groups.entries()].map(([name, subjectIds]) => ({ name, subjectIds }));
});

// Stats cards
const displayStats = computed(() => [
    {
        label: 'Total Subjects',
        value: props.stats.total,
        icon: 'fa-solid fa-book',
        color: 'blue'
    },
    {
        label: 'Compulsory',
        value: props.stats.compulsory,
        icon: 'fa-solid fa-star',
        color: 'green'
    },
    {
        label: 'Optional',
        value: props.stats.optional,
        icon: 'fa-solid fa-circle-check',
        color: 'purple'
    },
    {
        label: 'With Practical',
        value: props.stats.with_practical,
        icon: 'fa-solid fa-flask',
        color: 'yellow'
    }
]);

// Methods
const openDeleteModal = (subject) => {
    selectedSubject.value = subject;
    showDeleteModal.value = true;
};

const openPaperGroupModal = () => {
    paperGroupClassId.value = selectedClass.value
        ? String(selectedClass.value)
        : (props.classes[0] ? String(props.classes[0].id) : '');
    paperGroupName.value = '';
    oldPaperGroupName.value = '';
    selectedPaperIds.value = [];
    paperGroupErrors.value = {};
    showPaperGroupModal.value = true;
};

const changePaperGroupClass = () => {
    paperGroupName.value = '';
    oldPaperGroupName.value = '';
    selectedPaperIds.value = [];
    paperGroupErrors.value = {};
};

const selectPaperGroup = (groupName) => {
    oldPaperGroupName.value = groupName;
    const group = existingPaperGroups.value.find((item) => item.name === groupName);
    paperGroupName.value = group?.name ?? '';
    selectedPaperIds.value = group ? [...group.subjectIds] : [];
};

const savePaperGroup = () => {
    paperGroupErrors.value = {};
    isSavingPaperGroup.value = true;
    router.post(route('subjects.combine-papers'), {
        class_id: paperGroupClassId.value,
        subject_ids: selectedPaperIds.value,
        group_name: paperGroupName.value,
        old_group_name: oldPaperGroupName.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showPaperGroupModal.value = false;
        },
        onError: (errors) => {
            paperGroupErrors.value = errors;
        },
        onFinish: () => {
            isSavingPaperGroup.value = false;
        },
    });
};

const deleteSubject = () => {
    if (!selectedSubject.value) return;
    
    isDeleting.value = true;
    router.delete(route('subjects.destroy', selectedSubject.value.id), {
        onSuccess: () => {
            showDeleteModal.value = false;
            selectedSubject.value = null;
            isDeleting.value = false;
        },
        onError: () => {
            isDeleting.value = false;
        }
    });
};

const resetFilters = () => {
    searchQuery.value = '';
    selectedType.value = '';
    selectedClass.value = '';
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
    <Head title="Subject Management" />
    
    <InstituteLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-semibold text-gray-800">
                        <i class="fa-solid fa-book text-blue-600 mr-2"></i>
                        Subject Management
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Manage all subjects for your institute
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="route('subjects.import')"
                        class="inline-flex items-center px-4 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium rounded-lg transition"
                    >
                        <i class="fa-solid fa-file-import mr-2"></i>
                        Bulk Import XLSX
                    </Link>
                    <button
                        type="button"
                        @click="openPaperGroupModal"
                        class="inline-flex items-center px-4 py-2.5 bg-purple-100 hover:bg-purple-200 text-purple-800 text-sm font-medium rounded-lg transition"
                    >
                        <i class="fa-solid fa-layer-group mr-2"></i>
                        Combine papers
                    </button>
                    <Link
                        :href="route('subjects.create')"
                        class="inline-flex items-center px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition shadow-sm hover:shadow-md"
                    >
                        <i class="fa-solid fa-plus mr-2"></i>
                        Create Subject
                    </Link>
                </div>
            </div>
        </template>

         <div class="space-y-6 w-full p-5">
        <div v-if="$page.props.flash?.message" class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ $page.props.flash.message }}
        </div>

        <div class="flex flex-col gap-3 rounded-xl border border-blue-100 bg-blue-50/70 p-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-blue-800">Subject actions</p>
                <p class="text-xs text-blue-700">Create a subject, bulk import an XLSX file, or combine papers for result grouping.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link
                    :href="route('subjects.import')"
                    class="inline-flex items-center justify-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800"
                >
                    <i class="fa-solid fa-file-import mr-2"></i>
                    Bulk Import XLSX
                </Link>
                <Link
                    :href="route('subjects.create')"
                    class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                >
                    <i class="fa-solid fa-plus mr-2"></i>
                    Create Subject
                </Link>
                <button
                    type="button"
                    @click="openPaperGroupModal"
                    class="inline-flex items-center justify-center rounded-lg border border-purple-200 bg-white px-4 py-2 text-sm font-semibold text-purple-700 transition hover:bg-purple-50"
                >
                    <i class="fa-solid fa-layer-group mr-2"></i>
                    Combine papers
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div 
                v-for="stat in displayStats" 
                :key="stat.label"
                class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow border-l-4"
                :class="{
                    'border-l-blue-500': stat.color === 'blue',
                    'border-l-green-500': stat.color === 'green',
                    'border-l-purple-500': stat.color === 'purple',
                    'border-l-yellow-500': stat.color === 'yellow'
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
                             'bg-purple-100 text-purple-600': stat.color === 'purple',
                             'bg-yellow-100 text-yellow-600': stat.color === 'yellow'
                         }"
                    >
                        <i :class="[stat.icon, 'text-2xl']"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Stats -->
        <div class="grid grid-cols-3 gap-3 mb-6">
            <div class="bg-white rounded-lg shadow-sm p-3 text-center border border-gray-100">
                <p class="text-xs text-gray-500">Elective</p>
                <p class="text-xl font-bold text-purple-600">{{ props.stats.elective || 0 }}</p>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-3 text-center border border-gray-100">
                <p class="text-xs text-gray-500">Additional</p>
                <p class="text-xl font-bold text-yellow-600">{{ props.stats.additional || 0 }}</p>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-3 text-center border border-gray-100">
                <p class="text-xs text-gray-500">Total Subjects</p>
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
                        placeholder="Search subjects by name, Bangla name, or code..."
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
                        v-model="selectedType"
                        class="px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm min-w-[150px] bg-white"
                    >
                        <option value="">All Types</option>
                        <option value="compulsory">Compulsory</option>
                        <option value="optional">Optional</option>
                        <option value="elective">Elective</option>
                        <option value="additional">Additional</option>
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
                    Showing <span class="font-medium text-gray-700">{{ filteredSubjects.length }}</span> of 
                    <span class="font-medium text-gray-700">{{ props.subjects.length }}</span> subjects
                </span>
                <span>
                    <i class="fa-regular fa-clock mr-1"></i>
                    Updated: {{ formatDate(new Date()) }}
                </span>
            </div>
        </div>

        <!-- Subjects Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12">
                                SL
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[180px]">
                                Subject
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Code
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Type
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Marks
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Pass Marks
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[150px]">
                                Assigned Classes
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Practical
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <!-- Empty State -->
                        <tr v-if="filteredSubjects.length === 0">
                            <td colspan="9" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="fa-solid fa-book text-5xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-500 font-medium">No subjects found</p>
                                    <p class="text-sm text-gray-400 mt-1">
                                        {{ searchQuery || selectedType || selectedClass ? 'Try adjusting your filters' : 'Add your first subject' }}
                                    </p>
                                    <button
                                        v-if="!searchQuery && !selectedType && !selectedClass"
                                        @click="$inertia.visit(route('subjects.create'))"
                                        class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition"
                                    >
                                        <i class="fa-solid fa-plus mr-2"></i>
                                        Add Your First Subject
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

                        <!-- Subject Rows -->
                        <tr 
                            v-for="(subject, index) in filteredSubjects" 
                            :key="subject.id"
                            class="hover:bg-gray-50 transition-colors duration-150 group"
                        >
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                {{ index + 1 }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                        <i class="fa-solid fa-book text-blue-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900">{{ subject.name }}</p>
                                        <p class="text-xs text-gray-500">{{ subject.bn_name || '' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    {{ subject.code }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span 
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="subject.type_badge_class"
                                >
                                    {{ subject.type_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                {{ subject.full_marks }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                {{ subject.pass_marks }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <!-- Assigned Classes Display -->
                                <div v-if="subject.classes && subject.classes.length > 0" class="flex flex-wrap gap-1">
                                    <span 
                                        v-for="cls in subject.classes.slice(0, 3)" 
                                        :key="cls.id"
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200"
                                    >
                                        {{ cls.name }}
                                    </span>
                                    <span
                                        v-for="cls in subject.classes.filter(item => item.pivot?.is_group_based && item.pivot?.group_name)"
                                        :key="`group-${cls.id}`"
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-50 text-purple-700 border border-purple-200"
                                    >
                                        {{ cls.name }}: {{ cls.pivot.group_name }}
                                    </span>
                                    <span 
                                        v-if="subject.classes.length > 3"
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600"
                                    >
                                        +{{ subject.classes.length - 3 }} more
                                    </span>
                                </div>
                                <span v-else class="text-xs text-gray-400">Not assigned</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span 
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="subject.has_practical ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                                >
                                    {{ subject.has_practical ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center space-x-1">
                                    <Link
                                        :href="route('subjects.edit', subject.id)"
                                        class="p-1.5 text-green-600 hover:text-green-900 hover:bg-green-50 rounded-lg transition"
                                        title="Edit"
                                    >
                                        <i class="fa-solid fa-edit"></i>
                                    </Link>
                                    <button
                                        @click="openDeleteModal(subject)"
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
                    Showing <span class="font-medium">{{ filteredSubjects.length }}</span> of 
                    <span class="font-medium">{{ props.subjects.length }}</span> subjects
                </div>
                <div class="flex items-center space-x-4 text-xs text-gray-400">
                    <span>
                        <i class="fa-regular fa-clock mr-1"></i>
                        Updated: {{ formatDate(new Date()) }}
                    </span>
                    <Link
                        :href="route('subjects.index')"
                        class="text-blue-600 hover:text-blue-800 transition"
                    >
                        <i class="fa-solid fa-refresh mr-1"></i>
                        Refresh
                    </Link>
                </div>
            </div>
        </div>

        <div v-if="showPaperGroupModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex min-h-screen items-center justify-center px-4 py-8">
                <button type="button" class="fixed inset-0 bg-gray-900/50" aria-label="Close dialog"
                    @click="showPaperGroupModal = false"></button>
                <section class="relative w-full max-w-2xl rounded-xl bg-white shadow-2xl">
                    <header class="flex items-start justify-between border-b border-gray-200 px-6 py-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">পত্র একত্র করুন</h3>
                            <p class="mt-1 text-sm text-gray-600">শ্রেণি বেছে পত্রগুলো নির্বাচন করুন। মূল বিষয়ের নাম একবারই লিখতে হবে।</p>
                        </div>
                        <button type="button" @click="showPaperGroupModal = false" class="text-gray-400 hover:text-gray-700" aria-label="Close">
                            <i class="fa-solid fa-times text-xl"></i>
                        </button>
                    </header>

                    <div class="space-y-5 px-6 py-5">
                        <label class="block text-sm font-medium text-gray-700">
                            শ্রেণি
                            <select v-model="paperGroupClassId" @change="changePaperGroupClass"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                                <option value="">শ্রেণি নির্বাচন করুন</option>
                                <option v-for="cls in classes" :key="cls.id" :value="String(cls.id)">{{ cls.name }}{{ cls.group ? ` — ${cls.group}` : '' }}</option>
                            </select>
                        </label>

                        <label class="block text-sm font-medium text-gray-700">
                            আগে থেকে করা গ্রুপ সম্পাদনা (ঐচ্ছিক)
                            <select :value="oldPaperGroupName" @change="selectPaperGroup($event.target.value)"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm" :disabled="!paperGroupClassId">
                                <option value="">নতুন গ্রুপ তৈরি করুন</option>
                                <option v-for="group in existingPaperGroups" :key="group.name" :value="group.name">{{ group.name }}</option>
                            </select>
                        </label>

                        <fieldset :disabled="!paperGroupClassId" class="space-y-2">
                            <legend class="mb-2 text-sm font-medium text-gray-700">একত্র করার পত্রগুলো</legend>
                            <div v-if="subjectsForPaperGroupClass.length" class="max-h-52 space-y-2 overflow-y-auto rounded-lg border border-gray-200 p-3">
                                <label v-for="subject in subjectsForPaperGroupClass" :key="subject.id"
                                    class="flex cursor-pointer items-start gap-3 rounded-md p-2 hover:bg-gray-50">
                                    <input v-model="selectedPaperIds" type="checkbox" :value="subject.id"
                                        class="mt-0.5 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                    <span class="text-sm text-gray-800">
                                        {{ subject.name }}
                                        <span v-if="subject.bn_name" class="block text-xs text-gray-500">{{ subject.bn_name }}</span>
                                    </span>
                                </label>
                            </div>
                            <p v-else class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
                                এই শ্রেণিতে কোনো বিষয় বরাদ্দ নেই। আগে বিষয় সম্পাদনা করে শ্রেণিতে বরাদ্দ করুন।
                            </p>
                        </fieldset>

                        <label class="block text-sm font-medium text-gray-700">
                            মার্কশিটে দেখানোর মূল বিষয়ের নাম
                            <input v-model="paperGroupName" type="text" maxlength="100"
                                placeholder="যেমন: কুরআন মাজিদ ও হাদিস"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            <span class="mt-1 block text-xs font-normal text-gray-500">
                                আলাদা করতে চাইলে নাম ফাঁকা রাখুন। নতুন গ্রুপে কমপক্ষে ২টি পত্র নির্বাচন করুন।
                            </span>
                        </label>
                        <p v-if="paperGroupErrors.subject_ids" class="text-sm text-red-600">
                            {{ paperGroupErrors.subject_ids }}
                        </p>
                        <p v-if="paperGroupErrors.group_name" class="text-sm text-red-600">
                            {{ paperGroupErrors.group_name }}
                        </p>
                    </div>

                    <footer class="flex justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4">
                        <button type="button" @click="showPaperGroupModal = false"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-white"
                            :disabled="isSavingPaperGroup">বাতিল</button>
                        <button type="button" @click="savePaperGroup"
                            class="rounded-lg bg-purple-700 px-4 py-2 text-sm font-medium text-white hover:bg-purple-800 disabled:opacity-50"
                            :disabled="isSavingPaperGroup || !paperGroupClassId || !selectedPaperIds.length || (paperGroupName.trim() && selectedPaperIds.length < 2)">
                            <i v-if="isSavingPaperGroup" class="fa-solid fa-spinner fa-spin mr-2"></i>
                            {{ isSavingPaperGroup ? 'সংরক্ষণ হচ্ছে…' : 'সংরক্ষণ করুন' }}
                        </button>
                    </footer>
                </section>
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
                                    Are you sure you want to delete <strong class="text-gray-900">{{ selectedSubject?.name }}</strong>?
                                </p>
                                
                                <div class="mt-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <p class="text-xs font-medium text-gray-700 mb-2">Subject Details:</p>
                                    <div class="space-y-1 text-xs text-gray-600">
                                        <div class="flex justify-between">
                                            <span>Name:</span>
                                            <span class="font-medium">{{ selectedSubject?.name }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Code:</span>
                                            <span class="font-medium">{{ selectedSubject?.code }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Type:</span>
                                            <span class="font-medium">{{ selectedSubject?.type_label }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Classes:</span>
                                            <span class="font-medium">{{ selectedSubject?.classes?.length || 0 }}</span>
                                        </div>
                                        <div v-if="selectedSubject?.classes?.length > 0" class="mt-1">
                                            <p class="text-xs text-gray-500">Assigned to:</p>
                                            <div class="flex flex-wrap gap-1 mt-1">
                                                <span 
                                                    v-for="cls in selectedSubject.classes" 
                                                    :key="cls.id"
                                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700"
                                                >
                                                    {{ cls.name }}
                                                </span>
                                            </div>
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

/* Class badge hover */
.bg-blue-50:hover {
    background-color: #dbeafe;
}
</style>