#!/usr/bin/env bash
set -euo pipefail
version=${1:?Version required}
release_root=${2:?New release directory required}
artifacts=${3:?Artifact directory required}
if [[ ! "$version" =~ ^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]] || [[ "${version%%.*}" == 0 && "$(cut -d. -f2 <<< "$version")" -lt 2 ]]; then
  echo 'This workflow publishes split runtime releases starting at 0.2.0.' >&2
  exit 1
fi
source_revision=${SOURCE_REVISION:-$(git rev-parse HEAD)}
source_root=$(pwd)
node scripts/build-core-manifest.mjs
node --input-type=module - <<'JS'
import { readFileSync } from 'node:fs'
const manifest = JSON.parse(readFileSync('platform/composer.json'))
const lock = JSON.parse(readFileSync('composer.lock')).packages.find(pkg => pkg.name === manifest.name)
function normalize(value) {
  if (Array.isArray(value)) return value.map(normalize)
  if (value && typeof value === 'object') return Object.fromEntries(Object.entries(value).sort(([a], [b]) => a.localeCompare(b)).map(([key, entry]) => [key, normalize(entry)]))
  return value
}
for (const key of ['require', 'replace', 'autoload', 'extra', 'bin']) {
  if (JSON.stringify(normalize(lock?.[key])) !== JSON.stringify(normalize(manifest[key]))) throw new Error('Runtime metadata changed. Run composer update binhtq1/kern-core before releasing.')
}
JS
node scripts/prepare-core.mjs "$version" "$release_root-core"
mkdir -p "$artifacts"
(
  cd "$release_root-core"
  git init --quiet --initial-branch=distribution
  if GIT_TERMINAL_PROMPT=0 git ls-remote --exit-code https://github.com/binhtq1/kern-core.git refs/heads/main >/dev/null 2>&1; then
    git fetch --quiet https://github.com/binhtq1/kern-core.git refs/heads/main
    git update-ref refs/heads/distribution FETCH_HEAD
    if git cat-file -e FETCH_HEAD:core/base/resources/upgrade/stock-assets.json 2>/dev/null; then
      git show FETCH_HEAD:core/base/resources/upgrade/stock-assets.json > "$artifacts/previous-stock-assets.json"
      node "$source_root/scripts/runtime-asset-manifest.mjs" core/base/resources/upgrade/stock-assets.json "$artifacts/previous-stock-assets.json"
    fi
  fi
  git config user.name 'github-actions[bot]'
  git config user.email '41898282+github-actions[bot]@users.noreply.github.com'
  git add --all --force
  git commit --quiet -m "Release Kern Core $version" -m "Source: $source_revision"
  git tag "v$version"
  git bundle create "$artifacts/kern-core-$version.bundle" "refs/tags/v$version"
  git rev-parse HEAD > "$artifacts/core-reference"
  composer archive --format=zip --dir="$artifacts" --file="kern-core-$version"
)
export CORE_REFERENCE
CORE_REFERENCE=$(cat "$artifacts/core-reference")
node scripts/prepare-release.mjs "$version" "$release_root"
composer --working-dir="$release_root" validate --strict
composer --working-dir="$release_root" archive --format=zip --dir="$artifacts" --file="kern-cms-$version"
(
  cd "$release_root"
  git init --quiet --initial-branch=distribution
  git config user.name 'github-actions[bot]'
  git config user.email '41898282+github-actions[bot]@users.noreply.github.com'
  git add --all --force
  git commit --quiet -m "Release Kern CMS $version" -m "Source: $source_revision"
  git tag "v$version"
  git bundle create "$artifacts/kern-cms-$version.bundle" "refs/tags/v$version"
)
node scripts/build-upgrader.mjs "$version" "$artifacts"
(cd "$artifacts" && sha256sum "kern-core-$version.zip" "kern-cms-$version.zip" kern-upgrade.php > SHA256SUMS)
