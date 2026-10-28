<?php

namespace Kern\Page\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Base\Events\DeletedContentEvent;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\Base\Http\Actions\DeleteResourceAction;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Base\Supports\AdminRegisteredTableData;
use Kern\Base\Supports\Breadcrumb;
use Kern\Media\Facades\RvMedia;
use Kern\Media\Models\MediaFile;
use Kern\Page\Forms\PageForm;
use Kern\Page\Http\Requests\PageRequest;
use Kern\Page\Models\Page;
use Kern\Page\Services\SavePageService;
use Kern\Page\Supports\AdminPageData;
use Kern\Page\Tables\PageTable;

class PageController extends BaseController
{
    protected function breadcrumb(): Breadcrumb
    {
        return parent::breadcrumb()
            ->add(trans('packages/page::pages.menu_name'), route('pages.index'));
    }

    public function index(PageTable $pageTable, Request $request, AdminPageData $data): mixed
    {
        $this->pageTitle(trans('packages/page::pages.menu_name'));

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(BaseStatusEnum::values())],
            'template' => ['nullable', Rule::in(array_keys(get_page_templates()))],
            'sort' => ['nullable', Rule::in(['id', 'name', 'created_at', 'status', 'template'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'ref_lang' => ['nullable', 'string', 'max:20'],
        ]);
        $language = $data->language();
        $translation = $data->isTranslation();
        $query = Page::query()->select('pages.*');
        if ($translation) {
            $query->leftJoin('pages_translations as admin_translation', function ($join) use ($language): void {
                $join->on('admin_translation.pages_id', '=', 'pages.id')->where('admin_translation.lang_code', $language);
            })->addSelect(DB::raw('COALESCE(admin_translation.name, pages.name) as name'));
        }
        $nameColumn = $translation ? 'COALESCE(admin_translation.name, pages.name)' : 'pages.name';
        if ($search = $filters['search'] ?? null) {
            $query->whereLike(DB::raw($nameColumn), '%'.$search.'%');
        }
        foreach (['status', 'template'] as $field) {
            if ($value = $filters[$field] ?? null) {
                $query->where('pages.'.$field, $value);
            }
        }
        $registered = app(AdminRegisteredTableData::class);
        $filters['advanced'] = $registered->applyFilters('pages', $query, $request, $language);
        $sort = $filters['sort'] ?? 'created_at';
        $query->orderByRaw(($sort === 'name' ? $nameColumn : 'pages.'.$sort).' '.($filters['direction'] ?? 'desc'))->orderBy('pages.id');
        if ($request->has('_export')) {
            return $registered->export('pages', $query, $request);
        }
        $pages = $query->paginate($filters['per_page'] ?? 20)->withQueryString()->through(fn (Page $page): array => $data->row($page));

        return Inertia::render('Pages/Index', [
            'pages' => $pages,
            'tableButtons' => $registered->buttons('pages'),
            'tableVisibility' => $registered->visibility('pages'),
            'filters' => $filters,
            'filterFields' => $registered->fields('pages', new Page, $language),
            'statuses' => $data->statuses(),
            'templates' => $data->templates(),
            'permissions' => $data->permissions($request),
            'bulkFields' => $translation ? ['name'] : ['status', 'template', 'name', 'created_at'],
            'urls' => ['index' => route('pages.index').($language ? '?'.http_build_query(['ref_lang' => $language]) : ''), 'create' => route('pages.create'), 'bulk' => route('pages.bulk').($language ? '?'.http_build_query(['ref_lang' => $language]) : ''), 'bulkDestroy' => route('pages.bulk').($language ? '?'.http_build_query(['ref_lang' => $language]) : '')],
        ]);
    }

    public function create(AdminPageData $data): mixed
    {
        $this->pageTitle(trans('packages/page::pages.create'));

        return Inertia::render('Pages/Form', $data->form(new Page));
    }

    public function store(PageRequest $request, SavePageService $service): mixed
    {
        $page = $service->execute($request);

        if ($request->header('X-Inertia')) {
            return redirect()->to($request->input('submitter') === 'save' ? route('pages.index') : route('pages.edit', $page))->with('success_msg', trans('core/base::notices.create_success_message'));
        }

        return $this
            ->httpResponse()
            ->setPreviousRoute('pages.index')
            ->setNextRoute('pages.edit', $page->getKey())
            ->withCreatedSuccessMessage();
    }

    public function edit(Page $page, AdminPageData $data): mixed
    {
        $this->pageTitle(trans('core/base::forms.edit_item', ['name' => $page->name]));

        return Inertia::render('Pages/Form', $data->form($page));
    }

