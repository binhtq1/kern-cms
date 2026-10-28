<?php

namespace Kern\Api\Http\Controllers;

use Inertia\Inertia;
use Kern\Api\Http\Requests\StoreSanctumTokenRequest;
use Kern\Api\Models\PersonalAccessToken;
use Kern\Base\Http\Actions\DeleteResourceAction;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Base\Supports\AdminRegisteredTableData;

class SanctumTokenController extends BaseController
{
    public function __construct()
    {
        $this->breadcrumb()
            ->add(trans('core/setting::setting.title'), route('settings.index'))
            ->add(trans('packages/api::api.settings'), route('api.settings'));
    }

    public function index(): mixed
    {
        $this->pageTitle(trans('packages/api::sanctum-token.name'));

        request()->validate(['search' => ['nullable', 'string', 'max:120']]);
        $search = request()->string('search')->limit(120)->toString();
        $query = PersonalAccessToken::query()->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))->orderByDesc('id');
        if (request()->has('_export')) {
            return app(AdminRegisteredTableData::class)->export('api-tokens', $query, request());
        }
        $tokens = $query->paginate(25)->withQueryString();

        $props = [
            'tableButtons' => app(AdminRegisteredTableData::class)->buttons('api-tokens'),
            'tableVisibility' => app(AdminRegisteredTableData::class)->visibility('api-tokens'),
            'tableUrl' => route('api.sanctum-token.index'),
            'tableFilters' => ['search' => $search],
            'search' => ['url' => route('api.sanctum-token.index'), 'value' => $search],
            'title' => trans('packages/api::sanctum-token.name'),
            'secret' => session()->get('plainTextToken'),
            'columns' => [['key' => 'id', 'label' => 'ID'], ['key' => 'name', 'label' => __('Name')], ['key' => 'abilities', 'label' => __('Abilities')], ['key' => 'last_used_at', 'label' => __('Last used')], ['key' => 'created_at', 'label' => __('Created At')]],
            'rows' => $tokens->map(fn ($token) => ['id' => $token->id, 'values' => ['id' => $token->id, 'created_at' => $token->created_at?->toIso8601String(), 'name' => $token->name, 'abilities' => implode(', ', $token->abilities ?? []), 'last_used_at' => $token->last_used_at?->toIso8601String()], 'actions' => request()->user()->hasPermission('api.sanctum-token.destroy') ? [[
                'label' => __('Revoke'), 'url' => route('api.sanctum-token.destroy', $token->id), 'method' => 'delete', 'confirm' => __('Revoke this API token?'), 'destructive' => true,
            ]] : []])->all(),
            'actions' => request()->user()->hasPermission('api.sanctum-token.create') ? [['label' => __('Create token'), 'url' => route('api.sanctum-token.create')]] : [],
            'pagination' => ['prev' => $tokens->previousPageUrl(), 'next' => $tokens->nextPageUrl(), 'total' => $tokens->total()],
        ];

        return request()->expectsJson() && ! request()->header('X-Inertia')
            ? response()->json(['component' => 'System/Console', 'props' => $props])
            : Inertia::render('System/Console', $props);
    }

    public function create()
    {
        $this->pageTitle(trans('packages/api::sanctum-token.create'));

        return Inertia::render('System/Token', ['submit' => route('api.sanctum-token.store')]);
    }

    public function store(StoreSanctumTokenRequest $request): BaseHttpResponse
    {
        $accessToken = $request->user()->createToken($request->input('name'));

        session()->flash('plainTextToken', $accessToken->plainTextToken);

        return $this
            ->httpResponse()
            ->setNextUrl(route('api.settings'))
            ->withCreatedSuccessMessage();
    }

    public function destroy(PersonalAccessToken $sanctumToken): DeleteResourceAction
    {
        return DeleteResourceAction::make($sanctumToken);
    }
}
