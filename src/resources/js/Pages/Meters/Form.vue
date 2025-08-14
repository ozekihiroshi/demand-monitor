<!-- resources/js/Pages/Meters/Form.vue -->
<script setup>
import { useForm, Link } from '@inertiajs/vue3';
const props = defineProps({ meter: Object, groups: Array });

const form = useForm({
  code: props.meter?.code ?? '',
  name: props.meter?.name ?? '',
  group_id: props.meter?.group_id ?? (props.groups[0]?.id || null),
  threshold_override: props.meter?.threshold_override ?? null,
  rate_override: props.meter?.rate_override ? JSON.stringify(props.meter.rate_override, null, 2) : ''
});

function submit() {
  if (props.meter) {
    form.put(route('admin.meters.update', props.meter.code));
  } else {
    form.post(route('admin.meters.store'));
  }
}
</script>

<template>
  <div class="max-w-2xl space-y-4">
    <div>
      <label>code（作成後は変更不可）</label>
      <input class="input w-full" v-model="form.code" :disabled="!!meter" />
      <div v-if="form.errors.code" class="err">{{ form.errors.code }}</div>
    </div>

    <div>
      <label>name</label>
      <input class="input w-full" v-model="form.name" />
      <div v-if="form.errors.name" class="err">{{ form.errors.name }}</div>
    </div>

    <div>
      <label>group</label>
      <select class="select w-full" v-model="form.group_id">
        <option v-for="g in groups" :value="g.id" :key="g.id">{{ g.name }}</option>
      </select>
      <div v-if="form.errors.group_id" class="err">{{ form.errors.group_id }}</div>
    </div>

    <div>
      <label>threshold_override（kW, 任意）</label>
      <input class="input w-full" type="number" min="1" step="1" v-model.number="form.threshold_override" />
      <div v-if="form.errors.threshold_override" class="err">{{ form.errors.threshold_override }}</div>
    </div>

    <details>
      <summary class="cursor-pointer">rate_override（JSON, 任意・将来の料金ロジック用）</summary>
      <textarea class="input w-full" rows="8" v-model="form.rate_override"
        placeholder='{"plan":"custom","basic_fee":1233.75,"summer_rate":15.95,"other_rate":15.85}' />
      <div v-if="form.errors.rate_override" class="err">{{ form.errors.rate_override }}</div>
    </details>

    <div class="flex items-center gap-2">
      <button class="btn-primary" @click.prevent="submit">保存</button>
      <Link :href="route('admin.meters.index')" class="link">戻る</Link>
    </div>
  </div>
</template>

<style scoped>
.input, .select, textarea { border: 1px solid #ddd; padding: .5rem .75rem; border-radius: .5rem; }
.btn-primary { background: #1f6feb; color:#fff; padding:.5rem .75rem; border-radius:.5rem; }
.err { color:#d00; font-size:.9rem; }
.link { color:#1f6feb; }
</style>

