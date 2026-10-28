<?php

namespace Kern\DataSynchronize\Http\Controllers;

use Inertia\Inertia;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Base\Supports\Breadcrumb;
use Kern\DataSynchronize\Exporter\Exporter;
use Kern\DataSynchronize\Http\Requests\ExportRequest;
use Throwable;

abstract class ExportController extends BaseController
{
    abstract protected function getExporter(): Exporter;

    protected function allowsSelectColumns(): bool
    {
        return true;
    }

    protected function breadcrumb(): Breadcrumb
    {
        return parent::breadcrumb()
            ->add(trans('core/base::layouts.tools'))
            ->add(trans('packages/data-synchronize::data-synchronize.tools.export_import_data'), route('tools.data-synchronize'));
    }

    public function index()
    {
        $this->pageTitle($this->getExporter()->getHeading());

        $exporter = $this->getExporter();

        return Inertia::render('Operations/Export', [
            'title' => $exporter->getHeading(), 'submit' => $exporter->getUrl(), 'available' => $exporter->hasDataToExport(),
            'columns' => array_map(fn ($column) => ['name' => $column->getName(), 'label' => $column->getLabel(), 'disabled' => $column->isDisabled()], $exporter->getColumns()),
            'counters' => array_map(fn ($counter) => ['label' => $counter->getLabel(), 'value' => $counter->getValue()], $exporter->getCounters()),
        ]);
    }

    public function store(ExportRequest $request)
    {
        if (BaseHelper::hasDemoModeEnabled()) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage(trans('core/base::system.disabled_in_demo_mode'));
        }

        try {
            $exporter = $this
                ->getExporter()
                ->format($request->input('format'));

            if ($this->allowsSelectColumns()) {
                $exporter->acceptedColumns($request->input('columns'));
            }

            return $exporter->export();
        } catch (Throwable $e) {
            BaseHelper::logError($e);

            return $this
                ->httpResponse()
                ->setError()
                ->setCode(400)
                ->setMessage($e->getMessage());
        }
    }
}
