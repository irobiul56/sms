<template>
    <Head title="User Dashboard" />

    <UserDashboardLayout>
        <!-- Loading Overlay -->
        <div v-if="loading || isInitialLoad" class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50">
            <div class="bg-white p-6 rounded-lg shadow-lg flex flex-col items-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
                <p class="text-gray-700 font-medium">Loading questions...</p>
            </div>
        </div>

        <div class="bg-white min-h-screen">
            <!-- Main Content -->
            <div class="w-full max-w-3xl p-4">
                <!-- Bottom Action Buttons -->
                <div class="flex items-center justify-between mt-4">
                    <div class="py-1 flex gap-1 items-center">
                        <p class="bg-gray-100 px-2 py-1 rounded">
                            Selected: {{ selectedQuestions.length }}/{{ totalValidQuestions }}
                        </p>
                        <button @click="toggleSelectAll" class="bg-gray-200 hover:bg-gray-300 px-2 py-1 rounded">
                            {{ isSelectAll ? 'Deselect All' : 'Select All' }}
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <button @click="saveQuestions"
                            class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded">
                            <span class="flex items-center gap-1">
                                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 448 512"
                                    height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M433.941 129.941l-83.882-83.882A48 48 0 0 0 316.118 32H48C21.49 32 0 53.49 0 80v352c0 26.51 21.49 48 48 48h352c26.51 0 48-21.49 48-48V163.882a48 48 0 0 0-14.059-33.941zM224 416c-35.346 0-64-28.654-64-64 0-35.346 28.654-64 64-64s64 28.654 64 64c0 35.346-28.654 64-64 64zm96-304.52V212c0 6.627-5.373 12-12 12H76c-6.627 0-12-5.373-12-12V108c0-6.627 5.373-12 12-12h228.52c3.183 0 6.235 1.264 8.485 3.515l3.48 3.48A11.996 11.996 0 0 1 320 111.48z">
                                    </path>
                                </svg>
                                Save as "{{ examName }}"
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Notice Banner -->
                <div class="text-center bg-yellow-50 p-2 mb-4">
                    <p>এই লিস্টে আপনার জন্য বাছাই করা <span class="text-green-600 font-bold">{{ filters.count }}টি ইউনিক
                            প্রশ্ন</span> তৈরি করে দেওয়া হয়েছে। আপনি চাইল এই প্রশ্নগুলো সিলেক্ট করে সেভ করতে পারেন। অথবা
                        অ্যাডভান্স ফিল্টার করেও সেভ করতে পারেন।</p>
                </div>

                <!-- Questions Container - Grouped by Board -->
                <div class="space-y-6">
                    <div v-for="(boardQuestions, boardName) in filteredGroupedQuestions" :key="boardName"
                        class="bg-white rounded-lg border">
                        <div class="divide-y">
                            <div v-for="question in boardQuestions" :key="question.id"
                                class="p-5 cursor-pointer"
                                :class="{ 'border-green-500 border-2 rounded': selectedQuestions.includes(question.id) }"
                                @click="toggleQuestionSelection(question.id)">
                                
                                <!-- Question Header -->
                                <div class="mb-2 flex justify-between items-center">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <input type="checkbox" 
                                            :checked="selectedQuestions.includes(question.id)"
                                            @change="toggleQuestionSelection(question.id)"
                                            @click.stop
                                            class="h-4 w-4 text-blue-600 rounded">
                                        <div class="text-xs flex items-center gap-2 flex-wrap">
                                            <mark><i>
                                                <span class="mr-1">
                                                    <span class="ml-1">{{ question.board?.name || 'Important' }}</span>
                                                    <span>'{{ question.board?.year || '***' }}</span>
                                                </span>
                                            </i></mark>
                                            
                                            <!-- ===== NEW: Level Badge ===== -->
                                            <span v-if="question.lavel" 
                                                class="text-xs px-2 py-0.5 rounded-full"
                                                :class="{
                                                    'bg-red-100 text-red-800': question.lavel.name === 'Easy',
                                                    'bg-yellow-100 text-yellow-800': question.lavel.name === 'Medium',
                                                    'bg-green-100 text-green-800': question.lavel.name === 'বহুনির্বাচনি প্রশ্ন',
                                                    'bg-purple-100 text-purple-800': question.lavel.name === 'Expert',
                                                    'bg-blue-100 text-blue-800': !['Easy', 'Medium', 'বহুনির্বাচনি প্রশ্ন', 'Expert'].includes(question.lavel.name)
                                                }">
                                                {{ question.lavel.name }}
                                            </span>
                                            
                                            <span class="text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded text-xs">
                                                {{ getChapterName(question.chapter_id) }}
                                            </span>
                                            <span class="text-xs px-2 py-0.5 rounded-full" 
                                                :class="{
                                                    'bg-purple-100 text-purple-800': question.question_type === 'stimulus_based',
                                                    'bg-green-100 text-green-800': question.question_type === 'standalone'
                                                }">
                                                {{ question.question_type === 'stimulus_based' ? 'উদ্দীপক' : 'Standalone' }}
                                            </span>
                                            <span v-if="question.question_type === 'stimulus_based'" 
                                                class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full">
                                                {{ question.sub_questions?.length || 0 }}টি প্রশ্ন
                                            </span>
                                        </div>
                                        <button v-if="question.question_type === 'standalone'" 
                                            @click.stop="toggleExplanation(question.id)"
                                            class="shrink-0 bg-green-600 hover:bg-green-500 text-white rounded-full text-xs px-1.5 py-0.5">
                                            ব্যাখা দেখুন
                                        </button>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div title="প্রশ্নে ভুল পেলে রিপোর্ট করুন"
                                            class="border p-1 rounded text-gray-500 hover:border-green-500 hover:text-green-500">
                                            <svg stroke="currentColor" fill="currentColor" stroke-width="0"
                                                viewBox="0 0 512 512" height="1em" width="1em"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path
                                                    d="M448 0H64C28.7 0 0 28.7 0 64v288c0 35.3 28.7 64 64 64h96v84c0 9.8 11.2 15.5 19.1 9.7L304 416h144c35.3 0 64-28.7 64-64V64c0-35.3-28.7-64-64-64z">
                                                </path>
                                            </svg>
                                        </div>
                                        <div class="flex flex-wrap gap-1">
                                            <span v-if="question.topic" class="text-xs p-1 border rounded-full px-1.5 py-0.5">
                                                {{ question.topic.name }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- ========================================== -->
                                <!-- STANDALONE QUESTION                       -->
                                <!-- ========================================== -->
                                <template v-if="question.question_type === 'standalone'">
                                    <div class="flex items-baseline">
                                        <span class="mr-1">{{ getQuestionDisplayIndex(question, boardQuestions) }}.</span>
                                        <div class="flex-1">
                                            <div class="mb-2">
                                                <LatexRenderer :content="getQuestionText(question)" />
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="hasMcqOptions(question)" class="lg:grid grid-cols-2 gap-2">
                                        <div v-for="(option, optIndex) in getMcqOptions(question)" :key="option.id"
                                            :class="{ 'bg-green-100 border-green-300': option.is_correct }"
                                            class="bg-gray-100 my-2 lg:m-0 rounded-lg p-2 flex gap-1 items-center">
                                            <div :class="{ 'bg-green-600 text-white': option.is_correct }"
                                                class="flex items-center justify-center h-5 w-5 border border-gray-400 rounded-full p-0.5">
                                                {{ bengaliChars[optIndex] }}
                                            </div>
                                            <div>
                                                <LatexRenderer :content="option.option_text" />
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="hasCqOptions(question)" class="lg:grid grid-cols-1 gap-2">
                                        <div v-for="(cqoption, optIndex) in getCqOptions(question)" :key="cqoption.id"
                                            class="bg-gray-100 my-2 lg:m-0 rounded-lg p-2 flex gap-1 items-center">
                                            <div class="flex items-center justify-center h-5 w-5 border border-gray-400 rounded-full p-0.5">
                                                {{ bengaliChars[optIndex] }}
                                            </div>
                                            <div>
                                                <LatexRenderer :content="cqoption.cq_text" />
                                            </div>
                                            <span class="ml-2 text-xs text-gray-500">({{ cqoption.mark }} pts)</span>
                                        </div>
                                    </div>

                                    <div v-if="getExplanation(question) && question.showExplanation"
                                        class="mt-1.5 bg-green-50 py-2 px-3 text-green-700 rounded transition-all duration-300">
                                        <div>
                                            <LatexRenderer :content="getExplanation(question)" />
                                        </div>
                                    </div>
                                </template>

                                <!-- ========================================== -->
                                <!-- STIMULUS BASED QUESTION                   -->
                                <!-- ========================================== -->
                                <template v-if="question.question_type === 'stimulus_based'">
                                    <div v-if="hasStimuli(question)" class="mb-4">
                                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                            <div class="text-sm font-medium text-gray-700 mb-2">
                                                <span class="bg-blue-100 text-blue-800 px-2 py-0.5 rounded">উদ্দীপক</span>
                                            </div>
                                            
                                            <div v-for="stimulus in getStimuli(question)" :key="stimulus.id" 
                                                class="mb-3 last:mb-0">
                                                <div class="prose prose-sm max-w-none">
                                                    <LatexRenderer :content="stimulus.content" />
                                                </div>
                                                
                                                <div v-if="stimulus.media_url" class="mt-2">
                                                    <img :src="stimulus.media_url" 
                                                         alt="Stimulus media" 
                                                         class="max-w-full h-auto rounded-lg border border-gray-200 max-h-64 object-contain">
                                                </div>
                                                
                                                <div v-if="stimulus.caption" class="mt-1 text-xs text-gray-500 italic">
                                                    {{ stimulus.caption }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="hasSubQuestions(question)" class="mt-3 space-y-4">
                                        <div v-for="sq in getSubQuestions(question)" :key="sq.id"
                                            class="border-l-4 border-blue-400 pl-4 bg-blue-50 rounded-r-lg p-3">
                                            <div class="flex items-start">
                                                <span class="font-medium text-sm text-blue-700 mr-2 min-w-8">
                                                    {{ sq.question_number }}.
                                                </span>
                                                <div class="flex-1">
                                                    <div class="mb-1">
                                                        <LatexRenderer :content="sq.question_text" />
                                                    </div>
                                                    <div class="flex items-center gap-2 mt-1">
                                                        <span v-if="sq.format" 
                                                            class="text-xs px-2 py-0.5 rounded-full"
                                                            :class="{
                                                                'bg-purple-100 text-purple-700': sq.format === 'mcq',
                                                                'bg-orange-100 text-orange-700': sq.format === 'cq'
                                                            }">
                                                            {{ sq.format.toUpperCase() }}
                                                        </span>
                                                        <span v-if="sq.mark" class="text-xs text-gray-500">
                                                            ({{ sq.mark }} marks)
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div v-if="sq.format === 'mcq' && sq.options?.length" 
                                                class="mt-2 grid grid-cols-2 gap-1">
                                                <div v-for="(opt, optIdx) in sq.options" :key="opt.id"
                                                    :class="{ 'bg-green-100 border-green-300': opt.is_correct }"
                                                    class="bg-white rounded p-2 flex items-center gap-1 text-sm border">
                                                    <span class="font-medium text-gray-600 min-w-6">
                                                        {{ String.fromCharCode(65 + optIdx) }}.
                                                    </span>
                                                    <LatexRenderer :content="opt.option_text" />
                                                    <span v-if="opt.is_correct" class="text-green-500 ml-1">✓</span>
                                                </div>
                                            </div>

                                            <div v-if="sq.format === 'cq' && sq.cqoptions?.length" 
                                                class="mt-2 space-y-1">
                                                <div v-for="(cq, cqIdx) in sq.cqoptions" :key="cq.id"
                                                    class="bg-white rounded p-2 flex items-center gap-1 text-sm border">
                                                    <span class="font-medium text-gray-600 min-w-6">
                                                        {{ String.fromCharCode(97 + cqIdx) }})
                                                    </span>
                                                    <LatexRenderer :content="cq.cq_text" />
                                                    <span class="ml-2 text-xs text-gray-500">({{ cq.mark }} pts)</span>
                                                </div>
                                            </div>
                                            
                                            <div v-if="sq.explanation && question.showExplanation"
                                                class="mt-2 bg-green-50 py-1.5 px-2 text-green-700 rounded text-sm border border-green-200">
                                                <span class="font-medium">ব্যাখা:</span>
                                                <LatexRenderer :content="sq.explanation" />
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="!hasSubQuestions(question) && hasStimuli(question)" 
                                        class="text-center text-gray-500 text-sm py-2">
                                        <i>এই উদ্দীপকের জন্য কোনো প্রশ্ন নেই</i>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Action Buttons -->
                <div class="flex items-center justify-between mt-4">
                    <div class="py-1 flex gap-1 items-center">
                        <p class="bg-gray-100 px-2 py-1 rounded">Selected: {{ selectedQuestions.length }}/{{ totalValidQuestions }}</p>
                        <button @click="toggleSelectAll" class="bg-gray-200 hover:bg-gray-300 px-2 py-1 rounded">
                            {{ isSelectAll ? 'Deselect All' : 'Select All' }}
                        </button>
                    </div>
                    <button @click="scrollToTop" class="bg-gray-200 hover:bg-gray-300 px-2.5 py-1 rounded flex gap-1 items-center">
                        Top
                        <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 384 512"
                            class="text-gray-600" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M214.6 41.4c-12.5-12.5-32.8-12.5-45.3 0l-160 160c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L160 141.2V448c0 17.7 14.3 32 32 32s32-14.3 32-32V141.2L329.4 246.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3l-160-160z">
                            </path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Sidebar (Filter Menu) -->
            <div
                class="hidden lg:block fixed top-20 inset-y-0 right-0 w-80 bg-white overflow-y-auto border-l z-10 font-9">
                <div class="p-4">
                    <div
                        class="border rounded-t text-lg font-bold py-2 text-center flex justify-center items-center gap-2">
                        এডভান্সড ফিল্টার মেনু
                    </div>

                    <!-- Selected Topics Display -->
                    <div v-if="selectedTopics.length > 0" class="bg-blue-50 p-3 rounded-lg border border-blue-200 mb-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-blue-800 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                                সিলেক্টেড টপিক ({{ selectedTopics.length }})
                            </span>
                            <button @click="clearTopicFilters" class="text-xs text-red-500 hover:text-red-700 hover:bg-red-50 px-2 py-1 rounded transition-colors">
                                সব বাদ দিন
                            </button>
                        </div>
                        <div class="flex flex-wrap gap-2 max-h-32 overflow-y-auto">
                            <span v-for="topic in selectedTopicsDetails" :key="topic.id" 
                                class="inline-flex items-center bg-white px-3 py-1 rounded-full border border-blue-200 text-sm text-blue-700 shadow-sm hover:shadow transition-all">
                                <span class="mr-1 text-xs text-gray-500">{{ topic.chapter_name }}:</span>
                                <span class="font-medium">{{ topic.name }}</span>
                                <button @click="removeSelectedTopic(topic.id)" 
                                    class="ml-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-full w-5 h-5 flex items-center justify-center transition-colors">
                                    ×
                                </button>
                            </span>
                        </div>
                    </div>

                    <!-- Question Type Filter -->
                    <div class="bg-white border rounded-lg my-4">
                        <div class="p-2 bg-gray-100 flex justify-between items-center">
                            <p><span class="font-bold">প্রশ্নের ধরণ</span></p>
                            <button v-if="selectedQuestionTypes.length > 0" @click="clearQuestionTypeFilters"
                                class="text-xs bg-gray-200 hover:bg-gray-300 px-2 py-1 rounded">
                                Clear All
                            </button>
                        </div>
                        <div class="border-t text-gray-700">
                            <div class="p-2">
                                <button v-if="quetype.length > 0 && selectedQuestionTypes.length < quetype.length"
                                    @click="selectAllQuestionTypes"
                                    class="text-xs bg-gray-200 hover:bg-gray-300 px-2 py-1 rounded mb-2">
                                    Select All Types
                                </button>
                                <div v-for="(typeItem, index) in quetype" :key="index" class="mb-1">
                                    <div @click="toggleQuestionType(typeItem.id)" :class="{
                                        'bg-blue-100 border-blue-300': selectedQuestionTypes.includes(typeItem.id),
                                        'hover:bg-gray-100': !selectedQuestionTypes.includes(typeItem.id)
                                    }"
                                        class="px-2 py-1.5 flex items-center cursor-pointer rounded border transition-colors">
                                        <input type="checkbox" :checked="selectedQuestionTypes.includes(typeItem.id)"
                                            class="mr-2 rounded cursor-pointer" @click.stop>
                                        <span class="w-full">{{ typeItem.name }}</span>
                                        <span v-if="selectedQuestionTypes.includes(typeItem.id)"
                                            class="ml-2 text-blue-600">
                                            ✓
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Question Categories Filter -->
                    <div v-if="filters.type === 'mix'" class="bg-white border rounded-lg my-4">
                        <div class="p-2 bg-gray-100 flex justify-between items-center">
                            <p><span class="font-bold">প্রশ্ন কেটেগোরিস</span></p>
                            <button v-if="selectedLevels.length > 0" @click="clearLevelFilters"
                                class="text-xs bg-gray-200 hover:bg-gray-300 px-2 py-1 rounded">
                                Clear All
                            </button>
                        </div>
                        <div class="border-t text-gray-700">
                            <div class="p-2">
                                <button v-if="level.length > 0 && selectedLevels.length < level.length"
                                    @click="selectAllLevels"
                                    class="text-xs bg-gray-200 hover:bg-gray-300 px-2 py-1 rounded mb-2">
                                    Select All Types
                                </button>
                                <div v-for="(levelItem, index) in level" :key="index" class="mb-1">
                                    <div @click="toggleLevel(levelItem.id)" :class="{
                                        'bg-blue-100 border-blue-300': selectedLevels.includes(levelItem.id),
                                        'hover:bg-gray-100': !selectedLevels.includes(levelItem.id)
                                    }"
                                        class="px-2 py-1.5 flex items-center cursor-pointer rounded border transition-colors">
                                        <input type="checkbox" :checked="selectedLevels.includes(levelItem.id)"
                                            class="mr-2 rounded cursor-pointer" @click.stop>
                                        <span class="w-full">{{ levelItem.name }}</span>
                                        <span v-if="selectedLevels.includes(levelItem.id)" class="ml-2 text-blue-600">
                                            ✓
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Topic Filter -->
                    <div class="bg-white border rounded-lg my-4">
                        <div class="p-2 bg-gray-100 flex justify-between items-center">
                            <p><span class="font-bold">অধ্যায়:</span> 
                                <span class="text-blue-600">{{ selectedTopics.length }}</span> টপিক সিলেক্টেড
                            </p>
                            <button v-if="selectedTopics.length > 0" @click="clearTopicFilters"
                                class="text-xs bg-gray-200 hover:bg-gray-300 px-2 py-1 rounded">
                                Clear All
                            </button>
                        </div>
                        <div class="border-t text-gray-700">
                            <div class="p-2 max-h-60 overflow-y-auto">
                                <div v-for="chapter in chapters" :key="chapter.id" class="mb-4">
                                    <div class="font-semibold mb-2 text-blue-600 border-b pb-1 flex justify-between items-center">
                                        <span>{{ chapter.name }}</span>
                                        <span class="text-xs text-gray-500">
                                            {{ chapter.topic?.filter(t => selectedTopics.includes(t.id)).length || 0 }}/{{ chapter.topic?.length || 0 }}
                                        </span>
                                    </div>
                                    <div class="space-y-1 ml-2">
                                        <div v-if="chapter.topic?.length > 0">
                                            <div v-for="topic in chapter.topic" :key="topic.id" class="mb-1">
                                                <div @click="toggleTopic(topic.id)" :class="{
                                                    'bg-blue-100 border-blue-300': selectedTopics.includes(topic.id),
                                                    'hover:bg-gray-100': !selectedTopics.includes(topic.id)
                                                }"
                                                    class="px-2 py-1.5 flex items-center cursor-pointer rounded border transition-colors">
                                                    <input type="checkbox" 
                                                        :checked="selectedTopics.includes(topic.id)"
                                                        class="mr-2 rounded cursor-pointer w-4 h-4 text-blue-600" 
                                                        @click.stop>
                                                    <span class="w-full">{{ topic.name }}</span>
                                                    <span v-if="selectedTopics.includes(topic.id)" class="ml-2 text-blue-600 font-bold">
                                                        ✓
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="text-gray-500 text-sm italic pl-2">
                                            এই অধ্যায়ে কোনো টপিক নেই
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-4 pt-4 border-t">
                                    <button 
                                        @click="selectAllTopics"
                                        class="text-xs bg-blue-100 hover:bg-blue-200 px-3 py-2 rounded w-full transition-colors flex items-center justify-center gap-2">
                                        <span v-if="selectedTopics.length === totalTopicsCount">
                                            সব বাদ দিন ({{ totalTopicsCount }})
                                        </span>
                                        <span v-else>
                                            সব সিলেক্ট করুন ({{ totalTopicsCount }})
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Board and Year Filter -->
                    <div class="bg-white border rounded-lg my-4">
                        <div class="bg-gray-100 p-2 flex justify-between items-center">
                            <p class="font-bold">বোর্ড</p>
                        </div>
                        <div class="border-t text-gray-700">
                            <div class="p-2">
                                <div class="p-2">
                                    <label class="block mb-1 text-sm font-medium">Year</label>
                                    <div class="flex flex-wrap gap-2">
                                        <button v-for="year in availableYears" :key="year" @click="toggleYear(year)"
                                            :class="{
                                                'bg-blue-500 text-white': selectedYears.includes(year),
                                                'bg-gray-200': !selectedYears.includes(year)
                                            }" class="px-3 py-1 rounded text-sm">
                                            {{ year }}
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-2">
                                    <div v-for="board in availableBoards" :key="board.id" class="mb-1">
                                        <label @click="toggleBoard(board.id)" :class="{
                                            'bg-blue-100 border-blue-300': selectedBoards.includes(board.id),
                                            'hover:bg-gray-100': !selectedBoards.includes(board.id)
                                        }"
                                            class="px-2 py-1.5 flex items-center cursor-pointer rounded border transition-colors">
                                            <input type="checkbox" :checked="selectedBoards.includes(board.id)"
                                                class="mr-2 rounded cursor-pointer" @click.stop>
                                            <span class="w-full">{{ board.name }} ({{ board.year }})</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </UserDashboardLayout>
