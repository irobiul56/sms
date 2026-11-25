<template>
    <Head title="User Dashboard" />

    <UserDashboardLayout>
        <!-- Error Alert -->
        <div v-if="printError" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50">
            <div class="bg-white p-6 rounded-lg shadow-lg max-w-md">
                <div class="text-center">
                    <svg class="mx-auto h-12 w-12 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 bangla mt-2">প্রিন্টে সমস্যা হয়েছে</h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500 bangla">আবার চেষ্টা করুন</p>
                    </div>
                    <div class="mt-4">
                        <button @click="printError = false" type="button" class="bangla inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:text-sm">
                            ঠিক আছে
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="bangla relative min-h-screen">
            <!-- Main content with full width -->
            <div 
                class="w-full px-4 py-4 transition-all duration-300"
                :class="{
                    'lg:pr-80 xl:pr-96': isSidebarVisible,
                    'lg:pr-4': !isSidebarVisible
                }"
            >
                <!-- Questions Container - Full Width -->
                <div id="questions-container" 
                    :class="[fontClasses[selectedFont], 'question-text']" 
                    class="break-inside-avoid first-letter:w-full p-[5mm] md:p-[10mm] print:p-0 print:w-full print:shadow-none scroll-m-0 bg-white w-full">
                    
                    <!-- Header section -->
                    <div class="py-2 print:py-0">
                        <h1 class="text-xl font-bold text-center" contenteditable="true">{{ institute?.name || '' }}</h1>
                        <h3 v-if="address" class="text-xl font-bold text-center" contenteditable="true">{{ institute?.address || '' }}</h3>
                        <div class="relative" contenteditable="true">
                            <p class="text-center text-lg">{{ savedQuestion?.exam_name || '' }}</p>
                            <p class="text-center text-lg">{{ getClassName() }}</p>
                            <p v-if="subject" class="text-center truncate">{{ getSubjectName() }}</p>
                    
                            <!-- Set indicator -->
                            <div v-if="setcode" class="absolute top-0 right-0 flex">
                                <p class="border px-2 border-gray-500">সেট</p>
                                <p class="border-y border-r px-2 border-gray-500">{{ currentSet }}</p>
                            </div>
                        </div>
                        <div class="flex justify-between relative">
                            <div class="flex items-center">
                                সময়—
                                <p class="mx-1" contenteditable="true">
                                    {{ totalTimeFormatted }}
                                </p>
                            </div>

                            <div class="flex items-center">
                                পূর্ণমান—
                                <span class="mx-1" contenteditable="true">
                                    {{ totalFullMarks }}
                                </span>
                            </div>
                        </div>
                        <hr>
                        <div v-if="instruction" class="text-center text-sm my-1">
                            <template v-if="hasMCQ && !hasCQ && !hasMix && !hasStimulusBased">
                                <span contenteditable="true"><i><span class="bangla-bold">দ্রষ্টব্যঃ</span> সরবরাহকৃত নৈর্ব্যত্তিক অভীক্ষার উত্তরপত্রে প্রশ্নের ক্রমিক নম্বরের বিপরীতে প্রদত্ত বর্ণসম্বলিত বৃত্ত সমুহ হতে সঠিক উত্তরের বৃত্তটি</i> 
                                (<svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 512 512" class="inline-block" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512z"></path>
                                </svg>) <i>বল পয়েন্ট কলম দ্বারা সম্পুর্ণ ভরাট করো । প্রতিটি প্রশ্নের মান ১ ।</i></span>
                                <p class="bangla-bold text-center text-sm mt-1 font-bold">প্রশ্নপত্রে কোনো প্রকার দাগ/চিহ্ন দেয়া যাবেনা ।</p>
                            </template>
                            <template v-if="hasCQ && !hasMCQ && !hasMix && !hasStimulusBased">
                                <span contenteditable="true">
                                    <i>
                                    <span class="bangla-bold">দ্রষ্টব্যঃ</span> 
                                    ডান পাশের সংখ্যা প্রশ্নের পূর্ণমান জ্ঞাপক। প্রদত্ত উদ্দীপকগুলো মনোযোগসহকারে পর এবং সংশ্লিষ্ট প্রশ্নগুলোর উত্তর দাও। যেকোনো 
                                    <span 
                                        class="border-none px-0 py-0 inline-block min-w-[1ch] text-center" 
                                        contenteditable="true"
                                    >৫</span> 
                                    টি প্রশ্নের উত্তর দিতে হবে।
                                    </i>
                                </span>
                            </template>
                        </div>
                    </div>

                    <!-- Questions section - Full Width -->
                    <div :style="{ fontSize: `${fontSize}px`, lineHeight: '1.3' }" class="question-content w-full">
                        <div v-if="allQuestions.length > 0" class="relative flex-1 w-full"
                            :class="{
                                'columns-1': columnCount === 1,
                                'columns-2': columnCount === 2,
                                'columns-3': columnCount === 3
                            }"
                            :style="{
                                'column-count': columnCount,
                                'column-rule': showColumnDivider ? '1px solid rgba(0, 0, 0, 0.2)' : 'none',
                                'column-gap': showColumnDivider ? '1.5rem' : '0.5rem'
                            }">

                            <!-- Group Questions by Level -->
                            <div v-for="(levelData, levelIndex) in groupedByLevel" :key="levelIndex" class="mb-2">
                                <!-- Level Header -->
                                <div class="flex flex-wrap justify-between items-center w-full bg-blue-50 p-1 rounded mb-1">
                                    <h3 class="font-bold bangla-bold text-blue-800 flex items-center gap-1">
                                        <span class="level-number">{{ toBengaliNumerals(levelIndex + 1) }}.</span>
                                        <span class="level-name" 
                                              contenteditable="true" 
                                              @blur="updateLevelName(levelData, $event)"
                                              @keydown.enter.prevent="updateLevelName(levelData, $event)"
                                              @keydown.esc="event => event.target.blur()"
                                              title="Click to edit level name"
                                              spellcheck="false">
                                            {{ levelData.levelName }}
                                        </span>
                                        <svg class="w-3 h-3 text-gray-400 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                        </svg>
                                        
                                    </h3>
                                    <span contenteditable="true" class="text-sm font-bold text-blue-600">{{ toBengaliInteger(levelData.totalMarks) }}</span>
                                </div>
                                
                                <!-- ========================================== -->
                                <!-- INLINE QUESTIONS - এক লাইনে সব প্রশ্ন     -->
                                <!-- ========================================== -->
                                <div v-if="levelData.is_inline" class="bg-white relative p-2 hover:bg-gray-50 rounded group break-inside-avoid">
                                    <div class="inline-question-text text-gray-800">
                                        <span v-html="formatInlineLevelQuestions(levelData)" />
                                    </div>
                                </div>

                                <!-- ========================================== -->
                                <!-- REGULAR QUESTIONS                         -->
                                <!-- ========================================== -->
                                <div v-if="!levelData.is_inline">
                                    <div v-for="(question, qIndex) in levelData.questions" :key="question.id" 
                                        class="bg-white relative p-0.5 hover:bg-gray-50 rounded group break-inside-avoid mb-1">
                                        
                                        <!-- ========================================== -->
                                        <!-- STIMULUS BASED QUESTIONS                   -->
                                        <!-- ========================================== -->
                                        <div v-if="isStimulusBased(question)">
                                            <!-- Stimulus Content - No serial number -->
                                            <div v-if="hasStimuli(question)" class="mb-1 mt-1">
                                                <div>
                                                    <div v-for="stimulus in getStimuli(question)" :key="stimulus.id" class="mb-1 last:mb-0">
                                                        <LatexRenderer :content="stimulus.content" />
                                                        <div v-if="stimulus.media_url" class="mt-1">
                                                            <img :src="stimulus.media_url" alt="Stimulus" class="max-w-full h-auto rounded border border-gray-200 max-h-48">
                                                        </div>
                                                        <div v-if="stimulus.caption" class="text-xs text-gray-500 mt-1">
                                                            {{ stimulus.caption }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Sub Questions with serial numbers -->
                                            <div v-if="hasSubQuestions(question)" class="space-y-1 mt-1">
                                                <div v-for="(sq, sqIndex) in getSubQuestions(question)" :key="sq.id"
                                                    class="pl-2 p-1">
                                                    
                                                    <div class="flex items-start">
                                                        <span class="bangla-bold text-gray-900 mr-2 min-w-8">
                                                            {{ toBengaliNumerals(getSubQuestionSerial(levelData, qIndex, sqIndex)) }}.
                                                        </span>
                                                        <div class="flex-1">
                                                            <LatexRenderer :content="sq.question_text" />
                                                           
                                                        </div>
                                                    </div>

                                                    <!-- Sub MCQ Options -->
                                                    <div v-if="sq.format === 'mcq' && sq.options?.length" 
                                                        class="relative grid grid-cols-2 ml-7 group mt-1">
                                                        <div v-for="(opt, optIdx) in sq.options" :key="opt.id" 
                                                            class="option flex flex-1 items-baseline">
                                                            <div class="shrink-0 mr-1 flex justify-center items-center"
                                                                :class="{
                                                                    'h-4 w-4 border border-gray-500 rounded-full': optionStyle === 'circle',
                                                                    'text-black': optionStyle === 'parens',
                                                                    'text-black': optionStyle === 'dot',
                                                                    'text-black': optionStyle === 'paren',
                                                                    'bg-black text-white': showAnswers && opt.is_correct && optionStyle === 'circle',
                                                                    'font-bold underline': showAnswers && opt.is_correct && optionStyle !== 'circle'
                                                                }">
                                                                <span v-if="optionStyle === 'circle'" class="text-xs">
                                                                    {{ getBanglaLetter(optIdx) }}
                                                                </span>
                                                                <span v-else-if="optionStyle === 'parens'">
                                                                    ({{ getBanglaLetter(optIdx) }})
                                                                </span>
                                                                <span v-else-if="optionStyle === 'dot'">
                                                                    {{ getBanglaLetter(optIdx) }}.
                                                                </span>
                                                                <span v-else-if="optionStyle === 'paren'">
                                                                    {{ getBanglaLetter(optIdx) }})
                                                                </span>
                                                            </div>
                                                            <div>
                                                                <LatexRenderer :content="opt.option_text" />
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Sub CQ Options -->
                                                    <div v-if="sq.format === 'cq' && sq.cqoptions?.length" 
                                                        class="relative grid grid-cols-1 ml-7 group mt-1">
                                                        <div v-for="(cq, cqIdx) in sq.cqoptions" :key="cq.id" 
                                                            class="option flex items-center justify-between w-full">
                                                            <div class="flex items-baseline flex-grow">
                                                                <div class="shrink-0 mr-1 flex justify-center items-center"
                                                                    :class="{
                                                                        'h-4 w-4 border border-gray-500 rounded-full': optionStyle === 'circle',
                                                                        'text-black': optionStyle === 'parens',
                                                                        'text-black': optionStyle === 'dot',
                                                                        'text-black': optionStyle === 'paren',
                                                                    }">
                                                                    <span v-if="optionStyle === 'circle'" class="text-xs">
                                                                        {{ getBanglaLetter(cqIdx) }}
                                                                    </span>
                                                                    <span v-else-if="optionStyle === 'parens'">
                                                                        ({{ getBanglaLetter(cqIdx) }})
                                                                    </span>
                                                                    <span v-else-if="optionStyle === 'dot'">
                                                                        {{ getBanglaLetter(cqIdx) }}.
                                                                    </span>
                                                                    <span v-else-if="optionStyle === 'paren'">
                                                                        {{ getBanglaLetter(cqIdx) }})
                                                                    </span>
                                                                </div>
                                                                <div class="flex-grow">
                                                                    <LatexRenderer :content="cq.cq_text" />
                                                                </div>
                                                            </div>
                                                            <div class="shrink-0 ml-4">
                                                                {{ toBengaliInteger(cq.mark) }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- ========================================== -->
                                        <!-- STANDALONE QUESTIONS (MCQ, CQ, Mix)       -->
                                        <!-- ========================================== -->
                                        <div v-if="!isStimulusBased(question)">
                                            <div class="flex items-baseline gap-x-2">
                                                <!-- Show serial number only if more than 1 question in level -->
                                                <span v-if="levelData.questions.length > 1" class="bangla-bold text-gray-900">
                                                    {{ toBengaliNumerals(getQuestionSerial(levelData, qIndex)) }}.
                                                </span>
                                                <div class="flex flex-wrap justify-between items-center w-full">
                                                    <div class="bangla-bold">
                                                        <LatexRenderer :content="getQuestionText(question)" />
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- MCQ Options -->
                                            <div v-if="question.format === 'mcq' && hasMcqOptions(question)" 
                                                class="relative grid grid-cols-2 ml-7 group mt-1">
                                                <div v-for="(option, optionIndex) in getMcqOptions(question)" :key="`opt-${optionIndex}`" 
                                                    class="option flex flex-1 items-baseline">
                                                    <div class="shrink-0 mr-1 flex justify-center items-center"
                                                        :class="{
                                                            'h-4 w-4 border border-gray-500 rounded-full': optionStyle === 'circle',
                                                            'text-black': optionStyle === 'parens',
                                                            'text-black': optionStyle === 'dot',
                                                            'text-black': optionStyle === 'paren',
                                                            'bg-black text-white': showAnswers && option.is_correct && optionStyle === 'circle',
                                                            'font-bold underline': showAnswers && option.is_correct && optionStyle !== 'circle'
                                                        }">
                                                        <span v-if="optionStyle === 'circle'" class="text-xs">
                                                            {{ getBanglaLetter(optionIndex) }}
                                                        </span>
                                                        <span v-else-if="optionStyle === 'parens'">
                                                            ({{ getBanglaLetter(optionIndex) }})
                                                        </span>
                                                        <span v-else-if="optionStyle === 'dot'">
                                                            {{ getBanglaLetter(optionIndex) }}.
                                                        </span>
                                                        <span v-else-if="optionStyle === 'paren'">
                                                            {{ getBanglaLetter(optionIndex) }})
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <LatexRenderer :content="option.option_text" />
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- CQ Options -->
                                            <div v-if="question.format === 'cq' && hasCqOptions(question)" 
                                                class="relative grid grid-cols-1 ml-7 group mt-1">
                                                <div v-for="(cqoption, optionIndex) in getCqOptions(question)" :key="`cq-opt-${optionIndex}`" 
                                                    class="option flex items-center justify-between w-full">
                                                    <div class="flex items-baseline flex-grow">
                                                        <div class="shrink-0 mr-1 flex justify-center items-center"
                                                            :class="{
                                                                'h-4 w-4 border border-gray-500 rounded-full': optionStyle === 'circle',
                                                                'text-black': optionStyle === 'parens',
                                                                'text-black': optionStyle === 'dot',
                                                                'text-black': optionStyle === 'paren',
                                                            }">
                                                            <span v-if="optionStyle === 'circle'" class="text-xs">
                                                                {{ getBanglaLetter(optionIndex) }}
                                                            </span>
                                                            <span v-else-if="optionStyle === 'parens'">
                                                                ({{ getBanglaLetter(optionIndex) }})
                                                            </span>
                                                            <span v-else-if="optionStyle === 'dot'">
                                                                {{ getBanglaLetter(optionIndex) }}.
                                                            </span>
                                                            <span v-else-if="optionStyle === 'paren'">
                                                                {{ getBanglaLetter(optionIndex) }})
                                                            </span>
                                                        </div>
                                                        <div class="flex-grow">
                                                            <LatexRenderer :content="cqoption.cq_text" />
                                                        </div>
                                                    </div>
                                                    <div class="shrink-0 ml-4">
                                                        {{ toBengaliInteger(cqoption.mark) }}
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Explanation -->
                                            <div v-if="getExplanation(question) && question.showExplanation"
                                                class="mt-1 bg-green-50 py-1 px-2 text-green-700 rounded transition-all duration-300">
                                                <div>
                                                    <LatexRenderer :content="getExplanation(question)" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="p-4 text-center text-gray-500">
                            No saved questions found
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- MOBILE FLOATING SETTINGS BUTTON            -->
            <!-- ========================================== -->
            <button 
                @click="openMobileSidebar"
                class="lg:hidden fixed bottom-6 right-6 z-40 bg-blue-600 hover:bg-blue-700 text-white rounded-full p-4 shadow-lg transition-all duration-300 hover:scale-110 flex items-center justify-center"
                :class="{ 'scale-0 opacity-0': isMobileSidebarOpen }"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </button>

            <!-- Mobile Overlay -->
            <div 
                v-if="isMobileSidebarOpen"
                class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden transition-opacity duration-300"
                @click="closeMobileSidebar"
            ></div>

            <!-- ========================================== -->
            <!-- MOBILE SIDEBAR SETTINGS - DRAWER           -->
            <!-- ========================================== -->
            <div 
                class="fixed bottom-0 left-0 right-0 z-50 bg-white rounded-t-2xl shadow-2xl transition-transform duration-300 transform lg:hidden max-h-[85vh] overflow-y-auto"
                :class="isMobileSidebarOpen ? 'translate-y-0' : 'translate-y-full'"
            >
                <!-- Drag Handle -->
                <div class="flex justify-center pt-2 pb-1">
                    <div class="w-12 h-1 bg-gray-300 rounded-full"></div>
                </div>

                <!-- Close Button -->
                <button 
                    @click="closeMobileSidebar"
                    class="absolute top-3 right-4 p-1 rounded-full hover:bg-gray-100 transition-colors"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <div class="p-4 pb-6">
                    <h1 class="py-2 flex items-center gap-2 justify-center rounded text-center bg-gray-50 mb-3 shadow">
                        <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 512 512" class="text-gray-500 text-sm" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                            <path d="M495.9 166.6c3.2 8.7 .5 18.4-6.4 24.6l-43.3 39.4c1.1 8.3 1.7 16.8 1.7 25.4s-.6 17.1-1.7 25.4l43.3 39.4c6.9 6.2 9.6 15.9 6.4 24.6c-4.4 11.9-9.7 23.3-15.8 34.3l-4.7 8.1c-6.6 11-14 21.4-22.1 31.2c-5.9 7.2-15.7 9.6-24.5 6.8l-55.7-17.7c-13.4 10.3-28.2 18.9-44 25.4l-12.5 57.1c-2 9.1-9 16.3-18.2 17.8c-13.8 2.3-28 3.5-42.5 3.5s-28.7-1.2-42.5-3.5c-9.2-1.5-16.2-8.7-18.2-17.8l-12.5-57.1c-15.8-6.5-30.6-15.1-44-25.4L83.1 425.9c-8.8 2.8-18.6 .3-24.5-6.8c-8.1-9.8-15.5-20.2-22.1-31.2l-4.7-8.1c-6.1-11-11.4-22.4-15.8-34.3c-3.2-8.7-.5-18.4 6.4-24.6l43.3-39.4C64.6 273.1 64 264.6 64 256s.6-17.1 1.7-25.4L22.4 191.2c-6.9-6.2-9.6-15.9-6.4-24.6c4.4-11.9 9.7-23.3 15.8-34.3l4.7-8.1c6.6-11 14-21.4 22.1-31.2c5.9-7.2 15.7-9.6 24.5-6.8l55.7 17.7c8.8-2.8 18.6-.3 24.5 6.8c8.1 9.8 15.5 20.2 22.1 31.2l4.7 8.1c6.1 11 11.4 22.4 15.8 34.3zM256 336a80 80 0 1 0 0-160 80 80 0 1 0 0 160z"></path>
                        </svg> 
                        <span class="text-lg">সেটিংস</span>
                    </h1>
                    
                    <!-- Print Button -->
                    <button 
                      @click="printQuestions"
                      :disabled="isPrinting"
                      class="hover:bg-blue-400 bg-blue-500 transition-all py-2 rounded w-full text-center text-white flex items-center gap-2 justify-center"
                    >
                      <svg v-if="!isPrinting" stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 512 512" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                        <path d="M400 96V56c0-13.3-10.7-24-24-24H136c-13.3 0-24 10.7-24 24v40H24c-13.3 0-24 10.7-24 24v104c0 13.3 10.7 24 24 24h8v96c0 13.3 10.7 24 24 24h400c13.3 0 24-10.7 24-24v-96h8c13.3 0 24-10.7 24-24V120c0-13.3-10.7-24-24-24h-88zm-8 0H120V56h272v40zM104 392H56v-80h48v80zm344 0h-48v-80h48v80zm40-128H24V120h432v144zm-40 64c13.3 0 24-10.7 24-24s-10.7-24-24-24-24 10.7-24 24 10.7 24 24 24z"></path>
                      </svg>
                      <span v-if="!isPrinting">প্রিন্ট করুন</span>
                      <span v-else class="bangla">প্রিন্ট প্রস্তুত হচ্ছে...</span>
                    </button>
                    
                    <!-- Answer sheet toggle -->
                    <div class="bg-gray-100 p-2 rounded flex justify-between items-center my-2">
                        <span class="bangla">উত্তরপত্র</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="showAnswers" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                        </label>
                    </div>
                    
                    <!-- Option style selector -->
                    <div class="relative bg-gray-100 p-2 rounded my-2">
                        <p class="bangla mb-2">অপশন স্টাইল</p>
                        <div class="flex gap-2">
                            <div @click="setOptionStyle('circle')" 
                                class="p-1 flex-1 flex justify-center items-center cursor-pointer rounded"
                                :class="{ 'bg-emerald-600 text-white': optionStyle === 'circle', 'bg-white hover:bg-gray-100': optionStyle !== 'circle' }">
                                <div class="h-5 w-5 border border-gray-500 rounded-full flex items-center justify-center">○</div>
                            </div>
                            <div @click="setOptionStyle('parens')" 
                                class="p-1 flex-1 flex justify-center items-center cursor-pointer rounded"
                                :class="{ 'bg-emerald-600 text-white': optionStyle === 'parens', 'bg-white hover:bg-gray-100': optionStyle !== 'parens' }">( )</div>
                            <div @click="setOptionStyle('dot')" 
                                class="p-1 flex-1 flex justify-center items-center cursor-pointer rounded"
                                :class="{ 'bg-emerald-600 text-white': optionStyle === 'dot', 'bg-white hover:bg-gray-100': optionStyle !== 'dot' }">
                                <span class="text-2xl leading-none">.</span>
                            </div>
                            <div @click="setOptionStyle('paren')" 
                                class="p-1 flex-1 flex justify-center items-center cursor-pointer rounded"
                                :class="{ 'bg-emerald-600 text-white': optionStyle === 'paren', 'bg-white hover:bg-gray-100': optionStyle !== 'paren' }">)</div>
                        </div>
                    </div>
                    
                    <!-- Address toggle -->
                    <div>
                        <div class="bg-gray-100 p-2 rounded flex justify-between items-center my-2">
                            <span class="bangla">ঠিকানা</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input v-model="address" type="checkbox" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Shuffle button -->
                    <div>
                        <div class="cursor-pointer bg-gray-100 hover:bg-emerald-600 hover:text-white flex justify-between items-center p-2 rounded my-2" 
                            @click="shuffleQuestions" tabindex="0">
                            <p>শাফল করুন</p>
                            <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 512 512" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                <path d="M403.8 34.4c12-5 25.7-2.2 34.9 6.9l64 64c6 6 9.4 14.1 9.4 22.6s-3.4 16.6-9.4 22.6l-64 64c-9.2 9.2-22.9 11.9-34.9 6.9s-19.8-16.6-19.8-29.6V160H352c-10.1 0-19.6 4.7-25.6 12.8L284 229.3 244 176l31.2-41.6C293.3 110.2 321.8 96 352 96h32V64c0-12.9 7.8-24.6 19.8-29.6zM164 282.7L204 336l-31.2 41.6C154.7 401.8 126.2 416 96 416H32c-17.7 0-32-14.3-32-32s14.3-32 32-32H96c10.1 0 19.6-4.7 25.6-12.8L164 282.7zm274.6 188c-9.2 9.2-22.9 11.9-34.9 6.9s-19.8-16.6-19.8-29.6V416H352c-30.2 0-58.7-14.2-76.8-38.4L121.6 172.8c-6-8.1-15.5-12.8-25.6-12.8H32c-17.7 0-32-14.3-32-32s14.3-32 32-32H96c30.2 0 58.7 14.2 76.8 38.4L326.4 339.2c6 8.1 15.5 12.8 25.6 12.8h32V320c0-12.9 7.8-24.6 19.8-29.6s25.7-2.2 34.9 6.9l64 64c6 6 9.4 14.1 9.4 22.6s-3.4 16.6-9.4 22.6l-64 64z"></path>
                            </svg>
                        </div>
                    </div>
                    
                    <!-- Instructions toggle -->
                    <div class="bg-gray-100 p-2 rounded flex justify-between items-center my-2">
                        <span class="bangla">নির্দেশনা</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input v-model="instruction" type="checkbox" class="sr-only peer" checked>
                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                        </label>
                    </div>
                    
                    <!-- Subject name toggle -->
                    <div class="bg-gray-100 p-2 rounded flex justify-between items-center my-2">
                        <span class="bangla">বিষয়ের নাম</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input v-model="subject" type="checkbox" class="sr-only peer" checked>
                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                        </label>
                    </div>
                    
                    <!-- Set code toggle -->
                    <div class="bg-gray-100 p-2 rounded flex justify-between items-center my-2">
                        <span class="bangla">সেট কোড</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input v-model="setcode" type="checkbox" class="sr-only peer" checked>
                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                        </label>
                    </div>
                    
                    <!-- Font Selector -->
                    <div class="bg-gray-100 my-2 p-2">
                        <div class="rounded justify-between items-center">
                            <p class="bangla mb-1 text-center">ফন্ট পরিবর্তন</p>
                            <select 
                                v-model="selectedFont"
                                class="w-full p-1 border rounded bangla"
                            >
                                <option value="bangla">বাংলা</option>
                                <option value="SolaimanLipi">সোলাইমান লিপি</option>
                                <option value="Kalpurush">কালপুরুষ</option>
                                <option value="Times New Roman">টাইমস নিউ রোমান</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Font size control -->
                    <div class="bg-gray-100 p-2 rounded flex justify-between items-center my-2">
                        <span class="bangla">ফন্ট সাইজ</span>
                        <div class="flex items-center gap-1">
                            <button 
                                @click="decreaseFontSize"
                                class="hover:bg-white px-2 rounded text-lg"
                                :disabled="fontSize <= minFontSize"
                                :class="{ 'opacity-50 cursor-not-allowed': fontSize <= minFontSize }"
                            >-</button>
                            <p class="border rounded p-0.5 px-1.5 bg-white">{{ fontSize }}</p>
                            <button 
                                @click="increaseFontSize"
                                class="hover:bg-white px-2 rounded text-lg"
                                :disabled="fontSize >= maxFontSize"
                                :class="{ 'opacity-50 cursor-not-allowed': fontSize >= maxFontSize }"
                            >+</button>
                        </div>
                    </div>
                    
                    <!-- Columns selector -->
                    <div class="my-2">
                        <p class="bangla mb-1">কলাম</p>
                        <div class="flex gap-2 justify-center">
                            <div 
                                @click="setColumns(1)"
                                class="border rounded py-2 px-3 flex-1 cursor-pointer transition-colors"
                                :class="{ 'border-black': columnCount === 1, 'hover:border-gray-500': columnCount !== 1 }">
                                <div class="flex justify-center gap-0.5 items-center">
                                    <div class="w-4 h-6 bg-gray-300"></div>
                                </div>
                            </div>
                            
                            <div 
                                @click="setColumns(2)"
                                class="border rounded py-2 px-3 flex-1 cursor-pointer transition-colors"
                                :class="{ 'border-black': columnCount === 2, 'hover:border-gray-500': columnCount !== 2 }">
                                <div class="flex justify-center gap-0.5 items-center">
                                    <div class="w-4 h-6 bg-gray-300"></div>
                                    <div class="w-4 h-6 bg-gray-300"></div>
                                </div>
                            </div>
                            
                            <div 
                                @click="setColumns(3)"
                                class="border rounded py-2 px-3 flex-1 cursor-pointer transition-colors"
                                :class="{ 'border-black': columnCount === 3, 'hover:border-gray-500': columnCount !== 3 }">
                                <div class="flex justify-center gap-0.5 items-center">
                                    <div class="w-4 h-6 bg-gray-300"></div>
                                    <div class="w-4 h-6 bg-gray-300"></div>
                                    <div class="w-4 h-6 bg-gray-300"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Column divider toggle -->
                    <div class="bg-gray-100 p-2 rounded flex justify-between items-center my-2">
                        <span class="bangla">কলাম ডিভাইডার</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input v-model="showColumnDivider" type="checkbox" class="sr-only peer" checked>
                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                        </label>
                    </div>

                    <!-- Table Size Controls -->
                    <div v-if="hasTable" class="bg-gray-100 p-3 rounded my-2">
                        <p class="bangla text-center font-bold mb-3">টেবিল সাইজ কন্ট্রোল</p>
                        
                        <div class="slider-demo-block">
                            <span class="demonstration">প্রস্থ</span>
                            <el-slider 
                                v-model="tableWidth" 
                                :min="50" 
                                :max="100" 
                                :step="1"
                                :format-tooltip="(val) => val + '%'"
                                class="table-slider"
                            />
                            <span class="slider-value">{{ tableWidth }}%</span>
                        </div>
                        
                        <div class="slider-demo-block mt-2">
                            <span class="demonstration">উচ্চতা</span>
                            <el-slider 
                                v-model="tableHeight" 
                                :min="30" 
                                :max="100" 
                                :step="1"
                                :format-tooltip="(val) => val + '%'"
                                class="table-slider"
                            />
                            <span class="slider-value">{{ tableHeight }}%</span>
                        </div>
                        
                        <div class="slider-demo-block mt-2">
                            <span class="demonstration">সেল প্যাডিং</span>
                            <el-slider 
                                v-model="cellPadding" 
                                :min="2" 
                                :max="30" 
                                :step="1"
                                :format-tooltip="(val) => val + 'px'"
                                class="table-slider"
                            />
                            <span class="slider-value">{{ cellPadding }}px</span>
                        </div>
                        
                        <div class="slider-demo-block mt-2">
                            <span class="demonstration">বর্ডার সাইজ</span>
                            <el-slider 
                                v-model="borderWidth" 
                                :min="1" 
                                :max="5" 
                                :step="0.5"
                                :format-tooltip="(val) => val + 'px'"
                                class="table-slider"
                            />
                            <span class="slider-value">{{ borderWidth }}px</span>
                        </div>
                        
                        <button 
                            @click="resetTableStyles"
                            class="w-full mt-3 bg-blue-500 hover:bg-blue-600 text-white py-1.5 rounded text-sm transition-colors"
                        >
                            ডিফল্ট সাইজে রিসেট
                        </button>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- DESKTOP SIDEBAR SETTINGS                   -->
            <!-- ========================================== -->
            <div 
                class="hidden lg:block fixed top-20 inset-y-0 right-0 bg-white overflow-y-auto border-l z-10 font-10 shadow-lg transition-all duration-300"
                :class="isSidebarVisible ? 'w-72 xl:w-80' : 'w-0 border-0'"
            >
                <div class="relative overflow-hidden" :class="isSidebarVisible ? 'p-4' : 'p-0'">
                    <!-- Sidebar Toggle Button -->
                    <button 
                        @click="toggleSidebar"
                        class="absolute -left-10 top-4 bg-white border border-gray-200 rounded-l-lg p-2 shadow-md hover:bg-gray-50 transition-colors z-20"
                        :class="{ 'hidden': !isSidebarVisible }"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    
                    <div v-if="isSidebarVisible" class="bg-white backdrop-blur-lg">
                        <h1 class="py-2 flex items-center gap-2 justify-center rounded text-center bg-gray-50 mb-2 shadow">
                            <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 512 512" class="text-gray-500 text-sm" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                <path d="M495.9 166.6c3.2 8.7 .5 18.4-6.4 24.6l-43.3 39.4c1.1 8.3 1.7 16.8 1.7 25.4s-.6 17.1-1.7 25.4l43.3 39.4c6.9 6.2 9.6 15.9 6.4 24.6c-4.4 11.9-9.7 23.3-15.8 34.3l-4.7 8.1c-6.6 11-14 21.4-22.1 31.2c-5.9 7.2-15.7 9.6-24.5 6.8l-55.7-17.7c-13.4 10.3-28.2 18.9-44 25.4l-12.5 57.1c-2 9.1-9 16.3-18.2 17.8c-13.8 2.3-28 3.5-42.5 3.5s-28.7-1.2-42.5-3.5c-9.2-1.5-16.2-8.7-18.2-17.8l-12.5-57.1c-15.8-6.5-30.6-15.1-44-25.4L83.1 425.9c-8.8 2.8-18.6 .3-24.5-6.8c-8.1-9.8-15.5-20.2-22.1-31.2l-4.7-8.1c-6.1-11-11.4-22.4-15.8-34.3c-3.2-8.7-.5-18.4 6.4-24.6l43.3-39.4C64.6 273.1 64 264.6 64 256s.6-17.1 1.7-25.4L22.4 191.2c-6.9-6.2-9.6-15.9-6.4-24.6c4.4-11.9 9.7-23.3 15.8-34.3l4.7-8.1c6.6-11 14-21.4 22.1-31.2c5.9-7.2 15.7-9.6 24.5-6.8l55.7 17.7c8.8-2.8 18.6-.3 24.5 6.8c8.1 9.8 15.5 20.2 22.1 31.2l4.7 8.1c6.1 11 11.4 22.4 15.8 34.3zM256 336a80 80 0 1 0 0-160 80 80 0 1 0 0 160z"></path>
                            </svg> 
                            <span class="text-lg">সেটিংস</span>
                        </h1>
                        
                        <!-- Print Button -->
                        <button 
                          @click="printQuestions"
                          :disabled="isPrinting"
                          class="hover:bg-blue-400 bg-blue-500 transition-all py-2 rounded w-full text-center text-white flex items-center gap-2 justify-center"
                        >
                          <svg v-if="!isPrinting" stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 512 512" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                            <path d="M400 96V56c0-13.3-10.7-24-24-24H136c-13.3 0-24 10.7-24 24v40H24c-13.3 0-24 10.7-24 24v104c0 13.3 10.7 24 24 24h8v96c0 13.3 10.7 24 24 24h400c13.3 0 24-10.7 24-24v-96h8c13.3 0 24-10.7 24-24V120c0-13.3-10.7-24-24-24h-88zm-8 0H120V56h272v40zM104 392H56v-80h48v80zm344 0h-48v-80h48v80zm40-128H24V120h432v144zm-40 64c13.3 0 24-10.7 24-24s-10.7-24-24-24-24 10.7-24 24 10.7 24 24 24z"></path>
                          </svg>
                          <span v-if="!isPrinting">প্রিন্ট করুন</span>
                          <span v-else class="bangla">প্রিন্ট প্রস্তুত হচ্ছে...</span>
                        </button>
                        
                        <!-- Answer sheet toggle -->
                        <div class="bg-gray-100 p-2 rounded flex justify-between items-center my-2">
                            <span class="bangla">উত্তরপত্র</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="showAnswers" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                            </label>
                        </div>
                        
                        <!-- Option style selector -->
                        <div class="relative bg-gray-100 p-2 rounded my-2">
                            <p class="bangla mb-2">অপশন স্টাইল</p>
                            <div class="flex gap-2">
                                <div @click="setOptionStyle('circle')" 
                                    class="p-1 flex-1 flex justify-center items-center cursor-pointer rounded"
                                    :class="{ 'bg-emerald-600 text-white': optionStyle === 'circle', 'bg-white hover:bg-gray-100': optionStyle !== 'circle' }">
                                    <div class="h-5 w-5 border border-gray-500 rounded-full flex items-center justify-center">○</div>
                                </div>
                                <div @click="setOptionStyle('parens')" 
                                    class="p-1 flex-1 flex justify-center items-center cursor-pointer rounded"
                                    :class="{ 'bg-emerald-600 text-white': optionStyle === 'parens', 'bg-white hover:bg-gray-100': optionStyle !== 'parens' }">( )</div>
                                <div @click="setOptionStyle('dot')" 
                                    class="p-1 flex-1 flex justify-center items-center cursor-pointer rounded"
                                    :class="{ 'bg-emerald-600 text-white': optionStyle === 'dot', 'bg-white hover:bg-gray-100': optionStyle !== 'dot' }">
                                    <span class="text-2xl leading-none">.</span>
                                </div>
                                <div @click="setOptionStyle('paren')" 
                                    class="p-1 flex-1 flex justify-center items-center cursor-pointer rounded"
                                    :class="{ 'bg-emerald-600 text-white': optionStyle === 'paren', 'bg-white hover:bg-gray-100': optionStyle !== 'paren' }">)</div>
                            </div>
                        </div>
                        
                        <!-- Address toggle -->
                        <div>
                            <div class="bg-gray-100 p-2 rounded flex justify-between items-center my-2">
                                <span class="bangla">ঠিকানা</span>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input v-model="address" type="checkbox" class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Shuffle button -->
                        <div>
                            <div class="cursor-pointer bg-gray-100 hover:bg-emerald-600 hover:text-white flex justify-between items-center p-2 rounded my-2" 
                                @click="shuffleQuestions" tabindex="0">
                                <p>শাফল করুন</p>
                                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 512 512" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M403.8 34.4c12-5 25.7-2.2 34.9 6.9l64 64c6 6 9.4 14.1 9.4 22.6s-3.4 16.6-9.4 22.6l-64 64c-9.2 9.2-22.9 11.9-34.9 6.9s-19.8-16.6-19.8-29.6V160H352c-10.1 0-19.6 4.7-25.6 12.8L284 229.3 244 176l31.2-41.6C293.3 110.2 321.8 96 352 96h32V64c0-12.9 7.8-24.6 19.8-29.6zM164 282.7L204 336l-31.2 41.6C154.7 401.8 126.2 416 96 416H32c-17.7 0-32-14.3-32-32s14.3-32 32-32H96c10.1 0 19.6-4.7 25.6-12.8L164 282.7zm274.6 188c-9.2 9.2-22.9 11.9-34.9 6.9s-19.8-16.6-19.8-29.6V416H352c-30.2 0-58.7-14.2-76.8-38.4L121.6 172.8c-6-8.1-15.5-12.8-25.6-12.8H32c-17.7 0-32-14.3-32-32s14.3-32 32-32H96c30.2 0 58.7 14.2 76.8 38.4L326.4 339.2c6 8.1 15.5 12.8 25.6 12.8h32V320c0-12.9 7.8-24.6 19.8-29.6s25.7-2.2 34.9 6.9l64 64c6 6 9.4 14.1 9.4 22.6s-3.4 16.6-9.4 22.6l-64 64z"></path>
                                </svg>
                            </div>
                        </div>
                        
                        <!-- Instructions toggle -->
                        <div class="bg-gray-100 p-2 rounded flex justify-between items-center my-2">
                            <span class="bangla">নির্দেশনা</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input v-model="instruction" type="checkbox" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                            </label>
                        </div>
                        
                        <!-- Subject name toggle -->
                        <div class="bg-gray-100 p-2 rounded flex justify-between items-center my-2">
                            <span class="bangla">বিষয়ের নাম</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input v-model="subject" type="checkbox" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                            </label>
                        </div>
                        
                        <!-- Set code toggle -->
                        <div class="bg-gray-100 p-2 rounded flex justify-between items-center my-2">
                            <span class="bangla">সেট কোড</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input v-model="setcode" type="checkbox" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                            </label>
                        </div>
                        
                        <!-- Font Selector -->
                        <div class="bg-gray-100 my-2 p-2">
                            <div class="rounded justify-between items-center">
                                <p class="bangla mb-1 text-center">ফন্ট পরিবর্তন</p>
                                <select 
                                    v-model="selectedFont"
                                    class="w-full p-1 border rounded bangla"
                                >
                                    <option value="bangla">বাংলা</option>
                                    <option value="SolaimanLipi">সোলাইমান লিপি</option>
                                    <option value="Kalpurush">কালপুরুষ</option>
                                    <option value="Times New Roman">টাইমস নিউ রোমান</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Font size control -->
                        <div class="bg-gray-100 p-2 rounded flex justify-between items-center my-2">
                            <span class="bangla">ফন্ট সাইজ</span>
                            <div class="flex items-center gap-1">
                                <button 
                                    @click="decreaseFontSize"
                                    class="hover:bg-white px-2 rounded text-lg"
                                    :disabled="fontSize <= minFontSize"
                                    :class="{ 'opacity-50 cursor-not-allowed': fontSize <= minFontSize }"
                                >-</button>
                                <p class="border rounded p-0.5 px-1.5 bg-white">{{ fontSize }}</p>
                                <button 
                                    @click="increaseFontSize"
                                    class="hover:bg-white px-2 rounded text-lg"
                                    :disabled="fontSize >= maxFontSize"
                                    :class="{ 'opacity-50 cursor-not-allowed': fontSize >= maxFontSize }"
                                >+</button>
                            </div>
                        </div>
                        
                        <!-- Columns selector -->
                        <div class="my-2">
                            <p class="bangla mb-1">কলাম</p>
                            <div class="flex gap-2 justify-center">
                                <div 
                                    @click="setColumns(1)"
                                    class="border rounded py-2 px-3 flex-1 cursor-pointer transition-colors"
                                    :class="{ 'border-black': columnCount === 1, 'hover:border-gray-500': columnCount !== 1 }">
                                    <div class="flex justify-center gap-0.5 items-center">
                                        <div class="w-4 h-6 bg-gray-300"></div>
                                    </div>
                                </div>
                                
                                <div 
                                    @click="setColumns(2)"
                                    class="border rounded py-2 px-3 flex-1 cursor-pointer transition-colors"
                                    :class="{ 'border-black': columnCount === 2, 'hover:border-gray-500': columnCount !== 2 }">
                                    <div class="flex justify-center gap-0.5 items-center">
                                        <div class="w-4 h-6 bg-gray-300"></div>
                                        <div class="w-4 h-6 bg-gray-300"></div>
                                    </div>
                                </div>
                                
                                <div 
                                    @click="setColumns(3)"
                                    class="border rounded py-2 px-3 flex-1 cursor-pointer transition-colors"
                                    :class="{ 'border-black': columnCount === 3, 'hover:border-gray-500': columnCount !== 3 }">
                                    <div class="flex justify-center gap-0.5 items-center">
                                        <div class="w-4 h-6 bg-gray-300"></div>
                                        <div class="w-4 h-6 bg-gray-300"></div>
                                        <div class="w-4 h-6 bg-gray-300"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Column divider toggle -->
                        <div class="bg-gray-100 p-2 rounded flex justify-between items-center my-2">
                            <span class="bangla">কলাম ডিভাইডার</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input v-model="showColumnDivider" type="checkbox" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                            </label>
                        </div>

                        <!-- Table Size Controls -->
                        <div v-if="hasTable" class="bg-gray-100 p-3 rounded my-2">
                            <p class="bangla text-center font-bold mb-3">টেবিল সাইজ কন্ট্রোল</p>
                            
                            <div class="slider-demo-block">
                                <span class="demonstration">প্রস্থ</span>
                                <el-slider 
                                    v-model="tableWidth" 
                                    :min="50" 
                                    :max="100" 
                                    :step="1"
                                    :format-tooltip="(val) => val + '%'"
                                    class="table-slider"
                                />
                                <span class="slider-value">{{ tableWidth }}%</span>
                            </div>
                            
                            <div class="slider-demo-block mt-2">
                                <span class="demonstration">উচ্চতা</span>
                                <el-slider 
                                    v-model="tableHeight" 
                                    :min="30" 
                                    :max="100" 
                                    :step="1"
                                    :format-tooltip="(val) => val + '%'"
                                    class="table-slider"
                                />
                                <span class="slider-value">{{ tableHeight }}%</span>
                            </div>
                            
                            <div class="slider-demo-block mt-2">
                                <span class="demonstration">সেল প্যাডিং</span>
                                <el-slider 
                                    v-model="cellPadding" 
                                    :min="2" 
                                    :max="30" 
                                    :step="1"
                                    :format-tooltip="(val) => val + 'px'"
                                    class="table-slider"
                                />
                                <span class="slider-value">{{ cellPadding }}px</span>
                            </div>
                            
                            <div class="slider-demo-block mt-2">
                                <span class="demonstration">বর্ডার সাইজ</span>
                                <el-slider 
                                    v-model="borderWidth" 
                                    :min="1" 
                                    :max="5" 
                                    :step="0.5"
                                    :format-tooltip="(val) => val + 'px'"
                                    class="table-slider"
                                />
                                <span class="slider-value">{{ borderWidth }}px</span>
                            </div>
                            
                            <button 
                                @click="resetTableStyles"
                                class="w-full mt-3 bg-blue-500 hover:bg-blue-600 text-white py-1.5 rounded text-sm transition-colors"
                            >
                                ডিফল্ট সাইজে রিসেট
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Floating Desktop Sidebar Toggle Button (when hidden) -->
            <button 
                v-if="!isSidebarVisible"
                @click="toggleSidebar"
                class="hidden lg:block fixed right-4 top-24 bg-white border border-gray-200 rounded-lg p-2 shadow-md hover:bg-gray-50 transition-colors z-20"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>
    </UserDashboardLayout>
