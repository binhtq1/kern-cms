<?php

namespace Kern\Analytics\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Kern\Analytics\Exceptions\InvalidConfiguration;
use Kern\Analytics\Facades\Analytics;
use Kern\Analytics\Http\Requests\AnalyticsRequest;
use Kern\Analytics\Period;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Dashboard\Supports\DashboardWidgetInstance;
use Throwable;

class AnalyticsController extends BaseController
{
    public function getGeneral(AnalyticsRequest $request)
    {
        try {
            $period = $this->getPeriodFromRequest($request);

            $dimensions = $this->getDimensionFromRequest($request);

            $chartStats = $this->getChartStats($period, $dimensions);

            $countryStats = $this->getCountryStats($period, 'countryIsoCode');

            [$sessions, $totalUsers, $screenPageViews, $bounceRate] = $this->getTotalStats($period, $dimensions);

            return $this->httpResponse()->setData(['stats' => [__('Sessions') => $sessions, __('Users') => $totalUsers, __('Page views') => $screenPageViews, __('Bounce rate') => round($bounceRate * 100, 2).'%'], 'chart' => $chartStats->values()->all(), 'countries' => $countryStats]);
        } catch (InvalidConfiguration $exception) {
            return $this->handleInvalidConfigException($exception);
        } catch (Throwable $exception) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage($exception->getMessage());
        }
    }

    protected function getTotalStats(Period $period, string $dimensions): array
    {
        if ($dimensions === 'hour') {
            $dimensions = 'date';
        }

        $sessions = 0;
        $totalUsers = 0;
        $screenPageViews = 0;
        $weightedBounceTotal = 0;

        $totalQuery = Analytics::performQuery($period, ['sessions', 'totalUsers', 'screenPageViews', 'bounceRate'], $dimensions)->toArray();

        foreach ($totalQuery as $item) {
            $sessions += $item['sessions'];
            $totalUsers += $item['totalUsers'];
            $screenPageViews += $item['screenPageViews'];
            $weightedBounceTotal += Arr::get($item, 'bounceRate', 0) * $item['sessions'];
        }

        return [$sessions, $totalUsers, $screenPageViews, $sessions > 0 ? $weightedBounceTotal / $sessions : 0];
    }

    protected function getCountryStats(Period $period, string $dimensions): array
    {
        $countryStats = Analytics::performQuery($period, 'sessions', $dimensions)->toArray();

        foreach ($countryStats as $key => $item) {
            $countryStats[$key] = array_values($item);
        }

        return $countryStats;
    }

    protected function getChartStats(Period $period, string $dimensions): Collection
    {
        $visitorData = [];

        $queryRows = Analytics::performQuery($period, ['totalUsers', 'screenPageViews'], $dimensions)->toArray();

        foreach ($queryRows as $dateRow) {
            $dateRow = array_values($dateRow);

            $axisKey = $dimensions === 'hour' ? (int) $dateRow[0] : $dateRow[0];

            $visitorData[$axisKey] = [
                'axis' => $this->getAxisByDimensions($dateRow[0], $dimensions),
                'visitors' => $dateRow[1],
                'pageViews' => $dateRow[2],
            ];
        }

        if ($dimensions === 'hour') {
            for ($index = 0; $index < 24; $index++) {
                if (! isset($visitorData[$index])) {
                    $visitorData[$index] = [
                        'axis' => $index.'h',
                        'visitors' => 0,
                        'pageViews' => 0,
                    ];
                }
            }
        }

        ksort($visitorData);

        return collect($visitorData);
    }

    protected function getAxisByDimensions(string $dateRow, string $dimensions = 'hour'): string
    {
        return match ($dimensions) {
            'date' => BaseHelper::formatDate($dateRow),
            'yearMonth' => Carbon::createFromFormat('Ym', $dateRow)->format('M Y'),
            default => (int) $dateRow.'h',
        };
    }

    public function getTopVisitPages(AnalyticsRequest $request)
    {
        try {
            $period = $this->getPeriodFromRequest($request);

            $query = Analytics::fetchMostVisitedPages($period, 10);

            $pages = [];

            $schema = $request->getScheme().'://';

            foreach ($query as $item) {
                if (empty($item['fullPageUrl'])) {
                    continue;
                }

                $pageUrl = $item['fullPageUrl'];

                if (! Str::startsWith($pageUrl, $schema)) {
                    $pageUrl = $schema.$pageUrl;
                }

                $pages[] = [
                    'pageTitle' => $item['pageTitle'],
                    'url' => $pageUrl,
                    'pageViews' => $item['screenPageViews'] ?? $item['pageViews'],
                ];
            }

            return $this->httpResponse()->setData([
                'columns' => [['key' => 'index', 'label' => '#'], ['key' => 'pageTitle', 'label' => trans('plugins/analytics::analytics.url')], ['key' => 'pageViews', 'label' => trans('plugins/analytics::analytics.views')]],
                'rows' => collect($pages)->map(fn (array $row, int $index): array => [...$row, 'url' => in_array(strtolower((string) parse_url($row['url'], PHP_URL_SCHEME)), ['http', 'https'], true) ? $row['url'] : null, 'index' => $index + 1])->values()->all(),
                'link_key' => 'pageTitle',
                'target' => '_blank',
                'empty' => ['title' => __('No results found')],
            ]);
        } catch (InvalidConfiguration $exception) {
            return $this->handleInvalidConfigException($exception);
        } catch (Throwable $exception) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage($exception->getMessage());
        }
    }

    public function getTopBrowser(AnalyticsRequest $request)
    {
        try {
            $period = $this->getPeriodFromRequest($request);

            $browsers = Analytics::fetchTopBrowsers($period);

            return $this->httpResponse()->setData([
                'columns' => [['key' => 'index', 'label' => '#'], ['key' => 'browser', 'label' => trans('plugins/analytics::analytics.browser')], ['key' => 'sessions', 'label' => trans('plugins/analytics::analytics.sessions')]],
                'rows' => collect($browsers)->values()->map(fn ($row, int $index): array => [...(array) $row, 'index' => $index + 1])->all(),
                'empty' => ['title' => __('No results found')],
            ]);
        } catch (InvalidConfiguration $exception) {
            return $this->handleInvalidConfigException($exception);
        } catch (Throwable $exception) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage($exception->getMessage());
        }
    }

    public function getTopReferrer(AnalyticsRequest $request)
    {
        try {
            $period = $this->getPeriodFromRequest($request);

            $query = Analytics::fetchTopReferrers($period, 10);

            $referrers = [];

            foreach ($query as $item) {
                $referrers[] = [
                    'url' => $item['sessionSource'] ?? $item['url'],
                    'pageViews' => $item['screenPageViews'] ?? $item['pageViews'],
                ];
            }

            return $this->httpResponse()->setData([
                'columns' => [['key' => 'index', 'label' => '#'], ['key' => 'url', 'label' => trans('plugins/analytics::analytics.url')], ['key' => 'pageViews', 'label' => trans('plugins/analytics::analytics.views')]],
                'rows' => collect($referrers)->map(fn (array $row, int $index): array => [...$row, 'index' => $index + 1])->values()->all(),
                'empty' => ['title' => __('No results found')],
            ]);
        } catch (InvalidConfiguration $exception) {
            return $this->handleInvalidConfigException($exception);
        } catch (Throwable $exception) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage($exception->getMessage());
        }
    }

    protected function getPeriodFromRequest(AnalyticsRequest $request): Period
    {
        $dashboardInstance = new DashboardWidgetInstance;
        $predefinedRangeFound = $dashboardInstance->getFilterRange($request->input('predefined_range'));
        if ($request->input('changed_predefined_range')) {
            $dashboardInstance->saveSettings(
                'widget_analytics_general',
                ['predefined_range' => $predefinedRangeFound['key']]
            );
        }

        $startDate = $predefinedRangeFound['startDate'];
        $endDate = $predefinedRangeFound['endDate'];

        return Period::create($startDate, $endDate);
    }

    protected function getDimensionFromRequest(AnalyticsRequest $request): string
    {
        $predefinedRangeFound = (new DashboardWidgetInstance)->getFilterRange($request->input('predefined_range'));

        return Arr::get([
            'this_week' => 'date',
            'last_7_days' => 'date',
            'this_month' => 'date',
            'last_30_days' => 'date',
            'this_year' => 'yearMonth',
        ], $predefinedRangeFound['key'], 'hour');
    }

    protected function handleInvalidConfigException(InvalidConfiguration $exception): BaseHttpResponse
    {
        $message = $exception->getMessage() ?: trans('plugins/analytics::analytics.wrong_configuration');

        return $this->httpResponse()->setError()->setMessage($message)->setData([
            'configuration' => [
                'title' => trans('plugins/analytics::analytics.not_set_up'),
                'description' => __('Connect your Google Analytics property and service account to view real traffic data.'),
                'url' => request()->user()->hasPermission('analytics.settings') ? route('analytics.settings') : null,
            ],
        ]);
    }
}
