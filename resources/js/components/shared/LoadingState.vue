<script setup lang="ts">
defineProps<{
  label?: string;
}>();
</script>

<template>
  <div class="flex flex-col items-center justify-center py-24 gap-6">
    <div class="loader"></div>
    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">
      {{ label ?? 'Memuat data...' }}
    </p>
  </div>
</template>

<style scoped>
.loader {
  width: 120px;
  height: 20px;
  -webkit-mask: radial-gradient(circle closest-side, #000 94%, #0000) left / 20% 100%;
  background: linear-gradient(#064e3b 0 0) left / 0% 100% no-repeat #c9a84c22;
  animation: l17 2s infinite steps(6);
  border-radius: 999px;
}

@keyframes l17 {
  100% { background-size: 120% 100%; }
}
</style>