</template>

<script setup>
import LatexRenderer from '@/Components/LatexRenderer.vue';
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, computed, nextTick, watch, onUnmounted } from 'vue';
import { ElMessage } from 'element-plus';

const props = defineProps({
    savedquestion: {
        type: Array,
        required: true,
        default: () => []
    },
    institute: {
        type: Object,
        default: () => ({})
    }
});

// ============ Component State ============
const savedQuestion = ref(props.savedquestion?.[0] || null);
const optionStyle = ref('circle');
const showAnswers = ref(false);
const address = ref(true);
const instruction = ref(true);
const subject = ref(true);
const setcode = ref(true);
const currentSet = ref('ক');
const sets = ['ক', 'খ', 'গ', 'ঘ'];
const selectedFont = ref('bangla');
const fontSize = ref(14);
const minFontSize = 10;
const maxFontSize = 24;
const columnCount = ref(1);
const showColumnDivider = ref(true);
const isPrinting = ref(false);
const printError = ref(false);
const isSidebarVisible = ref(true);
const isMobileSidebarOpen = ref(false);

// ============ Font Classes ============
const fontClasses = {
    'bangla': 'bangla-font',
    'SolaimanLipi': 'font-solaiman-lipi',
    'Kalpurush': 'font-kalpurush',
    'Times New Roman': 'font-times-new-roman'
};

