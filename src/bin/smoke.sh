#!/usr/bin/env bash
set -euo pipefail

# ==============================================================================
# Laravel smoke test runner (Sanctum/Traefik対応・CSRF安定化・詳細デバッグ付き)
# TSV:   category  role  method  url  expect  check_type  check_val  follow
#        ※ follow: 1(既定)=リダイレクト追随, 0=追随しない（Location検査用）
# CREDS: BASE_URL と各ロールの EMAIL/PASS、任意で HOST_HEADER/RESOLVE_*、INSECURE
# envs:  SMOKE_DEBUG=1 で詳細ログ（POSTレスポンスヘッダ/Location/Set-Cookie など）
# 変更点:
#  - URLの ${VAR} 変数展開をサポート（可搬性UP）
#  - セッションクッキーが切れていたら自動で再ログイン
#  - 行ごとに -L の有無（follow）を切り替え可能
# ==============================================================================

# ---- 基本設定 ---------------------------------------------------------------
ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
TSV="${TSV:-$ROOT_DIR/tests.smoke.tsv}"
CREDS="${CREDS:-$ROOT_DIR/creds.env}"
JQ_BIN="${JQ_BIN:-jq}"
ONLY_CATEGORY="${ONLY_CATEGORY:-}"
ONLY_ROLE="${ONLY_ROLE:-}"
SMOKE_DEBUG="${SMOKE_DEBUG:-0}"

# ---- 便利関数 ---------------------------------------------------------------
log() { printf "%s\n" "$*" >&2; }
die() { log "ERROR: $*"; exit 1; }
trim() { tr -d '[:space:]'; }
have_jq() { command -v "$JQ_BIN" >/dev/null 2>&1; }
cookie_for_role() { echo "$WORK/cookie_$1.txt"; }

