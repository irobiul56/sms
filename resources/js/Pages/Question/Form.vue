<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";
import QuillEditor from '@/Components/QuillEditor.vue';

const editorRef = ref(null);

const insertTable = () => {
    if (editorRef.value) {
        editorRef.value.insertTable();
    }
};

const getContent = () => {
    console.log(editorRef.value?.getContent());
};

const props = defineProps({
  question: Object,
  classes: Array,
  subjects: Array,
  chapters: Array,
  topics: Array,
  types: Array,
  levels: Array,
  boards: Array
});

// ============ Form Initialization ============
const form = useForm({
  id: props.question?.id || null,
  class_id: props.question?.academic_classes_id ? Number(props.question.academic_classes_id) : null,
  subject_id: props.question?.subject_id ? Number(props.question.subject_id) : null,
  chapter_id: props.question?.chapter_id ? Number(props.question.chapter_id) : null,
  topic_id: props.question?.topic_id ? Number(props.question.topic_id) : null,
  type_id: props.question?.type?.map(t => Number(t.id)) || [],
  level_id: props.question?.level_id ? Number(props.question.level_id) : null,
  board_id: props.question?.board_id ? Number(props.question.board_id) : null,
  format: props.question?.format || 'mcq',
  question_type: props.question?.question_type || 'standalone',
  question_text: props.question?.question_text || '',
  explanation: props.question?.explanation || '',
  mark: props.question?.mark || '',
  display_order: props.question?.display_order || 0,

  // ============ Stimulus Data ============
  stimuli: props.question?.stimuli?.map(stimulus => ({
    id: stimulus.id || null,
    stimulus_type: stimulus.stimulus_type || 'text',
    content: stimulus.content || '',
    media_url: stimulus.media_url || null,
    caption: stimulus.caption || '',
    display_order: stimulus.display_order || 0
  })) || [{ 
    stimulus_type: 'text', 
    content: '', 
    media_url: null, 
    caption: '',
    display_order: 1 
  }],

  // ============ Sub Questions ============
  sub_questions: props.question?.sub_questions?.map(sq => ({
    id: sq.id || null,
    format: sq.format || 'mcq',
    question_text: sq.question_text || '',
    explanation: sq.explanation || '',
    mark: sq.mark || '',
    question_number: sq.question_number || 1,
    display_order: sq.display_order || 0,
    stimulus_id: sq.stimulus_id || null,
    options: sq.options?.map(opt => ({
      id: opt.id || null,
      option_text: opt.option_text || '',
      is_correct: opt.is_correct === true || opt.is_correct === 1 || opt.is_correct === '1',
      display_order: opt.display_order || 0
    })) || [],
    cq_options: sq.cqoptions?.map(cq => ({
      id: cq.id || null,
      cq_text: cq.cq_text || '',
      mark: cq.mark || '',
      display_order: cq.display_order || 0
    })) || []
  })) || [],

  // ============ Legacy Data (Backward Compatibility) ============
  options: props.question?.options?.map(opt => ({
    id: opt.id || null,
    option_text: opt.option_text || '',
    is_correct: opt.is_correct === true || opt.is_correct === 1 || opt.is_correct === '1'
  })) || Array(4).fill().map(() => ({
    option_text: '',
    is_correct: false
  })),

  cq: props.question?.cqoptions?.map(cq => ({
    id: cq.id || null,
    cq_text: cq.cq_text || '',
    mark: cq.mark || ''
  })) || Array(4).fill().map(() => ({
    cq_text: '',
    mark: ''
  }))
});

// ============ UI State ============
const showStimulusSection = ref(form.question_type === 'stimulus_based');
const activeSubQuestionTab = ref(0);

// ============ Computed Properties ============
const filteredSubjects = computed(() => {
  return form.class_id ? 
    props.subjects.filter(s => s.academic_classes_id == form.class_id) : 
    [];
});

const filteredChapters = computed(() => {
  return form.subject_id ? 
    props.chapters.filter(c => c.subject_id == form.subject_id) : 
    [];
});

const filteredTopics = computed(() => {
  return form.chapter_id ? 
    props.topics.filter(t => t.chapter_id == form.chapter_id) : 
    [];
});