// ============ Check if table exists ============
const hasTable = computed(() => {
    const allText = allQuestions.value.map(q => {
        return JSON.stringify(q);
    }).join('');
    return /<table/i.test(allText);
});

// ============ Table Style Controls ============
const tableWidth = ref(100);
const tableHeight = ref(100);
const cellPadding = ref(8);
const borderWidth = ref(1);

watch([tableWidth, tableHeight, cellPadding, borderWidth], () => {
    if (hasTable.value) {
        applyTableStyles();
    }
});

const applyTableStyles = () => {
    nextTick(() => {
        const tables = document.querySelectorAll('#questions-container table');
        tables.forEach(table => {
            table.style.width = tableWidth.value + '%';
            table.style.height = tableHeight.value + '%';
            table.style.display = 'table';
            table.style.tableLayout = 'fixed';
            
            const cells = table.querySelectorAll('td, th');
            cells.forEach(cell => {
                cell.style.padding = cellPadding.value + 'px';
                cell.style.borderWidth = borderWidth.value + 'px';
                cell.style.wordWrap = 'break-word';
            });
        });
    });
};

const resetTableStyles = () => {
    tableWidth.value = 100;
    tableHeight.value = 100;
    cellPadding.value = 8;
    borderWidth.value = 1;
    
    setTimeout(() => {
        applyTableStyles();
    }, 100);
};

