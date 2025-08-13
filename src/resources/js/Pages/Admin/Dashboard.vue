
<script setup>
const props = defineProps({
  cards: Object,
});
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-semibold">Dashboard</h1>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="p-4 rounded-2xl shadow bg-white">
        <div class="text-sm text-gray-500">閾値超過予測中</div>
        <div class="text-3xl font-bold">{{ cards.overThreshold }}</div>
      </div>
      <div class="p-4 rounded-2xl shadow bg-white">
        <div class="text-sm text-gray-500">未設定/不完全メーター</div>
        <div class="text-3xl font-bold">{{ cards.unconfigured }}</div>
      </div>
      <div class="p-4 rounded-2xl shadow bg-white">
        <div class="text-sm text-gray-500">直近ピーク Top5</div>
        <ul class="mt-2 text-sm list-disc list-inside" v-if="cards.topPeaks?.length">
          <li v-for="(p,i) in cards.topPeaks" :key="i">{{ p.code }} — {{ p.kw }} kW</li>
        </ul>
        <div v-else class="mt-2 text-gray-400">データなし</div>
      </div>
    </div>
  </div>
</template>
