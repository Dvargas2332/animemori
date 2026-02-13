#!/usr/bin/env bash
set -euo pipefail

scripts_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

bash "$scripts_dir/lint-php.sh"
bash "$scripts_dir/check-no-sensitive.sh"

echo "CI checks passed"

