<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { usePage, useForm, router } from '@inertiajs/vue3';
import { ElMessage, ElLoading } from "element-plus";
import { Plus, Edit, Delete, Search, Refresh } from '@element-plus/icons-vue';
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import LatexRenderer from '@/Components/LatexRenderer.vue';

import { debounce } from 'lodash';

const { props } = usePage();

// ============ State ============
const isLoading = ref(false);
const isTableLoading = ref(true);
const isInitialLoad = ref(true);
const isSearching = ref(false);

// Data from props
const questions = ref(props.questions ?? []);
const pagination = ref(props.pagination ?? {
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    from: 0,
    to: 0
});

// Filters
const filters = ref({
    education: props.filters?.education || '',
    class: props.filters?.class || '',
    subject: props.filters?.subject || '',
    chapter: props.filters?.chapter || '',
    topic: props.filters?.topic || '',
    search: props.filters?.search || '',
    per_page: props.filters?.per_page || 10
});

// Static data (cached from server)
const education = ref(props.education ?? []);
const classes = ref(props.classes ?? []);
const subjects = ref(props.subjects ?? []);
const chapters = ref(props.chapters ?? []);
const topics = ref(props.topics ?? []);
const levels = ref(props.levels ?? []);
const types = ref(props.types ?? []);
const boards = ref(props.boards ?? []);

// ============ Computed ============
const filteredClasses = computed(() => {
    if (!filters.value.education) return [];
    return classes.value.filter(cls => cls.education_id == filters.value.education);
});

const filteredSubjects = computed(() => {
    if (!filters.value.class) return [];
    return subjects.value.filter(subject => subject.academic_classes_id == filters.value.class);
});

const filteredChapters = computed(() => {
    if (!filters.value.subject) return [];
    return chapters.value.filter(chapter => chapter.subject_id == filters.value.subject);
});

const filteredTopics = computed(() => {
    if (!filters.value.chapter) return [];
    return topics.value.filter(topic => topic.chapter_id == filters.value.chapter);
});

const totalPages = computed(() => pagination.value.last_page);

const bengaliChars = ['ক', 'খ', 'গ', 'ঘ', 'ঙ', 'চ', 'ছ', 'জ', 'ঝ', 'ঞ', 'ট', 'ঠ', 'ড', 'ঢ', 'ণ', 'ত', 'থ', 'দ', 'ধ', 'ন', 'প', 'ফ', 'ব', 'ভ', 'ম', 'য', 'র', 'ল', 'শ', 'ষ', 'স', 'হ', 'ড়', 'ঢ়', 'য়'];

// ============ Methods ============

// Debounced search function
const debouncedSearch = debounce(() => {
    fetchQuestions();
}, 500);

// Fetch questions with filters
const fetchQuestions = () => {
    isTableLoading.value = true;
    isSearching.value = true;

    const params = {
        education: filters.value.education || '',
        class: filters.value.class || '',
        subject: filters.value.subject || '',
        chapter: filters.value.chapter || '',
        topic: filters.value.topic || '',
        search: filters.value.search || '',
        per_page: filters.value.per_page || 10,
        page: pagination.value.current_page || 1
    };

    router.get(route('question.index'), params, {
        preserveState: true,
        preserveScroll: true,
        only: ['questions', 'pagination'],
        onSuccess: (page) => {
            questions.value = page.props.questions || [];
            pagination.value = page.props.pagination || {
                current_page: 1,
                last_page: 1,
                per_page: 10,
                total: 0,
                from: 0,
                to: 0
            };
            isTableLoading.value = false;
            isSearching.value = false;
            isInitialLoad.value = false;
        },
        onError: () => {
            isTableLoading.value = false;
            isSearching.value = false;
            ElMessage.error('Failed to load questions');
        }
    });
};

// Change page
const changePage = (page) => {
    if (page < 1 || page > pagination.value.last_page) return;
    if (page === pagination.value.current_page) return;
    
    pagination.value.current_page = page;
    fetchQuestions();
};

