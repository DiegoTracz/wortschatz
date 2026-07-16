import vue from '@vitejs/plugin-vue';
import autoprefixer from 'autoprefixer';
import laravel from 'laravel-vite-plugin';
import { cpSync } from 'node:fs';
import path from 'path';
import tailwindcss from 'tailwindcss';
import { defineConfig, type Plugin } from 'vite';

/**
 * Assets de runtime servidos como estáticos (fora do bundle):
 *
 * - pdf.js v6 decodifica JBIG2/JPX (comuns em PDFs escaneados) via WASM e
 *   carrega os binários de `wasmUrl` — que precisa ser um diretório com os
 *   nomes originais dos arquivos, então o import `?url` do Vite (nome com
 *   hash) não serve.
 * - tesseract.js (OCR do modo recorte do leitor) carrega worker e core WASM
 *   por URL em runtime, pela mesma razão. O modelo de idioma
 *   (public/vendor/ocr/deu.traineddata.gz) é commitado no repo.
 *
 * As pastas copiadas ficam fora do git (.gitignore).
 */
function runtimeAssets(): Plugin {
    return {
        name: 'copy-runtime-assets',
        buildStart() {
            cpSync(
                path.resolve(__dirname, 'node_modules/pdfjs-dist/wasm'),
                path.resolve(__dirname, 'public/vendor/pdfjs-wasm'),
                { recursive: true },
            );
            cpSync(
                path.resolve(__dirname, 'node_modules/tesseract.js/dist/worker.min.js'),
                path.resolve(__dirname, 'public/vendor/ocr-engine/worker.min.js'),
            );
            cpSync(
                path.resolve(__dirname, 'node_modules/tesseract.js-core'),
                path.resolve(__dirname, 'public/vendor/ocr-engine/core'),
                { recursive: true },
            );
        },
    };
}

export default defineConfig({
    plugins: [
        runtimeAssets(),
        laravel({
            input: ['resources/js/app.ts'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
    css: {
        postcss: {
            plugins: [tailwindcss, autoprefixer],
        },
    },
});
