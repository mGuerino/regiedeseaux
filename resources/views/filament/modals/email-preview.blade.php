<div>
    <style>
        .preview-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .preview-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.5rem;
        }
        .preview-content {
            font-size: 0.875rem;
            color: #374151;
        }
        .preview-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .preview-list-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0;
        }
        .preview-doc-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.5rem;
            border-radius: 0.375rem;
            margin-bottom: 0.5rem;
        }
        .preview-doc-item:hover {
            background-color: #f9fafb;
        }
        .preview-doc-icon {
            flex-shrink: 0;
            margin-top: 0.125rem;
        }
        .preview-doc-info {
            flex: 1;
            min-width: 0;
        }
        .preview-doc-name {
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .preview-doc-meta {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.125rem;
        }
        .preview-footer {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid #e5e7eb;
            font-size: 0.75rem;
            color: #6b7280;
        }
        .preview-muted {
            font-size: 0.875rem;
            color: #9ca3af;
        }
        .preview-icon-green {
            color: #10b981;
        }
    </style>

    <div class="preview-card">
        <h3 class="preview-title">Sujet</h3>
        <p class="preview-content">{{ $subject ?: 'Aucun sujet' }}</p>
    </div>

    <div class="preview-card">
        <h3 class="preview-title">Message</h3>
        <p class="preview-content" style="white-space: pre-line;">{{ $message ?: 'Aucun message' }}</p>
    </div>

    <div class="preview-card">
        <h3 class="preview-title">
            Destinataires ({{ count($recipients) }})
        </h3>
        @if(count($recipients) > 0)
            <ul class="preview-list">
                @foreach($recipients as $recipient)
                    <li class="preview-list-item">
                        <svg style="width: 1rem; height: 1rem; color: #10b981;" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                        </svg>
                        <span class="preview-content">{{ $recipient }}</span>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="preview-muted">Aucun destinataire sélectionné</p>
        @endif
    </div>

    <div class="preview-card">
        <h3 class="preview-title">
            Documents joints ({{ $documents->count() }})
        </h3>
        @if($documents->count() > 0)
            <div>
                @foreach($documents as $document)
                    <div class="preview-doc-item">
                        <div class="preview-doc-icon">
                            <x-filament::icon 
                                :icon="$document->getFileIconHeroicon()" 
                                style="width: 1.25rem; height: 1.25rem; {{ str_contains($document->getFileIconColor(), 'red') ? 'color: #ef4444;' : (str_contains($document->getFileIconColor(), 'green') ? 'color: #10b981;' : 'color: #3b82f6;') }}"
                            />
                        </div>
                        <div class="preview-doc-info">
                            <div class="preview-doc-name">{{ $document->document_name }}</div>
                            <div class="preview-doc-meta">
                                {{ $document->getFileSizeFormatted() }} • 
                                {{ $document->created_at->format('d/m/Y') }} • 
                                <span style="text-transform: capitalize;">{{ $document->document_type }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="preview-footer">
                Taille totale : {{ collect($documents)->sum(fn($doc) => $doc->getFileSizeBytes()) / (1024 * 1024) < 1 
                    ? round(collect($documents)->sum(fn($doc) => $doc->getFileSizeBytes()) / 1024, 1) . ' Ko'
                    : round(collect($documents)->sum(fn($doc) => $doc->getFileSizeBytes()) / (1024 * 1024), 2) . ' Mo' }}
            </div>
        @else
            <p class="preview-muted">Aucun document sélectionné</p>
        @endif
    </div>
</div>