# 前後の空白を除去（タブ/スペース対応）
strip_ws() {
  local s="${1-}"
  s="${s#"${s%%[![:space:]]*}"}"   # ltrim
  s="${s%"${s##*[![:space:]]}"}"   # rtrim
  printf '%s' "$s"
}

# 安全な間接参照（未定義でも空文字で返す / set -u セーフ）
get_env() {
  local __n="$1"
  eval "printf '%s' \"\${$__n-}\""
}

# 役割名を ENV 変数キー化（大文字/ハイフンや空白はアンダースコアに）
role_key() {
  local k="${1^^}"
  k="${k//-/_}"
  k="${k// /_}"
  k="${k//[^A-Z0-9_]/_}"
  printf '%s' "$k"
}

# 任意: セッションクッキー名の検出パターンを上書き可能
SESSION_COOKIE_RE="${SESSION_COOKIE_RE:-(^|[_-])session$}"

# CookieJar に「セッションクッキー」があるか判定（6列目=Cookie名でマッチ）
has_session_cookie() {
  local jar="$1"
  awk -v re="$SESSION_COOKIE_RE" '($6 ~ re){found=1} END{exit(found?0:1)}' "$jar"
}

# URLの ${VAR} を環境変数で展開
expand_vars() { local s="$1"; eval "printf '%s' \"$s\""; }

# ---- 認証情報のロード -------------------------------------------------------
[[ -f "$CREDS" ]] || die "creds.env が見つかりません: $CREDS"
# shellcheck disable=SC1090
source "$CREDS"

BASE="${BASE_URL:-http://localhost}"

# ---- cURL オプション（Traefik/Host/HTTPS/SNI/自己署名などを吸収） ----------
CURL_HOST_ARGS=()
if [[ -n "${HOST_HEADER:-}" ]]; then
  CURL_HOST_ARGS=(-H "Host: $HOST_HEADER")
fi

CURL_RESOLVE_ARGS=()
if [[ -n "${RESOLVE_HOST:-}" && -n "${RESOLVE_ADDR:-}" ]]; then
  if [[ "$BASE" == https* ]]; then port=443; else port=80; fi
  CURL_RESOLVE_ARGS=(--resolve "$RESOLVE_HOST:$port:$RESOLVE_ADDR")
fi

CURL_TLS_ARGS=()
if [[ "${INSECURE:-0}" == "1" ]]; then
  CURL_TLS_ARGS=(-k)
fi

CURL_DEBUG=()
if [[ "$SMOKE_DEBUG" == "1" ]]; then
  CURL_DEBUG=(-v)
fi

# ---- 作業領域 ---------------------------------------------------------------
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

# ---- CSRF: XSRF-TOKEN を取得してURLデコードした値をecho --------------------
fetch_csrf_token() {
  local jar="$1"

  curl -sS -c "$jar" -b "$jar" \
    -H 'X-Requested-With: XMLHttpRequest' \
    "${CURL_DEBUG[@]}" "${CURL_HOST_ARGS[@]}" "${CURL_RESOLVE_ARGS[@]}" "${CURL_TLS_ARGS[@]}" \
    "$BASE/sanctum/csrf-cookie" >/dev/null || return 1

  local raw
  raw="$(awk '$6=="XSRF-TOKEN"{print $7}' "$jar" | tail -n1)"
  [[ -n "$raw" ]] || return 1

  python3 -c 'import sys, urllib.parse; print(urllib.parse.unquote(sys.argv[1]))' "$raw" || return 1
}

# ---- HTML から CSRF 抽出（hidden or meta） ---------------------------------
extract_form_csrf() {
  local html="$1"
  local token=""

  # 1) hidden _token
  if command -v grep >/dev/null 2>&1 && grep -P '' </dev/null >/dev/null 2>&1; then
    token="$(grep -oP 'name="_token"\s+value="\K[^"]+' "$html" || true)"
  fi
  if [[ -z "$token" ]]; then
    token="$(sed -n 's/.*name="_token"[[:space:]]\+value="\([^"]\+\)".*/\1/p' "$html" | head -n1 || true)"
  fi

  # 2) <meta name="csrf-token" content="...">
  if [[ -z "$token" ]]; then
    if command -v grep >/dev/null 2>&1 && grep -P '' </dev/null >/dev/null 2>&1; then
      token="$(grep -oP '<meta\s+name="csrf-token"\s+content="\K[^"]+' "$html" || true)"
    fi
    if [[ -z "$token" ]]; then
      token="$(sed -n 's/.*<meta[[:space:]]\+name="csrf-token"[[:space:]]\+content="\([^"]\+\)".*/\1/p' "$html" | head -n1 || true)"
    fi
  fi

  [[ -n "$token" ]] && printf '%s\n' "$token"
}

# ---- ロールのクレデンシャル有無を事前チェック -------------------------------
creds_available_for_role() {
  local role="$1"
  [[ "$role" == "public" ]] && return 0
  local KEY; KEY="$(role_key "$role")"
  local email_var="${KEY}_EMAIL"
  local pass_var="${KEY}_PASS"
  local __e __p
  __e="$(get_env "$email_var")"
  __p="$(get_env "$pass_var")"
  [[ -n "$__e" && -n "$__p" ]]
}

# ---- ログイン（Sanctum優先 → 従来フォームにフォールバック） --------------
login() {
  local role="$1"
  local jar; jar="$(cookie_for_role "$role")"

  local KEY; KEY="$(role_key "$role")"
  local email_var="${KEY}_EMAIL"
  local pass_var="${KEY}_PASS"
  local email; email="$(get_env "$email_var")"
  local pass;  pass="$(get_env "$pass_var")"

  [[ -n "$email" && -n "$pass" ]] || die "未設定のクレデンシャル: $email_var / $pass_var"
  rm -f "$jar"

  # --- 1) Sanctum 正攻法 -----------------------------------------------------
  local xsrf=""
  xsrf="$(fetch_csrf_token "$jar" || true)"
  if [[ -n "$xsrf" ]]; then
    local hdr="$WORK/login_post_headers_${role}.txt"
    local code
    code="$(curl -sS -b "$jar" -c "$jar" -X POST \
      "${CURL_DEBUG[@]}" "${CURL_HOST_ARGS[@]}" "${CURL_RESOLVE_ARGS[@]}" "${CURL_TLS_ARGS[@]}" \
      -D "$hdr" \
      -H "X-XSRF-TOKEN: ${xsrf}" \
      -H 'Accept: application/json' \
      -H 'Content-Type: application/x-www-form-urlencoded' \
      -e "$BASE/login" \
      --data "email=$email&password=$pass" \
      "$BASE/login" -o /dev/null -w '%{http_code}')"

    if has_session_cookie "$jar"; then
      return 0
    fi
    [[ "$SMOKE_DEBUG" == "1" ]] && {
      log "Sanctum login HTTP=$code (session cookie not found) → フォールバックを試行"
      log "--- POST /login response headers (Sanctum) ---"
      sed -n '1,200p' "$hdr" >&2 || true
      log "----------------------------------------------"
      log "CookieJar after POST:"; sed -n '1,200p' "$jar" >&2 || true
    }
  else
    log "CSRF取得に失敗（Sanctum未対応 or Host/HTTPS不一致）→ フォールバックを試行"
  fi

  # --- 2) 従来フォーム -------------------------------------------------------
  local login_html="$WORK/login_${role}.html"
  curl -sS -c "$jar" -b "$jar" -L \
    "${CURL_DEBUG[@]}" "${CURL_HOST_ARGS[@]}" "${CURL_RESOLVE_ARGS[@]}" "${CURL_TLS_ARGS[@]}" \
    "$BASE/login" -o "$login_html"

  local csrf=""
  csrf="$(extract_form_csrf "$login_html" || true)"

  local code2
  local xsrf2="$xsrf"
  if [[ -z "$csrf" ]]; then
    xsrf2="$(fetch_csrf_token "$jar" || true)"
  fi
  if [[ -z "$csrf" && -z "$xsrf2" ]]; then
    [[ "$SMOKE_DEBUG" == "1" ]] && { log "--- /login HTML head ---"; head -c 600 "$login_html" | sed 's/[^[:print:]\t]/./g' >&2; echo >&2; }
    die "CSRFトークン抽出失敗（role=$role フォールバック）"
  fi

  if [[ -n "$csrf" ]]; then
    code2="$(curl -sS -b "$jar" -c "$jar" -L \
      "${CURL_DEBUG[@]}" "${CURL_HOST_ARGS[@]}" "${CURL_RESOLVE_ARGS[@]}" "${CURL_TLS_ARGS[@]}" \
      -H 'Content-Type: application/x-www-form-urlencoded' \
      --data "email=$email&password=$pass&_token=$csrf" \
      "$BASE/login" -o /dev/null -w '%{http_code}')"
  else
    code2="$(curl -sS -b "$jar" -c "$jar" -L \
      "${CURL_DEBUG[@]}" "${CURL_HOST_ARGS[@]}" "${CURL_RESOLVE_ARGS[@]}" "${CURL_TLS_ARGS[@]}" \
      -H "X-CSRF-TOKEN: ${xsrf2}" \
      -H 'Content-Type: application/x-www-form-urlencoded' \
      --data "email=$email&password=$pass" \
      "$BASE/login" -o /dev/null -w '%{http_code}')"
  fi

  if has_session_cookie "$jar"; then
    return 0
  fi

  if [[ "$SMOKE_DEBUG" == "1" ]]; then
    log "--- Fallback login failed: http=$code2 ---"
    log "CookieJar:"; sed -n '1,200p' "$jar" >&2 || true
    log "Login HTML first 600B:"; head -c 600 "$login_html" | sed 's/[^[:print:]\t]/./g' >&2 || true
  fi

  die "ログイン失敗（$role, http=$code2）"
}

# ---- 1ケース実行 -----------------------------------------------------------
run_case() {
  local category="$1" role="$2" method="$3" url="$4" expect="$5" ctype="$6" cval="$7" follow="${8:-1}"

  # 期待コード（空白除去）
  local expect_trim; expect_trim="$(printf '%s' "$expect" | trim)"

  # URLの ${VAR} 展開
  url="$(expand_vars "$url")"

  local jar=""
  if [[ "$role" != "public" ]]; then
    if ! creds_available_for_role "$role"; then
      echo "SKIP [$category][$role][$method $url] (creds missing)"
      return 0
    fi
    jar="$(cookie_for_role "$role")"
    if [[ ! -f "$jar" ]]; then
      login "$role"
    else
      # ★ セッション切れを自動回復
      if ! has_session_cookie "$jar"; then
        login "$role"
      fi
    fi
  fi

  local body="$WORK/body.$$.$RANDOM"
  local hdr="$WORK/hdr.$$.$RANDOM"
  local code
  local curl_args=()
  local curl_follow=()

  case "${method^^}" in
    GET|"")   curl_args+=() ;;
    HEAD)     curl_args+=(-I) ;;
    POST)     curl_args+=(-X POST) ;;
    *)        curl_args+=() ;;
  esac

  if [[ "${follow:-1}" == "1" || -z "${follow:-}" ]]; then
    curl_follow=(-L)
  else
    curl_follow=()
  fi

  if [[ "$role" == "public" ]]; then
    code="$(curl -sS "${curl_follow[@]}" \
      "${CURL_DEBUG[@]}" "${CURL_HOST_ARGS[@]}" "${CURL_RESOLVE_ARGS[@]}" "${CURL_TLS_ARGS[@]}" \
      -D "$hdr" "${curl_args[@]}" "$BASE$url" -o "$body" -w '%{http_code}')"
  else
    code="$(curl -sS -b "$jar" "${curl_follow[@]}" \
      "${CURL_DEBUG[@]}" "${CURL_HOST_ARGS[@]}" "${CURL_RESOLVE_ARGS[@]}" "${CURL_TLS_ARGS[@]}" \
      -D "$hdr" "${curl_args[@]}" "$BASE$url" -o "$body" -w '%{http_code}')"
  fi

  local code_trim; code_trim="$(printf '%s' "$code" | trim)"

  local ok=1
  [[ "$code_trim" == "$expect_trim" ]] || ok=0

  # 追加チェック
  if [[ "$ok" -eq 1 && -n "${ctype:-}" && "$ctype" != "none" ]]; then
    case "$ctype" in
      jq)
        if have_jq; then
          "$JQ_BIN" -e "$cval" "$body" >/dev/null 2>&1 || ok=0
        else
          log "SKIP jq-check (jq未導入) : $url"
        fi
        ;;
      grep)
        grep -q -- "$cval" "$body" || ok=0
        ;;
      grep_headers)
        grep -Eqi -- "$cval" "$hdr" || ok=0
        ;;
      *)
        log "未知のcheck_type: $ctype"
        ;;
    esac
  fi

  if [[ "$ok" -eq 1 ]]; then
    echo "PASS [$category][$role][$method $url] => $code_trim"
  else
    echo "FAIL [$category][$role][$method $url] => got:$code_trim expect:$expect_trim"
    echo "----- headers (first 30 lines) -----"
    sed -n '1,30p' "$hdr" 2>/dev/null | sed 's/[^[:print:]\t]/./g' || true
    echo "----- body head (200B) -----"
    head -c 200 "$body" | sed 's/[^[:print:]\t]/./g'
    echo -e "\n----------------------------"
    return 1
  fi
}

