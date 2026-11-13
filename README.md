# Kern CMS

Kern CMS is distributed as the Composer project `binhtq1/kern-cms` and uses the `binhtq1/kern-core` runtime.

```bash
composer create-project binhtq1/kern-cms my-website
```

## Upgrade a website from 0.1.0 to 0.3.1

The `kern-upgrade.php` tool upgrades an existing `0.1.0` website directly to `0.3.1`. It switches the Composer configuration to `kern-core`, updates dependencies, runs migrations, and updates assets. You can skip `0.2.0` and `0.3.0`.

These examples use Bash or Zsh on Linux. Replace `/var/www/my-website` with the website root containing `artisan`, `composer.json`, and `.env`. Test on a copy of the website first, then upgrade the live website during a maintenance window. Run one command block at a time. If a block fails, stop and resolve the error before continuing.

### 1. Prepare for the upgrade

- PHP CLI and the PHP runtime serving the website must both be **8.4** or later. Composer 2 must be on `PATH` and use the same compatible PHP environment.
- Install the `zip` extension and the other extensions the CMS requires. Enable `proc_open` and `allow_url_fopen`. The server must be able to reach GitHub and Packagist over HTTPS.
- The website must have a complete installation with `.env`, `composer.lock`, `vendor/autoload.php`, `storage/installed`, and the original `0.1.0` directory structure. Keep the existing `APP_KEY`.
- The account running the commands needs read and write access to the website files. Allow enough free disk space for copies of the source code, `vendor`, uploads, and database, plus temporary downloads and extracted files.
- The tool supports the `mysql`, `pgsql`, and `sqlite` drivers. MySQL or MariaDB using the `mysql` driver requires `mysqldump`. PostgreSQL requires a version of `pg_dump` compatible with the database server. The database account needs permission to create a dump. SQLite must use an on-disk database. Snapshot support is limited to these three drivers, which excludes the separate `mariadb` driver.
- Make an independent backup before deploying. Back up data on S3 or other external services, and any symlink targets outside the website, separately.

```bash
cd /var/www/my-website
php -v
composer --version
```

### 2. Download the upgrade tool for release 0.3.1

