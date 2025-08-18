
// resources/js/public-charts.js
async function main() {
  const el = document.getElementById('chart-root')
  if (!el) return
  const code = el.dataset.code
  const bucket = el.dataset.bucket || '30m'
  try {
    const res = await fetch(`/api/v1/meters/${encodeURIComponent(code)}/series?bucket=${encodeURIComponent(bucket)}`, {
      headers: { 'Accept': 'application/json' }
    })
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const json = await res.json()
    const pre = document.createElement('pre')
    pre.textContent = JSON.stringify(json, null, 2)
    el.appendChild(pre)
  } catch (e) {
    el.innerHTML = `<pre style="color:#b00">API error: ${String(e)}</pre>`
  }
}
document.addEventListener('DOMContentLoaded', main)
