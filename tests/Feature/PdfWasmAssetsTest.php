<?php

test('os decodificadores wasm do pdf.js são publicados junto com o build', function () {
    // PDFs escaneados (JBIG2/JPX) dependem destes binários em runtime; o
    // plugin copy-pdfjs-wasm do vite.config os copia para public/ no build.
    expect(file_exists(public_path('vendor/pdfjs-wasm/jbig2.wasm')))->toBeTrue()
        ->and(file_exists(public_path('vendor/pdfjs-wasm/openjpeg.wasm')))->toBeTrue()
        ->and(file_exists(public_path('vendor/pdfjs-wasm/jbig2_nowasm_fallback.js')))->toBeTrue();
});

test('os assets do OCR (modo recorte) são publicados junto com o build', function () {
    // Worker/core copiados pelo vite.config; o modelo de alemão é commitado.
    expect(file_exists(public_path('vendor/ocr-engine/worker.min.js')))->toBeTrue()
        ->and(is_dir(public_path('vendor/ocr-engine/core')))->toBeTrue()
        ->and(file_exists(public_path('vendor/ocr/deu.traineddata.gz')))->toBeTrue()
        ->and(file_exists(public_path('vendor/ocr/eng.traineddata.gz')))->toBeTrue();
});