# ---- 実行 -------------------------------------------------------------------
[[ -f "$TSV" ]] || die "tests.smoke.tsv が見つかりません: $TSV"

rc=0
# 8列目 follow を読み取る
while IFS=$'\t' read -r category role method url expect ctype cval follow || [[ -n "${category:-}" ]]; do
  # コメント/空行スキップ
  [[ -z "${category// }" ]] && continue
  [[ "$category" = \#* ]] && continue

  # トリム
  category="$(strip_ws "${category:-}")"
  role="$(strip_ws "${role:-}")"
  method="$(strip_ws "${method:-}")"
  url="$(strip_ws "${url:-}")"
  expect="$(strip_ws "${expect:-}")"
  ctype="$(strip_ws "${ctype:-}")"
  cval="$(strip_ws "${cval:-}")"
  follow="$(strip_ws "${follow:-1}")"

  # カラム不足の壊れた行はスキップ
  if [[ -z "${role:-}" || -z "${method:-}" || -z "${url:-}" || -z "${expect:-}" ]]; then
    log "SKIP invalid row: category='$category' role='${role:-}' method='${method:-}' url='${url:-}' expect='${expect:-}'"
    continue
  fi

  # 絞り込み
  [[ -n "$ONLY_CATEGORY" && "$category" != "$ONLY_CATEGORY" ]] && continue
  [[ -n "$ONLY_ROLE" && "$role" != "$ONLY_ROLE" ]] && continue

  # 空の check_type は none 扱い
  [[ -z "${ctype:-}" ]] && ctype="none"

  if ! run_case "$category" "$role" "$method" "$url" "$expect" "$ctype" "$cval" "$follow"; then
    rc=1
  fi
done < "$TSV"

exit "$rc"
