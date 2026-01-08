<?php

use App\Http\Controllers\PmsDocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PmsDocumentController::class, 'index'])->name('documents.index');
Route::post('/documents', [PmsDocumentController::class, 'store'])->name('documents.store');
Route::get('/documents/{document}', [PmsDocumentController::class, 'show'])->name('documents.show');
Route::post('/documents/{document}/analyze', [PmsDocumentController::class, 'analyze'])->name('documents.analyze');
Route::delete('/documents/{document}', [PmsDocumentController::class, 'destroy'])->name('documents.destroy');
Route::get('/analyses/{analysis}/download', [PmsDocumentController::class, 'download'])
    ->name('analyses.download');
Route::get('/analyses/{analysis}/download/pdf', [PmsDocumentController::class, 'downloadPdf'])
    ->name('analyses.download.pdf');
Route::post('/analyses/{analysis}/youtrack/description', [PmsDocumentController::class, 'generateYouTrackDescription'])
    ->name('analyses.youtrack.description');
Route::post('/analyses/{analysis}/youtrack', [PmsDocumentController::class, 'createYouTrackIssue'])
    ->name('analyses.youtrack.create');
