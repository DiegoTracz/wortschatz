<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\ArticleDetectionController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BookCoverController;
use App\Http\Controllers\BookCoverImageController;
use App\Http\Controllers\BookFileController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrichmentController;
use App\Http\Controllers\HighlightController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\PdfImportController;
use App\Http\Controllers\StudyController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\TranslationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('estudar', [StudyController::class, 'index'])->name('study.index');
    Route::post('estudar/{card}', [StudyController::class, 'review'])->name('study.review');

    Route::get('importar', [ImportController::class, 'create'])->name('import.create');
    Route::post('importar', [ImportController::class, 'store'])->name('import.store');
    Route::post('importar/kindle', [ImportController::class, 'kindle'])->name('import.kindle');

    Route::get('livros', [BookController::class, 'index'])->name('books.index');
    Route::post('livros/importar-pdf', PdfImportController::class)->name('books.import_pdf');
    Route::post('livros/{book}/capa', BookCoverController::class)->name('books.cover');
    Route::get('livros/{book}/capa.jpg', BookCoverImageController::class)->name('books.cover.image');
    Route::get('livros/{book}/ler', [BookController::class, 'read'])->name('books.read');
    Route::get('livros/{book}/arquivo.pdf', BookFileController::class)->name('books.file');
    Route::get('livros/{book}/buscar', [BookController::class, 'search'])->name('books.search');
    Route::post('livros/{book}/destaques', [HighlightController::class, 'store'])->name('highlights.store');
    Route::delete('destaques/{highlight}', [HighlightController::class, 'destroy'])->name('highlights.destroy');
    Route::put('livros/{book}/idioma', [BookController::class, 'updateLanguage'])->name('books.language');
    Route::get('livros/{book}', [BookController::class, 'show'])->name('books.show');
    Route::delete('livros/{book}', [BookController::class, 'destroy'])->name('books.destroy');

    Route::get('sincronizar', [SyncController::class, 'index'])->name('sync.index');
    Route::get('sincronizar/exportar', [SyncController::class, 'export'])->name('sync.export');
    Route::post('sincronizar/importar', [SyncController::class, 'import'])->name('sync.import');

    Route::get('cartoes', [CardController::class, 'index'])->name('cards.index');
    Route::post('cartoes', [CardController::class, 'store'])->name('cards.store');
    Route::put('cartoes/{card}', [CardController::class, 'update'])->name('cards.update');
    Route::delete('cartoes/{card}', [CardController::class, 'destroy'])->name('cards.destroy');

    Route::post('traduzir', TranslationController::class)->name('translate');
    Route::post('artigo', ArticleDetectionController::class)->name('article.detect');
    Route::post('enriquecer', EnrichmentController::class)->name('enrich');

    Route::get('ia', [AiController::class, 'edit'])->name('ai.edit');
    Route::patch('ia', [AiController::class, 'update'])->name('ai.update');

    // Debug temporário do leitor de PDF: registra erros do client no log do Laravel.
    Route::post('debug/pdf-log', function (Request $request) {
        Log::warning('[pdf-reader] '.$request->string('message'), $request->only(['url', 'stack']));

        return response()->json(['logged' => true]);
    })->name('debug.pdf_log');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
