<script lang="ts" setup>
defineProps<{ messages: string[] }>();
</script>

<template>
  <div class="border-t pt-3 text-left text-sm max-h-40 overflow-y-auto">
    <p v-for="(m, i) in messages" :key="i">{{ m }}</p>
  </div>
</template>
