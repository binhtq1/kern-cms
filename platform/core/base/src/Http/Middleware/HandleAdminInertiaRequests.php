<?php

namespace Kern\Base\Http\Middleware;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Middleware;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Facades\DashboardMenu;
use Kern\Base\Models\AdminNotification;
use Kern\Base\Supports\AdminAppearance;
use Kern\Base\Supports\AdminRenderer;
use Kern\Base\Supports\AdminSessionContext;
use Kern\Contact\Enums\ContactStatusEnum;
use Kern\Contact\Models\Contact;
use Kern\Media\Facades\RvMedia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HandleAdminInertiaRequests extends Middleware
{
    protected $rootView = 'core/base::admin.app';

    public function handle(Request $request, Closure $next, string $module = 'pages'): Response
    {
        $renderer = app(AdminRenderer::class);

        if ($reason = $renderer->assetFallbackReason()) {
            return response()->json([
                'error' => true,
                'message' => str_replace('Blade is required because', 'React admin is unavailable because', $reason).' Run npm run build:admin.',
            ], 503);
        }

        if ($reason = $renderer->fallbackReason($module)) {
            $message = str_replace('Blade is required by', 'Unsupported React admin integration:', $reason);
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['error' => true, 'message' => $message], 503);
            }

            return parent::handle($request, fn (Request $request): Response => Inertia::render('System/Compatibility', [
                'title' => 'Admin integration requires an update',
                'message' => $message,
            ])->toResponse($request)->setStatusCode(503));
        }

        return $this->preserveBackgroundFeedback($request, parent::handle($request, function (Request $request) use ($next): Response {
            $response = $next($request);
            if ($request->routeIs('settings.email.template.iframe') || ! str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
                return $response;
            }

            $original = $response instanceof HttpResponse ? $response->getOriginalContent() : null;
            if ($original instanceof View && $original->name() === $this->rootView) {
                return $response;
            }
            if ($response->isRedirection() || $response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
                return $response;
            }

            $message = 'This admin route returned a legacy HTML interface. Update the integration to return an Inertia page or JSON/download response.';
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['error' => true, 'message' => $message], 503);
            }

            Inertia::setRootView($this->rootView);

            return Inertia::render('System/Compatibility', [
                'title' => 'Admin response requires an update',
                'message' => $message,
            ])->toResponse($request)->setStatusCode(503);
        }));
    }

    protected function preserveBackgroundFeedback(Request $request, Response $response): Response
    {
        if (! $request->isMethod('GET') || $request->header('X-Inertia') || ! $request->expectsJson() || ! $response instanceof JsonResponse || ! $request->user() || ! $request->hasSession()) {
            return $response;
        }

        $session = $request->session();
        $pending = array_intersect(['success_msg', 'error_msg', 'errors'], array_merge($session->get('_flash.old', []), $session->get('_flash.new', [])));
        $pending = array_values(array_filter($pending, fn (string $key): bool => $session->exists($key)));
        if ($pending) {
            $session->keep($pending);
        }

        return $response;
    }

    public function version(Request $request): ?string
    {
        $manifest = public_path('build/admin/manifest.json');

        return is_file($manifest) ? hash_file('sha256', $manifest) : null;
    }

    public function share(Request $request): array
    {
        $navigation = function (iterable $items) use (&$navigation, $request): array {
            $result = [];
            foreach ($items as $item) {
                $result[] = [
                    'label' => trans($item['name']),
                    'url' => $item['url'],
                    'active' => $item['active'],
                    'inertia' => app(AdminRenderer::class)->isInertiaRoute($item['route']),
                    'count' => $item['id'] === 'cms-plugins-contact' && is_plugin_active('contact') && $request->user()?->hasPermission('contacts.index')
                        ? Contact::query()->where('status', ContactStatusEnum::UNREAD)->count() : null,
                    'children' => $navigation($item['children']),
                ];
            }

            return $result;
        };

        return [
            ...parent::share($request),
            'admin' => fn (): array => [
                'name' => setting('admin_title', 'Kern CMS'),
                'locale' => app()->getLocale(),
                'timeZone' => config('app.timezone'),
                'appearance' => app(AdminAppearance::class)->reactData(),
                'capabilities' => [
                    'browseMedia' => $request->user()?->hasPermission('media.index') ?? false,
                    'uploadMedia' => ($request->user()?->hasPermission('media.index') ?? false) && RvMedia::hasPermission('files.create'),
                ],
                'translations' => trans('core/base::admin'),
                'user' => $request->user() ? ['name' => $request->user()->name, 'email' => $request->user()->email] : null,
                'session' => $request->user() ? ['context' => AdminSessionContext::issue($request->user()), 'statusUrl' => route('admin.session-status'), 'loginUrl' => route('access.login')] : null,
                'navigation' => $request->user() ? $navigation(DashboardMenu::getAll()) : [],
                'header' => $this->headerData($request),
                'urls' => ['dashboard' => route('dashboard.index'), 'logout' => route('access.logout'), 'search' => route('core.global-search'), 'profile' => $request->user() ? route('users.profile.view', $request->user()) : null],
            ],
            'flash' => fn (): array => [
                'success' => $request->session()->get('success_msg') ?? $request->session()->get('status'),
                'error' => $request->session()->get('error_msg'),
            ],
        ];
    }

    protected function headerData(Request $request): ?array
    {
        if (! $request->user()) {
            return null;
        }

        $contacts = null;
        if (is_plugin_active('contact') && $request->user()->hasPermission('contacts.edit')) {
            $items = Contact::query()->where('status', ContactStatusEnum::UNREAD)
                ->select(['id', 'name', 'email', 'phone', 'created_at'])->latest()->limit(10)->get();
            if ($items->isNotEmpty()) {
                $contacts = [
                    'url' => $request->user()->hasPermission('contacts.index') ? route('contacts.index') : null,
                    'inertia' => app(AdminRenderer::class)->isInertiaRoute('contacts.edit'),
                    'items' => $items->map(fn (Contact $contact): array => [
                        'id' => $contact->id, 'name' => $contact->name, 'email' => $contact->email,
                        'phone' => $contact->phone, 'createdAt' => $contact->created_at?->toIso8601String(),
                        'url' => route('contacts.edit', $contact),
                    ])->all(),
                ];
            }
        }

        return [
            'website' => BaseHelper::getAdminPrefix() !== '' ? url('/') : null,
            'notifications' => [
                'count' => AdminNotification::countUnread(),
                'url' => route('notifications.index'),
                'readAll' => route('notifications.read-all-notification'),
                'clearAll' => route('notifications.destroy-all-notification'),
            ],
            'contacts' => $contacts,
        ];
    }
}
