@props(['template'])

@php
    $stats = $template->getVariableStats();
    $autoMapped = $template->getAutoMappedVariables();
    $manuallyMapped = $template->getManuallyMappedVariables();
    $unmapped = $template->getUnmappedVariables();
    $fullMapping = $template->getFullMapping();
@endphp

<div class="space-y-4">
    {{-- En-tête avec statistiques --}}
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            📋 Variables détectées : {{ $stats['total'] }}
        </h3>
        <div class="flex gap-2">
            @if($stats['auto'] > 0)
                <span class="fi-badge fi-badge-color-success text-xs">
                    ✓ Auto: {{ $stats['auto'] }}
                </span>
            @endif
            @if($stats['manual'] > 0)
                <span class="fi-badge fi-badge-color-info text-xs">
                    🔧 Manuel: {{ $stats['manual'] }}
                </span>
            @endif
            @if($stats['unmapped'] > 0)
                <span class="fi-badge fi-badge-color-warning text-xs">
                    ⚠ Non mappées: {{ $stats['unmapped'] }}
                </span>
            @endif
        </div>
    </div>

    {{-- Variables auto-mappées --}}
    @if(count($autoMapped) > 0)
        <div class="rounded-lg border border-success-200 bg-success-50 p-4 dark:border-success-700 dark:bg-success-900/20">
            <h4 class="text-sm font-semibold text-success-800 dark:text-success-300 mb-2">
                ✓ Variables mappées automatiquement ({{ count($autoMapped) }})
            </h4>
            <div class="grid grid-cols-2 gap-2">
                @foreach($autoMapped as $variable)
                    <div class="flex items-center gap-2 text-xs">
                        <code class="bg-white dark:bg-gray-800 px-2 py-1 rounded text-success-700 dark:text-success-400">
                            ${{ '{' . $variable . '}' }}
                        </code>
                        @if(isset($fullMapping[$variable]))
                            <span class="text-gray-500 dark:text-gray-400">→</span>
                            <span class="text-gray-600 dark:text-gray-300 truncate" title="{{ $fullMapping[$variable] }}">
                                {{ $fullMapping[$variable] }}
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Variables mappées manuellement --}}
    @if(count($manuallyMapped) > 0)
        <div class="rounded-lg border border-info-200 bg-info-50 p-4 dark:border-info-700 dark:bg-info-900/20">
            <h4 class="text-sm font-semibold text-info-800 dark:text-info-300 mb-2">
                🔧 Variables mappées manuellement ({{ count($manuallyMapped) }})
            </h4>
            <div class="grid grid-cols-2 gap-2">
                @foreach($manuallyMapped as $variable)
                    <div class="flex items-center gap-2 text-xs">
                        <code class="bg-white dark:bg-gray-800 px-2 py-1 rounded text-info-700 dark:text-info-400">
                            ${{ '{' . $variable . '}' }}
                        </code>
                        @if(isset($fullMapping[$variable]))
                            <span class="text-gray-500 dark:text-gray-400">→</span>
                            <span class="text-gray-600 dark:text-gray-300 truncate" title="{{ $fullMapping[$variable] }}">
                                @if(str_starts_with($fullMapping[$variable], '__FIXED__:'))
                                    "{{ substr($fullMapping[$variable], 10) }}"
                                @else
                                    {{ $fullMapping[$variable] }}
                                @endif
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Variables non mappées --}}
    @if(count($unmapped) > 0)
        <div class="rounded-lg border border-warning-200 bg-warning-50 p-4 dark:border-warning-700 dark:bg-warning-900/20">
            <h4 class="text-sm font-semibold text-warning-800 dark:text-warning-300 mb-2">
                ⚠ Variables non mappées ({{ count($unmapped) }})
            </h4>
            <p class="text-xs text-warning-700 dark:text-warning-400 mb-2">
                Ces variables ne sont pas reconnues automatiquement. Mappez-les ci-dessous pour qu'elles soient remplies lors de la génération.
            </p>
            <div class="grid grid-cols-2 gap-2">
                @foreach($unmapped as $variable)
                    <code class="bg-white dark:bg-gray-800 px-2 py-1 rounded text-warning-700 dark:text-warning-400 text-xs">
                        ${{ '{' . $variable . '}' }}
                    </code>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Message si toutes les variables sont mappées --}}
    @if(count($unmapped) === 0 && $stats['total'] > 0)
        <div class="rounded-lg border border-success-200 bg-success-50 p-3 dark:border-success-700 dark:bg-success-900/20">
            <p class="text-sm text-success-800 dark:text-success-300 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Toutes les variables sont correctement mappées ! Ce template est prêt à l'emploi.
            </p>
        </div>
    @endif
</div>
