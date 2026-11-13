<?php

namespace Kern\Analytics;

use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Kern\Analytics\Abstracts\AnalyticsAbstract;
use Kern\Analytics\Abstracts\AnalyticsContract;
use Kern\Analytics\Exceptions\InvalidConfiguration;
use Kern\Analytics\Traits\DateRangeTrait;
use Kern\Analytics\Traits\DimensionTrait;
use Kern\Analytics\Traits\FilterByDimensionTrait;
use Kern\Analytics\Traits\FilterByMetricTrait;
use Kern\Analytics\Traits\MetricAggregationTrait;
use Kern\Analytics\Traits\MetricTrait;
use Kern\Analytics\Traits\OrderByDimensionTrait;
use Kern\Analytics\Traits\OrderByMetricTrait;
use Kern\Analytics\Traits\ResponseTrait;
use Kern\Analytics\Traits\RowOperationTrait;

class Analytics extends AnalyticsAbstract implements AnalyticsContract
{
    use DateRangeTrait;
    use DimensionTrait;
    use FilterByDimensionTrait;
    use FilterByMetricTrait;
    use MetricAggregationTrait;
    use MetricTrait;
    use OrderByDimensionTrait;
    use OrderByMetricTrait;
    use ResponseTrait;
    use RowOperationTrait;

    public array $orderBys = [];

    public function __construct(int|string $propertyId, string $credentials)
    {
        $this->propertyId = $propertyId;
        $this->credentials = $credentials;
    }

    public function getCredentials(): string
    {
        return $this->credentials;
    }

    public function getClient(): BetaAnalyticsDataClient
    {
        $storage = Storage::disk('local');

        $fileName = 'analytics-credentials.json';

        if (! $storage->exists($fileName) || md5_file($storage->path($fileName)) !== md5($this->getCredentials())) {
            $storage->put('analytics-credentials.json', $this->getCredentials());
        }

        if (! $storage->exists($fileName)) {
            throw new InvalidConfiguration('The credentials file does not exist.');
        }

        return new BetaAnalyticsDataClient([
            'credentials' => $storage->path($fileName),
        ]);
    }

    public function get(): AnalyticsResponse
    {
        $response = $this->getClient()->runReport(new RunReportRequest(array_filter([
            'property' => 'properties/'.$this->getPropertyId(),
            'date_ranges' => $this->dateRanges,
            'metrics' => $this->metrics,
            'dimensions' => $this->dimensions,
            'order_bys' => $this->orderBys,
            'metric_aggregations' => $this->metricAggregations,
            'dimension_filter' => $this->dimensionFilter,
            'metric_filter' => $this->metricFilter,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'keep_empty_rows' => $this->keepEmptyRows,
        ], fn (mixed $value): bool => $value !== null)));

        return $this->formatResponse($response);
    }

    public function fetchMostVisitedPages(Period $period, int $maxResults = 20): Collection
    {
        return $this->dateRange($period)
            ->metrics('screenPageViews')
            ->dimensions(['pageTitle', 'fullPageUrl'])
            ->orderByMetricDesc('screenPageViews')
            ->limit($maxResults)
            ->get()
            ->table;
    }

    public function fetchTopReferrers(Period $period, int $maxResults = 20): Collection
    {
        return $this->dateRange($period)
            ->metrics('screenPageViews')
            ->dimensions('sessionSource')
            ->orderByMetricDesc('screenPageViews')
            ->limit($maxResults)
            ->get()
            ->table;
    }

    public function fetchTopBrowsers(Period $period, int $maxResults = 10): Collection
    {
        return $this->dateRange($period)
            ->metrics('sessions')
            ->dimensions('browser')
            ->orderByMetricDesc('sessions')
            ->get()
            ->table;
    }

    public function performQuery(Period $period, string|array $metrics, string|array $dimensions = []): Collection
    {
        $that = clone $this;

        $query = $that
            ->dateRange($period)
            ->metrics($metrics);

        if ($dimensions) {
            $query = $query->dimensions($dimensions);
        }

        return $query->get()->table;
    }
}
