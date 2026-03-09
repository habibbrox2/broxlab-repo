#!/usr/bin/env bash
# Clone the repo, install npm deps, and restore the database from Database/full/latest.sql.
# Usage: ./scripts/clone-and-restore.sh <repo-url> [destination] [branch]

set -euo pipefail

REPO_URL=${1:-}
DEST=${2:-./broxbhai}
BRANCH=${3:-main}

if [[ -z "$REPO_URL" ]]; then
  echo "Usage: $0 <repo-url> [destination] [branch]"
  exit 1
fi

echo "> Cloning $REPO_URL into $DEST"
git clone --branch "$BRANCH" --depth 1 "$REPO_URL" "$DEST"

echo "> Installing npm dependencies"
cd "$DEST"
npm install

echo "> Restoring database from Database/full/latest.sql"
npm run restore-db -- --yes

echo "✅ Clone + restore complete."
