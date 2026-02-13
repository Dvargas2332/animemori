#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$root"

php_files="$(find . \
  -type f -name '*.php' \
  -not -path './.tmp/*' \
  -not -path './log/*' \
  -print)"

if [[ -z "${php_files}" ]]; then
  echo "No PHP files found"
  exit 0
fi

fail=0
while IFS= read -r f; do
  # php -l returns non-zero on syntax error.
  if ! php -l "$f" >/dev/null; then
    echo "Lint failed: $f"
    fail=1
  fi
done <<<"$php_files"

exit "$fail"