const stimulusTypes = [
  { label: 'Text', value: 'text' },
  { label: 'Passage', value: 'passage' },
  { label: 'Image', value: 'image' },
  { label: 'Audio', value: 'audio' },
  { label: 'Video', value: 'video' },
  { label: 'Table', value: 'table' }
];

// ============ Watchers ============
watch(() => form.class_id, (newVal) => {
  if (!newVal) {
    form.subject_id = '';
    form.chapter_id = '';
    form.topic_id = '';
  } else {
    form.subject_id = '';
  }
});

watch(() => form.subject_id, (newVal) => {
  if (!newVal) {
    form.chapter_id = '';
    form.topic_id = '';
  } else {
    form.chapter_id = '';
  }
});

watch(() => form.chapter_id, (newVal) => {
  if (!newVal) {
    form.topic_id = '';
  }
});

watch(() => form.question_type, (newVal) => {
  showStimulusSection.value = newVal === 'stimulus_based';
  if (newVal === 'stimulus_based' && form.sub_questions.length === 0) {
    addSubQuestion();
  }
});

// ============ Helper Methods ============

// --- Stimulus Methods ---
const addStimulus = () => {
  form.stimuli.push({
    stimulus_type: 'text',
    content: '',
    media_url: null,
    caption: '',
    display_order: form.stimuli.length + 1
  });
};

const removeStimulus = (index) => {
  if (form.stimuli.length > 1) {
    form.stimuli.splice(index, 1);
    form.stimuli.forEach((s, i) => s.display_order = i + 1);
  }
};

// --- Sub Question Methods ---
const addSubQuestion = () => {
  const newSubQ = {
    format: 'mcq',
    question_text: '',
    explanation: '',
    mark: '',
    question_number: form.sub_questions.length + 1,
    display_order: form.sub_questions.length,
    stimulus_id: form.stimuli.length > 0 ? form.stimuli[0].id : null,
    options: Array(4).fill().map(() => ({
      option_text: '',
      is_correct: false,
      display_order: 0
    })),
    cq_options: []
  };
  form.sub_questions.push(newSubQ);
  activeSubQuestionTab.value = form.sub_questions.length - 1;
};

const removeSubQuestion = (index) => {
  if (form.sub_questions.length > 1) {
    form.sub_questions.splice(index, 1);
    form.sub_questions.forEach((sq, i) => sq.question_number = i + 1);
    if (activeSubQuestionTab.value >= form.sub_questions.length) {
      activeSubQuestionTab.value = form.sub_questions.length - 1;
    }
  }
};

// --- Option Methods for Sub Questions ---
const addSubQuestionOption = (subQIndex) => {
  form.sub_questions[subQIndex].options.push({
    option_text: '',
    is_correct: false,
    display_order: form.sub_questions[subQIndex].options.length
  });
};

const removeSubQuestionOption = (subQIndex, optionIndex) => {
  if (form.sub_questions[subQIndex].options.length > 2) {
    form.sub_questions[subQIndex].options.splice(optionIndex, 1);
  }
};

// --- CQ Methods for Sub Questions ---
const addSubQuestionCq = (subQIndex) => {
  form.sub_questions[subQIndex].cq_options.push({
    cq_text: '',
    mark: '',
    display_order: form.sub_questions[subQIndex].cq_options.length
  });
};

const removeSubQuestionCq = (subQIndex, cqIndex) => {
  if (form.sub_questions[subQIndex].cq_options.length > 1) {
    form.sub_questions[subQIndex].cq_options.splice(cqIndex, 1);
  }
};

// --- Legacy Methods ---
const addOption = () => form.options.push({ option_text: '', is_correct: false });
const removeOption = (index) => form.options.splice(index, 1);
const addCq = () => form.cq.push({ cq_text: '', mark: '' });
const removeCq = (index) => form.cq.splice(index, 1);

