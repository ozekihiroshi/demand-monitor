<template>
  <div class="min-h-screen bg-gray-50">
    <div class="flex">
      <aside class="w-60 p-4 bg-white shadow-sm space-y-2">
        <Link :href="route('admin.dashboard')">Dashboard</Link>
        <Link :href="route('admin.meters.index')">メーター</Link>
      </aside>
      <main class="flex-1 p-6">
        <slot />
      </main>
    </div>
  </div>
</template>