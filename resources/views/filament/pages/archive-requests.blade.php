<x-filament-panels::page>
    <x-filament::tabs wire:model.live="activeTab">
        <x-filament::tabs.item alpine-active="$wire.activeTab === 'archive'" :active="$activeTab === 'archive'" wire:click="$set('activeTab', 'archive')">
            Archiver
        </x-filament::tabs.item>
        
        <x-filament::tabs.item alpine-active="$wire.activeTab === 'unarchive'" :active="$activeTab === 'unarchive'" wire:click="$set('activeTab', 'unarchive')">
            Désarchiver
        </x-filament::tabs.item>
    </x-filament::tabs>

    @if($activeTab === 'archive')
        {{ $this->formArchive }}
        
        <x-filament::section class="mt-6">
            <x-slot name="heading">
                Instructions - Archivage
            </x-slot>

            <x-slot name="description">
                Comment utiliser cette page pour archiver les demandes en lot
            </x-slot>

            <div class="prose dark:prose-invert">
                <ol>
                    <li>Sélectionnez le <strong>type de date</strong> (date de réponse ou date de demande)</li>
                    <li>Choisissez une <strong>date limite</strong> (les demandes avant cette date seront archivées)</li>
                    <li>Filtrez par <strong>statut</strong> (par défaut: uniquement les demandes terminées)</li>
                    <li>Optionnellement, filtrez par <strong>commune</strong></li>
                    <li>Cliquez sur <strong>"Aperçu"</strong> pour voir combien de demandes seront archivées</li>
                    <li>Vérifiez la liste des références affichées</li>
                    <li>Cliquez sur <strong>"Archiver"</strong> pour confirmer l'archivage</li>
                </ol>

                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mt-4">
                    <p class="text-sm text-blue-800 dark:text-blue-200 m-0">
                        💡 <strong>Astuce :</strong> Les demandes archivées restent accessibles via le filtre "Archivées" 
                        dans la liste des demandes. Vous pouvez les désarchiver individuellement ou en lot.
                    </p>
                </div>
            </div>
        </x-filament::section>
    @else
        {{ $this->formUnarchive }}
        
        <x-filament::section class="mt-6">
            <x-slot name="heading">
                Instructions - Désarchivage
            </x-slot>

            <x-slot name="description">
                Comment utiliser cette page pour désarchiver les demandes en lot
            </x-slot>

            <div class="prose dark:prose-invert">
                <ol>
                    <li>Choisissez une <strong>date de début</strong> (les demandes archivées après cette date seront désarchivées)</li>
                    <li>Optionnellement, filtrez par <strong>commune</strong></li>
                    <li>Cliquez sur <strong>"Aperçu"</strong> pour voir combien de demandes seront désarchivées</li>
                    <li>Vérifiez la liste des références affichées</li>
                    <li>Cliquez sur <strong>"Désarchiver"</strong> pour confirmer le désarchivage</li>
                </ol>

                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mt-4">
                    <p class="text-sm text-green-800 dark:text-green-200 m-0">
                        💡 <strong>Astuce :</strong> Le désarchivage rend les demandes à nouveau visibles dans la liste principale 
                        et les statistiques. Les informations d'archivage (date et auteur) seront effacées.
                    </p>
                </div>
            </div>
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