// ============ Form Submission ============
const submitQuestion = () => {
  let submitData = {
    ...form.data(),
    question_type: form.question_type
  };

  if (form.question_type === 'stimulus_based') {
    submitData.stimuli = form.stimuli;
    submitData.sub_questions = form.sub_questions.map(sq => ({
      ...sq,
      options: sq.options.filter(o => o.option_text.trim() !== ''),
      cq_options: sq.cq_options.filter(c => c.cq_text.trim() !== '')
    }));
    delete submitData.options;
    delete submitData.cq;
  } else {
    // For standalone questions
    const subQuestion = {
      format: form.format,
      question_text: form.question_text,
      explanation: form.explanation,
      mark: form.mark,
      question_number: 1
    };

    // Only add options for MCQ and MIX formats
    if (form.format === 'mcq') {
      subQuestion.options = form.options.filter(o => o.option_text.trim() !== '');
    }

    // Only add cq_options for CQ format
    if (form.format === 'cq') {
      subQuestion.cq_options = form.cq.filter(c => c.cq_text.trim() !== '');
    }

    // For MIX format - only send question_text and explanation
    if (form.format === 'mix') {
      // Only send question_text and explanation, no options or cq_options
      subQuestion.options = [];
      subQuestion.cq_options = [];
    }

    submitData.sub_questions = [subQuestion];
    submitData.stimuli = [];
    delete submitData.options;
    delete submitData.cq;
  }

  const url = form.id ? 
    route('question.update', form.id) : 
    route('question.store');

  form[form.id ? 'put' : 'post'](url, {
    preserveScroll: true,
    data: submitData,
    onSuccess: () => {
      router.visit(route('question.index'));
    }
  });
};
</script>

