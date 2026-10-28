<?php

namespace Kern\Installer\Http\Requests;

use Illuminate\Validation\Rule;
use Kern\Installer\Enums\DatabaseConnectionsEnum;
use Kern\Support\Http\Requests\Request;

class SaveEnvironmentRequest extends Request
{
    public const DATABASE_NAME_MAX_LENGTH = 60;

    public const SQLITE_PATH_MAX_LENGTH = 4096;

    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:120'],
            'app_url' => ['required', 'url'],
            'database_connection' => ['required', 'string', 'max:60', Rule::in(DatabaseConnectionsEnum::values())],
            'database_hostname' => ['required_unless:database_connection,sqlite', 'nullable', 'string', 'max:255'],
            'database_port' => ['required_unless:database_connection,sqlite', 'nullable', 'integer', 'between:1,65535'],
            'database_name' => ['required', 'string', 'max:'.($this->input('database_connection') === 'sqlite'
                ? self::SQLITE_PATH_MAX_LENGTH
                : self::DATABASE_NAME_MAX_LENGTH)],
            'database_username' => ['required_unless:database_connection,sqlite', 'nullable', 'string', 'max:60'],
            'database_password' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'environment_custom.required_if' => trans(
                'packages/installer::installer.environment.wizard.form.name_required'
            ),
        ];
    }
}
