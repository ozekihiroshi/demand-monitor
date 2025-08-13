<script setup>
const props = defineProps({ meter: Object })
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-semibold">{{ meter.code }} / {{ meter.name }}</h1>

    <div class="grid md:grid-cols-2 gap-4">
      <div class="p-4 bg-white rounded-2xl shadow">
        <div class="font-semibold mb-2">30分・1分オーバーレイ</div>
        <iframe :src="`/admin/meters/${meter.code}/series`" class="w-full h-[420px] border rounded"></iframe>
      </div>
      <div class="p-4 bg-white rounded-2xl shadow">
        <div class="font-semibold mb-2">当日予測</div>
        <iframe :src="`/admin/meters/${meter.code}/demand`" class="w-full h-[420px] border rounded"></iframe>
      </div>
    </div>
  </div>
</template>

