<?php

namespace Kern\Base\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Base\Models\AdminNotification;
use Kern\Base\Models\AdminNotificationQueryBuilder;

class NotificationController extends BaseController
{
    public function index(): BaseHttpResponse|Response
    {
        $notificationsCount = AdminNotification::countUnread();

        /**
         * @var AdminNotificationQueryBuilder $adminQuery
         */
        $adminQuery = AdminNotification::query();

        $query = $adminQuery->hasPermission();

        $notifications = $query
            ->latest()
            ->paginate(10);

        if (request()->boolean('admin_json') || (request()->expectsJson() && ! request()->header('X-Inertia'))) {
            return $this->httpResponse()->setData([
                'count' => $notificationsCount,
                'items' => $notifications->map(fn (AdminNotification $notification): array => [
                    'id' => $notification->id,
                    'title' => strip_tags($notification->title),
                    'description' => strip_tags($notification->description ?? ''),
                    'descriptionHtml' => BaseHelper::clean($notification->description ?? ''),
                    'unread' => $notification->read_at === null,
                    'createdAt' => $notification->created_at?->toIso8601String(),
                    'actionLabel' => strip_tags($notification->action_label ?: trans('core/base::notifications.view')),
                    'actionUrl' => filled($notification->action_url) && $notification->action_url !== '#' ? route('notifications.read-notification', $notification) : null,
                    'deleteUrl' => route('notifications.destroy', $notification),
                ])->all(),
                'pagination' => ['page' => $notifications->currentPage(), 'lastPage' => $notifications->lastPage(), 'total' => $notifications->total()],
            ]);
        }

        if ((! request()->expectsJson() || request()->header('X-Inertia'))) {
            return Inertia::render('System/Console', [
                'title' => __('Notifications'), 'stats' => [['label' => __('Unread'), 'value' => $notificationsCount]],
                'columns' => [['key' => 'status', 'label' => __('Status')], ['key' => 'title', 'label' => __('Title')], ['key' => 'description', 'label' => __('Description')], ['key' => 'created_at', 'label' => __('Created at')]],
                'rows' => $notifications->map(fn ($notification) => ['id' => $notification->id, 'unread' => $notification->read_at === null, 'values' => ['status' => $notification->read_at === null ? __('Unread') : __('Read'), 'title' => strip_tags($notification->title), 'description' => strip_tags($notification->description ?? ''), 'created_at' => $notification->created_at?->toIso8601String()], 'actions' => [
                    ['label' => __('Read'), 'url' => route('notifications.read-notification', $notification->id)],
                    ['label' => __('Delete'), 'url' => route('notifications.destroy', $notification->id), 'method' => 'delete', 'confirm' => __('Delete this notification?'), 'destructive' => true],
                ]])->all(),
                'actions' => [['label' => __('Mark all read'), 'url' => route('notifications.read-all-notification'), 'method' => 'put'], ['label' => __('Clear all'), 'url' => route('notifications.destroy-all-notification'), 'method' => 'delete', 'confirm' => __('Delete all notifications? This action cannot be undone.'), 'destructive' => true, 'disabled' => $notifications->total() === 0]],
                'pagination' => ['prev' => $notifications->previousPageUrl(), 'next' => $notifications->nextPageUrl(), 'total' => $notifications->total()],
            ]);
        }

    }

    public function destroy(int|string $id): BaseHttpResponse
    {
        $notificationItem = AdminNotification::query()->hasPermission()->findOrFail($id);
        $notificationItem->delete();

        /**
         * @var AdminNotificationQueryBuilder $adminQuery
         */
        $adminQuery = AdminNotification::query();

        /**
         * @var Builder $query
         */
        $query = $adminQuery->hasPermission();

        if (! $query->exists()) {
            return $this
                ->httpResponse()
                ->setData(['count' => 0, 'items' => []]);
        }

        return $this->httpResponse();
    }

    public function deleteAll(): BaseHttpResponse
    {
        AdminNotification::query()->hasPermission()->delete();

        return $this->httpResponse();
    }

    public function read(int|string $id): RedirectResponse
    {
        /**
         * @var AdminNotification $notificationItem
         */
        $notificationItem = AdminNotification::query()->hasPermission()->findOrFail($id);

        if ($notificationItem->read_at === null) {
            $notificationItem->markAsRead();
        }

        if (! $notificationItem->action_url || $notificationItem->action_url == '#') {
            return redirect()->back();
        }

        return redirect()->to(url($notificationItem->action_url));
    }

    public function readAll(): BaseHttpResponse
    {
        AdminNotification::query()
            ->hasPermission()
            ->whereNull('read_at')
            ->update([
                'read_at' => Carbon::now(),
            ]);

        return $this->httpResponse();
    }

    public function countUnread(): BaseHttpResponse
    {
        return $this
            ->httpResponse()
            ->setData(AdminNotification::countUnread());
    }
}