</template>

<script setup>
import LatexRenderer from '@/Components/LatexRenderer.vue';
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue';
import { Head } from '@inertiajs/vue3';
import { router, usePage } from '@inertiajs/vue3';
import { ref, watch, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { debounce } from 'lodash';

const props = defineProps({
    questions: Array,
    groupedQuestions: Object,
    availableYears: Array,
    chapters: Array,
    filters: Object,
    boards: Array,
    quetype: Array,
    level: Array,
});

const examName = computed(() => {
    return usePage().props.exam_name || '';
});

// ============ Helper Functions ============

const getQuestionText = (question) => {
    if (!question) return 'No question';
    if (question.question_type === 'standalone') {
        return question.question_text || question.sub_questions?.[0]?.question_text || 'No question text';
    }
    return null;
};

const hasMcqOptions = (question) => {
    if (!question) return false;
    if (question.question_type === 'standalone') {
        return question.options?.length > 0;
    }
    return false;
};

const getMcqOptions = (question) => {
    if (!question) return [];
    if (question.question_type === 'standalone') {
        return question.options || [];
    }
    return [];
};

const hasCqOptions = (question) => {
    if (!question) return false;
    if (question.question_type === 'standalone') {
        return question.cqoptions?.length > 0;
    }
    return false;
};

const getCqOptions = (question) => {
    if (!question) return [];
    if (question.question_type === 'standalone') {
        return question.cqoptions || [];
    }
    return [];
};

const hasStimuli = (question) => {
    return question?.question_type === 'stimulus_based' && question.stimuli?.length > 0;
};

const getStimuli = (question) => {
    return question?.stimuli || [];
};

const hasSubQuestions = (question) => {
    return question?.question_type === 'stimulus_based' && question.sub_questions?.length > 0;
};

const getSubQuestions = (question) => {
    return question?.sub_questions || [];
};

const getExplanation = (question) => {
    if (!question) return null;
    if (question.question_type === 'standalone') {
        return question.explanation || question.sub_questions?.[0]?.explanation || null;
    }
    return null;
};

const isValidQuestion = (question) => {
    if (!question) return false;
    if (!question.id) return false;
    
    if (question.question_type === 'standalone') {
        const hasText = question.question_text || question.sub_questions?.[0]?.question_text;
        const hasOptions = question.options?.length > 0;
        const hasCqOptions = question.cqoptions?.length > 0;
        return hasText || hasOptions || hasCqOptions;
    }
    
    if (question.question_type === 'stimulus_based') {
        const hasStimuli = question.stimuli?.length > 0;
        const hasSubQuestions = question.sub_questions?.length > 0;
        return hasStimuli || hasSubQuestions;
    }
    
    return !!question.question_text;
};

const getQuestionDisplayIndex = (question, boardQuestions) => {
    const index = boardQuestions.findIndex(q => q.id === question.id);
    return index + 1;
};

// ============ State ============
const loading = ref(false);
const isInitialLoad = ref(true);
const isUpdatingFromProps = ref(false);
const isFiltering = ref(false);

// Data from props
const questions = ref([]);
const groupedQuestions = ref({});
const chapters = ref([]);
const filters = ref({});

// Filter selections - initialize with default values
const selectedTopics = ref([]);
const selectedQuestionTypes = ref([]);
const selectedLevels = ref([]);
const selectedBoards = ref([]);
const selectedYears = ref([]);

// UI state
const selectedQuestions = ref([]);
const isSelectAll = ref(false);

// Store initial selections to prevent reset
const initialSelections = ref({});

// Bengali characters
const bengaliChars = ['ক', 'খ', 'গ', 'ঘ', 'ঙ', 'চ', 'ছ', 'জ', 'ঝ', 'ঞ', 'ট', 'ঠ', 'ড', 'ঢ', 'ণ', 'ত', 'থ', 'দ', 'ধ', 'ন', 'প', 'ফ', 'ব', 'ভ', 'ম', 'য', 'র', 'ল', 'শ', 'ষ', 'স', 'হ', 'ড়', 'ঢ়', 'য়'];

// ============ Computed Properties ============

const allTopics = computed(() => {
    return chapters.value.flatMap(chapter => 
        chapter.topic ? chapter.topic.map(topic => ({
            ...topic,
            chapter_name: chapter.name
        })) : []
    );
});

const totalTopicsCount = computed(() => allTopics.value.length);

const selectedTopicsDetails = computed(() => {
    return allTopics.value
        .filter(topic => selectedTopics.value.includes(topic.id));
});

const getChapterName = (chapterId) => {
    const chapter = chapters.value.find(c => c.id === chapterId);
    return chapter ? chapter.name : 'Unknown Chapter';
};

const availableYears = computed(() => {
    const years = new Set();
    props.boards?.forEach(board => {
        if (board.year) years.add(board.year);
    });
    return Array.from(years).sort((a, b) => b - a);
});

const availableBoards = computed(() => {
    if (!selectedYears.value.length) {
        return props.boards || [];
    }
    return (props.boards || []).filter(board =>
        selectedYears.value.includes(board.year)
    );
});

const filteredGroupedQuestions = computed(() => {
    const result = {};
    for (const [boardName, boardQuestions] of Object.entries(groupedQuestions.value)) {
        const validQuestions = boardQuestions.filter(question => isValidQuestion(question));
        if (validQuestions.length > 0) {
            result[boardName] = validQuestions;
        }
    }
    return result;
});

const totalValidQuestions = computed(() => {
    let count = 0;
    for (const boardQuestions of Object.values(filteredGroupedQuestions.value)) {
        count += boardQuestions.length;
    }
    return count;
});

const allValidQuestions = computed(() => {
    const result = [];
    for (const boardQuestions of Object.values(filteredGroupedQuestions.value)) {
        result.push(...boardQuestions);
    }
    return result;
});

// ============ Filter Methods ============

const toggleQuestionType = (typeId) => {
    const index = selectedQuestionTypes.value.indexOf(typeId);
    if (index > -1) {
        selectedQuestionTypes.value.splice(index, 1);
    } else {
        selectedQuestionTypes.value.push(typeId);
    }
};

const selectAllQuestionTypes = () => {
    selectedQuestionTypes.value = props.quetype.map(type => type.id);
};

const clearQuestionTypeFilters = () => {
    selectedQuestionTypes.value = [];
};

const toggleTopic = (topicId) => {
    const index = selectedTopics.value.indexOf(topicId);
    if (index > -1) {
        selectedTopics.value.splice(index, 1);
    } else {
        selectedTopics.value.push(topicId);
    }
};

const selectAllTopics = () => {
    if (selectedTopics.value.length === totalTopicsCount.value) {
        selectedTopics.value = [];
    } else {
        selectedTopics.value = allTopics.value.map(topic => topic.id);
    }
};

const clearTopicFilters = () => {
    selectedTopics.value = [];
};

const removeSelectedTopic = (topicId) => {
    const index = selectedTopics.value.indexOf(topicId);
    if (index > -1) {
        selectedTopics.value.splice(index, 1);
    }
};

const toggleBoard = (boardId) => {
    const index = selectedBoards.value.indexOf(boardId);
    if (index > -1) {
        selectedBoards.value.splice(index, 1);
    } else {
        selectedBoards.value.push(boardId);
    }
};

const toggleYear = (year) => {
    const index = selectedYears.value.indexOf(year);
    if (index > -1) {
        selectedYears.value.splice(index, 1);
    } else {
        selectedYears.value.push(year);
    }
    selectedBoards.value = [];
};

const toggleLevel = (levelId) => {
    const index = selectedLevels.value.indexOf(levelId);
    if (index > -1) {
        selectedLevels.value.splice(index, 1);
    } else {
        selectedLevels.value.push(levelId);
    }
};

const selectAllLevels = () => {
    selectedLevels.value = props.level.map(level => level.id);
};

const clearLevelFilters = () => {
    selectedLevels.value = [];
};

// ============ Fetch Questions ============

const fetchQuestions = () => {
    if (isFiltering.value || isUpdatingFromProps.value) return;
    isFiltering.value = true;
    loading.value = true;

    const params = {
        chapter_id: filters.value.chapter_id || [],
        topic_id: selectedTopics.value || [],
        type: filters.value.type || '',
        count: filters.value.count || 30,
        board_ids: selectedBoards.value || [],
        years: selectedYears.value || [],
        question_types: selectedQuestionTypes.value || [],
        levels: selectedLevels.value || []
    };

    Object.keys(params).forEach(key => {
        if (Array.isArray(params[key]) && params[key].length === 0) {
            delete params[key];
        }
        if (params[key] === '' || params[key] === null || params[key] === undefined) {
            delete params[key];
        }
    });

    router.get(route('sltquestion'), params, {
        preserveState: true,
        replace: true,
        only: ['questions', 'groupedQuestions', 'chapters', 'filters'],
        onSuccess: (page) => {
            questions.value = page.props.questions || [];
            groupedQuestions.value = page.props.groupedQuestions || {};
            chapters.value = page.props.chapters || [];
            filters.value = page.props.filters || {};
            
            loading.value = false;
            isFiltering.value = false;
            isInitialLoad.value = false;
        },
        onError: () => {
            loading.value = false;
            isFiltering.value = false;
        }
    });
};

// ============ Question Methods ============

const toggleExplanation = (questionId) => {
    const question = questions.value.find(q => q.id === questionId);
    if (question) {
        question.showExplanation = !question.showExplanation;
    }
};

const toggleQuestionSelection = (questionId) => {
    const index = selectedQuestions.value.indexOf(questionId);
    if (index > -1) {
        selectedQuestions.value.splice(index, 1);
    } else {
        selectedQuestions.value.push(questionId);
    }
};

const toggleSelectAll = () => {
    isSelectAll.value = !isSelectAll.value;
    if (isSelectAll.value) {
        selectedQuestions.value = allValidQuestions.value.map(q => q.id);
    } else {
        selectedQuestions.value = [];
    }
};

const scrollToTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const saveQuestions = () => {
    if (selectedQuestions.value.length === 0) {
        alert('Please select at least one question');
        return;
    }

    router.post(route('save.questions'), {
        question_ids: selectedQuestions.value,
        exam_name: examName.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedQuestions.value = [];
            isSelectAll.value = false;
        }
    });
};

