@props(['auto', 'manual', 'unmapped', 'total', 'record' => null])

@php
    $autoList = $record ? $record->getAutoMappedVariables() : [];
    $manualList = $record ? $record->getManuallyMappedVariables() : [];
    $unmappedList = $record ? $record->getUnmappedVariables() : [];
    $allVariables = $record ? ($record->variables ?? []) : [];
@endphp

<div class="flex flex-wrap gap-2">
    @if($total === 0)
        <span class="fi-badge fi-badge-color-gray text-xs">Aucune variable</span>
    @else
        @if($auto > 0)
            <span 
                class="fi-badge fi-badge-color-success text-xs cursor-help" 
                title="Variables mappées automatiquement&#10;{{ implode(', ', array_map(fn($v) => '${' . $v . '}', $autoList)) }}"
            >
                <svg class="w-3 h-3 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Auto: {{ $auto }}
            </span>
        @endif
        
        @if($manual > 0)
            <span 
                class="fi-badge fi-badge-color-info text-xs cursor-help" 
                title="Variables mappées manuellement&#10;{{ implode(', ', array_map(fn($v) => '${' . $v . '}', $manualList)) }}"
            >
                <svg class="w-3 h-3 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                Manuel: {{ $manual }}
            </span>
        @endif
        
        @if($unmapped > 0)
            <span 
                class="fi-badge fi-badge-color-warning text-xs cursor-help" 
                title="Variables non mappées&#10;{{ implode(', ', array_map(fn($v) => '${' . $v . '}', $unmappedList)) }}"
            >
                <svg class="w-3 h-3 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                Non mappées: {{ $unmapped }}
            </span>
        @endif
        
        <span 
            class="fi-badge fi-badge-color-gray text-xs cursor-help" 
            title="Total de variables&#10;{{ implode(', ', array_map(fn($v) => '${' . $v . '}', $allVariables)) }}"
        >
            Total: {{ $total }}
        </span>
    @endif
</div>
