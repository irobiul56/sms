<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import UserNavbar from '@/Components/UserNavbar.vue';
import UserSidebar from '@/Components/UserSidebar.vue';

const isCollapsed = ref(false);
const isMobileSidebarOpen = ref(false);

// Check if mobile (screen width < 1024px)
const isMobile = computed(() => window.innerWidth < 1024);

// Toggle sidebar - works differently for mobile and desktop
const toggleSidebar = () => {
  if (isMobile.value) {
    // Mobile: toggle drawer
    isMobileSidebarOpen.value = !isMobileSidebarOpen.value;
  } else {
    // Desktop: toggle collapse
    isCollapsed.value = !isCollapsed.value;
  }
};

// Close mobile sidebar
const closeMobileSidebar = () => {
  isMobileSidebarOpen.value = false;
};

// Handle window resize
const handleResize = () => {
  if (window.innerWidth >= 1024) {
    // Close mobile sidebar when switching to desktop
    isMobileSidebarOpen.value = false;
  }
};

// Keyboard shortcut: Escape to close mobile sidebar
const handleEscape = (event) => {
  if (event.key === 'Escape' && isMobileSidebarOpen.value) {
    closeMobileSidebar();
  }
};

// Lifecycle hooks
onMounted(() => {
  window.addEventListener('resize', handleResize);
  window.addEventListener('keydown', handleEscape);
});

onUnmounted(() => {
  window.removeEventListener('resize', handleResize);
  window.removeEventListener('keydown', handleEscape);
});
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <UserNavbar @toggle-sidebar="toggleSidebar" />
    
    <div class="flex relative">
      <!-- Mobile Overlay -->
      <div 
        v-if="isMobileSidebarOpen"
        class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden transition-opacity duration-300"
        @click="closeMobileSidebar"
      ></div>

      <!-- Sidebar -->
      <UserSidebar 
        :is-collapsed="isCollapsed"
        :is-open="isMobileSidebarOpen"
        @close="closeMobileSidebar"
      />
      
      <!-- Main Content - with padding for fixed sidebar -->
      <main 
        class="flex-1 min-h-screen overflow-x-hidden transition-all duration-300"
        :class="{
          'lg:ml-20': isCollapsed,
          'lg:ml-64': !isCollapsed
        }"
      >
        <div class="p-4">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>

<style scoped>
/* Smooth transitions */
main {
  transition: margin-left 0.3s ease-in-out;
}

/* Mobile */
@media (max-width: 1023px) {
  main {
    margin-left: 0 !important;
  }
}
</style>