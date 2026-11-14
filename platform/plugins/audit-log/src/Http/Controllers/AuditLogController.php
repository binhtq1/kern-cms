<?php

namespace Kern\AuditLog\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Kern\AuditLog\Models\AuditHistory;
use Kern\Base\Events\DeletedContentEvent;
use Kern\Base\Http\Actions\DeleteResourceAction;
use Kern\Base\Http\Controllers\BaseSystemController;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Base\Supports\AdminRegisteredTableData;
use Kern\Media\Facades\RvMedia;

class AuditLogController extends BaseSystemController
{
    public function getWidgetActivities(Request $request)
    {
        $limit = $request->integer('paginate', 10);
        $limit = $limit > 0 ? $limit : 10;

        $histories = AuditHistory::query()
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate($limit);

        $histories->withQueryString();

        return $this->httpResponse()->setData([
            'records' => $histories->map(function (AuditHistory $record): array {
                $user = $record->user;
                $action = Lang::has('plugins/audit-log::history.'.$record->action) ? trans('plugins/audit-log::history.'.$record->action) : $record->action;
                $module = $record->module === 'user' && $user->id === auth()->id() ? '' : (Lang::has('plugins/audit-log::history.'.$record->module) ? trans('plugins/audit-log::history.'.$record->module) : $record->module);

                return [
                    'title' => trim($action.' '.$module),
                    'user' => $user->exists ? $user->name : trans('plugins/audit-log::history.system'),
                    'avatar' => $user->exists ? $user->avatar_url : (setting('admin_favicon') ? RvMedia::getImageUrl(setting('admin_favicon')) : asset(RvMedia::getDefaultImage())),
                    'action' => $action,
                    'module' => $module,
                    'reference_name' => $record->reference_name && $user->name !== $record->reference_name ? $record->reference_name : null,
                    'ip_address' => $record->ip_address,
                    'created_at' => $record->created_at?->toIso8601String(),
                    'relative_time' => $record->created_at?->diffForHumans(),
                ];
            })->all(),
            'pagination' => ['current_page' => $histories->currentPage(), 'last_page' => $histories->lastPage(), 'per_page' => $histories->perPage(), 'total' => $histories->total(), 'prev' => $histories->previousPageUrl(), 'next' => $histories->nextPageUrl()],
            'empty' => ['title' => __('No results found'), 'description' => __('It looks as through there are no activities here.')],
        ]);
    }

    public function index()
    {

        $this->pageTitle(trans('plugins/audit-log::history.name'));

        $query = AuditHistory::query()->with('user');
        $filters = request()->validate(['search' => ['nullable', 'string', 'max:200'], 'sort' => ['nullable', Rule::in(['id', 'action'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $sort = $filters['sort'] ?? 'id';
        $direction = $filters['direction'] ?? 'desc';
        $search = request()->string('search')->limit(200)->toString();
        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                foreach (['action', 'module', 'type', 'ip_address', 'user_agent', 'reference_name'] as $index => $column) {
                    $query->{$index === 0 ? 'where' : 'orWhere'}($column, 'like', '%'.$search.'%');
                }
                $query->orWhereHas('user', fn ($query) => $query->where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%'));
            });
        }
        if (request()->has('_export')) {
            return app(AdminRegisteredTableData::class)->export('audit-logs', $query, request());
        }
        $query->orderBy($sort, $direction);
        if ($sort !== 'id') {
            $query->orderByDesc('id');
        }
        $records = $query->paginate($filters['per_page'] ?? 25)->withQueryString();
        $canDelete = request()->user()->hasPermission('audit-log.destroy');

        $props = [
            'tableButtons' => app(AdminRegisteredTableData::class)->buttons('audit-logs'),
            'tableVisibility' => app(AdminRegisteredTableData::class)->visibility('audit-logs'),
            'tableUrl' => route('audit-log.index'),
            'tableFilters' => [...$filters, 'search' => $search, 'sort' => $sort, 'direction' => $direction],
            'title' => trans('plugins/audit-log::history.name'),
            'columns' => [['key' => 'id', 'label' => 'ID', 'sortable' => true], ['key' => 'user', 'label' => __('User')], ['key' => 'action', 'label' => __('Action'), 'sortable' => true], ['key' => 'module', 'label' => __('Module')], ['key' => 'reference_name', 'label' => __('Reference Name')], ['key' => 'ip_address', 'label' => __('IP Address')], ['key' => 'created_at', 'label' => __('Created At')]],
            'rows' => $records->map(fn ($record) => ['id' => $record->id, 'values' => ['id' => $record->id, 'user' => $record->user->exists ? $record->user->name : trans('plugins/audit-log::history.system'), 'action' => Lang::has('plugins/audit-log::history.'.$record->action) ? trans('plugins/audit-log::history.'.$record->action) : $record->action, 'module' => Lang::has('plugins/audit-log::history.'.$record->module) ? trans('plugins/audit-log::history.'.$record->module) : $record->module, 'reference_name' => $record->reference_name, 'ip_address' => $record->ip_address, 'created_at' => $record->created_at?->toIso8601String()], 'actions' => $canDelete ? [[
                'label' => __('Delete'), 'url' => route('audit-log.destroy', $record->id), 'method' => 'delete', 'confirm' => __('Delete this log?'), 'destructive' => true,
            ]] : []])->all(),
            'actions' => $canDelete ? [['label' => __('Clear logs'), 'url' => route('audit-log.empty'), 'method' => 'delete', 'confirm' => __('Delete all logs permanently?'), 'destructive' => true]] : [],
            'bulkDelete' => $canDelete ? ['url' => route('audit-log.bulk-destroy')] : null,
            'pagination' => ['prev' => $records->previousPageUrl(), 'next' => $records->nextPageUrl(), 'total' => $records->total()],
            'search' => ['url' => route('audit-log.index'), 'value' => $search],
        ];

        return request()->expectsJson() && ! request()->header('X-Inertia')
            ? response()->json(['component' => 'System/Console', 'props' => $props])
            : Inertia::render('System/Console', $props);
    }

    public function destroy(AuditHistory $auditLog)
    {
        return DeleteResourceAction::make($auditLog);
    }

    public function bulkDestroy(Request $request): BaseHttpResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:audit_histories,id'],
        ]);

        DB::transaction(function () use ($request, $data): void {
            $records = AuditHistory::query()->whereIn('id', $data['ids'])->lockForUpdate()->get();
            if ($records->count() !== count($data['ids'])) {
                throw ValidationException::withMessages(['ids' => __('One or more selected logs no longer exist.')]);
            }
            foreach ($records as $record) {
                if (! $record->delete()) {
                    throw ValidationException::withMessages(['ids' => __('Unable to delete the selected logs.')]);
                }
                DeletedContentEvent::dispatch($record::class, $request, $record);
            }
        });

        return $this->httpResponse()->withDeletedSuccessMessage();
    }

    public function deleteAll()
    {
        AuditHistory::query()->truncate();

        return $this
            ->httpResponse()
            ->withDeletedSuccessMessage();
    }
}
