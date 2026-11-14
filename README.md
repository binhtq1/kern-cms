# Kern CMS

Kern CMS is a Laravel 13 content management system. The Composer project `binhtq1/kern-cms` uses the `binhtq1/kern-core` runtime.

## Install

Requires PHP 8.4 or later and Composer 2.

```sh
composer create-project binhtq1/kern-cms my-website &&
cd my-website &&
php artisan serve
```

Open http://127.0.0.1:8000 and complete the installation wizard.

## Update an existing site

For sites already using `binhtq1/kern-core`, back up the site files and database and pause background workers before updating. Run these commands from the site directory.

```sh
composer update binhtq1/kern-core --with-all-dependencies &&
php artisan cms:upgrade
```

Verify that the site works before resuming workers. [Release notes](https://github.com/binhtq1/kern-cms/releases).

## Upgrade from 0.1.0

The standalone upgrader can take an original `0.1.0` installation directly to `0.4.2`. It needs PHP's `zip` extension and `proc_open`. Database snapshots require `mysqldump` for MySQL, `pg_dump` for PostgreSQL, or a file-backed SQLite database.

Back up your site files and database, then test on a copy first. Run the following from the site directory. [Kern CMS v0.4.2 release](https://github.com/binhtq1/kern-cms/releases/tag/v0.4.2).

```sh
curl -fsSL https://github.com/binhtq1/kern-cms/releases/download/v0.4.2/kern-upgrade.php -o kern-upgrade.php &&
(printf '%s  %s\n' \
  85a0e96c058c2bbde62757f25958f1af362a5ec921db2d23e2eb8f1103e2aade kern-upgrade.php | sha256sum --check -) &&
php kern-upgrade.php --to=0.4.2 --check
```

Resolve any conflicts reported by the check. Once it passes, pause workers, scheduled jobs, and other processes that write to the site, then run the upgrade.

```sh
php kern-upgrade.php --to=0.4.2
```

The tool stores its backup in `storage/kern-upgrader/`. If the upgrade fails, keep the site offline until the issue is resolved or you restore the matching site files and database. [Detailed backup restoration steps](https://github.com/binhtq1/kern-cms/blob/fce84bcf1c74ff90b3e7799164a45cc94c0b2ecf/README.md#restore-a-backup-after-a-failed-upgrade).

## License

[MIT](LICENSE).
