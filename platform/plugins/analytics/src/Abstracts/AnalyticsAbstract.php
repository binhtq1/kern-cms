<?php

namespace Kern\Analytics\Abstracts;

use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;
use Kern\Analytics\Period;

abstract class AnalyticsAbstract
{
    use Macroable;

    public ?string $propertyId = null;

    public ?string $credentials = null;

    public function getPropertyId(): string
    {
        return $this->propertyId;
    }

    abstract public function fetchMostVisitedPages(Period $period, int $maxResults = 20): Collection;

    abstract public function fetchTopReferrers(Period $period, int $maxResults = 20): Collection;

    abstract public function fetchTopBrowsers(Period $period, int $maxResults = 10): Collection;
}
