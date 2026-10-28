<?php

namespace Kern\Setting\Http\Requests;

use Illuminate\Validation\Rule;
use Kern\Base\Rules\OnOffRule;
use Kern\Support\Http\Requests\Request;

class DataTableSettingRequest extends Request
{
    public function rules(): array
    {
        return [
            'datatables_default_show_column_visibility' => $onOffRule = new OnOffRule,
            'datatables_default_show_export_button' => $onOffRule,
            'datatables_pagination_type' => ['nullable', Rule::in(['dropdown'])],
        ];
    }
}
