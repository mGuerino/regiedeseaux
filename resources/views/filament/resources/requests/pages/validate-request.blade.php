<x-filament-panels::page>
    @php
        $previewUrl = $this->getPreviewUrl();
        $status = $this->record->validation_status;
    @endphp

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            @if ($previewUrl)
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <iframe
                        src="{{ $previewUrl }}#view=FitH&amp;navpanes=0"
                        title="Aperçu de l'attestation {{ $this->record->reference }}"
                        class="h-[80vh] w-full"
                    ></iframe>
                </div>

                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    L'aperçu ne s'affiche pas ?
                    <a href="{{ $previewUrl }}" target="_blank" class="font-medium text-primary-600 underline dark:text-primary-400">
                        Ouvrir le PDF dans un nouvel onglet
                    </a>
                </p>
            @else
                <x-filament::section>
                    <x-slot name="heading">Aperçu indisponible</x-slot>

                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Aucun PDF n'a été produit pour cette attestation. Renvoyez la demande en validation depuis
                        la fiche pour régénérer le document.
                    </p>
                </x-filament::section>
            @endif
        </div>

        <div class="space-y-6">
            <x-filament::section>
                <x-slot name="heading">Statut de validation</x-slot>

                @if ($status)
                    <x-filament::badge :color="$status->getColor()" :icon="$status->getIcon()" size="lg">
                        {{ $status->getLabel() }}
                    </x-filament::badge>
                @else
                    <x-filament::badge color="gray" size="lg">Non soumise</x-filament::badge>
                @endif

                @if ($this->record->rejection_reason)
                    <div class="mt-4 rounded-lg bg-danger-50 p-3 dark:bg-danger-500/10">
                        <p class="text-sm font-medium text-danger-700 dark:text-danger-400">Motif du refus</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-danger-600 dark:text-danger-300">
                            {{ $this->record->rejection_reason }}
                        </p>
                    </div>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Demande</x-slot>

                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Référence</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">{{ $this->record->reference }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Demandeur</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">
                            {{ trim(($this->record->applicant->first_name ?? '') . ' ' . ($this->record->applicant->last_name ?? '')) ?: 'Non renseigné' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Commune</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">{{ $this->record->municipality->name ?? 'Non renseignée' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Parcelles</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">
                            {{ $this->record->parcels->pluck('ident')->implode(', ') ?: 'Aucune' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Eau potable</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">
                            {{ $this->record->water_status ? 'Raccordable' : 'Non raccordable' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Assainissement</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">
                            {{ $this->record->wastewater_status ? 'Raccordable' : 'Non raccordable' }}
                        </dd>
                    </div>
                </dl>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Signature</x-slot>

                @if ($this->record->signatory)
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $this->record->signatory->name }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $this->record->signatory->title }}</p>

                    @if ($this->record->signatory->hasSignature())
                        <img
                            src="{{ \Illuminate\Support\Facades\Storage::disk(\App\Models\Agent::SIGNATURE_DISK)->url($this->record->signatory->signature_path) }}"
                            alt="Signature de {{ $this->record->signatory->name }}"
                            class="mt-3 max-h-20"
                        >
                    @else
                        <p class="mt-3 text-sm text-warning-600 dark:text-warning-400">
                            Aucune image de signature enregistrée pour ce signataire.
                        </p>
                    @endif
                @else
                    <p class="text-sm text-warning-600 dark:text-warning-400">
                        Aucun signataire n'est renseigné sur cette demande.
                    </p>
                @endif
            </x-filament::section>

            @if ($this->record->validation_requested_at)
                <x-filament::section>
                    <x-slot name="heading">Historique</x-slot>

                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Envoyée en validation</dt>
                            <dd class="font-medium text-gray-950 dark:text-white">
                                {{ $this->record->validation_requested_at->format('d/m/Y à H:i') }}
                                @if ($this->record->validationRequestedBy)
                                    par {{ $this->record->validationRequestedBy->getFilamentName() }}
                                @endif
                            </dd>
                        </div>
                        @if ($this->record->validated_at)
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Validée</dt>
                                <dd class="font-medium text-gray-950 dark:text-white">
                                    {{ $this->record->validated_at->format('d/m/Y à H:i') }}
                                    @if ($this->record->validatedBy)
                                        par {{ $this->record->validatedBy->getFilamentName() }}
                                    @endif
                                </dd>
                            </div>
                        @endif
                    </dl>
                </x-filament::section>
            @endif
        </div>
    </div>
</x-filament-panels::page>