// ============ Computed Properties ============

const allQuestions = computed(() => {
    return savedQuestion.value?.questions || [];
});

const getClassName = () => {
    return allQuestions.value[0]?.academic_class?.name || '';
};

const getSubjectName = () => {
    return allQuestions.value[0]?.subject?.name || '';
};

// ============ Sidebar Toggle ============
const toggleSidebar = () => {
    isSidebarVisible.value = !isSidebarVisible.value;
};

// ============ Mobile Sidebar Methods ============
const openMobileSidebar = () => {
    isMobileSidebarOpen.value = true;
    document.body.style.overflow = 'hidden';
};

const closeMobileSidebar = () => {
    isMobileSidebarOpen.value = false;
    document.body.style.overflow = '';
};

// ============ Handle Resize ============
const handleResize = () => {
    if (window.innerWidth < 1024) {
        isSidebarVisible.value = false;
    } else {
        isSidebarVisible.value = true;
    }
};

// ============ Handle Escape Key ============
const handleEscapeKey = (event) => {
    if (event.key === 'Escape' && isMobileSidebarOpen.value) {
        closeMobileSidebar();
    }
};

// ============ GROUPED BY LEVEL ============
const groupedByLevel = computed(() => {
    const levelMap = new Map();
    
    allQuestions.value.forEach((q) => {
        let levelName = 'সাধারণ প্রশ্ন';
        let isInline = false;
        let levelId = null;
        
        const levelData = q.lavel || q.level || null;
        
        if (levelData) {
            levelName = levelData.name?.trim() || 'সাধারণ প্রশ্ন';
            
            if (levelData.is_inline !== undefined && levelData.is_inline !== null) {
                const val = levelData.is_inline;
                isInline = val === true || 
                          val === 1 || 
                          val === '1' || 
                          val === 'true' || 
                          val === 'TRUE' ||
                          (typeof val === 'string' && val.toLowerCase() === 'true');
            } else {
                isInline = false;
            }
            
            levelId = levelData.id || null;
        }
        
        if (q.inline_data && Object.keys(q.inline_data).length > 0) {
            isInline = true;
        }
        
        const levelKey = levelId ? `level_${levelId}` : `${levelName}_${isInline}`;
        
        if (!levelMap.has(levelKey)) {
            levelMap.set(levelKey, {
                levelName: levelName,
                is_inline: isInline,
                questions: [],
                totalMarks: 0,
                levelId: levelId
            });
        }
        
        const level = levelMap.get(levelKey);
        level.questions.push(q);
        level.totalMarks += calculateQuestionMark(q);
    });
    
    return Array.from(levelMap.values());
});

