@php
    $record = $getRecord();
    $status = $record?->validation_status;
@endphp

@if ($status)
    <div @class([
        'rounded-xl border p-4',
        'border-warning-300 bg-warning-50 dark:border-warning-400/30 dark:bg-warning-400/10' => $record->isAwaitingValidation(),
        'border-danger-300 bg-danger-50 dark:border-danger-400/30 dark:bg-danger-400/10' => $record->isValidationRejected(),
        'border-success-300 bg-success-50 dark:border-success-400/30 dark:bg-success-400/10' => $record->isValidated(),
    ])>
        <div class="flex flex-wrap items-center gap-3">
            <x-filament::badge :color="$status->getColor()" :icon="$status->getIcon()">
                {{ $status->getLabel() }}
            </x-filament::badge>

            <p class="text-sm text-gray-600 dark:text-gray-400">
                @if ($record->isAwaitingValidation())
                    Envoyée en validation le {{ $record->validation_requested_at?->format('d/m/Y à H:i') }}@if ($record->validationRequestedBy) par {{ $record->validationRequestedBy->getFilamentName() }}@endif.
                    Toute modification de la demande annulera cette validation.
                @elseif ($record->isValidated())
                    Validée le {{ $record->validated_at?->format('d/m/Y à H:i') }}@if ($record->validatedBy) par {{ $record->validatedBy->getFilamentName() }}@endif.
                    Toute modification de la demande annulera cette validation.
                @else
                    Corrigez la demande puis renvoyez-la en validation.
                @endif
            </p>
        </div>

        @if ($record->rejection_reason)
            <div class="mt-3 rounded-lg bg-white/70 p-3 dark:bg-gray-900/40">
                <p class="text-sm font-medium text-danger-700 dark:text-danger-400">Motif du refus</p>
                <p class="mt-1 whitespace-pre-line text-sm text-danger-600 dark:text-danger-300">{{ $record->rejection_reason }}</p>
            </div>
        @endif
    </div>
@endif
