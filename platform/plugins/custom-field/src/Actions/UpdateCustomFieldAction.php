<?php

namespace Kern\CustomField\Actions;

use Illuminate\Support\Facades\Auth;
use Kern\CustomField\Forms\CustomFieldForm;
use Kern\CustomField\Models\FieldGroup;
use Kern\CustomField\Repositories\Interfaces\FieldGroupInterface;

class UpdateCustomFieldAction extends AbstractAction
{
    public function __construct(protected FieldGroupInterface $fieldGroupRepository) {}

    public function run(FieldGroup $fieldGroup, array $data): array
    {
        $form = CustomFieldForm::createFromModel($fieldGroup);

        $result = null;

        $form
            ->saving(function () use ($fieldGroup, $data, &$result): void {
                $data['updated_by'] = Auth::guard()->id();
                $result = $this->fieldGroupRepository->updateFieldGroup($fieldGroup->getKey(), $data);
            });

        if (! $result) {
            return $this->error();
        }

        return $this->success(null, [
            'id' => $fieldGroup->getKey(),
        ]);
    }
}