// ============ Format Inline Level Questions ============
const formatInlineLevelQuestions = (levelData) => {
    if (!levelData || !levelData.questions || levelData.questions.length === 0) {
        return '';
    }
    
    let allTexts = [];
    
    levelData.questions.forEach((question) => {
        if (question.question_text) {
            let text = question.question_text.trim();
            let parts = text.split(/[,،،؛;\n]+/).filter(p => p.trim() !== '');
            
            if (parts.length > 0) {
                parts.forEach(p => {
                    let clean = p.trim();
                    if (clean) {
                        allTexts.push(`<span class="inline-item">${clean}</span>`);
                    }
                });
            } else if (text) {
                allTexts.push(`<span class="inline-item">${text}</span>`);
            }
        }
    });
    
    if (allTexts.length === 0) {
        return 'No questions available';
    }
    
    return allTexts.join(', ') + '।';
};

// ============ Helper Functions ============
const getQuestionText = (question) => {
    if (!question) return 'No question';
    return question.question_text || 'No question text';
};

const getMcqOptions = (question) => {
    return question?.options || [];
};

const getCqOptions = (question) => {
    return question?.cqoptions || [];
};

const hasMcqOptions = (question) => getMcqOptions(question).length > 0;
const hasCqOptions = (question) => getCqOptions(question).length > 0;
const hasStimuli = (question) => question?.question_type === 'stimulus_based' && question.stimuli?.length > 0;
const getStimuli = (question) => question?.stimuli || [];
const hasSubQuestions = (question) => question?.question_type === 'stimulus_based' && question.sub_questions?.length > 0;
const getSubQuestions = (question) => question?.sub_questions || [];
const isStimulusBased = (question) => question?.question_type === 'stimulus_based';
const getExplanation = (question) => question?.explanation || null;

