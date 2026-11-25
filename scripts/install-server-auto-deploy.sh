#!/usr/bin/env bash
# Jalankan sekali dari mesin lokal:
#   bash scripts/install-server-auto-deploy.sh
#
# Membuat repo bare di server sebagai push URL kedua. Setiap `git push origin master`
# akan mengirim ke GitHub DAN ke server; hook post-receive yang melakukan pull,
# view:cache/config:cache, dan menyalin build ke docroot.
set -euo pipefail

HOST=kilat
BARE=/home/kilatsch/deploy-inlinesia.git
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
HOOK="$REPO_ROOT/deploy/post-receive"

echo "Menyiapkan repo bare di $HOST:$BARE ..."
ssh "$HOST" "mkdir -p '$BARE' && git init --bare '$BARE' >/dev/null && echo OK"

echo "Menginstall hook post-receive ..."
scp "$HOOK" "$HOST:$BARE/hooks/post-receive"
ssh "$HOST" "chmod +x '$BARE/hooks/post-receive' && echo OK"

echo "Menambahkan push URL kedua ke remote origin ..."
cd "$REPO_ROOT"
git remote set-url --add --push origin "$HOST:$BARE"

echo "Konfigurasi remote sekarang:"
git remote -v

echo "Selesai. Sekali jalankan: git push origin master"
