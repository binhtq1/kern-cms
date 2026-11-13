<?php

namespace Kern\Analytics\Facades;

use Illuminate\Support\Facades\Facade;
use Kern\Analytics\Abstracts\AnalyticsAbstract;

/**
 * @method static string getCredentials()
 * @method static \Google\Analytics\Data\V1beta\BetaAnalyticsDataClient getClient()
 * @method static \Kern\Analytics\AnalyticsResponse get()
 * @method static \Illuminate\Support\Collection fetchMostVisitedPages(\Kern\Analytics\Period $period, int $maxResults = 20)
 * @method static \Illuminate\Support\Collection fetchTopReferrers(\Kern\Analytics\Period $period, int $maxResults = 20)
 * @method static \Illuminate\Support\Collection fetchTopBrowsers(\Kern\Analytics\Period $period, int $maxResults = 10)
 * @method static \Illuminate\Support\Collection performQuery(\Kern\Analytics\Period $period, array|string $metrics, array|string $dimensions = [])
 * @method static string getPropertyId()
 * @method static void macro(string $name, object|callable $macro)
 * @method static void mixin(object $mixin, bool $replace = true)
 * @method static bool hasMacro(string $name)
 * @method static void flushMacros()
 * @method static \Kern\Analytics\Analytics dateRange(\Kern\Analytics\Period $period)
 * @method static \Kern\Analytics\Analytics dateRanges(\Kern\Analytics\Period ...$items)
 * @method static \Kern\Analytics\Analytics metric(string $name)
 * @method static \Kern\Analytics\Analytics metrics(array|string $items)
 * @method static \Kern\Analytics\Analytics dimension(string $name)
 * @method static \Kern\Analytics\Analytics dimensions(array|string $items)
 * @method static \Kern\Analytics\Analytics orderByMetric(string $name, string $order = 'ASC')
 * @method static \Kern\Analytics\Analytics orderByMetricDesc(string $name)
 * @method static \Kern\Analytics\Analytics orderByDimension(string $name, string $order = 'ASC')
 * @method static \Kern\Analytics\Analytics orderByDimensionDesc(string $name)
 * @method static \Kern\Analytics\Analytics metricAggregation(int $value)
 * @method static \Kern\Analytics\Analytics metricAggregations(int ...$items)
 * @method static \Kern\Analytics\Analytics whereDimension(string $name, int $matchType, $value, bool $caseSensitive = false)
 * @method static \Kern\Analytics\Analytics whereDimensionIn(string $name, array $values, bool $caseSensitive = false)
 * @method static \Kern\Analytics\Analytics whereMetric(string $name, int $operation, $value)
 * @method static \Kern\Analytics\Analytics whereMetricBetween(string $name, $from, $to)
 * @method static \Kern\Analytics\Analytics keepEmptyRows(bool $keepEmptyRows = false)
 * @method static \Kern\Analytics\Analytics limit(int|null $limit = null)
 * @method static \Kern\Analytics\Analytics offset(int|null $offset = null)
 *
 * @see \Kern\Analytics\Analytics
 */
class Analytics extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AnalyticsAbstract::class;
    }
}
