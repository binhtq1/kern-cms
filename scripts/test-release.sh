#!/usr/bin/env bash
set -euo pipefail
version=${1:?Version required}
artifacts=${2:?Absolute artifact directory required}
source_root=$(pwd)
work=$(mktemp -d "${TMPDIR:-/tmp}/kern-release-tests.XXXXXXXX")
mkdir -p "$work/runtime-tool"
unzip -q "$artifacts/kern-core-$version.zip" -d "$work/runtime-tool"
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
  php "$source_root/tests/fixtures/upgrade-website.php" seed
  cp platform/core/base/helpers/common.php "$work/legacy-common.php"
  cp composer.json "$work/legacy-composer.json"
  printf '\n// Site customization in the old core\n' >> platform/core/base/helpers/common.php
  if php "$work/runtime-tool/bin/migrate-to-core.php" --project="$project" --check > "$work/legacy-conflict.log" 2>&1; then
    echo 'Legacy core customizations must be rejected.' >&2; exit 1
  fi
  cmp composer.json "$work/legacy-composer.json"
  cp "$work/legacy-common.php" platform/core/base/helpers/common.php
  cp vite.config.ts "$work/legacy-vite.config.ts"
  printf '\n// Customer build configuration\n' >> vite.config.ts
  if php "$work/runtime-tool/bin/migrate-to-core.php" --project="$project" --check > "$work/build-conflict.log" 2>&1; then
    echo 'Customized build configuration must be rejected.' >&2; exit 1
  fi
  cmp composer.json "$work/legacy-composer.json"
  cp "$work/legacy-vite.config.ts" vite.config.ts
  php "$work/runtime-tool/bin/migrate-to-core.php" --project="$project" --check
  php "$work/runtime-tool/bin/migrate-to-core.php" --project="$project"
  composer config repositories.core "$core_repository"
  composer update binhtq1/kern-core 'kern/*' --with-all-dependencies --minimal-changes --no-interaction --prefer-dist
  php artisan cms:upgrade --check
  php artisan cms:upgrade
  php "$source_root/tests/fixtures/upgrade-website.php" verify
  php artisan cms:upgrade
  php "$source_root/tests/fixtures/upgrade-website.php" verify
)
printf 'Release and upgrade integration tests passed. Disposable fixtures: %s\n' "$work"