// ============ Serial Number Functions ============
const getQuestionSerial = (levelData, qIndex) => {
    let serial = 0;
    for (let i = 0; i < qIndex; i++) {
        const q = levelData.questions[i];
        if (q.question_type === 'stimulus_based') {
            serial += (q.sub_questions?.length || 0);
        } else {
            serial += 1;
        }
    }
    serial += 1;
    return serial;
};

const getSubQuestionSerial = (levelData, qIndex, sqIndex) => {
    let serial = 0;
    for (let i = 0; i < qIndex; i++) {
        const q = levelData.questions[i];
        if (q.question_type === 'stimulus_based') {
            serial += (q.sub_questions?.length || 0);
        } else {
            serial += 1;
        }
    }
    serial += sqIndex + 1;
    return serial;
};

// ============ Calculate Mark ============
const calculateQuestionMark = (question) => {
    if (!question) return 0;
    let mark = 0;
    
    if (question.question_type === 'standalone') {
        if (question.format === 'mcq') {
            mark = 1;
        } else if (question.format === 'cq') {
            mark = question.cqoptions?.reduce((sum, cq) => sum + parseFloat(cq.mark || 0), 0) || 0;
        } else if (question.format === 'mix') {
            mark = parseFloat(question.mark) || 0;
        }
    }
    
    if (question.question_type === 'stimulus_based') {
        question.sub_questions?.forEach(sq => {
            if (sq.format === 'mcq') {
                mark += 1;
            } else if (sq.format === 'cq') {
                mark += sq.cqoptions?.reduce((sum, cq) => sum + parseFloat(cq.mark || 0), 0) || 0;
            }
        });
    }
    
    return mark;
};

