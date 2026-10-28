<div id="{{ $chart->getElementId() }}" style="width: 100%; min-height: 300px;"></div>

@php
    $chartOptions = json_decode($chart->toJSON(), true);
@endphp

@if ($chart->isUseInlineJs())
    {!! Assets::styleToHtml('apexchart') !!}
    {!! Assets::scriptToHtml('apexchart') !!}
@endif

@php ob_start(); @endphp
<script>
    (() => {
        const initialize = () => {
            const element = document.getElementById({{ Js::from($chart->getElementId()) }})
            if (!element || element.dataset.chartInitialized || !window.ApexCharts) return
            const settings = {{ Js::from($chartOptions) }}
            const kind = {{ Js::from(strtolower($chart->chartType)) }}
            const donut = kind === 'donut'
            const rows = settings.data || []
            const keys = Array.isArray(settings.ykeys) ? settings.ykeys : [settings.ykeys].filter(Boolean)
            const xkey = Array.isArray(settings.xkey) ? settings.xkey[0] : settings.xkey
            const series = donut ? rows.map((row) => Number(row.value || 0)) : keys.map((key, index) => ({
                name: settings.labels?.[index] || key,
                data: rows.map((row) => ({ x: row[xkey], y: row[key] == null ? null : Number(row[key]) })),
            }))
            element.dataset.chartInitialized = 'true'
            new ApexCharts(element, {
                chart: { type: donut ? 'donut' : ['bar', 'area', 'line'].includes(kind) ? kind : 'line', height: 300, toolbar: { show: false } },
                series,
                ...(donut ? { labels: rows.map((row) => String(row.label || '')) } : {}),
                colors: settings.lineColors || settings.barColors || settings.colors,
                dataLabels: { enabled: false },
                grid: { show: settings.grid !== false },
                stroke: { curve: settings.smooth === false ? 'straight' : 'smooth' },
                legend: { show: true },
                yaxis: { min: typeof settings.ymin === 'number' ? settings.ymin : undefined },
            }).render()
        }
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true })
        else initialize()
    })()
</script>
@php $chartScript = ob_get_clean(); @endphp

@if ($chart->isUseInlineJs() || request()->ajax())
    {!! $chartScript !!}
@else
    @push('footer')
        {!! $chartScript !!}
    @endpush
@endif
