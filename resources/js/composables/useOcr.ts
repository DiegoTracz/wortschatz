import { createWorker, type Worker } from 'tesseract.js';
import { ref } from 'vue';

/**
 * OCR offline com tesseract.js (modo recorte do leitor de PDF): reconhece o
 * texto de um trecho da página em alemão. Todos os assets são servidos
 * localmente — worker e core WASM copiados pelo Vite para
 * /vendor/ocr-engine, modelo de idioma commitado em /vendor/ocr — porque o
 * app desktop roda offline.
 *
 * O worker é um singleton de módulo: o modelo (~2 MB descomprimido) carrega
 * uma vez por sessão e fica quente para os próximos recortes.
 */
let workerPromise: Promise<Worker> | null = null;

function getWorker(): Promise<Worker> {
    workerPromise ??= createWorker('deu', 1, {
        workerPath: '/vendor/ocr-engine/worker.min.js',
        corePath: '/vendor/ocr-engine/core',
        langPath: '/vendor/ocr',
    });

    return workerPromise;
}

export function useOcr() {
    const recognizing = ref(false);

    async function recognize(canvas: HTMLCanvasElement): Promise<string> {
        recognizing.value = true;
        try {
            const worker = await getWorker();
            const { data } = await worker.recognize(canvas);

            // OCR quebra linhas visualmente; para um trecho de estudo o que
            // interessa é o texto corrido.
            return data.text.replace(/\s+/g, ' ').trim();
        } finally {
            recognizing.value = false;
        }
    }

    return { recognizing, recognize };
}
