<?php

namespace Kern\Base\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Kern\Base\Contracts\Exceptions\IgnoringReport;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Facades\EmailHandler;
use Kern\Base\Http\Middleware\EnsureReactAdminRoutes;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Base\Supports\AdminRenderer;
use Kern\Base\Supports\AdminSessionContext;
use Kern\Language\Facades\Language;
use Kern\Media\Facades\RvMedia;
use Kern\Translation\Supports\AdminTranslationData;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class ExceptionConfigurator
{
    public static function configure(Exceptions $exceptions): void
    {
        $exceptions->dontReport([IgnoringReport::class, SpreadsheetException::class]);
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => self::expectsJson($request));
        $exceptions->report(fn (Throwable $exception) => self::sendErrorReport($exception));
        $exceptions->render(function (AuthenticationException $exception, Request $request): Response {
            if (self::canRecoverExpiredSession($request)) {
                return AdminSessionContext::recoveryResponse();
            }
            if ($request->header('X-Inertia') && self::isAdminRequest($request)) {
                return Inertia::location(route('access.login'));
            }

            if (self::expectsJson($request)) {
                return self::jsonError($exception->getMessage(), 401);
            }

            if (array_filter($exception->guards())) {
                $response = redirect()->guest($exception->redirectTo($request) ?? (Route::has('login') ? route('login') : url('login')));

                return apply_filters('cms_unauthenticated_response', $response, $request, $exception);
            }

            return redirect()->guest(route('access.login'));
        });
        $exceptions->render(function (TransportExceptionInterface $exception, Request $request): Response {
            $message = trans(is_in_admin(true)
                ? 'core/base::errors.error_when_sending_email'
                : 'core/base::errors.error_when_sending_email_guest');

            return self::expectsJson($request)
                ? self::jsonError($message, 500)
                : BaseHttpResponse::make()->setError()->setMessage($message)->toResponse($request);
        });
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            $status = $response->getStatusCode();
            if ($response->headers->get('X-Admin-Session-Recovery') === '1') {
                return $response;
            }
            if ($status === 419 && (! $request->user() || ! AdminSessionContext::matches($request)) && self::canRecoverExpiredSession($request)) {
                return AdminSessionContext::recoveryResponse();
            }
            if ($status === 419 && $request->header('X-Inertia') && ! $request->isMethodSafe() && $request->user()
                && app(AdminRenderer::class)->isInertiaRoute() && ($returnUrl = self::expiredMutationReturnUrl($request))) {
                $message = 'Your session expired. Your changes have not been saved. Please try again.';

                return redirect()->to($returnUrl)->with('error_msg', $message)->withErrors(['_request' => $message]);
            }
            if (self::isAdminRequest($request) && ! self::expectsJson($request) && $status >= 400 && ! $response->headers->has('X-Inertia-Location')) {
                if ($reason = app(AdminRenderer::class)->assetFallbackReason()) {
                    return self::jsonError(str_replace('Blade is required because', 'React admin is unavailable because', $reason).' Run npm run build:admin.', 503);
                }
                Inertia::setRootView('core/base::admin.app');
                Inertia::version(app(HandleAdminInertiaRequests::class)->version($request));
                $inertiaError = Inertia::render('Pages/Error', [
                    'status' => $status,
                    'message' => match ($status) {
                        403 => trans('core/acl::permissions.action_unauthorized'),
                        404 => trans('core/base::errors.not_found'),
                        419 => 'Your session expired. Refresh the page and try again.',
                        default => 'The request could not be completed.',
                    },
                ])->toResponse($request)->setStatusCode($status);
                if ($status === 403 && ! $request->isMethodSafe() && AdminSessionContext::matches($request)) {
                    $inertiaError->headers->set('X-Admin-Draft-Forbidden', '1');
                }
                if ($status >= 500 && ! $request->isMethodSafe() && AdminSessionContext::matches($request) && self::expiredMutationReturnUrl($request) !== null) {
                    $inertiaError->headers->set('X-Admin-Draft-Error', '1');
                }

                return $inertiaError;
            }
            if (! app()->isDownForMaintenance() && $exception instanceof HttpExceptionInterface) {
                do_action(BASE_ACTION_SITE_ERROR, $exception->getStatusCode());
            }

            if ($exception instanceof DisabledInDemoModeException || $exception->getPrevious() instanceof DisabledInDemoModeException) {
                return self::jsonError($exception->getMessage(), 403);
            }

            if (! $exception instanceof HttpExceptionInterface) {
                if ($status < 500 || config('app.debug') || self::expectsJson($request)) {
                    return $response;
                }
                $exception = new HttpException($status, previous: $exception);
            }

            if (self::expectsJson($request)) {
                $message = match ($status) {
                    401 => trans('core/acl::permissions.access_denied_message'),
                    403 => trans('core/acl::permissions.action_unauthorized'),
                    404 => trans('core/base::errors.not_found'),
                    413 => trans('core/media::media.upload_failed', [
                        'size' => BaseHelper::humanFilesize(RvMedia::getServerConfigMaxUploadFileSize()),
                    ]),
                    default => $exception->getMessage(),
                };

                return self::jsonError($message, $status, $exception->getHeaders());
            }

            if ($exception instanceof NotFoundHttpException && setting('redirect_404_to_homepage', false)) {
                return redirect('/');
            }

            $view = 'core/base::errors.'.$status;
            if (! is_in_admin(true) || ! view()->exists($view)) {
                $view = apply_filters('get_http_exception_view', 'errors::'.$status, $exception);
            }

            return view()->exists($view)
                ? response()->view($view, ['exception' => $exception], $status, $exception->getHeaders())
                : $response;
        });
    }

    protected static function canRecoverExpiredSession(Request $request): bool
    {
        if ($request->isMethodSafe() || AdminSessionContext::actor($request) === null) {
            return false;
        }
        if ($request->expectsJson() && is_in_admin(true)) {
            $parts = parse_url((string) $request->headers->get('referer'));

            return is_array($parts) && ! isset($parts['user']) && ! isset($parts['pass'])
                && ($parts['scheme'] ?? '') === $request->getScheme()
                && ($parts['host'] ?? '') === $request->getHost()
                && ($parts['port'] ?? (($parts['scheme'] ?? '') === 'https' ? 443 : 80)) === $request->getPort();
        }

        return $request->header('X-Inertia') && app(AdminRenderer::class)->isInertiaRoute()
            && self::expiredMutationReturnUrl($request) !== null;
    }

    protected static function expiredMutationReturnUrl(Request $request): ?string
    {
        $returnUrl = $request->headers->get('referer');
        $parts = $returnUrl ? parse_url($returnUrl) : false;
        if (! is_array($parts) || isset($parts['user']) || isset($parts['pass'])
            || ($parts['scheme'] ?? '') !== $request->getScheme()
            || ($parts['host'] ?? '') !== $request->getHost()
            || ($parts['port'] ?? (($parts['scheme'] ?? '') === 'https' ? 443 : 80)) !== $request->getPort()) {
            return null;
        }
        $returnRequest = Request::create($returnUrl, 'GET');
        try {
            $route = app('router')->getRoutes()->match($returnRequest);
        } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
            return null;
        }

        $renderer = app(AdminRenderer::class);
        $sourceRoute = $request->route();
        $sourceName = $sourceRoute?->getName() ?? '';
        $targets = [
            'contacts.reply' => ['contacts.edit'],
            'users.update-profile' => ['users.profile.view'],
            'users.change-password' => ['users.profile.view'],
            'users.update-preferences' => ['users.profile.view'],
            'users.profile.image' => ['users.profile.view'],
            'users.profile.image.destroy' => ['users.profile.view'],
            'users.make-super' => ['users.profile.view'],
            'users.remove-super' => ['users.profile.view'],
            'roles.assign' => ['users.profile.view'],
            'roles.duplicate' => ['roles.edit'],
            'languages.store' => ['languages.index', 'settings.language'],
            'languages.edit' => ['languages.index', 'settings.language'],
            'languages.settings' => ['languages.index', 'settings.language'],
            'languages.set.default.update' => ['languages.index', 'settings.language'],
            'translations.group.edit' => ['translations.index'],
            'translations.theme-translations.post' => ['translations.theme-translations'],
            'translations.theme-translations.re-import' => ['translations.theme-translations'],
            'translations.react.import' => [$request->boolean('theme') ? 'translations.theme-translations' : 'translations.index'],
            'translations.locales.delete' => ['translations.locales'],
            'settings.email.template.restore' => ['settings.email.template.edit'],
            'custom-fields.import' => ['custom-fields.index'],
            'member.verify-email' => ['member.edit'],
            'theme.active' => ['theme.index'],
            'theme.remove' => ['theme.index'],
        ];
        $targetNames = $targets[$sourceName] ?? null;
        $profileAssignment = $sourceName === 'roles.assign' && $route->getName() === 'users.profile.view';
        if (! $renderer->isInertiaRoute($route->getName())
            || (! $profileAssignment && $renderer->routeModule($route->getName()) !== $renderer->routeModule($sourceName))
            || ! self::sameRecoveryLocale($request, $returnRequest, $sourceName, $route->getName())) {
            return null;
        }
        if ($targetNames !== null) {
            if (! in_array($route->getName(), $targetNames, true)) {
                return null;
            }
            foreach ($route->parameters() as $key => $value) {
                $original = match (true) {
                    $sourceName === 'roles.assign' && $key === 'user' => $request->input('pk'),
                    $sourceName === 'member.verify-email' && $key === 'member' => $sourceRoute->parameter('id'),
                    default => $sourceRoute->parameter($key),
                };
                $original = $original instanceof UrlRoutable ? $original->getRouteKey() : $original;
                if ((! is_int($original) && ! is_string($original)) || (string) $original !== (string) $value) {
                    return null;
                }
            }

            return $returnUrl;
        }
        foreach (['update' => 'edit', 'store' => 'create', 'bulk' => 'index', 'destroy' => 'index'] as $action => $destination) {
            if (! str_ends_with($sourceName, '.'.$action)) {
                continue;
            }
            $expectedName = substr($sourceName, 0, -strlen($action)).$destination;
            if (! Route::has($expectedName)) {
                continue;
            }
            if ($route->getName() !== $expectedName) {
                return null;
            }
            foreach ($route->parameters() as $key => $value) {
                $original = $sourceRoute->parameter($key);
                $original = $original instanceof UrlRoutable ? $original->getRouteKey() : $original;
                if ((! is_int($original) && ! is_string($original)) || (string) $original !== (string) $value) {
                    return null;
                }
            }

            return $returnUrl;
        }

        if ($route->uri() !== $sourceRoute?->uri()) {
            return null;
        }
        foreach ($route->parameters() as $key => $value) {
            $original = $sourceRoute->parameter($key);
            $original = $original instanceof UrlRoutable ? $original->getRouteKey() : $original;
            if ((! is_int($original) && ! is_string($original)) || (string) $original !== (string) $value) {
                return null;
            }
        }

        return $returnUrl;
    }

    protected static function sameRecoveryLocale(Request $request, Request $returnRequest, string $sourceName, ?string $returnName): bool
    {
        if (in_array($returnName, ['translations.index', 'translations.theme-translations'], true)) {
            $locales = app(AdminTranslationData::class)->locales();
            $default = isset($locales[app()->getLocale()]) ? app()->getLocale() : array_key_first($locales);
            $returned = $returnRequest->input('ref_lang') ?? $default;
            if (! is_string($returned) || ! isset($locales[$returned])) {
                return false;
            }
            $name = $request->input('name');
            $source = match ($sourceName) {
                'translations.group.edit' => is_string($name) ? explode('|', $name, 2)[0] : null,
                'translations.theme-translations.post' => $request->input('pk'),
                'translations.theme-translations.re-import' => $returned,
                default => $request->input('ref_lang') ?? $default,
            };

            return $source === $returned;
        }
        $defaultLocale = null;
        if (is_plugin_active('language') && is_plugin_active('language-advanced')) {
            $candidate = Language::getDefaultLocaleCode();
            if ($candidate && Language::getActiveLanguage()->contains('lang_code', $candidate)) {
                $defaultLocale = $candidate;
            }
        }
        $returnLocale = $returnRequest->query('ref_lang');
        $sourceLocale = $request->input('ref_lang');
        $returnLocale = $returnLocale === null || $returnLocale === '' ? $defaultLocale : $returnLocale;
        $sourceLocale = $sourceLocale === null || $sourceLocale === '' ? $defaultLocale : $sourceLocale;

        return $returnLocale === $sourceLocale;
    }

    protected static function expectsJson(Request $request): bool
    {
        if ($request->header('X-Inertia') && self::isAdminRequest($request)) {
            return false;
        }

        return $request->is('api/*') || $request->expectsJson();
    }

    protected static function isAdminRequest(Request $request): bool
    {
        foreach ($request->route()?->gatherMiddleware() ?? [] as $middleware) {
            if ($middleware === EnsureReactAdminRoutes::class || $middleware === HandleAdminInertiaRequests::class || str_starts_with($middleware, HandleAdminInertiaRequests::class.':')) {
                return true;
            }
        }

        $prefix = trim(BaseHelper::getAdminPrefix(), '/');

        return $prefix !== '' && $request->is($prefix, $prefix.'/*');
    }

    /** @param array<string, string|string[]> $headers */
    protected static function jsonError(string $message, int $status, array $headers = []): Response
    {
        return response()->json(['error' => true, 'data' => null, 'message' => $message], $status, $headers);
    }

    protected static function sendErrorReport(Throwable $exception): void
    {
        if (app()->isLocal() || app()->runningInConsole() || app()->isDownForMaintenance()) {
            return;
        }

        try {
            $emailEnabled = (bool) setting('enable_send_error_reporting_via_email', false);
            $slackEnabled = (bool) config('core.base.general.error_reporting.via_slack', false);
            if ((! $emailEnabled && ! $slackEnabled)
                || Str::contains(strtolower((string) request()->userAgent()), config('core.base.general.error_reporting.ignored_bots', []))
                || ! Cache::add('send_error_exception', true, now()->addMinutes(5))) {
                return;
            }

            if ($emailEnabled) {
                EmailHandler::sendErrorException($exception);
            }
            if ($slackEnabled) {
                logger()->channel('slack')->critical($exception->getMessage(), [
                    'Request URL' => request()->url(),
                    'Request Method' => request()->method(),
                    'Exception Type' => $exception::class,
                    'File Path' => str_replace(base_path().'/', '', $exception->getFile()).':'.$exception->getLine(),
                ]);
            }
        } catch (Throwable $reportingException) {
            logger()->warning('Failed to send CMS error report.', ['exception' => $reportingException]);
        }
    }
}