const calculateTotalMCQ = () => {
    let total = 0;
    allQuestions.value.forEach(q => {
        if (q.question_type === 'stimulus_based') {
            q.sub_questions?.forEach(sq => {
                if (sq.format === 'mcq') total += 1;
            });
        } else if (q.format === 'mcq') {
            total += 1;
        }
    });
    return total;
};

const calculateTotalCQ = () => {
    let total = 0;
    allQuestions.value.forEach(q => {
        if (q.question_type === 'stimulus_based') {
            q.sub_questions?.forEach(sq => {
                if (sq.format === 'cq') total += 1;
            });
        } else if (q.format === 'cq') {
            total += 1;
        }
    });
    return total;
};

// ============ Utility Functions ============
const getBanglaLetter = (index) => {
    const banglaLetters = ['ক', 'খ', 'গ', 'ঘ', 'ঙ', 'চ', 'ছ', 'জ', 'ঝ', 'ঞ'];
    return banglaLetters[index] || String.fromCharCode(2433 + index);
};

const toBengaliInteger = (num) => {
    if (!num && num !== 0) return '০';
    const numerals = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
    return num.toString().replace(/[0-9]/g, digit => numerals[digit]);
};

const toBengaliNumerals = (num) => toBengaliInteger(num);

// ============ Computed ============
const hasMCQ = computed(() => calculateTotalMCQ() > 0);
const hasCQ = computed(() => calculateTotalCQ() > 0);
const hasMix = computed(() => allQuestions.value.some(q => q.format === 'mix' && q.question_type !== 'stimulus_based'));
const hasStimulusBased = computed(() => allQuestions.value.some(q => q.question_type === 'stimulus_based'));

const totalFullMarks = computed(() => {
    let total = 0;
    allQuestions.value.forEach(q => total += calculateQuestionMark(q));
    return toBengaliInteger(total);
});

const totalTimeFormatted = computed(() => {
    const totalMCQ = calculateTotalMCQ();
    const totalCQ = calculateTotalCQ();
    const totalQuestions = allQuestions.value.length;
    
    if (totalQuestions === 0) return '২ ঘন্টা ৩০ মিনিট';
    
    const totalMinutes = (totalMCQ * 1) + (totalCQ * 10);
    
    if (totalMinutes < 60) {
        return `${toBengaliInteger(totalMinutes)} মিনিট`;
    } else {
        const hours = Math.floor(totalMinutes / 60);
        const minutes = totalMinutes % 60;
        return minutes === 0 ? 
            `${toBengaliInteger(hours)} ঘন্টা` : 
            `${toBengaliInteger(hours)} ঘন্টা ${toBengaliInteger(minutes)} মিনিট`;
    }
});

// ============ Methods ============
const setOptionStyle = (style) => { optionStyle.value = style; };
const increaseFontSize = () => { if (fontSize.value < maxFontSize) fontSize.value++; };
const decreaseFontSize = () => { if (fontSize.value > minFontSize) fontSize.value--; };
const setColumns = (count) => { columnCount.value = count; };

const updateLevelName = (levelData, event) => {
    const newName = event.target.innerText.trim();
    if (newName && newName !== levelData.levelName) {
        levelData.levelName = newName;
        ElMessage.success('Level name updated successfully!');
    } else if (!newName) {
        event.target.innerText = levelData.levelName;
        ElMessage.warning('Level name cannot be empty!');
    }
};

const shuffleQuestions = () => {
    currentSet.value = sets[Math.floor(Math.random() * sets.length)];
    const questionsCopy = JSON.parse(JSON.stringify(allQuestions.value));
    
    for (let i = questionsCopy.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [questionsCopy[i], questionsCopy[j]] = [questionsCopy[j], questionsCopy[i]];
    }
    
    savedQuestion.value = { ...savedQuestion.value, questions: questionsCopy };
};

const printQuestions = async () => {
    isPrinting.value = true;
    printError.value = false;
    
    try {
        if (window.MathJax && window.MathJax.typesetPromise) {
            await window.MathJax.typesetPromise();
        }
        
        const printContainer = document.createElement('div');
        printContainer.className = 'print-only-container';
        const questionsContainer = document.getElementById('questions-container');
        const clone = questionsContainer.cloneNode(true);
        
        clone.className = `${fontClasses[selectedFont.value]} question-text`;
        clone.style.fontSize = `${fontSize.value}px`;
        
        printContainer.appendChild(clone);
        
        const style = document.createElement('style');
        style.textContent = `
            @media print {
                body * { visibility: hidden; }
                .print-only-container, .print-only-container * { visibility: visible; }
                .print-only-container {
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    width: 100%;
                    padding: 10mm;
                    background: white;
                    z-index: 99999;
                }
                .break-inside-avoid { page-break-inside: avoid; break-inside: avoid; }
                mjx-container { display: inline-block !important; }
                @page { size: A4; margin: 10mm; }
            }
        `;
        
        document.head.appendChild(style);
        document.body.appendChild(printContainer);
        
        await new Promise(r => setTimeout(r, 100));
        window.print();
        
        setTimeout(() => {
            document.body.removeChild(printContainer);
            document.head.removeChild(style);
            isPrinting.value = false;
        }, 100);
        
    } catch (error) {
        console.error('Print failed:', error);
        printError.value = true;
        isPrinting.value = false;
    }
};

// ============ Lifecycle ============
onMounted(() => {
    const fontLinks = [
        'https://fonts.maateen.me/siyam-rupali/font.css',
        'https://fonts.maateen.me/kalpurush/font.css',
        'https://fonts.maateen.me/solaiman-lipi/font.css'
    ];
    
    fontLinks.forEach(link => {
        if (!document.querySelector(`link[href="${link}"]`)) {
            const fontLink = document.createElement('link');
            fontLink.href = link;
            fontLink.rel = 'stylesheet';
            document.head.appendChild(fontLink);
        }
    });
    
    window.addEventListener('resize', handleResize);
    document.addEventListener('keydown', handleEscapeKey);
    
    if (window.innerWidth < 1024) {
        isSidebarVisible.value = false;
    } else {
        isSidebarVisible.value = true;
    }
});

onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
    document.removeEventListener('keydown', handleEscapeKey);
    document.body.style.overflow = '';
});
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;700&display=swap');
@import url('https://fonts.maateen.me/siyam-rupali/font.css');
@import url('https://fonts.maateen.me/kalpurush/font.css');
@import url('https://fonts.maateen.me/solaiman-lipi/font.css');