// ============ Initialize Selections ============

const initializeSelections = () => {
    // Store initial selections from props
    if (props.filters) {
        if (props.filters.topic_id) {
            selectedTopics.value = [...props.filters.topic_id];
            initialSelections.value.topics = [...props.filters.topic_id];
        }
        if (props.filters.question_types) {
            selectedQuestionTypes.value = [...props.filters.question_types];
        }
        if (props.filters.levels) {
            selectedLevels.value = [...props.filters.levels];
        }
        if (props.filters.board_ids) {
            selectedBoards.value = [...props.filters.board_ids];
        }
        if (props.filters.years) {
            selectedYears.value = [...props.filters.years];
        }
    }
};

// ============ Watchers ============

const debouncedFetch = debounce(() => {
    fetchQuestions();
}, 500);

// Watch selectedTopics - but only trigger fetch on user interaction
watch(selectedTopics, (newVal, oldVal) => {
    // Skip if initial load or updating from props
    if (isInitialLoad.value || isUpdatingFromProps.value || isFiltering.value) return;
    
    // Skip if the change is just the initial sync from props
    if (oldVal.length === 0 && newVal.length === 0) return;
    
    // Check if the change is from user interaction
    const isUserChange = !isUpdatingFromProps.value;
    if (isUserChange) {
        debouncedFetch();
    }
}, { deep: true });

