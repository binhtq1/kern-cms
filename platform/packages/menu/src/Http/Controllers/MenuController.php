<?php

namespace Kern\Menu\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Kern\Base\Events\CreatedContentEvent;
use Kern\Base\Http\Actions\DeleteResourceAction;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Base\Supports\AdminRegisteredTableData;
use Kern\Base\Supports\Breadcrumb;
use Kern\Language\Models\LanguageMeta;
use Kern\Menu\Events\RenderingMenuOptions;
use Kern\Menu\Facades\Menu;
use Kern\Menu\Forms\MenuForm;
use Kern\Menu\Forms\MenuNodeForm;
use Kern\Menu\Http\Requests\MenuNodeRequest;
use Kern\Menu\Http\Requests\MenuRequest;
use Kern\Menu\Models\Menu as MenuModel;
use Kern\Menu\Models\MenuLocation;
use Kern\Menu\Models\MenuNode;
use Kern\Menu\Repositories\Eloquent\MenuRepository;
use Kern\Menu\Supports\AdminMenuData;
use Kern\Menu\Supports\AdminMenuLanguage;
use Kern\Menu\Tables\MenuTable;
use Kern\Support\Services\Cache\Cache;

class MenuController extends BaseController
{
    protected Cache $cache;

    public function __construct()
    {
        $this->cache = Cache::make(MenuRepository::class);
    }

    protected function breadcrumb(): Breadcrumb
    {
        return parent::breadcrumb()
            ->add(trans('packages/theme::theme.appearance'))
            ->add(trans('packages/menu::menu.name'), route('menus.index'));
    }

    public function index(MenuTable $table)
    {
        return app(AdminMenuData::class)->index();
    }

    public function references(): JsonResponse
    {
        abort_unless(request()->user()->hasPermission('menus.create') || request()->user()->hasPermission('menus.edit'), 403);
        RenderingMenuOptions::dispatch();
        app(AdminMenuLanguage::class)->props();

        return response()->json(app(AdminMenuData::class)->references());
    }

    public function create()
    {
        RenderingMenuOptions::dispatch();

        return app(AdminMenuData::class)->form();
    }

    public function store(MenuRequest $request)
    {
        $tree = $this->validatedTree($request);
        $form = DB::transaction(function () use ($request, $tree): MenuForm {
            $localization = app(AdminMenuLanguage::class);
            if ($source = $localization->source($request)) {
                $source->newQuery()->whereKey($source->getKey())->lockForUpdate()->firstOrFail();
                if (! LanguageMeta::query()->where('reference_type', MenuModel::class)->where('reference_id', $source->getKey())->exists()) {
                    LanguageMeta::saveMetaData($source);
                }
            }
            $localization->validate($request);
            $form = MenuForm::create()->setRequest($request);

            $form
                ->saving(function (MenuForm $form) use ($request): void {
                    $form
                        ->getModel()
                        ->fill($request->safe()->only(['name', 'status']))
                        ->save();

                    /**
                     * @var MenuModel $menu
                     */
                    $menu = $form->getModel();

                    $this->cache->flush();
                    $this->saveMenuLocations($menu, $request);
                });
            $this->saveTree($request, $tree, $form->getModel()->getKey());

            return $form;
        });

        return $this
            ->httpResponse()
            ->setPreviousUrl(route('menus.index', app(AdminMenuLanguage::class)->routeParameters()))
            ->setNextUrl(route('menus.edit', ['menu' => $form->getModel()->getKey(), ...app(AdminMenuLanguage::class)->routeParameters()]))
            ->withCreatedSuccessMessage();
    }

    protected function saveMenuLocations(MenuModel $menu, Request $request): bool
    {
        $locations = $request->input('locations', []);

        MenuLocation::query()
            ->where('menu_id', $menu->getKey())
            ->whereNotIn('location', $locations)
            ->each(fn (MenuLocation $location) => $location->delete());

        foreach ($locations as $location) {
            $menuLocation = MenuLocation::query()->firstOrCreate([
                'menu_id' => $menu->getKey(),
                'location' => $location,
            ]);

            event(new CreatedContentEvent(MENU_LOCATION_MODULE_SCREEN_NAME, $request, $menuLocation));
        }

        return true;
    }

    public function edit(int|string $id)
    {
        RenderingMenuOptions::dispatch();

        return app(AdminMenuData::class)->form(MenuModel::query()->findOrFail($id));
    }

