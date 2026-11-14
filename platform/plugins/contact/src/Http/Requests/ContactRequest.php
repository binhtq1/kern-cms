<?php

namespace Kern\Contact\Http\Requests;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Rules\EmailRule;
use Kern\Base\Rules\OnOffRule;
use Kern\Base\Rules\PhoneNumberRule;
use Kern\Contact\Enums\CustomFieldType;
use Kern\Contact\Models\CustomField;
use Kern\Support\Http\Requests\Request;
use Throwable;

class ContactRequest extends Request
{
    protected $errorBag = 'contact';

    protected array $mandatoryFields;

    protected array $displayFields;

    protected Collection $customFields;

    public function mandatoryFields(array $fields): static
    {
        $this->mandatoryFields = $fields;

        return $this;
    }

    public function displayFields(array $fields): static
    {
        $this->displayFields = $fields;

        return $this;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:40'],
            'email' => ['nullable', new EmailRule, 'max:80'],
            'content' => ['required', 'string', 'max:10000'],
            'phone' => ['nullable', new PhoneNumberRule],
            'address' => ['nullable', 'string', 'max:500'],
            'subject' => ['nullable', 'string', 'max:500'],
            'agree_terms_and_policy' => ['sometimes', 'accepted:1'],
        ];

        try {
            $rules = $this->applyRules(
                $rules,
                $this->request->getString('display_fields'),
                $this->request->getString('required_fields')
            );
        } catch (Throwable $exception) {
            BaseHelper::logError($exception);
        }

        $customFields = $this->getCustomFields();

        if ($customFields->isNotEmpty()) {
            $rules['contact_custom_fields'] = [$customFields->contains('required', true) ? 'required' : 'nullable', 'array:'.implode(',', $customFields->modelKeys())];
        } else {
            $rules['contact_custom_fields'] = ['prohibited'];
        }

        foreach ($customFields as $customField) {
            $customFieldRules = [$customField->required ? 'required' : 'nullable'];

            $rules["contact_custom_fields.$customField->id"] = match ($customField->type->getValue()) {
                CustomFieldType::TEXT => [...$customFieldRules, 'string', 'max:255'],
                CustomFieldType::DROPDOWN, CustomFieldType::RADIO => [...$customFieldRules, 'string', 'max:255', Rule::in($customField->options->map(fn ($option): string => $option->value)->all())],
                CustomFieldType::TEXTAREA => [...$customFieldRules, 'string', 'max:1000'],
                CustomFieldType::NUMBER => [...$customFieldRules, 'numeric'],
                CustomFieldType::CHECKBOX => [$customField->required ? 'accepted' : 'nullable', new OnOffRule],
                CustomFieldType::DATE => [...$customFieldRules, 'date_format:Y-m-d'],
                CustomFieldType::DATETIME => [...$customFieldRules, 'date_format:Y-m-d\\TH:i,Y-m-d\\TH:i:s'],
                CustomFieldType::TIME => [...$customFieldRules, 'date_format:H:i,H:i:s'],
                default => $customFieldRules,
            };
        }

        return apply_filters('contact_request_rules', $rules, $this);
    }

    public function attributes(): array
    {
        $attributes = [
            'name' => __('Name'),
            'email' => __('Email'),
            'phone' => __('Phone'),
            'content' => __('Content'),
        ];

        $customFields = $this->getCustomFields();

        foreach ($customFields as $customField) {
            $attributes["contact_custom_fields.$customField->id"] = $customField->name;
        }

        return $attributes;
    }

    protected function getCustomFields(): Collection
    {
        if (isset($this->customFields)) {
            return $this->customFields;
        }

        return $this->customFields = CustomField::query()->wherePublished()->with('options')->get();
    }

    protected function filtersByDisplayFields(array $rules): array
    {
        if (empty($this->displayFields)) {
            $this->displayFields = ['email', 'address', 'phone', 'subject'];
        }

        $displayFields = [...$this->displayFields, ...$this->alwaysMandatoryFields()];

        foreach ($rules as $key => $rule) {
            if (! in_array($key, $displayFields)) {
                unset($rules[$key]);
            }
        }

        return $rules;
    }

    protected function applyMandatoryFields(array $rules): array
    {
        if (empty($this->mandatoryFields)) {
            $this->mandatoryFields = ['email'];
        }

        $mandatoryFields = [...$this->mandatoryFields, ...$this->alwaysMandatoryFields()];

        foreach ($rules as $key => $rule) {
            if (is_array($rule)) {
                foreach ($rule as $ruleKey => $ruleValue) {
                    if (in_array($ruleValue, ['required', 'nullable'])) {
                        $rule[$ruleKey] = in_array($key, $mandatoryFields) ? 'required' : 'nullable';
                    }

                    $rules[$key] = $rule;
                }
            }
        }

        foreach ($mandatoryFields as $mandatoryField) {
            if (! in_array($mandatoryField, array_keys($rules))
                && in_array($mandatoryField, $this->displayFields)) {
                $rules[$mandatoryField] = 'required';
            }
        }

        return $rules;
    }

    protected function alwaysMandatoryFields(): array
    {
        return ['name', 'content'];
    }

    public function applyRules(array $rules, ?string $displayFields, ?string $mandatoryFields): array
    {
        $this->mandatoryFields(array_filter(explode(',', (string) $mandatoryFields)));
        $this->displayFields(array_filter(explode(',', (string) $displayFields)));

        $rules = $this->filtersByDisplayFields($rules);

        return $this->applyMandatoryFields($rules);
    }
}
