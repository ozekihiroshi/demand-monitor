<!-- resources/js/Pages/Meters/Index.vue -->
<script setup>
import { router, Link } from '@inertiajs/vue3'
const props = defineProps({ meters: Object, groups: Array, filters: Object, can: Object })

function search(e) {
  router.get(route('admin.meters.index'), {
    search: e.target.value,
    group_id: props.filters.group_id
  }, { preserveState: true, replace: true })
}
function pickGroup(e) {
  router.get(route('admin.meters.index'), {
    search: props.filters.search,
    group_id: e.target.value || null
  }, { preserveState: true, replace: true })
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center gap-2">
      <input class="input" placeholder="検索: code / name" :value="filters.search" @input="search" />
      <select class="select" :value="filters.group_id" @change="pickGroup">
        <option value="">すべてのグループ</option>
        <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
      </select>
      <Link v-if="can.create" :href="route('admin.meters.create')" class="btn-primary">新規作成</Link>
    </div>

    <table class="w-full table-auto">
      <thead>
        <tr>
          <th>code</th>
          <th>name</th>
          <th>group</th>
          <!-- ★ 追加: グラフ列 -->
          <th class="text-right">グラフ</th>
          <!-- 既存の編集ボタン用の空ヘッダ -->
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="m in meters.data" :key="m.code">
          <td>{{ m.code }}</td>
          <td>{{ m.name }}</td>
          <td>{{ m.group?.name }}</td>

          <!-- ★ 追加: 行ごとのグラフリンク -->
          <td class="whitespace-nowrap text-right">
            <Link
              :href="route('admin.meters.charts.series', m.code)"
              class="text-xs underline mr-3"
            >時系列</Link>
            <Link
              :href="route('admin.meters.charts.demand', m.code)"
              class="text-xs underline"
            >需要</Link>
          </td>

          <!-- 既存: 編集リンク -->
          <td class="text-right">
            <Link :href="route('admin.meters.edit', m.code)" class="link">編集</Link>
          </td>
        </tr>
      </tbody>
    </table>

    <div class="mt-4 flex gap-2">
      <Link
        v-for="link in meters.links"
        :key="link.url || link.label"
        :href="link.url || '#'"
        v-html="link.label"
        :class="['px-2', link.active ? 'font-bold' : '']"
      />
    </div>
  </div>
</template>

<style scoped>
.input, .select { border: 1px solid #ddd; padding: .5rem .75rem; border-radius: .5rem; }
.btn-primary { background: #1f6feb; color:#fff; padding:.5rem .75rem; border-radius:.5rem; }
.link { color:#1f6feb; }
</style>
