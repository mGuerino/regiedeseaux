<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Introduction --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-content p-6">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0">
                        <x-filament::icon 
                            icon="heroicon-o-information-circle" 
                            class="h-6 w-6 text-primary-600 dark:text-primary-400"
                        />
                    </div>
                    <div class="flex-1">
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                            Gestion des Templates d'Attestation
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Créez et gérez les templates Word utilisés pour générer les attestations. 
                            Les variables sont détectées automatiquement et peuvent être mappées manuellement si nécessaire.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Table des templates --}}
        {{ $this->table }}
    </div>
</x-filament-panels::page>
