#!/usr/bin/env bash
set -euo pipefail
package=${1:?Package required}
version=${2:?Version required}
artifacts=${3:?Artifact directory required}
[[ "$package" == kern-core || "$package" == kern-cms ]]
[[ "$version" =~ ^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]]
repository="binhtq1/$package"
remote="https://github.com/$repository.git"
local_ref="refs/tags/$package-v$version"
git fetch "$artifacts/$package-$version.bundle" "refs/tags/v$version:$local_ref"
expected=$(git rev-parse "$local_ref")
gh auth setup-git
existing=$(git -c http.https://github.com/.extraheader= ls-remote "$remote" "refs/tags/v$version" | cut -f1)
if [[ -n "$existing" && "$existing" != "$expected" ]]; then
  echo "The existing $repository v$version tag points to another commit. Use a new version." >&2
  exit 1
fi
if [[ -z "$existing" ]]; then
  refs=("$local_ref:refs/tags/v$version")
  if [[ "$package" == kern-core ]]; then refs+=("$local_ref:refs/heads/main"); fi
  git -c http.https://github.com/.extraheader= push --atomic "$remote" "${refs[@]}"
fi
if ! gh release view "v$version" --repo "$repository" >/dev/null 2>&1; then
  gh release create "v$version" --repo "$repository" --verify-tag --title "$package $version" --notes "Kern CMS $version. Runtime upgrades: composer update binhtq1/kern-core --with-all-dependencies, then php artisan cms:upgrade." "$artifacts/$package-$version.zip" "$artifacts/SHA256SUMS"
fi
