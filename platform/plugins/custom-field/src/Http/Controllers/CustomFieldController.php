<?php

namespace Kern\CustomField\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Kern\Base\Http\Actions\DeleteResourceAction;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Base\Supports\Breadcrumb;
use Kern\CustomField\Actions\CreateCustomFieldAction;
use Kern\CustomField\Actions\ExportCustomFieldsAction;
use Kern\CustomField\Actions\ImportCustomFieldsAction;
use Kern\CustomField\Actions\UpdateCustomFieldAction;
use Kern\CustomField\Http\Requests\CreateFieldGroupRequest;
use Kern\CustomField\Http\Requests\UpdateFieldGroupRequest;
use Kern\CustomField\Models\FieldGroup;
use Kern\CustomField\Support\AdminFieldGroupData;
use Kern\CustomField\Tables\CustomFieldTable;

class CustomFieldController extends BaseController
{
    protected function breadcrumb(): Breadcrumb
    {
        return parent::breadcrumb()
            ->add(trans('plugins/custom-field::base.page_title'), route('custom-fields.index'));
    }

    public function index(CustomFieldTable $dataTable)
    {
        return app(AdminFieldGroupData::class)->index();
    }

    public function create()
    {
        return app(AdminFieldGroupData::class)->form();
    }

    public function store(CreateFieldGroupRequest $request, CreateCustomFieldAction $action)
    {
        if ($request->header('X-Inertia')) {
            app(AdminFieldGroupData::class)->validate($request);
        }

        $result = $action->run($request->input());

        $response = $this->httpResponse()->withCreatedSuccessMessage();

        if ($result['error']) {
            $response->setError()->setMessage(
                Arr::first($result['messages'])
            );
        }

        return $response
            ->setPreviousRoute('custom-fields.index')
            ->setNextRoute('custom-fields.edit', $result['data']['id']);
    }

    public function edit(FieldGroup $customField)
    {
        return app(AdminFieldGroupData::class)->form($customField);
    }

    public function update(FieldGroup $customField, UpdateFieldGroupRequest $request, UpdateCustomFieldAction $action)
    {
        if ($request->header('X-Inertia')) {
            app(AdminFieldGroupData::class)->validate($request, $customField);
        }

        $result = $action->run($customField, $request->input());

        $response = $this->httpResponse();
        $response->withUpdatedSuccessMessage();

        if ($result['error']) {
            $response->setError()->setMessage(
                Arr::first($result['messages'])
            );
        }

        return $response
            ->setPreviousRoute('custom-fields.index');
    }

    public function destroy(FieldGroup $customField)
    {
        $action = DeleteResourceAction::make($customField);
        if (request()->header('X-Inertia')) {
            $result = $action->execute()->toArray();

            return redirect()->route('custom-fields.index')->with($result['error'] ? 'error_msg' : 'success_msg', $result['message']);
        }

        return $action;
    }

    public function getExport(ExportCustomFieldsAction $action, $id = null)
    {
        $ids = [];

        if (! $id) {
            foreach (FieldGroup::query()->get() as $item) {
                $ids[] = $item->id;
            }
        } else {
            $ids[] = $id;
        }

        $json = $action->run($ids)['data'];

        return response()->json($json, 200, [
            'Content-type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="export-field-group.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function postImport(ImportCustomFieldsAction $action, Request $request)
    {
        $request->validate(['json_data' => ['required', 'array', 'max:100']]);
        $json = app(AdminFieldGroupData::class)->importData($request->input('json_data'));
        $result = $action->run($json);
        if ($request->header('X-Inertia')) {
            return redirect()->route('custom-fields.index')->with($result['error'] ? 'error_msg' : 'success_msg', $result['message'] ?? trans('core/base::notices.create_success_message'));
        }

        return $result;
    }

    protected function registerAssets(): void {}
}
