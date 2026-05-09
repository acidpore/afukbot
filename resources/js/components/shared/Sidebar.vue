<script setup lang="ts">
defineProps<{
  activeTab: string;
  menuItems: Array<{ id: string; name: string; icon: string }>;
}>();

defineEmits(['update:activeTab']);
</script>

<template>
  <aside class="w-full lg:w-72 flex flex-col gap-6">
    <!-- Main Menu Card -->
    <div class="premium-card p-4 shadow-2xl shadow-primary/5">
      <div class="flex flex-col gap-2">
        <button 
          v-for="item in menuItems" 
          :key="item.id"
          @click="$emit('update:activeTab', item.id)"
          class="w-full flex items-center gap-4 px-5 py-4 rounded-2xl text-sm font-bold transition-all duration-300 group"
          :class="activeTab === item.id 
            ? 'bg-primary text-white shadow-xl shadow-primary/30 scale-[1.02]' 
            : 'text-slate-500 hover:bg-slate-50 hover:text-primary'"
        >
          <i :class="item.icon" class="text-xl group-hover:scale-110 transition-transform"></i>
          <span class="tracking-wide">{{ item.name }}</span>
        </button>
      </div>
    </div>

    <!-- Status Card -->
    <div class="premium-card bg-accent/5 border-accent/10 p-6 flex flex-col gap-4">
      <div>
        <p class="text-[10px] font-bold text-accent uppercase tracking-[0.2em] mb-1">System Status</p>
        <div class="flex items-center gap-2">
          <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse shadow-[0_0_8px_rgba(16,185,129,0.5)]"></div>
          <span class="text-sm font-bold text-emerald-800">All Systems Nominal</span>
        </div>
      </div>
      <div class="h-[1px] w-full bg-accent/10"></div>
      <p class="text-[10px] text-slate-400 font-medium leading-relaxed">
        Server: 127.0.0.1<br>
        Last Sync: Baru saja
      </p>
    </div>
  </aside>
</template>