// Change per page
const changePerPage = (perPage) => {
    filters.value.per_page = perPage;
    pagination.value.current_page = 1;
    fetchQuestions();
};

// Apply filters
const applyFilters = () => {
    pagination.value.current_page = 1;
    fetchQuestions();
};

// Reset filters
const resetFilters = () => {
    filters.value = {
        education: '',
        class: '',
        subject: '',
        chapter: '',
        topic: '',
        search: '',
        per_page: 10
    };
    pagination.value.current_page = 1;
    fetchQuestions();
};

// Delete question
const deleteQuestion = (questionId) => {
    if (!confirm("Are you sure you want to delete this question?")) return;

    const loading = ElLoading.service({
        lock: true,
        text: 'Deleting question...',
        background: 'rgba(0, 0, 0, 0.7)'
    });

    router.delete(route('question.destroy', questionId), {
        onSuccess: () => {
            loading.close();
            ElMessage.success("Question deleted successfully!");
            // Refresh current page
            fetchQuestions();
        },
        onError: () => {
            loading.close();
            ElMessage.error("Failed to delete the question.");
        }
    });
};

// Get question type label
const getQuestionTypeLabel = (type) => {
    const labels = {
        'standalone': 'Standalone',
        'stimulus_based': 'Stimulus Based'
    };
    return labels[type] || type;
};

// Get format badge class
const getFormatBadgeClass = (format) => {
    const classes = {
        'cq': 'bg-purple-100 text-purple-800',
        'mcq': 'bg-indigo-100 text-indigo-800',
        'mix': 'bg-green-100 text-green-800'
    };
    return classes[format] || 'bg-gray-100 text-gray-800';
};

// ============ Watchers ============

// Watch for filter changes
watch(() => filters.value.education, (newVal) => {
    if (!newVal) {
        filters.value.class = '';
        filters.value.subject = '';
        filters.value.chapter = '';
        filters.value.topic = '';
    } else {
        filters.value.class = '';
    }
    applyFilters();
});

watch(() => filters.value.class, (newVal) => {
    if (!newVal) {
        filters.value.subject = '';
        filters.value.chapter = '';
        filters.value.topic = '';
    } else {
        filters.value.subject = '';
    }
    applyFilters();
});

watch(() => filters.value.subject, (newVal) => {
    if (!newVal) {
        filters.value.chapter = '';
        filters.value.topic = '';
    } else {
        filters.value.chapter = '';
    }
    applyFilters();
});

watch(() => filters.value.chapter, (newVal) => {
    if (!newVal) {
        filters.value.topic = '';
    }
    applyFilters();
});

watch(() => filters.value.topic, () => {
    applyFilters();
});

watch(() => filters.value.search, (newVal, oldVal) => {
    if (newVal !== oldVal) {
        pagination.value.current_page = 1;
        debouncedSearch();
    }
});

// ============ Lifecycle ============
onMounted(() => {
    // Set initial loading state
    isTableLoading.value = true;
    
    // Simulate minimum loading time for better UX
    setTimeout(() => {
        isTableLoading.value = false;
        isInitialLoad.value = false;
    }, 500);

    // If questions are already loaded from server
    if (props.questions && props.questions.length > 0) {
        questions.value = props.questions;
        pagination.value = props.pagination || {
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: props.questions.length,
            from: 1,
            to: props.questions.length
        };
        isTableLoading.value = false;
        isInitialLoad.value = false;
    }
});

// Cleanup
onUnmounted(() => {
    debouncedSearch.cancel();
});

