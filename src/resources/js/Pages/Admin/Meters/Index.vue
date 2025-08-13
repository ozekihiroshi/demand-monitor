<script setup>
import { Link, router } from '@inertiajs/vue3'
const props = defineProps({ meters: Object, filters: Object, groups: Array })

function submit(e){
  e.preventDefault()
  const form = new FormData(e.target)
  router.get(route('admin.meters.index'), Object.fromEntries(form.entries()), { preserveState: true })
}
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-semibold">メーター一覧</h1>

    <form @submit="submit" class="flex flex-col md:flex-row gap-2 items-end">
      <div>
        <label class="block text-sm text-gray-600">検索</label>
        <input name="q" :value="filters.q" class="border rounded px-3 py-2 w-64" placeholder="コード/名称" />
      </div>
      <div>
        <label class="block text-sm text-gray-600">グループ</label>
        <select name="group_id" :value="filters.group_id" class="border rounded px-3 py-2 w-48">
          <option value="">すべて</option>
          <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
        </select>
      </div>
      <button class="px-4 py-2 rounded bg-black text-white">検索</button>
    </form>

    <div class="overflow-x-auto">
      <table class="min-w-full bg-white shadow rounded-2xl">
        <thead>
          <tr class="text-left text-sm text-gray-500">
            <th class="p-3">コード</th>
            <th class="p-3">名称</th>
            <th class="p-3">グループ</th>
            <th class="p-3">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="m in meters.data" :key="m.id" class="border-t">
            <td class="p-3 font-mono">{{ m.code }}</td>
            <td class="p-3">{{ m.name }}</td>
            <td class="p-3">{{ m.group?.name ?? '-' }}</td>
            <td class="p-3 flex gap-2">
              <Link :href="route('admin.meters.show', m.code)" class="underline">詳細</Link>
              <!-- 既存の管理グラフ（Blade）へ遷移させる外部リンク例 -->
              <a :href="`/admin/meters/${m.code}/series`" class="underline">30分/1分</a>
              <a :href="`/admin/meters/${m.code}/demand`" class="underline">当日予測</a>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="flex gap-2" v-if="meters.links">
      <Link v-for="l in meters.links" :key="l.url || l.label" :href="l.url || '#'" v-html="l.label"
            :class="['px-3 py-1 rounded', l.active ? 'bg-black text-white' : 'bg-gray-100']" />
    </div>
  </div>
</template>

