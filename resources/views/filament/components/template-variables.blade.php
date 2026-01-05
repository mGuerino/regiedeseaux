@php
    $autoMapped = $template->getAutoMappedVariables();
    $manuallyMapped = $template->getManuallyMappedVariables();
    $unmapped = $template->getUnmappedVariables();
@endphp

<div class="space-y-4">
    {{-- Statistiques --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="rounded-lg bg-success-50 p-4 dark:bg-success-950">
            <div class="text-2xl font-bold text-success-600 dark:text-success-400">
                {{ count($autoMapped) }}
            </div>
            <div class="text-sm text-success-700 dark:text-success-300">
                Variables mappées automatiquement
            </div>
        </div>
        
        <div class="rounded-lg bg-primary-50 p-4 dark:bg-primary-950">
            <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                {{ count($manuallyMapped) }}
            </div>
            <div class="text-sm text-primary-700 dark:text-primary-300">
                Variables mappées manuellement
            </div>
        </div>
        
        <div class="rounded-lg bg-warning-50 p-4 dark:bg-warning-950">
            <div class="text-2xl font-bold text-warning-600 dark:text-warning-400">
                {{ count($unmapped) }}
            </div>
            <div class="text-sm text-warning-700 dark:text-warning-300">
                Variables non mappées
            </div>
        </div>
    </div>

    {{-- Variables mappées automatiquement --}}
    @if(count($autoMapped) > 0)
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-x-3 overflow-hidden px-6 py-4">
                <div class="grid flex-1 gap-y-1">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        ✅ Variables reconnues automatiquement
                    </h3>
                    <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                        Ces variables sont mappées automatiquement avec les données de la demande
                    </p>
                </div>
            </div>
            <div class="fi-section-content p-6">
                <div class="grid grid-cols-2 gap-2">
                    @foreach($autoMapped as $variable)
                        <div class="flex items-center gap-2">
                            <x-filament::icon 
                                icon="heroicon-o-check-circle" 
                                class="h-4 w-4 text-success-600 dark:text-success-400"
                            />
                            <code class="text-sm text-gray-700 dark:text-gray-300">${{ $variable }}</code>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Variables mappées manuellement --}}
    @if(count($manuallyMapped) > 0)
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-x-3 overflow-hidden px-6 py-4">
                <div class="grid flex-1 gap-y-1">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        🔧 Variables mappées manuellement
                    </h3>
                    <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                        Ces variables ont été configurées manuellement
                    </p>
                </div>
            </div>
            <div class="fi-section-content p-6">
                <div class="space-y-2">
                    @foreach($manuallyMapped as $variable)
                        @php
                            $mapping = $template->variable_mappings[$variable] ?? '';
                            $isFixed = str_starts_with($mapping, '__FIXED__:');
                            $value = $isFixed ? substr($mapping, 10) : $mapping;
                        @endphp
                        <div class="flex items-center justify-between rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                            <div class="flex items-center gap-2">
                                <x-filament::icon 
                                    icon="heroicon-o-adjustments-horizontal" 
                                    class="h-4 w-4 text-primary-600 dark:text-primary-400"
                                />
                                <code class="text-sm font-semibold text-gray-700 dark:text-gray-300">${{ $variable }}</code>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-filament::icon 
                                    icon="heroicon-o-arrow-right" 
                                    class="h-4 w-4 text-gray-400"
                                />
                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                    @if($isFixed)
                                        <span class="font-mono text-xs px-2 py-1 rounded bg-gray-200 dark:bg-gray-700">{{ $value }}</span>
                                    @else
                                        <code>{{ $value }}</code>
                                    @endif
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Variables non mappées --}}
    @if(count($unmapped) > 0)
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-x-3 overflow-hidden px-6 py-4">
                <div class="grid flex-1 gap-y-1">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        ⚠️ Variables non mappées
                    </h3>
                    <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                        Ces variables seront remplacées par une chaîne vide lors de la génération
                    </p>
                </div>
            </div>
            <div class="fi-section-content p-6">
                <div class="grid grid-cols-2 gap-2">
                    @foreach($unmapped as $variable)
                        <div class="flex items-center gap-2">
                            <x-filament::icon 
                                icon="heroicon-o-exclamation-triangle" 
                                class="h-4 w-4 text-warning-600 dark:text-warning-400"
                            />
                            <code class="text-sm text-gray-700 dark:text-gray-300">${{ $variable }}</code>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 rounded-lg bg-warning-50 p-4 dark:bg-warning-950">
                    <p class="text-sm text-warning-700 dark:text-warning-300">
                        💡 Utilisez le bouton "Mapper" dans le tableau pour configurer ces variables.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Message si aucune variable --}}
    @if(count($template->variables ?? []) === 0)
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-content p-6">
                <div class="flex items-center gap-4">
                    <x-filament::icon 
                        icon="heroicon-o-information-circle" 
                        class="h-6 w-6 text-gray-400"
                    />
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Aucune variable détectée dans ce template.
                    </p>
                </div>
            </div>
        </div>
    @endif
</div>