    public function update(Page $page, PageRequest $request, SavePageService $service): mixed
    {
        $service->execute($request, $page);

        if ($request->header('X-Inertia')) {
            return redirect()->to($request->input('submitter') === 'save' ? route('pages.index').($request->input('ref_lang') ? '?'.http_build_query(['ref_lang' => $request->input('ref_lang')]) : '') : route('pages.edit', $page).($request->input('ref_lang') ? '?'.http_build_query(['ref_lang' => $request->input('ref_lang')]) : ''))->with('success_msg', trans('core/base::notices.update_success_message'));
        }

        return $this
            ->httpResponse()
            ->setPreviousRoute('pages.index')
            ->withUpdatedSuccessMessage();
    }

    public function destroy(Page $page, Request $request, AdminPageData $data): mixed
    {
        $action = DeleteResourceAction::make($page);
        if ($request->header('X-Inertia')) {
            $language = $data->language();
            $result = $action->execute()->toArray();

            return redirect()->to(route('pages.index').($language ? '?'.http_build_query(['ref_lang' => $language]) : ''))->with($result['error'] ? 'error_msg' : 'success_msg', $result['message']);
        }

        return $action;
    }

    public function media(Request $request): mixed
    {
        $input = $request->validate(['search' => ['nullable', 'string', 'max:120'], 'page' => ['nullable', 'integer', 'min:1'], 'type' => ['nullable', Rule::in(['image'])]]);
        $query = MediaFile::query()->where('visibility', 'public');
        if (setting('user_can_only_view_own_media', false) && ! $request->user()->isSuperUser()) {
            $query->where('user_id', $request->user()->getKey());
        }
        if (($input['type'] ?? null) === 'image') {
            $query->where('mime_type', 'like', 'image/%');
        }
        if ($search = $input['search'] ?? null) {
            $query->whereLike('name', '%'.$search.'%');
        }

        return response()->json($query->orderByDesc('created_at')->paginate(24)->through(fn (MediaFile $file): array => [
            'id' => (string) $file->getKey(), 'name' => $file->name, 'url' => $file->url,
            'src' => RvMedia::url($file->url), 'mime_type' => $file->mime_type,
        ]));
    }

    public function uploadMedia(Request $request): mixed
    {
        abort_unless(RvMedia::hasPermission('files.create'), 403);
        $request->validate(['file' => ['required', 'file']]);
        $result = RvMedia::handleUpload($request->file('file'), 0);
        if ($result['error']) {
            return response()->json($result, 422);
        }

        return response()->json(['error' => false, 'data' => ['id' => (string) $result['data']->getKey(), 'url' => $result['data']->url, 'src' => RvMedia::url($result['data']->url)]]);
    }

    public function bulk(Request $request, AdminPageData $data): mixed
    {
        $language = $data->language();
        $translation = $data->isTranslation();
        $indexQuery = app(AdminRegisteredTableData::class)->indexQuery($request, $language);
        $input = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'distinct', Rule::exists('pages', 'id')],
            'action' => ['required', Rule::in(['delete', 'update'])],
            'field' => ['required_if:action,update', Rule::in(['name', 'template', 'status', 'created_at'])],
            'value' => ['required_if:action,update'],
        ]);
        abort_unless($request->user()->hasPermission($input['action'] === 'delete' ? 'pages.destroy' : 'pages.edit'), 403);
        if ($input['action'] === 'update') {
            if ($translation && $input['field'] !== 'name') {
                throw ValidationException::withMessages(['field' => 'Only translated fields can be changed in this language.']);
            }
            $rules = match ($input['field']) {
                'name' => ['string', 'max:120'],
                'template' => [Rule::in(array_keys(get_page_templates()))],
                'status' => [Rule::in(BaseStatusEnum::values())],
                'created_at' => ['date'],
            };
            $request->validate(['value' => $rules]);
        }
        $request->replace($input['action'] === 'update' ? [$input['field'] => $input['value']] : []);
        $request->query->replace([]);

        DB::transaction(function () use ($input, $request, $language, $translation): void {
            foreach (Page::query()->whereKey($input['ids'])->get() as $page) {
                if ($input['action'] === 'delete') {
                    $page->delete();
                    DeletedContentEvent::dispatch(Page::class, $request, $page);
                } elseif ($translation && $input['field'] === 'name') {
                    $existing = DB::table('pages_translations')->where('pages_id', $page->getKey())->where('lang_code', $language)->first();
                    DB::table('pages_translations')->updateOrInsert(['pages_id' => $page->getKey(), 'lang_code' => $language], [
                        'name' => $input['value'], 'description' => $existing?->description ?? $page->description,
                        'content' => $existing?->content ?? $page->content,
                    ]);
                    UpdatedContentEvent::dispatch('form', $request, $page);
                } else {
                    PageForm::createFromModel($page)->setRequest($request)->saving(function ($form) use ($page, $input): void {
                        $page->{$input['field']} = $input['value'];
                        $page->save();
                    });
                }
            }
        });

        return redirect()->to(route('pages.index', $indexQuery))->with('success_msg', trans('core/base::notices.update_success_message'));
    }
}