watch(selectedQuestionTypes, () => {
    if (isInitialLoad.value || isUpdatingFromProps.value || isFiltering.value) return;
    debouncedFetch();
}, { deep: true });

watch(selectedLevels, () => {
    if (isInitialLoad.value || isUpdatingFromProps.value || isFiltering.value) return;
    debouncedFetch();
}, { deep: true });

watch(selectedBoards, () => {
    if (isInitialLoad.value || isUpdatingFromProps.value || isFiltering.value) return;
    debouncedFetch();
}, { deep: true });

watch(selectedYears, () => {
    if (isInitialLoad.value || isUpdatingFromProps.value || isFiltering.value) return;
    debouncedFetch();
}, { deep: true });

// Watch props.filters - but don't reset selections
watch(() => props.filters, (newFilters) => {
    if (!newFilters) return;
    
    isUpdatingFromProps.value = true;
    
    // Only update if selections are empty (initial load)
    if (selectedTopics.value.length === 0 && newFilters.topic_id) {
        selectedTopics.value = [...newFilters.topic_id];
    }
    
    if (selectedQuestionTypes.value.length === 0 && newFilters.question_types) {
        selectedQuestionTypes.value = [...newFilters.question_types];
    }
    
    if (selectedLevels.value.length === 0 && newFilters.levels) {
        selectedLevels.value = [...newFilters.levels];
    }
    
    if (selectedBoards.value.length === 0 && newFilters.board_ids) {
        selectedBoards.value = [...newFilters.board_ids];
    }
    
    if (selectedYears.value.length === 0 && newFilters.years) {
        selectedYears.value = [...newFilters.years];
    }
    
    filters.value = newFilters;
    
    nextTick(() => {
        isUpdatingFromProps.value = false;
    });
}, { immediate: true, deep: true });

