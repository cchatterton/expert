#!/usr/bin/env bash
set -euo pipefail
EXPERT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$EXPERT_ROOT"
mkdir -p dist
rm -rf dist/expert
rm -f dist/expert.zip expert.zip
cp -R expert dist/expert
find dist/expert -name '.DS_Store' -delete
(cd dist && zip -qr expert.zip expert)
cp dist/expert.zip expert.zip
python3 scripts/verify-package.py expert.zip
