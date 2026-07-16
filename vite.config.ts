import vue from '@vitejs/plugin-vue';
import autoprefixer from 'autoprefixer';
import laravel from 'laravel-vite-plugin';
import { cpSync } from 'node:fs';
import path from 'path';
import tailwindcss from 'tailwindcss';
import { defineConfig, type Plugin } from 'vite';

/**
 * O pdf.js v6 decodifica JBIG2/JPX (comuns em PDFs escaneados) via WASM e
 * carrega os binários em runtime a partir de `wasmUrl` — que precisa ser um
 * diretório com os nomes originais dos arquivos, então o import `?url` do
 * Vite (nome com hash) não serve. Copiamos o diretório para public/ e o
 * Laravel serve como estático; a pasta fica fora do git (.gitignore).
 */
function pdfjsWasm(): Plugin {
    return {
        name: 'copy-pdfjs-wasm',
        buildStart() {
            cpSync(
                path.resolve(__dirname, 'node_modules/pdfjs-dist/wasm'),
                path.resolve(__dirname, 'public/vendor/pdfjs-wasm'),
                { recursive: true },
            );
        },
    };
}

export default defineConfig({
    plugins: [
        pdfjsWasm(),
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
