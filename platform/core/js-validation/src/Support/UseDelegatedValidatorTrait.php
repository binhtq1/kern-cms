<?php

namespace Kern\JsValidation\Support;

trait UseDelegatedValidatorTrait
{
    /**
     * Delegated validator.
     *
     * @var DelegatedValidator
     */
    protected $validator;

    /**
     * Sets delegated Validator instance.
     *
     * @return void
     */
    public function setDelegatedValidator(DelegatedValidator $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Gets current DelegatedValidator instance.
     *
     * @return DelegatedValidator
     */
    public function getDelegatedValidator()
    {
        return $this->validator;
    }
}
