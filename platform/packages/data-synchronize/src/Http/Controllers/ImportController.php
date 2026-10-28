<?php

namespace Kern\DataSynchronize\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Base\Supports\Breadcrumb;
use Kern\DataSynchronize\Http\Requests\DownloadTemplateRequest;
use Kern\DataSynchronize\Http\Requests\ImportRequest;
use Kern\DataSynchronize\Importer\Importer;

abstract class ImportController extends BaseController
{
    abstract protected function getImporter(): Importer;

    protected function breadcrumb(): Breadcrumb
    {
        return parent::breadcrumb()
            ->add(trans('core/base::layouts.tools'))
            ->add(trans('packages/data-synchronize::data-synchronize.tools.export_import_data'), route('tools.data-synchronize'));
    }

    public function reactFallbackReason(): ?string
    {
        $importer = $this->getImporter();
        if (apply_filters('data_synchronize_importer_view', 'packages/data-synchronize::import') !== 'packages/data-synchronize::import') {
            return 'Blade is required by a custom importer view.';
        }
        foreach (['form_before', 'form_after', 'page_before', 'page_after', 'page_footer'] as $region) {
            if (filled(apply_filters('data_synchronize_import_'.$region, null, $importer))) {
                return 'Blade is required by importer rendering extensions.';
            }
        }

        return null;
    }

    public function index()
    {
        $this->pageTitle($this->getImporter()->getHeading());

        $importer = $this->getImporter();

        return Inertia::render('Operations/Import', [
            'title' => $importer->getHeading(), 'upload' => $importer->getUploadUrl(), 'validate' => $importer->getValidateUrl(), 'submit' => $importer->getImportUrl(),
            'example' => $importer->getDownloadExampleUrl(), 'chunkSize' => max(1, min(Importer::MAX_CHUNK_SIZE, $importer->chunkSize())), 'maxChunkSize' => Importer::MAX_CHUNK_SIZE,
            'examples' => array_map(fn (array $row): array => array_map(fn ($value): string => $value instanceof \BackedEnum ? (string) $value->value : ($value instanceof \UnitEnum ? $value->name : ((is_scalar($value) || $value instanceof \Stringable) ? (string) $value : '')), $row), $importer->getExamples()), 'extensions' => $importer->getFileExtensions(),
            'columns' => array_map(fn ($column) => ['name' => $column->getName(), 'label' => $column->getLabel(), 'description' => $column->getRulesDescription() ?: implode(', ', array_map(fn ($rule): string => (is_scalar($rule) || $rule instanceof \Stringable) ? (string) $rule : class_basename($rule), $column->getRules())), 'required' => in_array('required', $column->getRules(), true)], $importer->getColumns()),
        ]);
    }

    public function validateData(ImportRequest $request)
    {
        try {
            $response = $this->prepareImporter($request)->validate(
                $request->input('file_name'),
                $request->input('offset'),
                $request->input('limit'),
            );

            $message = $response->getFromOffset() <= $response->getNextOffset() ? trans('packages/data-synchronize::data-synchronize.import.validating_message', [
                'from' => number_format($response->getFromOffset()),
                'to' => number_format($response->getNextOffset()),
            ]) : null;

            $dataToResponse = [
                'total' => $response->total,
                'offset' => $response->offset,
                'count' => $response->count,
                'file_name' => $response->fileName,
                'errors' => $response->errors,
            ];

            $httpResponse = $this->httpResponse();

            if ($message) {
                $httpResponse->setMessage($message);
            }

            return $httpResponse
                ->setData($dataToResponse);
        } catch (Exception $e) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage($e->getMessage());
        }
    }

    public function import(ImportRequest $request)
    {
        if (BaseHelper::hasDemoModeEnabled()) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage(trans('core/base::system.disabled_in_demo_mode'));
        }

        try {
            $response = $this->prepareImporter($request)->import(
                $request->input('file_name'),
                $request->input('offset'),
                $request->input('limit'),
            );

            $from = $response->getFromOffset();
            $to = $response->getNextOffset();

            $total = $request->integer('total') + $response->imported;

            if ($from <= $to) {
                $message = $this->getImporter()->getImportingMessage($from, $to);
            } else {
                if ($total > 0) {
                    $message = $this->getImporter()->getDoneMessage($total);
                } else {
                    $message = trans('packages/data-synchronize::data-synchronize.import.no_data_message', [
                        'label' => $this->getImporter()->getLabel(),
                    ]);
                }
            }

            return $this
                ->httpResponse()
                ->setMessage($message)
                ->setData([
                    'offset' => $response->offset,
                    'count' => $response->count,
                    'total' => $total,
                    'failures' => $response->failures,
                ]);
        } catch (Exception $e) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage($e->getMessage());
        }
    }

    public function downloadExample(DownloadTemplateRequest $request)
    {
        if (BaseHelper::hasDemoModeEnabled()) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage(trans('core/base::system.disabled_in_demo_mode'));
        }

        return $this->getImporter()->downloadExample($request->input('format'));
    }

    protected function prepareImporter(Request $request): Importer
    {
        return $this->getImporter();
    }
}