Download the **release asset** from [v0.3.1](https://github.com/binhtq1/kern-cms/releases/tag/v0.3.1). The build uses the `kern-upgrade.php` file in the source branch as a template. Run the release asset to perform the upgrade.

```bash
curl --fail --location \
  https://github.com/binhtq1/kern-cms/releases/download/v0.3.1/kern-upgrade.php \
  --output kern-upgrade.php
```

Verify the SHA-256 checksum of the released tool.

```bash
printf '%s  %s\n' \
  b7765714d4148da4c335205f5e1fd784ef1ed020bb2bab7d964ca3c0c0e0bc29 \
  kern-upgrade.php | sha256sum --check -
```

Continue only if the output is `kern-upgrade.php: OK`. The tool also verifies the SHA-256 checksums of the CMS and runtime archives it downloads.

### 3. Check for conflicts before upgrading

```bash
php kern-upgrade.php --to=0.3.1 --check
```

A successful check prints the following line.

```text
Check passed. Website files and database were not changed.
```

`--check` examines the website structure, modified stock files, and custom plugin or theme code that still depends on jQuery. It leaves the website and database untouched. The tool checks the application, the database connection, and whether it can create a snapshot during the upgrade itself. Test custom integrations in staging as well as running this static check.

If the tool reports conflicting files, resolve each conflict and rerun `--check`. Move changes to core files into custom code, configuration, or appropriate overrides. Port custom jQuery code to vanilla JavaScript or React. The tool stops when it detects conflicts and leaves the customized files untouched.

### 4. Pause processes that write data

Use Supervisor, systemd, or your hosting control panel to pause queue workers, schedulers and cron jobs, data imports, and other background processes that can write to the database or upload storage. If multiple instances share the same database or storage, pause writes on all affected instances throughout the upgrade and verification.

Background processes may continue running in HTTP maintenance mode. The tool enables maintenance mode before creating the backup if it is currently disabled.

### 5. Run the upgrade

```bash
php kern-upgrade.php --to=0.3.1
```

The tool checks the application and database, enables maintenance mode, backs up the website and database, switches the Composer configuration, updates the runtime and assets, and runs `cms:upgrade`. This workflow includes the required Composer and migration commands.

A successful upgrade prints the following message.

```text
Kern CMS 0.3.1 upgraded successfully. Backup retained at ...
```

The pre-upgrade backup is stored in `storage/kern-upgrader/<timestamp>-<id>/`.

| File | Contents |
| --- | --- |
| `website.zip` | Source code, `vendor`, `.env`, uploads, and other regular files in the website before the upgrade. |
| `database.sql` | MySQL or MariaDB snapshot when using the `mysql` driver. |
| `database.pgsql` | PostgreSQL snapshot in custom archive format when using the `pgsql` driver. |
| `database.sqlite` | Consistent SQLite snapshot when using the `sqlite` driver. |
| `files.json` | SHA-256 checksums for the ZIP archive and individual files, plus a list of symlinks and their targets. |
| `database-info.json` | Database driver and the maintenance state before the tool enabled maintenance mode. |
| `status.json` | Upgrade status and the `backup_complete` flag. |

Only the snapshot for the active database driver is present. The ZIP excludes `.git`, `node_modules`, earlier upgrade backups, the contents of symlink targets, and any maintenance file the tool creates. The backup contains secrets from `.env` and website data. Keep it private and out of Git and public download locations.

### 6. Verify the upgrade

```bash
composer show binhtq1/kern-core
cat storage/app/kern/upgrade.json
```

Confirm that the runtime is `0.3.1` and that the status file contains `"version": "0.3.1"` and `completed_at`. Check administrator and member sign-in, content, settings, uploads, forms, and custom plugins and themes. Resume workers and the scheduler after verification.

If the tool enabled maintenance mode, it brings the website back online after a successful upgrade. If you enabled maintenance mode beforehand, bring the site back online with `php artisan up --no-interaction` once verification is complete. Keep the backup until you confirm that the website is stable.

## Restore a backup after a failed upgrade

Rollback is **manual**. After an error, the website may still be in maintenance mode. Keep it offline and pause any automated upgrade commands while the upgrade is incomplete. Record the error message and backup path. Troubleshoot the failure in a controlled manner or restore **the source code and database from the same pre-upgrade backup**.

Restoration returns the data to the point when the snapshot was taken. Preserve any data created after that point separately and reconcile it before bringing the website back online.

### 1. Keep the website offline and verify the backup

Block access using maintenance mode at the web server, reverse proxy, or hosting layer throughout the restoration. Keep workers, schedulers, and all instances that write data paused. The external maintenance layer is required because you will replace the source directory.

Set the absolute paths in the same SSH session. This example assumes the website root is a real directory. If your deployment uses symlinks or release directories, adapt the directory swap to your deployment process. Set `kern_backup_id` to the actual backup ID in the command below.

```bash
kern_site_dir=/var/www/my-website
kern_backup_id=REPLACE-WITH-BACKUP-ID
kern_backup_dir="$kern_site_dir/storage/kern-upgrader/$kern_backup_id"
kern_restore_dir=/var/www/my-website.restore-0.1.0
kern_failed_dir="/var/www/my-website.failed-$(date +%Y%m%d%H%M%S)"

cat "$kern_backup_dir/status.json"
cat "$kern_backup_dir/database-info.json"
```

Confirm that `backup_complete` is `true` and that the snapshot matches the database driver. If `backup_complete` is `false`, the backup is incomplete and the tool has yet to switch the CMS code. Resolve the backup error or use an independent backup for a full restoration.

Verify the ZIP against the hash stored in the backup.

```bash
php -r '
$backup = $argv[1];
$status = json_decode(file_get_contents($backup . "/status.json"), true, flags: JSON_THROW_ON_ERROR);
$files = json_decode(file_get_contents($backup . "/files.json"), true, flags: JSON_THROW_ON_ERROR);
if (($status["backup_complete"] ?? false) !== true || !hash_equals($files["archive_sha256"], hash_file("sha256", $backup . "/website.zip"))) {
    fwrite(STDERR, "Backup incomplete or ZIP checksum mismatch. Stop restoration.\n");
    exit(1);
}
echo "Backup ZIP verified.\n";
' "$kern_backup_dir"
```

Stop if verification fails. You can copy the entire backup directory to a private location outside the website before continuing.

### 2. Extract the source code into a new directory

This step requires `unzip`. Use paths for `kern_restore_dir` and `kern_failed_dir` that do not already exist. Extract into a fresh directory to avoid leaving behind files introduced by `0.3.1`.

```bash
test ! -e "$kern_restore_dir" &&
test ! -e "$kern_failed_dir" &&
unzip -t "$kern_backup_dir/website.zip" &&
mkdir "$kern_restore_dir" &&
unzip -q "$kern_backup_dir/website.zip" -d "$kern_restore_dir" &&
mkdir -p "$kern_restore_dir/storage/app/public" \
  "$kern_restore_dir/storage/framework/cache/data" \
  "$kern_restore_dir/storage/framework/cache/locks" \
  "$kern_restore_dir/storage/framework/sessions" \
  "$kern_restore_dir/storage/framework/views" \
  "$kern_restore_dir/storage/logs" \
  "$kern_restore_dir/bootstrap/cache"
```

Keep `.env`, `APP_KEY`, `composer.json`, `composer.lock`, and `vendor` from the backup. File ownership and executable permissions may need to be restored separately. Reapply permissions according to your server configuration, especially write access to `storage` and `bootstrap/cache` for the PHP or web server account.

### 3. Restore the database for your driver

Follow exactly **one** of the following options based on `database-info.json`. For MySQL or PostgreSQL, use a **new, empty database with a distinct name**, such as `kern_restore_0_1_0`. Keep the partially upgraded database for comparison. Importing into a database with existing tables can leave schema objects from the newer version behind.

Replace `DB_HOST`, `DB_USER`, the port, and the database name in the examples with your actual values. Use an account with permission to create or import the database, or have an administrator create an empty database and grant access first. Enter the password at the client prompt to keep it out of the command line.

If the website uses `DB_URL` or environment variables supplied by the hosting platform or container, update those values too so that they point to the restored database. They can override `DB_DATABASE` in `.env`. Verify the effective database connection before bringing the website back online or starting workers.

**MySQL/MariaDB** — use the character set and collation appropriate for the original database.

```bash
mysql -h DB_HOST -P 3306 -u DB_USER -p \
  -e 'CREATE DATABASE kern_restore_0_1_0 CHARACTER SET utf8mb4;' &&
mysql -h DB_HOST -P 3306 -u DB_USER -p kern_restore_0_1_0 \
  < "$kern_backup_dir/database.sql"
```

If an administrator already created an empty database, skip `CREATE DATABASE`. After a successful import, set `DB_DATABASE=kern_restore_0_1_0` and update the corresponding connection settings in `.env` inside `kern_restore_dir`. Keep the original `APP_KEY`.

**PostgreSQL** — use a version of `pg_restore` compatible with the version that created the dump.

```bash
createdb -h DB_HOST -p 5432 -U DB_USER -W \
  --template=template0 kern_restore_0_1_0 &&
pg_restore -h DB_HOST -p 5432 -U DB_USER -W \
  --exit-on-error --single-transaction --no-owner --no-privileges \
  --dbname=kern_restore_0_1_0 "$kern_backup_dir/database.pgsql"
```

If the empty database already exists, skip `createdb`. Continue only after the restore succeeds. Have an administrator reapply any site-specific grants or extensions as needed. Update `.env` in `kern_restore_dir` to point to the new database and keep the original `APP_KEY`.

**SQLite** — use the separate snapshot in place of the database file in the ZIP. Choose a new filename to avoid reusing old WAL or journal files.

```bash
test ! -e "$kern_restore_dir/database/restored-0.1.0.sqlite" &&
cp "$kern_backup_dir/database.sqlite" \
  "$kern_restore_dir/database/restored-0.1.0.sqlite"
```

In `.env` inside `kern_restore_dir`, set `DB_CONNECTION=sqlite` and set `DB_DATABASE` to the **path after the directory swap**, such as `/var/www/my-website/database/restored-0.1.0.sqlite`. Grant the PHP account read and write access to the database and its parent directory.

For command syntax, see the [MySQL guide to reloading SQL dumps](https://dev.mysql.com/doc/refman/8.4/en/reloading-sql-format-dumps.html) and the [PostgreSQL reference for pg_restore](https://www.postgresql.org/docs/current/app-pgrestore.html).

### 4. Swap the source directories and restore symlinks

Swap the directories only after extracting all source files and successfully restoring the database. Run the rename commands from outside the website directory.

```bash
cd /var/www &&
test -d "$kern_site_dir" &&
test -d "$kern_restore_dir" &&
test ! -e "$kern_failed_dir" &&
mv -T "$kern_site_dir" "$kern_failed_dir" &&
mv -T "$kern_restore_dir" "$kern_site_dir" &&
kern_backup_dir="$kern_failed_dir/storage/kern-upgrader/$kern_backup_id"
```

The partially upgraded directory and the full backup remain in `kern_failed_dir`. If the second move fails, keep external maintenance mode active and move `kern_failed_dir` back to the website path before retrying.

Review the `symlinks` entry in `files.json` and recreate each link according to the original configuration. You can use `storage:link` in the next step for the standard link from `public/storage` to `storage/app/public`. Check and restore custom links and target data outside the website separately. Verify each target before linking so that the restored site avoids partially upgraded data.

Restore `.git` and `node_modules` separately from source control or the build system if needed. The ZIP excludes them. Leave the partially upgraded directory separate from the restored website.

### 5. Refresh configuration caches and verify the restored website

Remove the configuration cache file so that the next boot uses the database connection you set in `.env`, then refresh the application caches.

```bash
cd "$kern_site_dir" &&
rm -f bootstrap/cache/config.php &&
php artisan config:clear --no-interaction &&
php artisan route:clear --no-interaction &&
php artisan view:clear --no-interaction &&
php artisan event:clear --no-interaction &&
php artisan down --retry=60 --no-interaction
```

If the original website used the standard `public/storage` link, recreate it after checking the symlink list.

```bash
php artisan storage:link --no-interaction
```

Reload PHP-FPM and OPcache using your server's deployment process to clear cached code from the partially upgraded version. Verify the restored `0.1.0` website against the restored database by checking admin sign-in, content, configuration, images, and custom functionality. To test over HTTP, keep external maintenance mode in effect for the public, allow access only to testers, and temporarily disable application maintenance mode. During rollback, avoid running migrations or the upgrade tool, and keep the original `APP_KEY`.

### 6. Bring the website back online

Once you confirm that the source code and database are in sync, disable application maintenance mode.

```bash
php artisan up --no-interaction
```

Disable maintenance mode at the web server or hosting layer, then restart workers and the scheduler with the restored source code. Keep the partially upgraded directory, the partially upgraded database, and the backup private so you can investigate the cause before the next upgrade.

## License

[MIT](LICENSE).
