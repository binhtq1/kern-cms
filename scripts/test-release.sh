#!/usr/bin/env bash
set -euo pipefail
version=${1:?Version required}
artifacts=${2:?Absolute artifact directory required}
source_root=$(pwd)
work=$(mktemp -d "${TMPDIR:-/tmp}/kern-release-tests.XXXXXXXX")
tool_artifacts="$work/upgrader-artifacts"
mkdir -p "$tool_artifacts"
ln -s "$artifacts/kern-core-$version.zip" "$tool_artifacts/kern-core-$version.zip"
ln -s "$artifacts/kern-cms-$version.zip" "$tool_artifacts/kern-cms-$version.zip"
if [[ -f "$artifacts/kern-cms-0.1.0.zip" ]]; then
  cp "$artifacts/kern-cms-0.1.0.zip" "$tool_artifacts/kern-cms-0.1.0.zip"
else
  curl --fail --location --silent --show-error https://github.com/binhtq1/kern-cms/releases/download/v0.1.0/kern-cms-0.1.0.zip --output "$tool_artifacts/kern-cms-0.1.0.zip"
fi
core_repository=$(php -r '$zip = new ZipArchive; $zip->open($argv[1]); $package = json_decode($zip->getFromName("composer.json"), true); $ref = trim(file_get_contents($argv[3])); echo json_encode(["type"=>"package","package"=>[...$package,"version"=>$argv[2],"source"=>["type"=>"git","url"=>"https://github.com/binhtq1/kern-core.git","reference"=>$ref],"dist"=>["type"=>"zip","url"=>"file://".$argv[1],"reference"=>$ref,"shasum"=>hash_file("sha1", $argv[1])]]]);' "$artifacts/kern-core-$version.zip" "$version" "$artifacts/core-reference")
starter_repository=$(php -r 'echo json_encode(["type"=>"package","package"=>["name"=>"binhtq1/kern-cms","version"=>$argv[1],"dist"=>["type"=>"zip","url"=>"file://".$argv[2],"reference"=>hash_file("sha1", $argv[2]),"shasum"=>hash_file("sha1", $argv[2])]]]);' "$version" "$artifacts/kern-cms-$version.zip")
for mode in default no-dev; do
  project="$work/kern-$mode-with-a-long-installation-directory"
  options=()
  theme=magazine
  if [[ "$mode" == no-dev ]]; then options+=(--no-dev); theme=ripple; fi
  composer create-project --no-scripts --no-install --repository="$starter_repository" --no-interaction binhtq1/kern-cms "$project" "$version"
  (
    cd "$project"
    cp .env.example .env
    php -r '$path = "composer.lock"; $lock = json_decode(file_get_contents($path), true); $ref = trim(file_get_contents($argv[2])); foreach ($lock["packages"] as &$package) { if ($package["name"] === "binhtq1/kern-core") { if (($package["source"]["reference"] ?? null) !== $ref) { throw new RuntimeException("Starter/runtime reference mismatch"); } $package["dist"]["url"] = "file://".$argv[1]; $package["dist"]["shasum"] = hash_file("sha1", $argv[1]); } } file_put_contents($path, json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");' "$artifacts/kern-core-$version.zip" "$artifacts/core-reference"
    composer install --no-interaction --prefer-dist "${options[@]}"
    composer run-script post-create-project-cmd --no-interaction
    php scripts/check-project.php "$theme"
    if php scripts/check-project.php "$theme" > "$work/$mode-guard.log" 2>&1; then
      echo 'The fresh-install checker must reject an installed website.' >&2; exit 1
    fi
  )
done
project="$work/legacy-website"
composer create-project binhtq1/kern-cms "$project" 0.1.0 --no-interaction --prefer-dist
php "$project/scripts/check-project.php" magazine
(
  cd "$project"
  php artisan cms:plugin:activate contact --no-interaction
  php artisan cms:plugin:activate member --no-interaction
)
(
  cd "$project"
  php "$source_root/tests/fixtures/upgrade-website.php" seed
  composer config repositories.core "$core_repository"
  cp platform/core/base/helpers/common.php "$work/legacy-common.php"
  cp composer.json "$work/legacy-composer.json"
  printf '\n// Site customization in the old core\n' >> platform/core/base/helpers/common.php
  if php "$artifacts/kern-upgrade.php" --to="$version" --artifacts="$tool_artifacts" --check > "$work/legacy-conflict.log" 2>&1; then
    echo 'Legacy core customizations must be rejected.' >&2; exit 1
  fi
  cmp composer.json "$work/legacy-composer.json"
  cp "$work/legacy-common.php" platform/core/base/helpers/common.php
  cp vite.config.ts "$work/legacy-vite.config.ts"
  printf '\n// Customer build configuration\n' >> vite.config.ts
  if php "$artifacts/kern-upgrade.php" --to="$version" --artifacts="$tool_artifacts" --check > "$work/build-conflict.log" 2>&1; then
    echo 'Customized build configuration must be rejected.' >&2; exit 1
  fi
  cmp composer.json "$work/legacy-composer.json"
  cp "$work/legacy-vite.config.ts" vite.config.ts
  cp platform/plugins/contact/resources/js/contact-public.js "$work/legacy-contact.js"
  printf '\n// Customer customization to a stock module\n' >> platform/plugins/contact/resources/js/contact-public.js
  cp platform/plugins/contact/resources/js/contact-public.js "$work/legacy-contact-custom.js"
  if php "$artifacts/kern-upgrade.php" --to="$version" --artifacts="$tool_artifacts" --check > "$work/stock-frontend-conflict.log" 2>&1; then
    echo 'Updating customized stock frontend files must be rejected.' >&2; exit 1
  fi
  if ! grep -Fq 'Customized stock files conflict' "$work/stock-frontend-conflict.log"; then
    cat "$work/stock-frontend-conflict.log" >&2; exit 1
  fi
  cmp platform/plugins/contact/resources/js/contact-public.js "$work/legacy-contact-custom.js"
  cmp composer.json "$work/legacy-composer.json"
  cp "$work/legacy-contact.js" platform/plugins/contact/resources/js/contact-public.js
  php "$artifacts/kern-upgrade.php" --to="$version" --artifacts="$tool_artifacts" --check
  php "$source_root/tests/fixtures/upgrade-website.php" verify-original
  php "$artifacts/kern-upgrade.php" --to="$version" --artifacts="$tool_artifacts"
  php "$source_root/tests/fixtures/upgrade-website.php" verify-tool "$version"
  php "$artifacts/kern-upgrade.php" --to="$version" --artifacts="$tool_artifacts"
  php "$source_root/tests/fixtures/upgrade-website.php" verify-tool "$version"
)
printf 'Release and upgrade integration tests passed. Disposable fixtures: %s\n' "$work"
