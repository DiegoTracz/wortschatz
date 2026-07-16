import { createWorker, type Worker } from 'tesseract.js';
import { ref } from 'vue';

/**
 * OCR offline com tesseract.js (modo recorte do leitor de PDF): reconhece o
 * texto de um trecho da página no idioma do livro. Todos os assets são
 * servidos localmente — worker e core WASM copiados pelo Vite para
 * /vendor/ocr-engine, modelos de idioma commitados em /vendor/ocr — porque o
 * app desktop roda offline.
 *
 * Um worker por idioma, cacheado no módulo: o modelo (~2 MB descomprimido)
 * carrega uma vez por sessão e fica quente para os próximos recortes.
 */
const OCR_LANGS: Record<string, string> = { de: 'deu', en: 'eng' };

const workers = new Map<string, Promise<Worker>>();

function getWorker(lang: string): Promise<Worker> {
    const tessLang = OCR_LANGS[lang] ?? OCR_LANGS.de;

    let promise = workers.get(tessLang);
    if (!promise) {
        promise = createWorker(tessLang, 1, {
            workerPath: '/vendor/ocr-engine/worker.min.js',
            corePath: '/vendor/ocr-engine/core',
            langPath: '/vendor/ocr',
        });
        workers.set(tessLang, promise);
    }

    return promise;
}

export function useOcr() {
    const recognizing = ref(false);

    async function recognize(canvas: HTMLCanvasElement, lang = 'de'): Promise<string> {
        recognizing.value = true;
        try {
            const worker = await getWorker(lang);
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
