<?php

namespace Kern\Table\Http\Controllers;

use Illuminate\Support\Arr;
use Kern\Table\Http\Requests\FilterRequest;

class TableFilterController extends TableController
{
    public function __invoke(FilterRequest $request): array
    {
        $class = $request->input('class');

        if (! class_exists($class)) {
            return [];
        }

        $key = $request->input('key');

        $table = $this->tableBuilder->create($class);

        $column = Arr::get($table->getFilters(), $key, []);
        $value = $request->input('value');
        $choices = Arr::get($column, 'choices', []);

        if (isset($column['callback'])) {
            $callback = $column['callback'];
            if (is_callable($callback)) {
                $choices = $callback($value);
            } elseif (method_exists($table, $callback)) {
                $choices = call_user_func_array([$table, $callback], [$value]);
            }
        }

        return [
            'type' => $column['type'] ?? 'text',
            'value' => $value,
            'choices' => $choices,
        ];
    }
}
