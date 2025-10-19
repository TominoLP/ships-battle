<template>
  <header class="sticky top-0 z-10 backdrop-blur bg-white/70 border-b">
    <div class="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between">
      <h1 class="text-xl font-bold flex items-center gap-2">
        <span>Schiffeversenken</span>
      </h1>
      <div class="text-sm text-gray-500">
        <slot name="right" />
      </div>
    </div>
  </header>
</template>
