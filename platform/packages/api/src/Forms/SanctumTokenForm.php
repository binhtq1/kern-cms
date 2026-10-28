<?php

namespace Kern\Api\Forms;

use Kern\Api\Http\Requests\StoreSanctumTokenRequest;
use Kern\Api\Models\PersonalAccessToken;
use Kern\Base\Forms\FieldOptions\NameFieldOption;
use Kern\Base\Forms\Fields\TextField;
use Kern\Base\Forms\FormAbstract;

class SanctumTokenForm extends FormAbstract
{
    public function buildForm(): void
    {
        $this
            ->setupModel(new PersonalAccessToken)
            ->setValidatorClass(StoreSanctumTokenRequest::class)
            ->add('name', TextField::class, NameFieldOption::make()->toArray());
    }
}
