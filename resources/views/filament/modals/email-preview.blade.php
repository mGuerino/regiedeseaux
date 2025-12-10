<div class="space-y-4">
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Sujet</h3>
        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $subject ?: 'Aucun sujet' }}</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Message</h3>
        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $message ?: 'Aucun message' }}</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">
            Destinataires ({{ count($recipients) }})
        </h3>
        @if(count($recipients) > 0)
            <ul class="text-sm text-gray-700 dark:text-gray-300 space-y-1">
                @foreach($recipients as $recipient)
                    <li class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                        </svg>
                        {{ $recipient }}
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Aucun destinataire sélectionné</p>
        @endif
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">
            Documents joints ({{ $documents->count() }})
        </h3>
        @if($documents->count() > 0)
            <ul class="text-sm text-gray-700 dark:text-gray-300 space-y-1">
                @foreach($documents as $document)
                    <li class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                        </svg>
                        {{ $document->document_name }}
                        <span class="text-xs text-gray-500">({{ ucfirst($document->document_type) }})</span>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Aucun document sélectionné</p>
        @endif
    </div>
</div>
