<x-filament-panels::page>
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}
    </x-filament-panels::form>

    <x-filament::section>
        <x-slot name="heading">
            Instructions
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
                    dans la liste des demandes. Vous pouvez les désarchiver individuellement si nécessaire.
                </p>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
