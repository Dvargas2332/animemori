#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$root"

# Patterns we do not want in the public repo.
patterns=(
  "ca-pub-"
  "pagead2.googlesyndication.com"
  "fonts.googleapis.com"
  "netflix"
  "lightsail"
  "bitnami@"
)

# Also block obvious IPv4 literals.
ip_regex='\\b([0-9]{1,3}\\.){3}[0-9]{1,3}\\b'

fail=0

for p in "${patterns[@]}"; do
  if rg -n --hidden -S "$p" . >/dev/null 2>&1; then
    echo "Sensitive pattern found: $p"
    rg -n --hidden -S "$p" . | head -n 50 || true
    fail=1
  fi
done

if rg -n --hidden -S -P "$ip_regex" . >/dev/null 2>&1; then
  echo "Potential IP literal found"
  rg -n --hidden -S -P "$ip_regex" . | head -n 50 || true
  fail=1
fi

exit "$fail"