watch(() => props.questions, (newQuestions) => {
    if (newQuestions) {
        questions.value = newQuestions.map(q => ({
            ...q,
            showExplanation: false
        }));
    }
}, { immediate: true });

watch(() => props.groupedQuestions, (newGrouped) => {
    if (newGrouped) {
        groupedQuestions.value = newGrouped;
    }
}, { immediate: true });

watch(() => props.chapters, (newChapters) => {
    if (newChapters) {
        chapters.value = newChapters;
    }
}, { immediate: true });

// ============ Lifecycle ============

onMounted(() => {
    loading.value = true;
    isInitialLoad.value = true;
    
    // Initialize selections from props
    initializeSelections();
    
    if (props.questions) {
        questions.value = props.questions.map(q => ({
            ...q,
            showExplanation: false
        }));
    }
    
    if (props.groupedQuestions) {
        groupedQuestions.value = props.groupedQuestions;
    }
    
    if (props.filters) {
        filters.value = props.filters;
    }
    
    if (props.chapters) {
        chapters.value = props.chapters;
    }
    
    // Set initial load to false after data is loaded
    setTimeout(() => {
        isInitialLoad.value = false;
        loading.value = false;
    }, 500);
});

onUnmounted(() => {
    debouncedFetch.cancel();
});

// Expose methods if needed
defineExpose({
    fetchQuestions,
    clearTopicFilters,
    selectedTopics
});
</script>

