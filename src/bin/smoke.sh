#!/usr/bin/env bash
set -euo pipefail

# ==== 設定 ====
ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
TSV="${TSV:-$ROOT_DIR/tests.smoke.tsv}"
CREDS="${CREDS:-$ROOT_DIR/creds.env}"
JQ_BIN="${JQ_BIN:-jq}"   # jqが無い場合は jq系チェックをSKIP

# ==== 便利関数 ====
log() { printf "%s\n" "$*" >&2; }
die() { log "ERROR: $*"; exit 1; }

[[ -f "$CREDS" ]] || die "creds.env が見つかりません: $CREDS"
source "$CREDS"

BASE="${BASE_URL:-http://localhost}"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

cookie_for_role() { echo "$WORK/cookie_$1.txt"; }

have_jq() { command -v "$JQ_BIN" >/dev/null 2>&1; }

# ログイン（ロール名小文字: engineer / operator / super など）
login() {
  local role="$1"
  local jar; jar="$(cookie_for_role "$role")"
  local RUPPER="${role^^}" # engineer -> ENGINEER
  local email_var="${RUPPER}_EMAIL"
  local pass_var="${RUPPER}_PASS"
  local email="${!email_var:-}"
  local pass="${!pass_var:-}"

  [[ -n "$email" && -n "$pass" ]] || die "未設定のクレデンシャル: $email_var / $pass_var"

  # ログインページからCSRFを取得
  curl -s -c "$jar" "$BASE/login" -o "$WORK/login_$role.html"
  local csrf
  csrf="$(grep -oP 'name="_token"\s+value="\K[^"]+' "$WORK/login_$role.html" || true)"
  [[ -n "$csrf" ]] || die "CSRFトークン抽出失敗（role=$role）"

  # 認証
  local code
  code="$(curl -s -b "$jar" -c "$jar" -L \
    -H 'Content-Type: application/x-www-form-urlencoded' \
    --data "email=$email&password=$pass&_token=$csrf" \
    "$BASE/login" -o /dev/null -w '%{http_code}')"

  [[ "$code" =~ ^2|3 ]] || die "ログイン失敗（$role, http=$code）"
}

# 1ケース実行
run_case() {
  local category="$1" role="$2" method="$3" url="$4" expect="$5" ctype="$6" cval="$7"
  local jar=""
  if [[ "$role" != "public" ]]; then
    jar="$(cookie_for_role "$role")"
    # まだCookieがなければログイン
    [[ -f "$jar" ]] || login "$role"
  fi

  # リクエスト発射
  local body="$WORK/body.$$.$RANDOM"
  local code
  if [[ "$role" == "public" ]]; then
    code="$(curl -s -L "$BASE$url" -o "$body" -w '%{http_code}')"
  else
    code="$(curl -s -b "$jar" -L "$BASE$url" -o "$body" -w '%{http_code}')"
  fi

  # ステータス判定
  local ok=1
  if [[ "$code" != "$expect" ]]; then ok=0; fi

  # ボディチェック
  if [[ "$ok" -eq 1 && "$ctype" != "none" ]]; then
    case "$ctype" in
      jq)
        if have_jq; then
          if ! "$JQ_BIN" -e "$cval" "$body" >/dev/null 2>&1; then ok=0; fi
        else
          log "SKIP jq-check (jq未導入) : $url"
        fi
        ;;
      grep)
        if ! grep -q -- "$cval" "$body"; then ok=0; fi
        ;;
      *)
        log "未知のcheck_type: $ctype"
        ;;
    esac
  fi

  if [[ "$ok" -eq 1 ]]; then
    echo "PASS [$category][$role][$method $url] => $code"
  else
    echo "FAIL [$category][$role][$method $url] => got:$code expect:$expect"
    echo "----- body head (200B) -----"
    head -c 200 "$body" | sed 's/[^[:print:]\t]/./g'
    echo -e "\n----------------------------"
    return 1
  fi
}

# カテゴリ/ロール絞り込み
ONLY_CATEGORY="${ONLY_CATEGORY:-}"
ONLY_ROLE="${ONLY_ROLE:-}"

# ==== 実行 ====
[[ -f "$TSV" ]] || die "tests.smoke.tsv が見つかりません: $TSV"

rc=0
while IFS=$'\t' read -r category role method url expect ctype cval; do
  # コメント/空行スキップ
  [[ -z "${category// }" ]] && continue
  [[ "$category" = \#* ]] && continue

  # 絞り込み
  [[ -n "$ONLY_CATEGORY" && "$category" != "$ONLY_CATEGORY" ]] && continue
  [[ -n "$ONLY_ROLE" && "$role" != "$ONLY_ROLE" ]] && continue

  if ! run_case "$category" "$role" "$method" "$url" "$expect" "$ctype" "$cval"; then
    rc=1
  fi
done < "$TSV"

exit "$rc"

