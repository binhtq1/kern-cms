<?php

namespace Kern\Contact\Http\Controllers;

use Kern\Base\Http\Controllers\BaseController;
use Kern\Base\Supports\AdminContentResource;
use Kern\Base\Supports\Breadcrumb;
use Kern\Contact\Forms\CustomFieldForm;
use Kern\Contact\Http\Requests\CustomFieldRequest;
use Kern\Contact\Models\CustomField;
use Kern\Contact\Tables\CustomFieldTable;

class CustomFieldController extends BaseController
{
    protected function breadcrumb(): Breadcrumb
    {
        return parent::breadcrumb()
            ->add(trans('plugins/contact::contact.name'))
            ->add(trans('plugins/contact::contact.custom_field.name'), route('contacts.custom-fields.index'));
    }

    public function index(CustomFieldTable $table)
    {
        return app(AdminContentResource::class)->index('contact-fields');
    }

    public function create(): mixed
    {
        return app(AdminContentResource::class)->form('contact-fields');
    }

    public function store(CustomFieldRequest $request)
    {
        if ($response = app(AdminContentResource::class)->prepare('contact-fields', $request)) {
            return $response;
        }

        $form = CustomFieldForm::create();
        $form->setRequest($request)->saveOnlyValidatedData();

        /**
         * @var CustomField $model
         */
        $model = $form->getModel();
        $model->saveOptions($request->input('options', []));

        return $this
            ->httpResponse()
            ->setPreviousRoute('contacts.custom-fields.index')
            ->setNextRoute('contacts.custom-fields.edit', $model->getKey())
            ->withCreatedSuccessMessage();
    }

    public function edit(CustomField $customField): mixed
    {
        return app(AdminContentResource::class)->form('contact-fields', $customField);
    }

    public function update(CustomField $customField, CustomFieldRequest $request)
    {
        if ($response = app(AdminContentResource::class)->prepare('contact-fields', $request, $customField)) {
            return $response;
        }

        CustomFieldForm::createFromModel($customField)->setRequest($request)->saveOnlyValidatedData();

        $customField->saveOptions($request->input('options', []));

        return $this
            ->httpResponse()
            ->setPreviousUrl(route('contacts.custom-fields.index'))
            ->withUpdatedSuccessMessage();
    }

    public function destroy(CustomField $customField): mixed
    {
        return app(AdminContentResource::class)->destroy('contact-fields', $customField);
    }
}
