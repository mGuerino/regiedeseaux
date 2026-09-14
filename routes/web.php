<?php

use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Request as RequestModel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Route pour télécharger un template
Route::get('/admin/templates/{id}/download', function ($id) {
    $template = DocumentTemplate::findOrFail($id);

    // Utiliser le disque 'templates' et la méthode helper du modèle
    if (! $template->fileExists()) {
        abort(404, 'Fichier template introuvable');
    }

    // Télécharger avec un nom user-friendly
    return Storage::disk('templates')->download(
        $template->file_path,
        $template->name.'.docx'
    );
})->name('templates.download')->middleware(['auth']);

// Route pour télécharger un document de demande
Route::get('/documents/{document}/download', function (Document $document) {
    // Vérifier que le fichier existe
    if (! Storage::disk('public')->exists($document->file_name)) {
        abort(404, 'Fichier introuvable');
    }

    // Télécharger le fichier avec le nom d'origine
    return Storage::disk('public')->download(
        $document->file_name,
        $document->document_name
    );
})->name('documents.download')->middleware(['auth']);

// Route d'affichage du PDF de l'attestation dans le navigateur (aperçu de validation)
Route::get('/requests/{request}/attestation-preview', function (RequestModel $request) {
    $document = $request->latestGeneratedDocument('pdf');

    if (! $document || ! Storage::disk('public')->exists($document->file_name)) {
        abort(404, 'Aucun aperçu PDF disponible pour cette attestation');
    }

    // Affichage dans le navigateur plutôt que téléchargement
    return response()->file(Storage::disk('public')->path($document->file_name), [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="'.Document::sanitizeFileName($document->document_name).'"',
    ]);
})->name('requests.attestation.preview')->middleware(['auth']);
