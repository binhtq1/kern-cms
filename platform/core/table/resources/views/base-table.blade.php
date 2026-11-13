@php
    /** @var Kern\Table\Abstracts\TableAbstract $table */
    $hasFilters = $table->hasFilters();
    $filtersOpen = $hasFilters && $table->isFiltering();
    $filterPanelId = $table->getOption('id') . '-advanced-filters';
    $filterLabel = trans('core/base::admin.Advanced filters');
    $appliedLabel = trans('core/base::admin.Applied advanced filters: :count');
    $appliedCount = 0;
    if ($filtersOpen) {
        foreach ($table->getFilterColumns() as $index => $column) {
            $operator = $table->request()->input('filter_operators.' . $index);
            $value = $table->request()->input('filter_values.' . $index);
            if ($column !== '' && is_string($operator) && $operator !== '' && is_scalar($value) && trim((string) $value) !== '') {
                $appliedCount++;
            }
        }
    }
@endphp

@once
    @if ($randomHash = setting('datatables_random_hash'))
        <script>window.DATATABLES_RANDOM_HASH = "{{ $randomHash }}";</script>
    @endif
@endonce

{!! apply_filters(BASE_FILTER_TABLE_BEFORE_RENDER, null, $table) !!}

<div class="table-wrapper">
    @if ($hasFilters)
        <x-core::card
            :id="$filterPanelId"
            class="mb-3 table-configuration-wrap"
            @style(['display: none' => !$filtersOpen, 'display: block' => $filtersOpen])
        >
            <x-core::card.body>
                <x-core::button
                    type="button"
                    icon="ti ti-x"
                    :icon-only="true"
                    :aria-label="trans('core/base::admin.Close')"
                    class="btn-show-table-options rounded-pill"
                    size="sm"
                />

                {!! $table->renderFilter() !!}
            </x-core::card.body>
        </x-core::card>
    @endif

    <x-core::card @class([
        'has-actions' => $table->hasBulkActions(),
        'has-filter' => $hasFilters,
    ])>
        <x-core::card.header>
            <div class="w-100 justify-content-between d-flex flex-wrap align-items-center gap-1">
                <div class="d-flex flex-wrap flex-md-nowrap align-items-center gap-1">
                    @if ($table->hasBulkActions())
                        <x-core::dropdown
                            type="button"
                            :label="trans('core/table::table.bulk_actions')"
                            wrapper-class="d-inline-block"
                        >
                            @foreach ($table->getBulkActions() as $action)
                                {!! $action !!}
                            @endforeach
                        </x-core::dropdown>
                    @endif

                    <div class="table-search-input">
                        <label>
                            <input type="search" class="form-control input-sm" placeholder="{{ trans('core/table::table.search') }}" style="min-width: 120px">
                            <button type="button" title="{{ trans('core/table::table.search') }}" class="search-icon"><x-core::icon name="ti ti-search" /></button>
                            <button type="button" title="{{ trans('core/table::table.clear') }}" class="search-reset-icon"><x-core::icon name="ti ti-x" /></button>
                        </label>
                    </div>

                    @if ($hasFilters)
                        <x-core::button
                            type="button"
                            class="btn-icon btn-show-table-options table-filter-toggle"
                            icon="ti ti-filter"
                            :tooltip="$filterLabel"
                            :aria-label="$appliedCount ? $filterLabel . '. ' . str_replace(':count', (string) $appliedCount, $appliedLabel) : $filterLabel"
                            :aria-controls="$filterPanelId"
                            :aria-expanded="$filtersOpen ? 'true' : 'false'"
                            :data-filter-label="$filterLabel"
                            :data-filter-applied-label="$appliedLabel"
                        >
                            <span
                                class="table-filter-count"
                                aria-hidden="true"
                                @if (!$appliedCount) hidden @endif
                            >{{ $appliedCount }}</span>
                        </x-core::button>
                    @endif
                </div>
                <div class="d-flex align-items-center gap-1">
                    @foreach($table->getButtons() as $button)
                        @if (Arr::get($button, 'extend') === 'collection')
                            <div class="dropdown d-inline-block">
                                <button class="btn buttons-collection dropdown-toggle {{ $button['className'] }}" data-bs-toggle="dropdown" tabindex="0" aria-controls="{{ $table->getOption('id') }}" type="button" aria-haspopup="dialog" aria-expanded="false">
                                    {!! $button['text'] !!}
                                </button>
                                <div class="dropdown-menu">
                                    @foreach($button['buttons'] as $buttonItem)
                                        <button class="dropdown-item {{ $buttonItem['className'] }}">
                                            {!! $buttonItem['text'] !!}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <button class="btn {{ $button['className'] }}" tabindex="0" aria-controls="{{ $table->getOption('id') }}" type="button" aria-haspopup="dialog" aria-expanded="false">
                                {!! $button['text'] !!}
                            </button>
                        @endif
                    @endforeach

                    @foreach($table->getDefaultButtons() as $defaultButton)
                        @if (is_string($defaultButton))
                            @switch($defaultButton)
                                @case('reload')
                                    <x-core::button
                                        type="button"
                                        data-bb-toggle="dt-buttons"
                                        data-bb-target=".buttons-reload"
                                        tabindex="0"
                                        aria-controls="{{ $table->getOption('id') }}"
                                        icon="ti ti-refresh"
                                    >
                                        {{ trans('core/base::tables.reload') }}
                                    </x-core::button>
                                    @break
                                @case('export')
                                    <div class="dropdown">
                                        <button title="{{ trans('core/base::tables.export') }}" class="btn buttons-collection dropdown-toggle buttons-export" data-bs-toggle="dropdown" tabindex="0" aria-controls="{{ $table->getOption('id') }}" type="button" aria-haspopup="dialog" aria-expanded="false">
                                            <span>
                                                <x-core::icon name="ti ti-download" /> {{ trans('core/base::tables.export') }}
                                            </span>
                                        </button>
                                        <div class="dropdown-menu">
                                            <button class="dropdown-item" data-bb-toggle="dt-exports" data-bb-target="csv" aria-controls="{{ $table->getOption('id') }}">
                                                <span>
                                                    <x-core::icon name="ti ti-file-type-csv" /> {{ trans('core/base::tables.csv') }}
                                                </span>
                                            </button>
                                            <button class="dropdown-item" data-bb-toggle="dt-exports" data-bb-target="excel" aria-controls="{{ $table->getOption('id') }}">
                                                <span>
                                                    <x-core::icon name="ti ti-file-type-xls" /> {{ trans('core/base::tables.excel') }}
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                    @break
                                @case('visibility')
                                    @break(! Auth::user() instanceof \Kern\ACL\Models\User || ! AdminHelper::isInAdmin(true))

                                    <div class="dropdown" data-bb-toggle="dt-columns-visibility-dropdown" aria-controls="{{ $table->getOption('id') }}">
                                        <button title="{{ trans('core/base::tables.toggle_columns') }}" class="btn buttons-collection dropdown-toggle buttons-visibility" data-bs-toggle="dropdown" data-bs-auto-close="outside" tabindex="0" aria-controls="{{ $table->getOption('id') }}" type="button" aria-haspopup="dialog" aria-expanded="false">
                                            <span>
                                                <x-core::icon name="ti ti-columns-3" />
                                            </span>
                                        </button>

                                        <div class="dropdown-menu p-2">
                                            <x-core::form :url="route('table.update-columns-visibility')" method="PUT" data-bb-toggle="dt-columns-visibility">
                                                <input type="hidden" name="table" value="{{ $table::class }}" />

                                                @foreach($table->getColumns() as $column)
                                                    @php /** @var \Kern\Table\Columns\Column $column */ @endphp

                                                    @continue(! $column instanceof \Kern\Table\Columns\Column || Str::contains($column->className, 'no-column-visibility') || in_array($column->name, $table->getDefaultVisibleColumns(), true))

                                                    {{ Form::onOffCheckbox("columns_visibility[{$column->name}]", $table->determineIfColumnIsVisible($column), ['label' => $column->titleAttr ?: $column->title, 'data-bb-toggle' => 'dt-columns-visibility-toggle']) }}
                                                @endforeach
                                            </x-core::form>
                                        </div>
                                    </div>
                                    @break
                            @endswitch
                        @endif
                    @endforeach
                </div>
            </div>
        </x-core::card.header>

        <div class="card-table">
            <div @class([
                'table-responsive',
                'table-has-actions' => $table->hasBulkActions(),
                'table-has-filter' => $table->hasFilters(),
            ])>
                @section('main-table')
                    {!! $dataTable->table(compact('id', 'class'), false) !!}
                @show
            </div>
        </div>
    </x-core::card>
</div>

{!! apply_filters(BASE_FILTER_TABLE_AFTER_RENDER, null, $table) !!}

@push('footer')
    @include('core/table::modal')

    <script type="application/json" data-kern-table="{{ $id }}">{!! json_encode($table->nativeOptions(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>

    {!! apply_filters(BASE_FILTER_TABLE_FOOTER_RENDER, null, $table) !!}
@endpush