<template>
  <AuthenticatedLayout>
    <Head :title="form.id ? 'Edit Question' : 'Create Question'" />

    <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 py-10 px-4 sm:px-6 lg:px-8">
      <div class="max-w-7xl mx-auto">
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
          <div class="bg-gradient-to-r from-indigo-600 to-blue-500 p-6 text-white">
            <h1 class="text-2xl font-bold">
              {{ form.id ? 'Edit Question' : 'Create New Question' }}
            </h1>
            <p class="text-blue-100 mt-1">
              {{ form.id ? 'Update your question details' : 'Fill in the form to create a new question' }}
            </p>
          </div>

          <div class="p-6 md:p-8">
            <form @submit.prevent="submitQuestion" class="space-y-6">
              <!-- Question Type Selection -->
              <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Question Structure</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Question Type</label>
                    <el-radio-group v-model="form.question_type" class="w-full">
                      <el-radio-button label="standalone" class="flex-1">
                        <span class="flex items-center justify-center">
                          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                          </svg>
                          Standalone
                        </span>
                      </el-radio-button>
                      <el-radio-button label="stimulus_based" class="flex-1">
                        <span class="flex items-center justify-center">
                          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                          </svg>
                          Stimulus Based
                        </span>
                      </el-radio-button>
                    </el-radio-group>
                  </div>
                </div>
              </div>

              <!-- Hierarchy Selection Cards -->
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm hover:shadow-md transition-shadow">
                  <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <span class="text-indigo-600">1.</span> Select Class
                  </label>
                  <el-select
                    v-model="form.class_id"
                    placeholder="Choose Class"
                    class="w-full"
                    clearable
                    filterable
                  >
                    <el-option
                      v-for="cls in props.classes"
                      :key="cls.id"
                      :label="cls.name"
                      :value="Number(cls.id)"
                    />
                  </el-select>
                  <div v-if="form.errors.class_id" class="text-red-500 text-xs mt-1">
                    {{ form.errors.class_id }}
                  </div>
                </div>

                <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm hover:shadow-md transition-shadow"
                  :class="{ 'opacity-70': !form.class_id }">
                  <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <span class="text-indigo-600">2.</span> Select Subject
                  </label>
                  <el-select
                    v-model="form.subject_id"
                    placeholder="Choose Subject"
                    class="w-full"
                    :disabled="!form.class_id"
                    clearable
                    filterable
                  >
                    <el-option
                      v-for="subject in filteredSubjects"
                      :key="subject.id"
                      :label="subject.name"
                      :value="Number(subject.id)"
                    />
                  </el-select>
                  <div v-if="form.errors.subject_id" class="text-red-500 text-xs mt-1">
                    {{ form.errors.subject_id }}
                  </div>
                </div>

                <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm hover:shadow-md transition-shadow"
                  :class="{ 'opacity-70': !form.subject_id }">
                  <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <span class="text-indigo-600">3.</span> Select Chapter
                  </label>
                  <el-select
                    v-model="form.chapter_id"
                    placeholder="Choose Chapter"
                    class="w-full"
                    :disabled="!form.subject_id"
                    clearable
                    filterable
                  >
                    <el-option
                      v-for="chapter in filteredChapters"
                      :key="chapter.id"
                      :label="chapter.name"
                      :value="Number(chapter.id)"
                    />
                  </el-select>
                  <div v-if="form.errors.chapter_id" class="text-red-500 text-xs mt-1">
                    {{ form.errors.chapter_id }}
                  </div>
                </div>

                <div class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm hover:shadow-md transition-shadow"
                  :class="{ 'opacity-70': !form.chapter_id }">
                  <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <span class="text-indigo-600">4.</span> Select Topic
                  </label>
                  <el-select
                    v-model="form.topic_id"
                    placeholder="Choose Topic"
                    class="w-full"
                    :disabled="!form.chapter_id"
                    clearable
                    filterable
                  >
                    <el-option
                      v-for="topic in filteredTopics"
                      :key="topic.id"
                      :label="topic.name"
                      :value="Number(topic.id)"
                    />
                  </el-select>
                  <div v-if="form.errors.topic_id" class="text-red-500 text-xs mt-1">
                    {{ form.errors.topic_id }}
                  </div>
                </div>
              </div>

              <!-- Meta Details -->
              <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Meta Details</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Question Type</label>
                    <el-select v-model="form.type_id" placeholder="Select Type" class="w-full" multiple filterable>
                      <el-option v-for="type in props.types" :key="type.id" :label="type.name" :value="Number(type.id)" />
                    </el-select>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Difficulty Level</label>
                    <el-select v-model="form.level_id" placeholder="Select Level" class="w-full">
                      <el-option v-for="level in props.levels" :key="level.id" :label="level.name" :value="Number(level.id)" />
                    </el-select>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Board</label>
                    <el-select v-model="form.board_id" placeholder="Select Board" class="w-full">
                      <el-option v-for="board in props.boards" :key="board.id" :label="board.name" :value="Number(board.id)" />
                    </el-select>
                  </div>
                </div>
              </div>

              <!-- ============================================ -->
              <!-- STANDALONE QUESTION SECTION                   -->
              <!-- ============================================ -->
              <div v-if="form.question_type === 'standalone'">
                <!-- Format Selection -->
                <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
                  <label class="block text-sm font-medium text-gray-700 mb-3">Format</label>
                  <el-radio-group v-model="form.format" class="w-full">
                    <el-radio-button label="mcq" class="flex-1">MCQ</el-radio-button>
                    <el-radio-button label="cq" class="flex-1">CQ</el-radio-button>
                    <el-radio-button label="mix" class="flex-1">Mixed</el-radio-button>
                  </el-radio-group>
                </div>

                <!-- Question Content -->
                <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
                  <div class="space-y-4">
                    <!-- Question Text - Show for all formats -->
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">Question Text</label>
                      <el-input v-model="form.question_text" ref="editorRef" type="textarea" :rows="3" />
                      <div v-if="form.errors.question_text" class="text-red-500 text-xs mt-1">
                        {{ form.errors.question_text }}
                      </div>
                    </div>

                    <!-- Explanation - Show for all formats -->
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">Explanation (Optional)</label>
                      <el-input v-model="form.explanation" type="textarea" :rows="3" 
                        placeholder="Add explanation if needed..." class="w-full" />
                    </div>

                    <!-- MCQ Options - Only for MCQ format -->
                    <div v-if="form.format === 'mcq'">
                      <div class="flex justify-between items-center mb-3">
                        <label class="block text-sm font-medium text-gray-700">Options</label>
                        <el-button @click="addOption" size="small" type="primary">Add Option</el-button>
                      </div>
                      <div v-for="(option, index) in form.options" :key="index" class="flex items-center gap-3 mb-2">
                        <el-input v-model="option.option_text" :placeholder="`Option ${String.fromCharCode(65 + index)}`" 
                          class="flex-1" />
                        <el-checkbox v-model="option.is_correct">Correct</el-checkbox>
                        <el-button @click="removeOption(index)" type="danger" plain size="small" 
                          :disabled="form.options.length <= 2">✕</el-button>
                      </div>
                    </div>

                    <!-- CQ Questions - Only for CQ format -->
                    <div v-if="form.format === 'cq'">
                      <div class="flex justify-between items-center mb-3">
                        <label class="block text-sm font-medium text-gray-700">Constructed Questions</label>
                        <el-button @click="addCq" size="small" type="success">Add Question</el-button>
                      </div>
                      <div v-for="(cq, index) in form.cq" :key="index" class="flex items-center gap-3 mb-2">
                        <el-input v-model="cq.cq_text" :placeholder="`Question ${index + 1}`" class="flex-1" />
                        <el-input v-model="cq.mark" placeholder="Marks" type="number" class="w-24" />
                        <el-button @click="removeCq(index)" type="danger" plain size="small" 
                          :disabled="form.cq.length <= 2">✕</el-button>
                      </div>
                    </div>

                    <!-- Mix format - Show nothing extra, only question_text and explanation -->
                    <div v-if="form.format === 'mix'" class="text-sm text-gray-500 italic">
                      <p>Mixed format question - only question text and explanation will be saved.</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- ============================================ -->
              <!-- STIMULUS BASED QUESTION SECTION              -->
              <!-- ============================================ -->
              <div v-if="form.question_type === 'stimulus_based'">
                <!-- Stimulus Section -->
                <div class="bg-white rounded-lg border-2 border-indigo-200 p-6 shadow-sm">
                  <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-indigo-800 flex items-center">
                      <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                      </svg>
                      Stimulus (Passage/Text/Image)
                    </h3>
                    <el-button @click="addStimulus" size="small" type="primary">Add Stimulus</el-button>
                  </div>

                  <div v-for="(stimulus, sIndex) in form.stimuli" :key="sIndex" 
                    class="border border-gray-200 rounded-lg p-4 mb-4 bg-gray-50">
                    <div class="flex justify-between items-start mb-3">
                      <span class="text-sm font-medium text-gray-600">Stimulus {{ sIndex + 1 }}</span>
                      <el-button @click="removeStimulus(sIndex)" type="danger" plain size="small" 
                        :disabled="form.stimuli.length <= 1">Remove</el-button>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                      <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Type</label>
                        <el-select v-model="stimulus.stimulus_type" placeholder="Type" class="w-full" size="small">
                          <el-option v-for="type in stimulusTypes" :key="type.value" 
                            :label="type.label" :value="type.value" />
                        </el-select>
                      </div>
                      <div v-if="['image', 'audio', 'video'].includes(stimulus.stimulus_type)">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Media URL</label>
                        <el-input v-model="stimulus.media_url" placeholder="URL" class="w-full" size="small" />
                      </div>
                    </div>

                    <div class="mt-3">
                      <label class="block text-xs font-medium text-gray-600 mb-1">Content</label>
                      <QuillEditor v-model="stimulus.content"/>
                    </div>

                    <div class="mt-3">
                      <label class="block text-xs font-medium text-gray-600 mb-1">Caption (Optional)</label>
                      <el-input v-model="stimulus.caption" placeholder="Caption..." class="w-full" size="small" />
                    </div>
                  </div>
                </div>

                <!-- Sub Questions Section -->
                <div class="bg-white rounded-lg border-2 border-blue-200 p-6 shadow-sm">
                  <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-blue-800 flex items-center">
                      <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                      </svg>
                      Sub Questions
                    </h3>
                    <el-button @click="addSubQuestion" size="small" type="success">Add Question</el-button>
                  </div>

                  <el-tabs v-model="activeSubQuestionTab" type="card" class="sub-question-tabs">
                    <el-tab-pane v-for="(sq, sqIndex) in form.sub_questions" :key="sqIndex" 
                      :label="`Q${sqIndex + 1}`" :name="sqIndex">
                      
                      <div class="p-4 border border-gray-200 rounded-lg">
                        <div class="flex justify-between items-start mb-4">
                          <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                              <label class="block text-xs font-medium text-gray-600 mb-1">Format</label>
                              <el-radio-group v-model="sq.format" size="small">
                                <el-radio-button label="mcq">MCQ</el-radio-button>
                                <el-radio-button label="cq">CQ</el-radio-button>
                              </el-radio-group>
                            </div>
                            <div>
                              <label class="block text-xs font-medium text-gray-600 mb-1">Question Number</label>
                              <el-input v-model="sq.question_number" type="number" size="small" class="w-20" />
                            </div>
                            <div>
                              <label class="block text-xs font-medium text-gray-600 mb-1">Marks</label>
                              <el-input v-model="sq.mark" type="number" placeholder="Marks" size="small" class="w-full" />
                            </div>
                          </div>
                          <el-button @click="removeSubQuestion(sqIndex)" type="danger" plain size="small" 
                            :disabled="form.sub_questions.length <= 1" class="ml-3">
                            Remove
                          </el-button>
                        </div>

                        <!-- Sub Question Text -->
                        <div class="mb-3">
                          <label class="block text-xs font-medium text-gray-600 mb-1">Question Text</label>
                          <el-input v-model="sq.question_text" type="textarea" :rows="2" 
                            :placeholder="`Question ${sqIndex + 1}`" class="w-full" />
                        </div>

                        <!-- MCQ Options for Sub Question -->
                        <div v-if="sq.format === 'mcq'">
                          <div class="flex justify-between items-center mb-2">
                            <label class="block text-xs font-medium text-gray-600">Options</label>
                            <el-button @click="addSubQuestionOption(sqIndex)" size="small" type="primary">Add Option</el-button>
                          </div>
                          <div v-for="(opt, oIndex) in sq.options" :key="oIndex" class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-medium text-gray-500 w-6">{{ String.fromCharCode(65 + oIndex) }}.</span>
                            <el-input v-model="opt.option_text" placeholder="Option text" size="small" class="flex-1" />
                            <el-checkbox v-model="opt.is_correct" size="small">Correct</el-checkbox>
                            <el-button @click="removeSubQuestionOption(sqIndex, oIndex)" type="danger" plain size="small" 
                              :disabled="sq.options.length <= 2">✕</el-button>
                          </div>
                        </div>

                        <!-- CQ Options for Sub Question -->
                        <div v-if="sq.format === 'cq'">
                          <div class="flex justify-between items-center mb-2">
                            <label class="block text-xs font-medium text-gray-600">Sub Questions</label>
                            <el-button @click="addSubQuestionCq(sqIndex)" size="small" type="success">Add</el-button>
                          </div>
                          <div v-for="(cq, cIndex) in sq.cq_options" :key="cIndex" class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-medium text-gray-500 w-6">{{ String.fromCharCode(97 + cIndex) }})</span>
                            <el-input v-model="cq.cq_text" placeholder="Sub question text" size="small" class="flex-1" />
                            <el-input v-model="cq.mark" placeholder="Marks" type="number" size="small" class="w-10" />
                            <el-button @click="removeSubQuestionCq(sqIndex, cIndex)" type="danger" plain size="small" 
                              :disabled="sq.cq_options.length <= 1">✕</el-button>
                          </div>
                        </div>
                      </div>
                    </el-tab-pane>
                  </el-tabs>
                </div>
              </div>

              <!-- ============================================ -->
              <!-- FORM ACTIONS                                  -->
              <!-- ============================================ -->
              <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex justify-end gap-4 rounded-b-2xl">
                <el-button 
                  @click="router.visit(route('question.index'))" 
                  :disabled="form.processing"
                  class="px-6 py-2 border border-gray-300 rounded-lg shadow-sm hover:bg-gray-100 transition-colors"
                >
                  Cancel
                </el-button>
                <el-button 
                  type="primary" 
                  native-type="submit"
                  :loading="form.processing"
                  class="px-6 py-2 bg-gradient-to-r from-indigo-600 to-blue-500 hover:from-indigo-700 hover:to-blue-600 rounded-lg shadow-md text-white transition-all hover:shadow-lg"
                >
                  {{ form.id ? 'Update Question' : 'Create Question' }}
                </el-button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style>
.el-select .el-input__wrapper {
  @apply shadow-sm hover:shadow-md transition-shadow;
}

.el-radio-button__orig-radio:checked + .el-radio-button__inner {
  @apply bg-indigo-600 border-indigo-600 text-white shadow-md;
}

.el-textarea__inner {
  @apply py-2 px-3 border-gray-300 rounded-lg shadow-sm hover:shadow-md transition-shadow;
}

.el-button {
  @apply transition-all duration-200;
}

.el-button--primary {
  @apply bg-gradient-to-r from-indigo-600 to-blue-500 border-transparent hover:from-indigo-700 hover:to-blue-600;
}

.sub-question-tabs .el-tabs__item {
  @apply font-medium;
}

.sub-question-tabs .el-tabs__item.is-active {
  @apply text-indigo-600 border-b-2 border-indigo-600;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>