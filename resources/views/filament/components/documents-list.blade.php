<div class="space-y-3">
    @foreach($documents as $document)
        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
            <div class="flex items-center gap-3 flex-1">
                {{-- Icône selon type de fichier --}}
                <div class="text-2xl flex-shrink-0">
                    @php
                        $extension = strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION));
                        $icon = match($extension) {
                            'pdf' => '📄',
                            'png', 'jpg', 'jpeg', 'gif', 'bmp' => '📷',
                            'xlsx', 'xls' => '📊',
                            'docx', 'doc' => '📝',
                            'zip', 'rar' => '📦',
                            default => '📎'
                        };
                    @endphp
                    {{ $icon }}
                </div>
                
                {{-- Nom et infos du document --}}
                <div class="flex-1 min-w-0">
                    <div class="font-medium text-gray-900 truncate">
                        {{ $document->document_name }}
                    </div>
                    <div class="text-sm text-gray-500 flex items-center gap-2">
                        <span>{{ $document->getFileSizeFormatted() }}</span>
                        @if($document->created_date)
                            <span>•</span>
                            <span>{{ $document->created_date->format('d/m/Y') }}</span>
                        @endif
                    </div>
                </div>
                
                {{-- Badge type de document --}}
                <div class="flex-shrink-0">
                    @if($document->document_type === 'generated')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            Généré
                        </span>
                    @elseif($document->document_type === 'attachment')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                            Pièce jointe
                        </span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                            Document
                        </span>
                    @endif
                </div>
            </div>
            
            {{-- Bouton télécharger --}}
            <a 
                href="{{ route('documents.download', $document->id) }}" 
                class="ml-3 inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition"
                title="Télécharger {{ $document->document_name }}"
            >
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Télécharger
            </a>
        </div>
    @endforeach
</div>
