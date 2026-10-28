# Kern CMS Assets

Asset management bundled with [Kern CMS](https://github.com/binhtq1/kern-cms).

[Source](https://github.com/binhtq1/kern-cms/tree/main/platform/packages/assets) · [Issues](https://github.com/binhtq1/kern-cms/issues) · [MIT license](LICENSE.md)

## Installation

This package is resolved from the local path repositories in the Kern CMS checkout.
Install the project's locked dependencies from the repository root:

```bash
composer install
```

The service provider is registered through Composer package discovery.

All assets resource will be manage in config file so we need to publish config to use.

```bash
php artisan vendor:publish --provider="Kern\Assets\Providers\AssetsServiceProvider" --tag=config
```

Add to your master layout view, in `head` tag:

```php
{!! \Assets::renderHeader() !!}
```

and before `body` tag close:

```php
{!! \Assets::renderFooter() !!}
```

## Methods

### Add scripts

```php
\Assets::addScripts(['key-of-assets-in-config-file']);
```

Example:

```php
\Assets::addScripts(['app', 'bootstrap', 'jquery']);
```

### Add styles

```php
\Assets::addStyles(['key-of-assets-in-config-file']);
```

Example:

```php
\Assets::addStyles(['bootstrap', 'font-awesome']);
```

### Remove scripts

```php
\Assets::removeScripts(['key-of-assets-in-config-file']);
```

Example:

```php
\Assets::removeScripts(['bootstrap']);
```

### Remove styles

```php
\Assets::removeStyles(['key-of-assets-in-config-file']);
```

Example:

```php
\Assets::removeStyles(['font-awesome']);
```

### Others

- Set version for assets. Add to `.env`

```bash
ASSETS_VERSION=1.0
```

Then all assets will be added `?v=1.0`

- Change to online mode

```bash
ASSETS_OFFLINE=false
```

Then assets will be loaded from CDN if it's defined in config file.

- To disable versioning:

```bash
ASSETS_ENABLE_VERSION=false
```

## Maintainer

Maintained for Kern CMS by [Binh Tran](https://github.com/binhtq1) (<quocbinhkrb@gmail.com>).

## License

See [LICENSE.md](LICENSE.md) for the MIT license and the original and Kern CMS copyright notices.