.bangla {
    font-family: 'Hind Siliguri', sans-serif;
}
.bangla-bold {
    font-family: 'Hind Siliguri', sans-serif;
    font-weight: 700;
}

.font-siyam-rupali {
    font-family: 'Siyam Rupali', Arial, sans-serif;
}
.font-solaiman-lipi {
    font-family: 'SolaimanLipi', Arial, sans-serif;
}
.font-kalpurush {
    font-family: 'Kalpurush', Arial, sans-serif;
}
.font-times-new-roman {
    font-family: 'Times New Roman', serif;
}

/* ========================================== */
/* FULL WIDTH STYLES                          */
/* ========================================== */

.max-w-3xl, .max-w-4xl {
    max-width: 100% !important;
    width: 100% !important;
}

#questions-container {
    width: 100% !important;
    max-width: 100% !important;
}

.question-content {
    width: 100% !important;
    max-width: 100% !important;
}

.columns-1, .columns-2, .columns-3 {
    width: 100% !important;
    max-width: 100% !important;
    column-fill: balance;
}

.question-text {
    font-size: 14px;
    line-height: 1.3;
    width: 100%;
}

.question-text :deep(.question-content) {
    font-family: inherit;
    width: 100%;
}

.question-text :deep(*) {
    font-family: inherit;
}

.question-content {
    line-height: 1.3;
}

.question-content :deep(*) {
    font-size: inherit;
}

.question-content :deep(.option-circle) {
    font-size: 12px;
    width: 1rem;
    height: 1rem;
}

.question-content :deep(.bg-white) {
    margin-bottom: 2px !important;
}

.question-content :deep(.option) {
    padding: 1px 0 !important;
}

button[disabled] {
    opacity: 0.5;
    cursor: not-allowed;
}

.break-inside-avoid {
    break-inside: avoid;
    page-break-inside: avoid;
}

.columns-1, .columns-2, .columns-3 {
    transition: column-count 0.3s ease;
}

@media (max-width: 768px) {
    .columns-3 {
        columns: 2 !important;
    }
}

@media (max-width: 640px) {
    .columns-2, .columns-3 {
        columns: 1 !important;
    }
}

.columns-1 {
    column-count: 1 !important;
}
.columns-2 {
    column-count: 2 !important;
}
.columns-3 {
    column-count: 3 !important;
}

.bangla-font {
    font-family: 'Hind Siliguri', sans-serif !important;
}
.font-solaiman-lipi {
    font-family: 'SolaimanLipi', Arial, sans-serif !important;
}
.font-kalpurush {
    font-family: 'Kalpurush', Arial, sans-serif !important;
}
.font-times-new-roman {
    font-family: 'Times New Roman', serif !important;
}

@media print {
    body {
        font-size: inherit !important;
        font-family: inherit !important;
    }
    .print-font-inherit * {
        font-family: inherit !important;
        font-size: inherit !important;
    }
    mjx-container {
        display: inline-block !important;
    }
    .latex-renderer {
        display: inline;
    }
    .MathJax, .MathJax_CHTML {
        display: inline-block !important;
    }
    
    #questions-container {
        width: 100% !important;
        max-width: 100% !important;
        padding: 0 !important;
    }
}

/* ========================================== */
/* LEVEL NAME EDITING STYLES                  */
/* ========================================== */

.level-name {
    outline: none;
    padding: 0 4px;
    border-radius: 4px;
    transition: all 0.2s ease;
    cursor: text;
    min-width: 50px;
    display: inline-block;
}

.level-name:hover {
    background-color: rgba(255, 255, 255, 0.5);
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
}

.level-name:focus {
    background-color: white;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
    border-radius: 4px;
}

.level-name:empty::before {
    content: "Enter level name...";
    color: #9ca3af;
    font-style: italic;
}

.level-name::after {
    content: "✎";
    font-size: 12px;
    color: #9ca3af;
    margin-left: 4px;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.level-name:hover::after {
    opacity: 1;
}

/* ========================================== */
/* INLINE QUESTION STYLING                    */
/* ========================================== */

.inline-question-text {
    color: #1f2937;
}

.inline-question-text .inline-item {
    display: inline-block;
    padding: 0 2px;
    transition: all 0.2s ease;
}

.inline-question-text .inline-item:hover {
    background-color: #f0f7ff;
    border-radius: 3px;
    padding: 0 4px;
}

@media print {
    .inline-question-text .inline-item {
        padding: 0 1px;
    }
    .inline-question-text .inline-item:hover {
        background-color: transparent;
    }
}

/* ========================================== */
/* SIDEBAR TRANSITIONS                       */
/* ========================================== */

.transition-all {
    transition: all 0.3s ease-in-out;
}

.w-0 {
    width: 0;
    overflow: hidden;
}

/* ========================================== */
/* MOBILE DRAWER STYLES                      */
/* ========================================== */

.transition-transform {
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.hover\:scale-110:hover {
    transform: scale(1.1);
}

.scale-0 {
    transform: scale(0);
}

.opacity-0 {
    opacity: 0;
}

.w-12 {
    width: 3rem;
}
.h-1 {
    height: 0.25rem;
}

.max-h-\[85vh\] {
    max-height: 85vh;
}

.rounded-t-2xl {
    border-top-left-radius: 1rem;
    border-top-right-radius: 1rem;
}

.fixed.inset-0 {
    transition: opacity 0.3s ease-in-out;
}

.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 2px;
}

.fixed.bottom-0 {
    will-change: transform;
}

/* ========================================== */
/* SLIDER STYLES                              */
/* ========================================== */

.slider-demo-block {
    display: flex;
    align-items: center;
    gap: 8px;
}

.slider-demo-block .demonstration {
    font-size: 13px;
    color: #4b5563;
    min-width: 60px;
    flex-shrink: 0;
}

.slider-demo-block .table-slider {
    flex: 1;
    min-width: 0;
}

.slider-demo-block .slider-value {
    font-size: 13px;
    color: #2563eb;
    font-weight: 600;
    min-width: 45px;
    text-align: right;
}

:deep(.el-slider) {
    margin-top: 0;
}

:deep(.el-slider .el-slider__runway) {
    height: 4px;
    background-color: #e5e7eb;
}

:deep(.el-slider .el-slider__bar) {
    background-color: #2563eb;
}

:deep(.el-slider .el-slider__button) {
    width: 16px;
    height: 16px;
    border: 2px solid #2563eb;
    background-color: white;
}

:deep(.el-slider .el-slider__button:hover) {
    transform: scale(1.1);
}

:deep(.el-slider .el-slider__button-wrapper) {
    height: 16px;
}

:deep(.el-slider .el-slider__stop) {
    background-color: #d1d5db;
}

:deep(.el-slider .el-slider__marks-text) {
    font-size: 11px;
    color: #6b7280;
}

/* ========================================== */
/* TABLE STYLES                               */
/* ========================================== */

:deep(#questions-container table) {
    border-collapse: collapse;
    width: 100% !important;
    margin: 10px 0;
    border: 1px solid #ccc;
    transition: all 0.3s ease;
    display: table !important;
    table-layout: fixed !important;
}

:deep(#questions-container table td),
:deep(#questions-container table th) {
    border: 1px solid #ccc;
    padding: 8px;
    text-align: left;
    vertical-align: top;
    min-width: 50px;
    transition: all 0.3s ease;
    word-wrap: break-word;
}

:deep(#questions-container table th) {
    background-color: #f2f2f2;
    font-weight: bold;
}

:deep(#questions-container table tr:nth-child(even)) {
    background-color: #f9f9f9;
}

:deep(#questions-container table tr:hover) {
    background-color: #f5f5f5;
}

@media (max-width: 768px) {
    :deep(#questions-container table) {
        display: block !important;
        overflow-x: auto;
        white-space: nowrap;
        width: 100% !important;
    }
    
    :deep(#questions-container table td),
    :deep(#questions-container table th) {
        min-width: 80px;
    }
}

/* ========================================== */
/* RESPONSIVE FIXES                           */
/* ========================================== */

@media (max-width: 1023px) {
    .lg\:pr-80, .lg\:pr-4, .xl\:pr-96 {
        padding-right: 1rem !important;
    }
    #questions-container {
        padding: 0.5rem !important;
    }
}

@media print {
    .hidden.lg\:block {
        display: none !important;
    }
    .lg\:pr-80, .lg\:pr-4, .xl\:pr-96 {
        padding-right: 0 !important;
    }
    #questions-container {
        padding: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }
}

.w-full {
    width: 100% !important;
}

.min-h-screen {
    min-height: 100vh;
}

.columns-2, .columns-3 {
    column-fill: balance;
}

@media (min-width: 1024px) {
    #questions-container {
        padding: 1rem 1.5rem !important;
    }
}
</style>