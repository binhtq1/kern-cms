<?php

namespace Kern\Table\Http\Controllers;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Kern\Base\Exceptions\DisabledInDemoModeException;
use Kern\Base\Facades\BaseHelper;
use Kern\Table\Abstracts\TableAbstract;
use Kern\Table\Http\Requests\BulkChangeRequest;
use Kern\Table\Http\Requests\SaveBulkChangeRequest;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class TableBulkChangeController extends TableController
{
    public function index(BulkChangeRequest $request): array
    {
        $class = $request->input('class');

        if (! class_exists($class)) {
            return [];
        }

        $object = $this->tableBuilder->create($class);

        $column = Arr::get($object->getAllBulkChanges(), $request->input('key'), []);
        $choices = Arr::get($column, 'choices', []);
        if (isset($column['callback'])) {
            $callback = $column['callback'];
            if (is_callable($callback)) {
                $choices = $callback();
            } elseif (method_exists($object, $callback)) {
                $choices = call_user_func([$object, $callback]);
            }
        }

        return [
            'type' => $column['type'] ?? 'text',
            'label' => $column['title'] ?? '',
            'choices' => $choices,
            'validation' => $column['validate'] ?? [],
        ];
    }

    public function update(SaveBulkChangeRequest $request)
    {
        if (BaseHelper::hasDemoModeEnabled()) {
            throw new DisabledInDemoModeException;
        }
        $ids = $request->input('ids');

        if (empty($ids)) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage(trans('core/table::table.please_select_record'));
        }

        $inputKey = $request->input('key');
        $inputValue = $request->input('value');

        $class = $request->input('class');

        abort_unless(is_subclass_of($class, TableAbstract::class), 403);

        $object = $this->tableBuilder->create($class);
        abort_unless($object->hasAction('edit'), 403);
        $edit = $object->getAction('edit');
        abort_unless($edit->getPermissions() && $edit->currentUserHasAnyPermissions(), 403);

        $columns = $object->getAllBulkChanges();
        $request->validate(['key' => ['required', Rule::in(array_keys($columns))]]);

        if (! empty($columns[$inputKey]['validate'])) {
            $validator = Validator::make($request->input(), [
                'value' => $columns[$inputKey]['validate'],
            ]);

            if ($validator->fails()) {
                return $this
                    ->httpResponse()
                    ->setError()
                    ->setMessage($validator->messages()->first());
            }
        }

        try {
            DB::transaction(function () use ($object, $ids, $inputKey, $inputValue): void {
                $object->validateBulkChangeTargets($ids, $inputKey, $inputValue);
                if (! $object->saveBulkChanges($ids, $inputKey, $inputValue)) {
                    throw ValidationException::withMessages(['key' => __('Unable to save changes.')]);
                }
            });
        } catch (ValidationException|HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage($exception->getMessage());
        }

        return $this
            ->httpResponse()
            ->setMessage(trans('core/table::table.save_bulk_change_success'));
    }
}
