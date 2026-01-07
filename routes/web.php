<?php

use App\Models\Document;
use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Route pour télécharger un template
Route::get('/admin/templates/{id}/download', function ($id) {
    $template = DocumentTemplate::findOrFail($id);
    
    if (!Storage::exists($template->file_path)) {
        abort(404, 'Fichier template introuvable');
    }
    
    return Storage::download(
        $template->file_path,
        basename($template->file_path)
    );
})->name('templates.download')->middleware(['auth']);

// Route pour télécharger un document de demande
Route::get('/documents/{document}/download', function (Document $document) {
    // Vérifier que le fichier existe
    if (!Storage::disk('public')->exists($document->file_name)) {
        abort(404, 'Fichier introuvable');
    }
    
    // Télécharger le fichier avec le nom d'origine
    return Storage::disk('public')->download(
        $document->file_name,
        $document->document_name
    );
})->name('documents.download')->middleware(['auth']);

