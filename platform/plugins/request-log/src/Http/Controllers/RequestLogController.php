<?php

namespace Kern\RequestLog\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Kern\Base\Events\DeletedContentEvent;
use Kern\Base\Http\Actions\DeleteResourceAction;
use Kern\Base\Http\Controllers\BaseSystemController;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Base\Supports\AdminRegisteredTableData;
use Kern\RequestLog\Models\RequestLog;

class RequestLogController extends BaseSystemController
{
    public function getWidgetRequestErrors(Request $request)
    {
        $limit = $request->integer('paginate', 10);
        $limit = $limit > 0 ? $limit : 10;

        $requests = RequestLog::query()
            ->orderByDesc('created_at')
            ->paginate($limit);

        $requests->withQueryString();

        return $this->httpResponse()->setData([
            'columns' => [['key' => 'index', 'label' => '#'], ['key' => 'title', 'label' => trans('core/base::tables.url')], ['key' => 'status_code', 'label' => trans('plugins/request-log::request-log.status_code')]],
            'records' => $requests->map(fn (RequestLog $record, int $index): array => [
                'index' => $index + 1,
                'title' => $record->url,
                'url' => in_array(strtolower((string) parse_url($record->url ?? '', PHP_URL_SCHEME)), ['http', 'https'], true) ? $record->url : null,
                'target' => '_blank',
                'status_code' => $record->status_code,
            ])->all(),
            'pagination' => ['current_page' => $requests->currentPage(), 'last_page' => $requests->lastPage(), 'per_page' => $requests->perPage(), 'total' => $requests->total(), 'prev' => $requests->previousPageUrl(), 'next' => $requests->nextPageUrl()],
            'empty' => ['title' => __('No results found'), 'description' => __('It looks as through there are no request errors here.')],
        ]);
    }

    public function index()
    {

        $this->pageTitle(trans('plugins/request-log::request-log.name'));

        $query = RequestLog::query();
        $filters = request()->validate(['search' => ['nullable', 'string', 'max:200'], 'sort' => ['nullable', Rule::in(['id', 'url', 'status_code', 'count'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $sort = $filters['sort'] ?? 'id';
        $direction = $filters['direction'] ?? 'desc';
        $search = request()->string('search')->limit(200)->toString();
        if ($search !== '') {
            $query->where('url', 'like', '%'.$search.'%');
        }
        if (request()->has('_export')) {
            return app(AdminRegisteredTableData::class)->export('request-logs', $query, request());
        }
        $query->orderBy($sort, $direction);
        if ($sort !== 'id') {
            $query->orderByDesc('id');
        }
        $records = $query->paginate($filters['per_page'] ?? 25)->withQueryString();
        $canDelete = request()->user()->hasPermission('request-log.destroy');

        $props = [
            'tableButtons' => app(AdminRegisteredTableData::class)->buttons('request-logs'),
            'tableVisibility' => app(AdminRegisteredTableData::class)->visibility('request-logs'),
            'tableUrl' => route('request-log.index'),
            'tableFilters' => [...$filters, 'search' => $search, 'sort' => $sort, 'direction' => $direction],
            'title' => trans('plugins/request-log::request-log.name'),
            'columns' => [['key' => 'id', 'label' => 'ID', 'sortable' => true], ['key' => 'url', 'label' => __('Url'), 'sortable' => true], ['key' => 'status_code', 'label' => __('Status Code'), 'sortable' => true], ['key' => 'count', 'label' => trans('plugins/request-log::request-log.count'), 'sortable' => true]],
            'rows' => $records->map(fn ($record) => ['id' => $record->id, 'links' => ['url' => $record->url], 'values' => ['id' => $record->id, 'url' => $record->url, 'status_code' => $record->status_code, 'count' => $record->count, 'created_at' => $record->created_at?->toIso8601String()], 'actions' => $canDelete ? [[
                'label' => __('Delete'), 'url' => route('request-log.destroy', $record->id), 'method' => 'delete', 'confirm' => __('Delete this log?'), 'destructive' => true,
            ]] : []])->all(),
            'actions' => $canDelete ? [['label' => __('Clear logs'), 'url' => route('request-log.empty'), 'method' => 'delete', 'confirm' => __('Delete all logs permanently?'), 'destructive' => true]] : [],
            'bulkDelete' => $canDelete ? ['url' => route('request-log.bulk-destroy')] : null,
            'pagination' => ['prev' => $records->previousPageUrl(), 'next' => $records->nextPageUrl(), 'total' => $records->total()],
            'search' => ['url' => route('request-log.index'), 'value' => $search],
        ];

        return request()->expectsJson() && ! request()->header('X-Inertia')
            ? response()->json(['component' => 'System/Console', 'props' => $props])
            : Inertia::render('System/Console', $props);
    }

    public function destroy(RequestLog $requestLog)
    {
        return DeleteResourceAction::make($requestLog);
    }

    public function bulkDestroy(Request $request): BaseHttpResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:request_logs,id'],
        ]);

        DB::transaction(function () use ($request, $data): void {
            $records = RequestLog::query()->whereIn('id', $data['ids'])->lockForUpdate()->get();
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
        RequestLog::query()->truncate();

        return $this
            ->httpResponse()
            ->withDeletedSuccessMessage();
    }
}