    public function update(MenuModel $menu, MenuRequest $request)
    {
        DB::transaction(function () use ($menu, $request): void {
            $menu->newQuery()->whereKey($menu->getKey())->lockForUpdate()->firstOrFail();
            app(AdminMenuLanguage::class)->validate($request);
            $tree = $this->validatedTree($request, $menu);
            $deletedNodes = preg_split('/\s+/', trim((string) $request->input('deleted_nodes', '')), flags: PREG_SPLIT_NO_EMPTY);
            $owned = $menu->menuNodes()->pluck('id')->map(fn ($id): string => (string) $id)->all();
            if (array_diff($deletedNodes, $owned)) {
                throw ValidationException::withMessages(['deleted_nodes' => __('A menu item belongs to another menu.')]);
            }
            MenuForm::createFromModel($menu)
                ->setRequest($request)
                ->saving(function (MenuForm $form) use ($request): void {
                    $form
                        ->getModel()
                        ->fill($request->safe()->only(['name', 'status']))
                        ->save();

                    /**
                     * @var MenuModel $menu
                     */
                    $menu = $form->getModel();

                    $this->saveMenuLocations($menu, $request);
                });

            if ($deletedNodes) {
                $menu->menuNodes()->whereIn('id', $deletedNodes)->delete();
            }

            $menuNodes = $this->saveTree($request, $tree, $menu->getKey());

            $request->merge(['menu_nodes' => json_encode($menuNodes)]);
        });

        $this->cache->flush();

        return $this
            ->httpResponse()
            ->setPreviousUrl(route('menus.index', app(AdminMenuLanguage::class)->routeParameters()))
            ->withUpdatedSuccessMessage();
    }

    public function destroy(MenuModel $menu)
    {
        return DeleteResourceAction::make($menu);
    }

    public function getNode(MenuNodeRequest $request)
    {
        MenuModel::query()->findOrFail($request->input('data.menu_id'));
        $data = app(AdminMenuData::class)->validateItem($request->input('data', []));
        $data['menu_id'] = $request->input('data.menu_id');
        $request->merge(['data' => $data]);
        $form = MenuNodeForm::create();

        $form->saving(function (MenuNodeForm $form) use ($request): void {
            /**
             * @var MenuNode $row
             */
            $row = $form->getModel();
            $row->fill($data = $request->input('data', []));
            $row = Menu::getReferenceMenuNode($data, $row);
            $row->save();
        });

        return $this
            ->httpResponse()
            ->setData([
                'node' => $form->getModel()->toArray(),
            ])
            ->withCreatedSuccessMessage();
    }

    protected function validatedTree(MenuRequest $request, ?MenuModel $menu = null): array
    {
        $tree = json_decode($request->input('menu_nodes') ?: '[]', true);
        if (! is_array($tree) || ! array_is_list($tree)) {
            throw ValidationException::withMessages(['menu_nodes' => __('Invalid menu structure.')]);
        }

        return app(AdminMenuData::class)->validateTree($tree, $menu);
    }

    protected function saveTree(MenuRequest $request, array $tree, int|string $menuId): array
    {
        $requests = $request === request() ? [$request] : [$request, request()];
        $snapshots = array_map(fn (Request $current): array => [
            'request' => $current,
            'input' => $current->isJson() ? $current->json() : $current->request,
            'body' => ($current->isJson() ? $current->json() : $current->request)->all(),
            'query' => $current->query->all(),
        ], $requests);

        foreach ($snapshots as $snapshot) {
            $snapshot['input']->remove('ref_from');
            $snapshot['request']->query->remove('ref_from');
            if ($request->filled('language')) {
                $snapshot['request']->merge(['language' => $request->input('language')]);
            }
        }

        try {
            return Menu::recursiveSaveMenu($tree, $menuId, 0);
        } finally {
            foreach ($snapshots as $snapshot) {
                $snapshot['input']->replace($snapshot['body']);
                $snapshot['request']->query->replace($snapshot['query']);
            }
        }
    }

    public function bulk(Request $request): RedirectResponse
    {
        $localization = app(AdminMenuLanguage::class);
        $locale = $localization->routeParameters();
        $indexQuery = app(AdminRegisteredTableData::class)->indexQuery($request, $locale['ref_lang'] ?? null);
        $input = $request->validate([
            'ids' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', Rule::exists(MenuModel::class, 'id')],
        ]);
        if ($localization->indexQuery(MenuModel::query())->whereKey($input['ids'])->count() !== count($input['ids'])) {
            throw ValidationException::withMessages(['ids' => 'The selected menus do not belong to this language.']);
        }
        $response = app(AdminRegisteredTableData::class)->bulk($request, 'menus', MenuModel::class, [
            'edit' => $request->user()->hasPermission('menus.edit'),
            'delete' => $request->user()->hasPermission('menus.destroy'),
        ]);

        return $response->setTargetUrl(route('menus.index', $indexQuery));
    }
}