<style scoped>
.cursor-pointer {
    cursor: pointer;
    transition: border-color 0.2s ease;
}

.question-item:hover {
    border-color: #e5e7eb;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.animate-spin {
    animation: spin 1s linear infinite;
}

.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

.prose {
    max-width: none;
}

.prose p {
    margin: 0 0 0.5em 0;
}

.prose p:last-child {
    margin-bottom: 0;
}

input[type="checkbox"] {
    accent-color: #2563eb;
    width: 16px;
    height: 16px;
    cursor: pointer;
}

.bg-blue-100 {
    background-color: #dbeafe;
}
.border-blue-300 {
    border-color: #93c5fd;
}
.text-blue-600 {
    color: #2563eb;
}

/* All tables inside questions */
:deep(.question-item table),
:deep(.prose table),
:deep(.ql-editor table),
:deep(.html-content table),
:deep(.latex-renderer table) {
    border-collapse: collapse;
    width: 100%;
    margin: 10px 0;
    border: 1px solid #ccc;
}

:deep(.question-item table td),
:deep(.question-item table th),
:deep(.prose table td),
:deep(.prose table th),
:deep(.ql-editor table td),
:deep(.ql-editor table th),
:deep(.html-content table td),
:deep(.html-content table th),
:deep(.latex-renderer table td),
:deep(.latex-renderer table th) {
    border: 1px solid #ccc;
    padding: 8px;
    text-align: left;
    vertical-align: top;
    min-width: 50px;
}

