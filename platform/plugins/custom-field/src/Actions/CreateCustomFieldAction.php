<?php

namespace Kern\CustomField\Actions;

use Illuminate\Support\Facades\Auth;
use Kern\CustomField\Forms\CustomFieldForm;
use Kern\CustomField\Repositories\Interfaces\FieldGroupInterface;

class CreateCustomFieldAction extends AbstractAction
{
    public function __construct(protected FieldGroupInterface $fieldGroupRepository) {}

    public function run(array $data): array
    {
        $form = CustomFieldForm::create();

        $result = null;

        $form
            ->saving(function () use ($data, &$result): void {
                $data['created_by'] = Auth::guard()->id();
                $data['updated_by'] = Auth::guard()->id();

                $result = $this->fieldGroupRepository->createFieldGroup($data);
            });

        if (! $result) {
            return $this->error();
        }

        return $this->success(null, [
            'id' => $result,
        ]);
    }
}