// ============ Expose ============
defineExpose({
    fetchQuestions,
    resetFilters,
    deleteQuestion
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Questions Management" />

        <!-- Flash Message -->
        <div v-if="$page.props.flash?.message"
            class="p-4 mb-6 text-sm text-white rounded-lg bg-gradient-to-r from-green-400 to-blue-500 shadow-lg animate-fadeIn">
            <div class="flex items-center justify-between">
                <span>{{ $page.props.flash?.message }}</span>
                <button @click="$page.props.flash.message = null" class="text-white hover:text-gray-200 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
        </div>

        <div class="container mx-auto px-4 sm:px-6 lg:px-8 mt-5">
            <!-- Search and Filter Section -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-gray-100">
                <div class="flex flex-col lg:flex-row items-center justify-between gap-4">
                    <!-- Education Level -->
                    <el-select v-model="filters.education" placeholder="All Education Levels" clearable
                        class="w-full lg:w-48" size="large" filterable>
                        <el-option v-for="edu in education" :key="edu.id" :label="edu.name" :value="edu.id" />
                    </el-select>

                    <!-- Class -->
                    <el-select v-model="filters.class" placeholder="All Classes" clearable
                        :disabled="!filters.education" class="w-full lg:w-48" size="large" filterable>
                        <el-option v-for="cls in filteredClasses" :key="cls.id" :label="cls.name" :value="cls.id" />
                    </el-select>

                    <!-- Subject -->
                    <el-select v-model="filters.subject" placeholder="All Subjects" clearable 
                        :disabled="!filters.class" class="w-full lg:w-48" size="large" filterable>
                        <el-option v-for="subject in filteredSubjects" :key="subject.id" :label="subject.name"
                            :value="subject.id" />
                    </el-select>

                    <!-- Chapter -->
                    <el-select v-model="filters.chapter" placeholder="All Chapters" clearable
                        :disabled="!filters.subject" class="w-full lg:w-48" size="large" filterable>
                        <el-option v-for="chapter in filteredChapters" :key="chapter.id" :label="chapter.name"
                            :value="chapter.id" />
                    </el-select>

                    <!-- Topic -->
                    <el-select v-model="filters.topic" placeholder="All Topics" clearable 
                        :disabled="!filters.chapter" class="w-full lg:w-48" size="large" filterable>
                        <el-option v-for="topic in filteredTopics" :key="topic.id" :label="topic.name"
                            :value="topic.id" />
                    </el-select>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto mt-4 lg:mt-0">
                        <el-button @click="resetFilters" size="large" class="w-full lg:w-auto">
                            <el-icon class="mr-2"><Refresh /></el-icon>
                            Reset
                        </el-button>
                        <Link :href="route('question.create')">
                            <el-button type="primary" size="large" class="w-full lg:w-auto">
                                <el-icon class="mr-2"><Plus /></el-icon>
                                Add Question
                            </el-button>
                        </Link>
                    </div>
                </div>

                <!-- Search Bar & Per Page -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mt-4 pt-4 border-t border-gray-100">
                    <div class="w-full sm:w-72">
                        <el-input
                            v-model="filters.search"
                            placeholder="Search questions..."
                            clearable
                            size="large"
                            :suffix-icon="Search"
                        />
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-gray-500">Show:</span>
                        <el-select v-model="filters.per_page" @change="changePerPage" size="small" class="w-20">
                            <el-option :value="10" label="10" />
                            <el-option :value="25" label="25" />
                            <el-option :value="50" label="50" />
                            <el-option :value="100" label="100" />
                        </el-select>
                    </div>
                </div>
            </div>

            <!-- Questions Table -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">Questions</h3>
                        <p class="text-sm text-gray-500 mt-1">
                            Showing {{ pagination.from || 0 }} to {{ pagination.to || 0 }} 
                            of {{ pagination.total || 0 }} questions
                        </p>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500">
                            Page {{ pagination.current_page }} of {{ pagination.last_page || 1 }}
                        </span>
                        <div class="flex space-x-2">
                            <el-button 
                                @click="changePage(pagination.current_page - 1)" 
                                :disabled="pagination.current_page <= 1" 
                                size="small"
                            >
                                Previous
                            </el-button>
                            <el-button 
                                @click="changePage(pagination.current_page + 1)" 
                                :disabled="pagination.current_page >= pagination.last_page" 
                                size="small"
                            >
                                Next
                            </el-button>
                        </div>
                    </div>
                </div>

                <!-- Table Loading State -->
                <div v-if="isTableLoading" class="p-8">
                    <div class="flex flex-col items-center justify-center space-y-4 py-12">
                        <div class="relative">
                            <div class="animate-spin rounded-full h-16 w-16 border-4 border-blue-200"></div>
                            <div class="animate-spin rounded-full h-16 w-16 border-4 border-blue-600 border-t-transparent absolute top-0 left-0"></div>
                        </div>
                        <div class="text-center">
                            <p class="text-gray-600 font-medium text-lg">
                                {{ isSearching ? 'Searching questions...' : 'Loading questions...' }}
                            </p>
                            <p class="text-sm text-gray-500 mt-2">
                                {{ isSearching ? 'Please wait while we find matching questions' : 'Please wait while we fetch your data' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Table Content -->
                <div v-else class="overflow-x-auto" style="max-height: calc(100vh - 300px)">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 sticky top-0 z-10">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12">
                                    #
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Question
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                                    Type
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">
                                    Format
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template v-if="questions.length === 0">
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center justify-center text-gray-500">
                                            <svg class="w-20 h-20 mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <p class="text-lg font-medium mb-1">No questions found</p>
                                            <p class="text-sm text-gray-400">Try adjusting your filters or search terms</p>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <template v-else>
                                <tr v-for="(question, index) in questions" :key="question.id"
                                    class="hover:bg-gray-50 transition-colors duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ ((pagination.current_page - 1) * pagination.per_page) + index + 1 }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900 max-w-md">
                                        <!-- Question Type Badge -->
                                        <span class="inline-block text-xs font-medium px-2 py-1 rounded bg-blue-100 text-blue-800 mb-2">
                                            {{ getQuestionTypeLabel(question.question_type) }}
                                        </span>
                                        
                                        <!-- Question Text -->
                                        <div class="font-medium break-words">
                                            <LatexRenderer :content="question.question_text || question.sub_questions?.[0]?.question_text || 'No question text'" />
                                        </div>

                                        <!-- Explanation -->
                                        <div v-if="question.explanation || question.sub_questions?.[0]?.explanation" 
                                            class="text-xs text-gray-500 mt-1">
                                            <span class="font-semibold">Explanation:</span> 
                                            <LatexRenderer :content="question.explanation || question.sub_questions?.[0]?.explanation" />
                                        </div>

                                        <!-- Stimulus Info -->
                                        <div v-if="question.question_type === 'stimulus_based' && question.stimuli?.length" 
                                            class="mt-2 text-xs text-gray-500">
                                            <span class="font-semibold">Stimulus:</span> 
                                            {{ question.stimuli.length }} item(s)
                                        </div>

                                        <!-- MCQ Options -->
                                        <div v-if="question.options?.length" class="mt-2 space-y-1">
                                            <div v-for="(option, oIndex) in question.options" :key="option.id"
                                                class="text-xs flex items-start">
                                                <span class="mr-2 font-medium min-w-6">{{ bengaliChars[oIndex] }}.</span>
                                                <span :class="{ 'font-semibold text-green-600': option.is_correct }">
                                                    <LatexRenderer :content="option.option_text" />
                                                </span>
                                                <span v-if="option.is_correct" class="ml-1 text-green-500">✓</span>
                                            </div>
                                        </div>

                                        <!-- Sub Questions for Stimulus Based -->
                                        <div v-if="question.question_type === 'stimulus_based' && question.sub_questions?.length" 
                                            class="mt-2 space-y-1">
                                            <div v-for="sq in question.sub_questions.slice(0, 3)" :key="sq.id"
                                                class="text-xs flex items-start">
                                                <span class="mr-2 font-medium">{{ sq.question_number }}.</span>
                                                <span>
                                                    <LatexRenderer :content="sq.question_text" />
                                                    <span v-if="sq.format" class="ml-1 text-gray-400">({{ sq.format.toUpperCase() }})</span>
                                                </span>
                                            </div>
                                            <div v-if="question.sub_questions.length > 3" class="text-xs text-gray-400">
                                                + {{ question.sub_questions.length - 3 }} more sub-questions
                                            </div>
                                        </div>

                                        <!-- CQ Options -->
                                        <div v-if="question.cqoptions?.length" class="mt-2 space-y-1">
                                            <div v-for="(option, oIndex) in question.cqoptions" :key="option.id"
                                                class="text-xs flex items-start">
                                                <span class="mr-2 font-medium min-w-6">{{ bengaliChars[oIndex] }}.</span>
                                                <span class="font-medium">
                                                    <LatexRenderer :content="option.cq_text" />
                                                </span>
                                                <span class="ml-2 text-gray-500">({{ option.mark }} pts)</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span :class="getFormatBadgeClass(question.format)" 
                                            class="px-2 py-1 rounded-full text-xs font-medium">
                                            {{ question.format.toUpperCase() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span :class="{
                                            'bg-blue-100 text-blue-800': question.question_type === 'standalone',
                                            'bg-purple-100 text-purple-800': question.question_type === 'stimulus_based'
                                        }" class="px-2 py-1 rounded-full text-xs font-medium">
                                            {{ question.question_type === 'standalone' ? 'Single' : 'Stimulus' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end space-x-1">
                                            <Link :href="route('question.edit', question.id)">
                                                <el-button size="small" type="primary" plain :icon="Edit" 
                                                    class="!p-1.5" title="Edit" />
                                            </Link>
                                            <el-button size="small" type="danger" plain :icon="Delete" 
                                                @click="deleteQuestion(question.id)" 
                                                class="!p-1.5" title="Delete" />
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div v-if="!isTableLoading && questions.length > 0" 
                    class="p-4 border-t border-gray-200 flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div class="text-sm text-gray-500">
                        Showing {{ pagination.from || 0 }} to {{ pagination.to || 0 }} 
                        of {{ pagination.total || 0 }} questions
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <el-button 
                                @click="changePage(1)" 
                                :disabled="pagination.current_page <= 1" 
                                size="small"
                            >
                                First
                            </el-button>
                            <el-button 
                                @click="changePage(pagination.current_page - 1)" 
                                :disabled="pagination.current_page <= 1" 
                                size="small"
                            >
                                Previous
                            </el-button>
                            <span class="text-sm font-medium px-3 py-1 bg-gray-100 rounded">
                                {{ pagination.current_page }} / {{ pagination.last_page || 1 }}
                            </span>
                            <el-button 
                                @click="changePage(pagination.current_page + 1)" 
                                :disabled="pagination.current_page >= pagination.last_page" 
                                size="small"
                            >
                                Next
                            </el-button>
                            <el-button 
                                @click="changePage(pagination.last_page)" 
                                :disabled="pagination.current_page >= pagination.last_page" 
                                size="small"
                            >
                                Last
                            </el-button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Animations */
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

.animate-fadeIn {
    animation: fadeIn 0.3s ease-out;
}

/* Loading Animation */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.animate-spin {
    animation: spin 1s linear infinite;
}

/* Table Styles */
.el-select {
    --el-select-input-focus-border-color: theme('colors.indigo.500');
    --el-select-border-color-hover: theme('colors.indigo.400');
}

.el-button--primary {
    --el-button-bg-color: theme('colors.blue.600');
    --el-button-hover-bg-color: theme('colors.blue.700');
    --el-button-active-bg-color: theme('colors.blue.800');
}

/* Sticky header */
.sticky {
    position: sticky;
    top: 0;
    z-index: 10;
}

/* Responsive */
@media (max-width: 1024px) {
    .el-select,
    .el-button {
        width: 100%;
    }
}

/* Scrollbar styling */
.overflow-x-auto::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
</style>