:deep(.question-item table th),
:deep(.prose table th),
:deep(.ql-editor table th),
:deep(.html-content table th),
:deep(.latex-renderer table th) {
    background-color: #f2f2f2;
    font-weight: bold;
}

:deep(.question-item table tr:nth-child(even)),
:deep(.prose table tr:nth-child(even)),
:deep(.ql-editor table tr:nth-child(even)),
:deep(.html-content table tr:nth-child(even)),
:deep(.latex-renderer table tr:nth-child(even)) {
    background-color: #f9f9f9;
}

:deep(.question-item table tr:hover),
:deep(.prose table tr:hover),
:deep(.ql-editor table tr:hover),
:deep(.html-content table tr:hover),
:deep(.latex-renderer table tr:hover) {
    background-color: #f5f5f5;
}

/* Responsive table */
:deep(.question-item table),
:deep(.prose table),
:deep(.ql-editor table),
:deep(.html-content table),
:deep(.latex-renderer table) {
    overflow-x: auto;
    display: block;
}

:deep(.question-item table td),
:deep(.question-item table th),
:deep(.prose table td),
:deep(.prose table th),
:deep(.ql-editor table td),
:deep(.ql-editor table th),
:deep(.html-content table td),
:deep(.html-content table th),
:deep(.latex-renderer table td),
:deep(.latex-renderer table th) {
    word-wrap: break-word;
    max-width: 200px;
}
</style>