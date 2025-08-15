
<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  userInfo: Object,
  metrics: Object,
  can: Object,
})
</script>

<template>
  <div class="p-6 space-y-6">
    <!-- ヘッダー -->
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold">Admin Dashboard</h1>
      <div class="flex items-center gap-3">
        <Link href="/profile" class="text-sm underline">プロフィール</Link>
        <Link
          href="/logout"
          method="post"
          as="button"
          class="text-sm px-3 py-1.5 rounded-md border"
        >
          ログアウト
        </Link>
      </div>
    </div>

    <!-- 自分情報 -->
    <div class="rounded-xl border p-4 bg-white">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
          <div class="text-sm text-gray-500">Signed in as</div>
          <div class="font-medium">{{ userInfo.name }} <span class="text-gray-500">({{ userInfo.email }})</span></div>
        </div>
        <div class="flex flex-wrap gap-2">
          <span
            v-for="r in userInfo.roles"
            :key="r"
            class="px-2 py-0.5 text-xs rounded-full border bg-gray-50"
          >
            {{ r }}
          </span>
          <span v-if="!userInfo.roles || userInfo.roles.length === 0" class="text-sm text-gray-500">
            (no roles)
          </span>
        </div>
      </div>

      <div class="mt-3 text-sm text-gray-600 flex flex-wrap gap-2">
        <span class="text-gray-500">Groups:</span>
        <template v-if="userInfo.groups && userInfo.groups.length">
          <span
            v-for="g in userInfo.groups"
            :key="g.id"
            class="px-2 py-0.5 rounded border"
          >{{ g.name }}</span>
        </template>
        <span v-else>なし</span>
      </div>
    </div>

    <!-- メトリクス -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="rounded-xl border p-4 bg-white">
        <div class="text-sm text-gray-500">Meters (total)</div>
        <div class="text-2xl font-semibold">{{ metrics.meters_total }}</div>
      </div>
      <div class="rounded-xl border p-4 bg-white">
        <div class="text-sm text-gray-500">Active</div>
        <div class="text-2xl font-semibold">{{ metrics.meters_active }}</div>
      </div>
      <div class="rounded-xl border p-4 bg-white">
        <div class="text-sm text-gray-500">Deleted</div>
        <div class="text-2xl font-semibold">{{ metrics.meters_deleted }}</div>
      </div>
    </div>

    <!-- クイック操作 -->
    <div class="rounded-xl border p-4 bg-white">
      <div class="flex flex-wrap items-center gap-3">
        <Link href="/admin/meters" class="px-3 py-2 rounded-md border">メーター一覧</Link>
        <Link
          v-if="can.createMeter"
          href="/admin/meters/create"
          class="px-3 py-2 rounded-md border"
        >
          メーター追加
        </Link>
        <div class="text-sm text-gray-500">（閲覧権限に応じて表示）</div>
      </div>
    </div>
  </div>
</template>
