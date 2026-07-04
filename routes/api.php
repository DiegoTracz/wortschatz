<?php

use App\Http\Controllers\Api\ImportController;
use Illuminate\Support\Facades\Route;

// Endpoints stateless (sem sessão/CSRF) para automação de importação:
// o watcher USB envia o My Clippings.txt e o scraper do Amazon Notebook
// envia entradas já estruturadas. Ver tools/ na raiz do repositório.
Route::middleware('import.token')->group(function () {
    Route::post('importar/arquivo', [ImportController::class, 'storeFile'])->name('api.import.file');
    Route::post('importar/destaques', [ImportController::class, 'storeEntries'])->name('api.import.entries');
});
