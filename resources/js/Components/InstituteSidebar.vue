<!-- UserSidebar.vue - Result Management System -->
<script setup>
import { computed, defineEmits, defineProps } from 'vue';
import NavLink from '@/Components/NavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
  isCollapsed: Boolean,
  isMobile: Boolean,
  isMobileOpen: Boolean,
});
const emit = defineEmits(['toggle-collapse', 'close-mobile']);
const page = usePage();
const isTeacher = computed(() => String(page.props.auth?.user?.usertype || '').toLowerCase() === 'teacher');
const teacherPermissions = computed(() => page.props.teacherPermissions || {});
const sidebarStats = computed(() => page.props.instituteSidebarStats || {});
const canViewAnyExamDocument = computed(() => [
  'admit_cards.print',
  'seat_plan.print',
  'marksheets.print',
  'tabulation_sheet.print',
].some(permission => teacherPermissions.value[permission]));

// Route helper function for active state
const isRouteActive = (patterns) => {
  const currentRoute = route().current();
  if (!currentRoute) return false;

  return patterns.some(pattern => {
    if (pattern.endsWith('*')) {
      const basePattern = pattern.replace('*', '');
      return currentRoute.startsWith(basePattern);
    }
    return currentRoute === pattern;
  });
};
</script>

<template>
  <aside
    class="z-50 flex min-h-0 shrink-0 flex-col overflow-hidden border-r border-gray-100 bg-white p-4 shadow-lg transition-all duration-300 md:sticky md:top-16 md:h-[calc(100vh-4rem)]"
    :class="[
      isMobile
        ? ['fixed bottom-0 left-0 top-16 h-[calc(100dvh-4rem)] w-72 max-w-[85vw]', isMobileOpen ? 'translate-x-0' : '-translate-x-full']
        : ['translate-x-0', isCollapsed ? 'w-20' : 'w-64'],
    ]"
  >
    <div v-if="isMobile" class="mb-3 flex items-center justify-between border-b border-gray-100 pb-3">
      <span class="font-semibold text-gray-800">Menu</span>
      <button
        type="button"
        aria-label="Close sidebar"
        class="rounded-lg p-2 text-gray-500 hover:bg-gray-100"
        @click="emit('close-mobile')"
      >
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <!-- Logo/Brand Section -->
    <!-- <div class="flex items-center justify-center mb-6 pb-4 border-b border-gray-100" 
         :class="isCollapsed ? 'px-2' : 'px-3'">
      <div class="flex items-center">
        <div class="w-10 h-10 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-lg flex items-center justify-center shadow-md flex-shrink-0">
          <i class="fa-solid fa-graduation-cap text-white text-lg"></i>
        </div>
        <span v-if="!isCollapsed" class="ml-3 text-lg font-bold text-gray-800 whitespace-nowrap">
          School RMS
        </span>
      </div>
    </div> -->

    <!-- Navigation -->
    <nav
      class="min-h-0 flex-1 overflow-y-auto"
      @click="isMobile && emit('close-mobile')"
    >
      <ul class="space-y-1">
        <!-- Student Management -->
        <li v-if="!isTeacher">
          <NavLink 
            :href="route('students.index')"
            :active="isRouteActive(['students', 'students.*'])"
            class="flex items-center p-3 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-200 group"
            :class="[
              isCollapsed ? 'justify-center px-2' : 'px-3',
              isRouteActive(['students', 'students.*']) ? 'bg-blue-50 text-blue-600' : 'text-gray-600'
            ]"
          >
            <div class="flex items-center w-full" :class="isCollapsed ? 'justify-center' : ''">
              <i class="fa-solid fa-user-graduate text-lg w-6 text-center" 
                 :class="isRouteActive(['students', 'students.*']) ? 'text-blue-600' : 'text-gray-400 group-hover:text-blue-600'"></i>
              <span v-if="!isCollapsed" class="ml-3 whitespace-nowrap">Students</span>
              <span v-if="!isCollapsed" class="ml-auto text-xs text-gray-400" title="Total students">{{ sidebarStats.students ?? 0 }}</span>
            </div>
          </NavLink>
        </li>

        <!-- Teachers Management -->
        <li v-if="!isTeacher">
          <NavLink 
            :href="route('teachers.index')"
            :active="isRouteActive(['teachers', 'teachers.*'])"
            class="flex items-center p-3 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-200 group"
            :class="[
              isCollapsed ? 'justify-center px-2' : 'px-3',
              isRouteActive(['teachers', 'teachers.*']) ? 'bg-blue-50 text-blue-600' : 'text-gray-600'
            ]"
          >
            <div class="flex items-center w-full" :class="isCollapsed ? 'justify-center' : ''">
              <i class="fa-solid fa-chalkboard-user text-lg w-6 text-center" 
                 :class="isRouteActive(['teachers', 'teachers.*']) ? 'text-blue-600' : 'text-gray-400 group-hover:text-blue-600'"></i>
              <span v-if="!isCollapsed" class="ml-3 whitespace-nowrap">Teachers</span>
              <span v-if="!isCollapsed" class="ml-auto text-xs text-gray-400" title="Total teachers">{{ sidebarStats.teachers ?? 0 }}</span>
            </div>
          </NavLink>
        </li>

        <!-- Subjects -->
        <li v-if="!isTeacher">
          <NavLink 
            :href="route('subjects.index')"
            :active="isRouteActive(['subjects', 'subjects.*'])"
            class="flex items-center p-3 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-200 group"
            :class="[
              isCollapsed ? 'justify-center px-2' : 'px-3',
              isRouteActive(['subjects', 'subjects.*']) ? 'bg-blue-50 text-blue-600' : 'text-gray-600'
            ]"
          >
            <div class="flex items-center w-full" :class="isCollapsed ? 'justify-center' : ''">
              <i class="fa-solid fa-book-open text-lg w-6 text-center"
                 :class="isRouteActive(['subjects', 'subjects.*']) ? 'text-blue-600' : 'text-gray-400 group-hover:text-blue-600'"></i>
              <span v-if="!isCollapsed" class="ml-3 whitespace-nowrap">Subjects</span>
              <span v-if="!isCollapsed" class="ml-auto text-xs text-gray-400" title="Total subjects">{{ sidebarStats.subjects ?? 0 }}</span>
            </div>
          </NavLink>
        </li>

       <!-- ✅ Teacher Subject Assignment (Index Page) -->
        <li v-if="!isTeacher">
          <NavLink
            :href="route('teacher-subjects.index')"
            :active="isRouteActive(['teacher-subjects', 'teacher-subjects.*'])"
            class="flex items-center p-3 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-200 group"
            :class="[
              isCollapsed ? 'justify-center px-2' : 'px-3',
              isRouteActive(['teacher-subjects', 'teacher-subjects.*']) ? 'bg-blue-50 text-blue-600' : 'text-gray-600'
            ]"
          >
            <div class="flex items-center w-full" :class="isCollapsed ? 'justify-center' : ''">
              <i class="fa-solid fa-user-tie text-lg w-6 text-center"
                 :class="isRouteActive(['teacher-subjects', 'teacher-subjects.*']) ? 'text-blue-600' : 'text-gray-400 group-hover:text-blue-600'"></i>
              <span v-if="!isCollapsed" class="ml-3 whitespace-nowrap">Teacher Subjects</span>
              <span v-if="!isCollapsed" class="ml-auto">
                <span class="px-2 py-0.5 text-xs font-medium bg-purple-100 text-purple-600 rounded-full">Assign</span>
              </span>
            </div>
          </NavLink>
        </li>

        <!-- Roles and Permissions -->
        <li v-if="!isTeacher">
          <NavLink
            :href="route('teacher-permissions.index')"
            :active="isRouteActive(['teacher-permissions.*'])"
            class="flex items-center p-3 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-200 group"
            :class="[
              isCollapsed ? 'justify-center px-2' : 'px-3',
              isRouteActive(['teacher-permissions.*']) ? 'bg-blue-50 text-blue-600' : 'text-gray-600'
            ]"
          >
            <div class="flex items-center w-full" :class="isCollapsed ? 'justify-center' : ''">
              <i class="fa-solid fa-shield-halved text-lg w-6 text-center"
                 :class="isRouteActive(['teacher-permissions.*']) ? 'text-blue-600' : 'text-gray-400 group-hover:text-blue-600'"></i>
              <span v-if="!isCollapsed" class="ml-3 whitespace-nowrap">Roles &amp; Permissions</span>
            </div>
          </NavLink>
        </li>

        <!-- Exams Management -->
        <li v-if="!isTeacher">
          <NavLink 
            :href="route('exams.index')"
            :active="isRouteActive(['exams', 'exams.*'])"
            class="flex items-center p-3 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-200 group"
            :class="[
              isCollapsed ? 'justify-center px-2' : 'px-3',
              isRouteActive(['exams', 'exams.*']) ? 'bg-blue-50 text-blue-600' : 'text-gray-600'
            ]"
          >
            <div class="flex items-center w-full" :class="isCollapsed ? 'justify-center' : ''">
              <i class="fa-solid fa-pen-to-square text-lg w-6 text-center" 
                 :class="isRouteActive(['exams', 'exams.*']) ? 'text-blue-600' : 'text-gray-400 group-hover:text-blue-600'"></i>
              <span v-if="!isCollapsed" class="ml-3 whitespace-nowrap">Exams</span>
              <span v-if="!isCollapsed && sidebarStats.upcoming_exams > 0" class="ml-auto">
                <span class="px-2 py-0.5 text-xs font-medium bg-yellow-100 text-yellow-600 rounded-full" title="Upcoming exams">{{ sidebarStats.upcoming_exams }}</span>
              </span>
            </div>
          </NavLink>
        </li>

        <!-- Assign Subjects to Exam -->
        <li v-if="!isTeacher">
          <NavLink 
            :href="route('exam-subjects.index')"
            :active="isRouteActive(['exam-subjects', 'exam-subjects.*'])"
            class="flex items-center p-3 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-200 group"
            :class="[
              isCollapsed ? 'justify-center px-2' : 'px-3',
              isRouteActive(['exam-subjects', 'exam-subjects.*']) ? 'bg-blue-50 text-blue-600' : 'text-gray-600'
            ]"
          >
            <div class="flex items-center w-full" :class="isCollapsed ? 'justify-center' : ''">
              <i class="fa-solid fa-list-check text-lg w-6 text-center" 
                 :class="isRouteActive(['exam-subjects', 'exam-subjects.*']) ? 'text-blue-600' : 'text-gray-400 group-hover:text-blue-600'"></i>
              <span v-if="!isCollapsed" class="ml-3 whitespace-nowrap">Exam Subjects</span>
            </div>
          </NavLink>
        </li>

        <li v-if="!isTeacher">
          <NavLink
            :href="route('class-routines.index')"
            :active="isRouteActive(['class-routines', 'class-routines.*'])"
            class="flex items-center p-3 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-200 group"
            :class="[
              isCollapsed ? 'justify-center px-2' : 'px-3',
              isRouteActive(['class-routines', 'class-routines.*']) ? 'bg-blue-50 text-blue-600' : 'text-gray-600'
            ]"
          >
            <div class="flex items-center w-full" :class="isCollapsed ? 'justify-center' : ''">
              <i class="fa-solid fa-calendar-days text-lg w-6 text-center"
                 :class="isRouteActive(['class-routines', 'class-routines.*']) ? 'text-blue-600' : 'text-gray-400 group-hover:text-blue-600'"></i>
              <span v-if="!isCollapsed" class="ml-3 whitespace-nowrap">Class Routine</span>
            </div>
          </NavLink>
        </li>

        <!-- Marks Entry -->
        <li v-if="!isTeacher || teacherPermissions['marks.enter'] || teacherPermissions['marks.view']">
          <NavLink 
            :href="route(isTeacher && !teacherPermissions['marks.enter'] ? 'marks.index' : 'marks.entry')"
            :active="isRouteActive(['marks', 'marks.*'])"
            class="flex items-center p-3 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-200 group"
            :class="[
              isCollapsed ? 'justify-center px-2' : 'px-3',
              isRouteActive(['marks', 'marks.*']) ? 'bg-blue-50 text-blue-600' : 'text-gray-600'
            ]"
          >
            <div class="flex items-center w-full" :class="isCollapsed ? 'justify-center' : ''">
              <i class="fa-solid fa-pen-fancy text-lg w-6 text-center" 
                 :class="isRouteActive(['marks', 'marks.*']) ? 'text-blue-600' : 'text-gray-400 group-hover:text-blue-600'"></i>
              <span v-if="!isCollapsed" class="ml-3 whitespace-nowrap">Marks Entry</span>
              <span v-if="!isCollapsed && sidebarStats.pending_marks > 0" class="ml-auto">
                <span class="px-2 py-0.5 text-xs font-medium bg-red-100 text-red-600 rounded-full" title="Exams awaiting marks entry">{{ sidebarStats.pending_marks }}</span>
              </span>
            </div>
          </NavLink>
        </li>

        <!-- Homework -->
        <li v-if="!isTeacher || teacherPermissions['homework.manage']">
          <NavLink
            :href="route('homework.index')"
            :active="isRouteActive(['homework', 'homework.*'])"
            class="flex items-center p-3 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-200 group"
            :class="[
              isCollapsed ? 'justify-center px-2' : 'px-3',
              isRouteActive(['homework', 'homework.*']) ? 'bg-blue-50 text-blue-600' : 'text-gray-600'
            ]"
          >
            <div class="flex items-center w-full" :class="isCollapsed ? 'justify-center' : ''">
              <i class="fa-solid fa-book-open-reader text-lg w-6 text-center"
                 :class="isRouteActive(['homework', 'homework.*']) ? 'text-blue-600' : 'text-gray-400 group-hover:text-blue-600'"></i>
              <span v-if="!isCollapsed" class="ml-3 whitespace-nowrap">Homework</span>
            </div>
          </NavLink>
        </li>

        <li v-if="!isTeacher || canViewAnyExamDocument">
          <NavLink
            :href="route('exam-documents.index')"
            :active="isRouteActive(['exam-documents', 'exam-documents.*'])"
            class="flex items-center p-3 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-200 group"
            :class="[
              isCollapsed ? 'justify-center px-2' : 'px-3',
              isRouteActive(['exam-documents', 'exam-documents.*']) ? 'bg-blue-50 text-blue-600' : 'text-gray-600'
            ]"
          >
            <div class="flex items-center w-full" :class="isCollapsed ? 'justify-center' : ''">
              <i class="fa-solid fa-ticket text-lg w-6 text-center"
                 :class="isRouteActive(['exam-documents', 'exam-documents.*']) ? 'text-blue-600' : 'text-gray-400 group-hover:text-blue-600'"></i>
              <span v-if="!isCollapsed" class="ml-3 whitespace-nowrap">Exam Documents</span>
            </div>
          </NavLink>
        </li>

        <!-- Results Management -->
        <li v-if="!isTeacher">
          <NavLink 
            :href="route('results.index')"
            :active="isRouteActive(['results', 'results.*'])"
            class="flex items-center p-3 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-200 group"
            :class="[
              isCollapsed ? 'justify-center px-2' : 'px-3',
              isRouteActive(['results', 'results.*']) ? 'bg-blue-50 text-blue-600' : 'text-gray-600'
            ]"
          >
            <div class="flex items-center w-full" :class="isCollapsed ? 'justify-center' : ''">
              <i class="fa-solid fa-file-lines text-lg w-6 text-center" 
                 :class="isRouteActive(['results', 'results.*']) ? 'text-blue-600' : 'text-gray-400 group-hover:text-blue-600'"></i>
              <span v-if="!isCollapsed" class="ml-3 whitespace-nowrap">Results</span>
              <span v-if="!isCollapsed" class="ml-auto text-xs text-gray-400" title="Total results">{{ sidebarStats.results ?? 0 }}</span>
            </div>
          </NavLink>
        </li>
        <li v-if="!isTeacher">
          <NavLink
            :href="route('combined-results.index')"
            :active="isRouteActive(['combined-results.*'])"
            class="flex items-center p-3 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-200 group"
            :class="[
              isCollapsed ? 'justify-center px-2' : 'px-3',
              isRouteActive(['combined-results.*']) ? 'bg-blue-50 text-blue-600' : 'text-gray-600'
            ]"
          >
            <div class="flex items-center w-full" :class="isCollapsed ? 'justify-center' : ''">
              <i class="fa-solid fa-calculator text-lg w-6 text-center"
                 :class="isRouteActive(['combined-results.*']) ? 'text-blue-600' : 'text-gray-400 group-hover:text-blue-600'"></i>
              <span v-if="!isCollapsed" class="ml-3 whitespace-nowrap">Combined Results</span>
            </div>
          </NavLink>
        </li>

        <!-- Publish Results -->
        <li v-if="!isTeacher || teacherPermissions['results.publish']">
          <NavLink 
            :href="route('results.publish')"
            :active="isRouteActive(['results.publish', 'results.publish.*'])"
            class="flex items-center p-3 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-200 group"
            :class="[
              isCollapsed ? 'justify-center px-2' : 'px-3',
              isRouteActive(['results.publish', 'results.publish.*']) ? 'bg-blue-50 text-blue-600' : 'text-gray-600'
            ]"
          >
            <div class="flex items-center w-full" :class="isCollapsed ? 'justify-center' : ''">
              <i class="fa-solid fa-globe text-lg w-6 text-center" 
                 :class="isRouteActive(['results.publish', 'results.publish.*']) ? 'text-blue-600' : 'text-gray-400 group-hover:text-blue-600'"></i>
              <span v-if="!isCollapsed" class="ml-3 whitespace-nowrap">Publish Results</span>
              <span v-if="!isCollapsed && sidebarStats.pending_results > 0" class="ml-auto">
                <span class="px-2 py-0.5 text-xs font-medium bg-green-100 text-green-600 rounded-full" title="Results ready to publish">{{ sidebarStats.pending_results }}</span>
              </span>
            </div>
          </NavLink>
        </li>

        <!-- Grade System -->
        <li v-if="!isTeacher">
          <NavLink 
            :href="route('grades.index')"
            :active="isRouteActive(['grades', 'grades.*'])"
            class="flex items-center p-3 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-200 group"
            :class="[
              isCollapsed ? 'justify-center px-2' : 'px-3',
              isRouteActive(['grades', 'grades.*']) ? 'bg-blue-50 text-blue-600' : 'text-gray-600'
            ]"
          >
            <div class="flex items-center w-full" :class="isCollapsed ? 'justify-center' : ''">
              <i class="fa-solid fa-star text-lg w-6 text-center" 
                 :class="isRouteActive(['grades', 'grades.*']) ? 'text-blue-600' : 'text-gray-400 group-hover:text-blue-600'"></i>
              <span v-if="!isCollapsed" class="ml-3 whitespace-nowrap">Grade System</span>
            </div>
          </NavLink>
        </li>

        <!-- Separator -->
        <li v-if="!isTeacher" class="relative my-4">
          <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-gray-200"></div>
          </div>
          <div class="relative flex justify-center" v-if="!isCollapsed">
            <span class="px-3 bg-white text-xs text-gray-400">Settings</span>
          </div>
        </li>

        <!-- Class Management -->
        <li v-if="!isTeacher">
          <NavLink 
            :href="route('classes.index')" 
            :active="isRouteActive(['classes', 'classes.*'])"
            class="flex items-center p-3 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-200 group"
            :class="[
              isCollapsed ? 'justify-center px-2' : 'px-3',
              isRouteActive(['classes', 'classes.*']) ? 'bg-blue-50 text-blue-600' : 'text-gray-600'
            ]"
          >
            <div class="flex items-center w-full" :class="isCollapsed ? 'justify-center' : ''">
              <i class="fa-solid fa-layer-group text-lg w-6 text-center" 
                 :class="isRouteActive(['classes', 'classes.*']) ? 'text-blue-600' : 'text-gray-400 group-hover:text-blue-600'"></i>
              <span v-if="!isCollapsed" class="ml-3 whitespace-nowrap">Classes</span>
              <span v-if="!isCollapsed" class="ml-auto text-xs text-gray-400" title="Total classes">{{ sidebarStats.classes ?? 0 }}</span>
            </div>
          </NavLink>
        </li>

        <!-- Sections -->
        <li v-if="!isTeacher">
          <NavLink 
             :href="route('sections.index')"
            :active="isRouteActive(['sections', 'sections.*'])"
            class="flex items-center p-3 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-200 group"
            :class="[
              isCollapsed ? 'justify-center px-2' : 'px-3',
              isRouteActive(['sections', 'sections.*']) ? 'bg-blue-50 text-blue-600' : 'text-gray-600'
            ]"
          >
            <div class="flex items-center w-full" :class="isCollapsed ? 'justify-center' : ''">
              <i class="fa-solid fa-grip text-lg w-6 text-center" 
                 :class="isRouteActive(['sections', 'sections.*']) ? 'text-blue-600' : 'text-gray-400 group-hover:text-blue-600'"></i>
              <span v-if="!isCollapsed" class="ml-3 whitespace-nowrap">Sections</span>
            </div>
          </NavLink>
        </li>

        <!-- Academic Years -->
        <li v-if="!isTeacher">
          <NavLink 
            :href="route('academic-years.index')" 
            :active="isRouteActive(['academic-years', 'academic-years.*'])"
            class="flex items-center p-3 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-200 group"
            :class="[
              isCollapsed ? 'justify-center px-2' : 'px-3',
              isRouteActive(['academic-years', 'academic-years.*']) ? 'bg-blue-50 text-blue-600' : 'text-gray-600'
            ]"
          >
            <div class="flex items-center w-full" :class="isCollapsed ? 'justify-center' : ''">
              <i class="fa-solid fa-calendar-days text-lg w-6 text-center" 
                 :class="isRouteActive(['academic-years', 'academic-years.*']) ? 'text-blue-600' : 'text-gray-400 group-hover:text-blue-600'"></i>
              <span v-if="!isCollapsed" class="ml-3 whitespace-nowrap">Academic Years</span>
            </div>
          </NavLink>
        </li>

        <!-- Attendance -->
        <li v-if="!isTeacher || teacherPermissions['attendance.view'] || teacherPermissions['attendance.manage']">
          <NavLink 
            :href="route('attendance.index')"
            :active="isRouteActive(['attendance', 'attendance.*'])"
            class="flex items-center p-3 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-200 group"
            :class="[
              isCollapsed ? 'justify-center px-2' : 'px-3',
              isRouteActive(['attendance', 'attendance.*']) ? 'bg-blue-50 text-blue-600' : 'text-gray-600'
            ]"
          >
            <div class="flex items-center w-full" :class="isCollapsed ? 'justify-center' : ''">
              <i class="fa-solid fa-clipboard-check text-lg w-6 text-center" 
                 :class="isRouteActive(['attendance', 'attendance.*']) ? 'text-blue-600' : 'text-gray-400 group-hover:text-blue-600'"></i>
              <span v-if="!isCollapsed" class="ml-3 whitespace-nowrap">Attendance</span>
            </div>
          </NavLink>
        </li>

        <!-- Settings -->
        <li>
          <NavLink 
            :href="route('profile.edit')"
            :active="isRouteActive(['profile.edit', 'profile.update', 'profile.destroy'])"
            class="flex items-center p-3 rounded-lg hover:bg-blue-50 hover:text-blue-600 transition-all duration-200 group"
            :class="[
              isCollapsed ? 'justify-center px-2' : 'px-3',
              isRouteActive(['profile.edit', 'profile.update', 'profile.destroy']) ? 'bg-blue-50 text-blue-600' : 'text-gray-600'
            ]"
          >
            <div class="flex items-center w-full" :class="isCollapsed ? 'justify-center' : ''">
              <i class="fa-solid fa-gear text-lg w-6 text-center" 
                 :class="isRouteActive(['profile.edit', 'profile.update', 'profile.destroy']) ? 'text-blue-600' : 'text-gray-400 group-hover:text-blue-600'"></i>
              <span v-if="!isCollapsed" class="ml-3 whitespace-nowrap">Settings</span>
            </div>
          </NavLink>
        </li>
      </ul>
    </nav>

    <!-- Bottom Section -->
    <div class="mt-auto shrink-0 pt-4 border-t border-gray-100">
      <!-- User Profile (when not collapsed) -->
      <Link v-if="!isCollapsed" :href="route('profile.edit')" class="flex items-center p-3 rounded-lg hover:bg-gray-50 transition-colors">
        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-500 to-indigo-500 flex items-center justify-center text-white font-bold">
          {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
        </div>
        <div class="ml-3 flex-1">
          <p class="text-sm font-medium text-gray-800">{{ $page.props.auth.user.name }}</p>
          <p class="text-xs text-gray-500">{{ $page.props.auth.user.email }}</p>
        </div>
        <i class="fa-solid fa-ellipsis-vertical text-gray-400"></i>
      </Link>

      <!-- Collapsed User Icon -->
      <Link v-if="isCollapsed" :href="route('profile.edit')" class="flex justify-center" aria-label="Open profile settings">
        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-500 to-indigo-500 flex items-center justify-center text-white font-bold cursor-pointer hover:shadow-lg transition-shadow">
          {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
        </div>
      </Link>

      <!-- Collapse/Expand Button -->
      <button 
        @click="emit('toggle-collapse')"
        class="w-full mt-2 p-2 rounded-lg hover:bg-gray-100 transition-colors flex items-center justify-center text-gray-500"
        :class="isCollapsed ? 'justify-center' : 'justify-end'"
      >
        <i :class="isCollapsed ? 'fa-solid fa-arrow-right' : 'fa-solid fa-arrow-left'"></i>
      </button>
    </div>
  </aside>
</template>

<style scoped>
nav::-webkit-scrollbar {
  width: 4px;
}

nav::-webkit-scrollbar-track {
  background: transparent;
}

nav::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 2px;
}

nav::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

/* Active link indicator */
.router-link-active {
  position: relative;
}

.router-link-active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 3px;
  height: 60%;
  background: #3b82f6;
  border-radius: 0 2px 2px 0;
}

/* Smooth hover transitions */
.nav-item {
  transition: all 0.2s ease-in-out;
}

.nav-item:hover {
  transform: translateX(4px);
}

/* Badge animations */
.badge-pulse {
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.05);
  }
  100% {
    transform: scale(1);
  }
}

/* Collapsed state styles */
.collapsed .nav-text {
  display: none;
}

.collapsed .nav-icon {
  margin-right: 0;
}

/* Tooltip for collapsed state */
.tooltip {
  position: relative;
}

.tooltip:hover::after {
  content: attr(data-tooltip);
  position: absolute;
  left: calc(100% + 10px);
  top: 50%;
  transform: translateY(-50%);
  background: #1f2937;
  color: white;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  white-space: nowrap;
  z-index: 50;
}
</style>