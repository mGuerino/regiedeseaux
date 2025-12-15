@php
    $columns = $this->getColumns();
    $pollingInterval = $this->getPollingInterval();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $hasHeading = filled($heading);
    $hasDescription = filled($description);
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->merge([
                'wire:poll.' . $pollingInterval => $pollingInterval ? true : null,
            ], escape: false)
            ->class([
                'fi-wi-stats-overview',
            ])
    "
>
    @if ($this->getFilters())
        <div class="pb-8 flex justify-end">
            <select
                id="status-filter"
                wire:model.live="filter"
                class="min-w-[200px] rounded-lg border-gray-300 bg-white px-4 py-2.5 text-base font-medium text-gray-900 shadow-sm transition duration-75 hover:bg-gray-50 focus:border-primary-500 focus:ring-2 focus:ring-inset focus:ring-primary-500 disabled:opacity-70 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600 dark:focus:border-primary-500"
            >
                @foreach ($this->getFilters() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @endif

    {{ $this->content }}
</x-filament-widgets::widget>
