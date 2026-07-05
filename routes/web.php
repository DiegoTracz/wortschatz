<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\ArticleDetectionController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BookCoverController;
use App\Http\Controllers\BookCoverImageController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrichmentController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\StudyController;
use App\Http\Controllers\TranslationController;
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
    Route::post('livros/{book}/capa', BookCoverController::class)->name('books.cover');
    Route::get('livros/{book}/capa.jpg', BookCoverImageController::class)->name('books.cover.image');
    Route::get('livros/{book}', [BookController::class, 'show'])->name('books.show');
    Route::delete('livros/{book}', [BookController::class, 'destroy'])->name('books.destroy');

    Route::get('cartoes', [CardController::class, 'index'])->name('cards.index');
    Route::post('cartoes', [CardController::class, 'store'])->name('cards.store');
    Route::put('cartoes/{card}', [CardController::class, 'update'])->name('cards.update');
    Route::delete('cartoes/{card}', [CardController::class, 'destroy'])->name('cards.destroy');

    Route::post('traduzir', TranslationController::class)->name('translate');
    Route::post('artigo', ArticleDetectionController::class)->name('article.detect');
    Route::post('enriquecer', EnrichmentController::class)->name('enrich');

    Route::get('ia', [AiController::class, 'edit'])->name('ai.edit');
    Route::patch('ia', [AiController::class, 'update'])->name('ai.update');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
