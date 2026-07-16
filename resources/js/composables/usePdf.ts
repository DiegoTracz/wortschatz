import type { PageViewport, PDFDocumentProxy, RenderTask } from 'pdfjs-dist';
import { ref, shallowRef } from 'vue';

/**
 * Carrega e renderiza um PDF com PDF.js (canvas + camada de texto selecionável).
 * O worker é empacotado localmente pelo Vite (`?url`), sem CDN — requisito do
 * app desktop (Electron/NativePHP roda offline e bloqueia fetch remoto).
 *
 * As coordenadas dos destaques são guardadas em espaço PDF (independentes de
 * zoom) via `viewport.convertToPdfPoint`; a reexibição usa o inverso
 * `viewport.convertToViewportRectangle`.
 */
export function usePdf() {
    const doc = shallowRef<PDFDocumentProxy | null>(null);
    const numPages = ref(0);
    const loading = ref(true);
    const error = ref<string | null>(null);

    // Render em voo: o pdf.js v6 recusa dois render() no mesmo canvas ao mesmo
    // tempo ("Cannot use the same canvas") — cancelamos o anterior antes de
    // trocar de página/zoom.
    let currentTask: RenderTask | null = null;

    // Import dinâmico: mantém o PDF.js fora do bundle SSR e só o carrega no client.
    // Build "legacy": traz os polyfills de recursos JS recentes (ex.:
    // Uint8Array.prototype.toHex) que faltam no Chromium do Electron.
    async function pdfjs() {
        const lib = await import('pdfjs-dist/legacy/build/pdf.mjs');
        if (!lib.GlobalWorkerOptions.workerSrc) {
            const workerUrl = (await import('pdfjs-dist/legacy/build/pdf.worker.min.mjs?url')).default;
            lib.GlobalWorkerOptions.workerSrc = workerUrl;
        }
        return lib;
    }

    async function load(url: string): Promise<void> {
        loading.value = true;
        error.value = null;
        try {
            const lib = await pdfjs();
            // wasmUrl: decodificadores JBIG2/JPX de PDFs escaneados — os
            // binários são copiados para public/ pelo plugin do vite.config.
            doc.value = await lib.getDocument({ url, wasmUrl: '/vendor/pdfjs-wasm/' }).promise;
            numPages.value = doc.value.numPages;
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Falha ao abrir o PDF.';
        } finally {
            loading.value = false;
        }
    }

    /**
     * Renderiza uma página no canvas e monta a camada de texto no `textLayerDiv`.
     * Retorna o viewport dessa página para conversão de coordenadas.
     */
    async function renderPage(pageNumber: number, canvas: HTMLCanvasElement, textLayerDiv: HTMLElement, scale: number): Promise<PageViewport | null> {
        if (!doc.value) {
            return null;
        }

        // Cancela um render anterior ainda em voo antes de reusar o canvas.
        currentTask?.cancel();

        const lib = await pdfjs();
        const page = await doc.value.getPage(pageNumber);
        const viewport = page.getViewport({ scale });

        // Renderiza na resolução do dispositivo para nitidez em telas HiDPI.
        const dpr = window.devicePixelRatio || 1;
        const context = canvas.getContext('2d');
        if (!context) {
            return null;
        }

        canvas.width = Math.floor(viewport.width * dpr);
        canvas.height = Math.floor(viewport.height * dpr);
        canvas.style.width = `${Math.floor(viewport.width)}px`;
        canvas.style.height = `${Math.floor(viewport.height)}px`;

        const task = page.render({
            canvasContext: context,
            viewport,
            transform: dpr !== 1 ? [dpr, 0, 0, dpr, 0, 0] : undefined,
        });
        currentTask = task;

        try {
            await task.promise;
        } catch (e) {
            // Cancelamento é esperado ao trocar rápido de página — não é erro.
            if (e instanceof Error && e.name === 'RenderingCancelledException') {
                return null;
            }
            throw e;
        } finally {
            if (currentTask === task) {
                currentTask = null;
            }
        }

        // Camada de texto invisível sobre o canvas — habilita a seleção. Uma
        // falha aqui não deve apagar a página já renderizada: degrada para uma
        // página sem seleção em vez de tela em branco.
        try {
            textLayerDiv.replaceChildren();
            textLayerDiv.style.width = `${viewport.width}px`;
            textLayerDiv.style.height = `${viewport.height}px`;
            textLayerDiv.style.setProperty('--total-scale-factor', String(scale));

            const textContent = await page.getTextContent();
            const textLayer = new lib.TextLayer({ textContentSource: textContent, container: textLayerDiv, viewport });
            await textLayer.render();
        } catch (e) {
            console.error('Falha na camada de texto do PDF (seleção indisponível nesta página):', e);
        }

        return viewport;
    }

    /**
     * Renderiza um recorte da página (retângulo em coordenadas PDF) num canvas
     * offscreen em escala alta — insumo para o OCR do modo recorte, que precisa
     * de mais resolução do que a página exibida.
     */
    async function renderRegion(
        pageNumber: number,
        rect: { x0: number; y0: number; x1: number; y1: number },
        targetScale = 3,
    ): Promise<HTMLCanvasElement | null> {
        if (!doc.value) {
            return null;
        }

        const page = await doc.value.getPage(pageNumber);
        const viewport = page.getViewport({ scale: targetScale });

        const full = document.createElement('canvas');
        full.width = Math.floor(viewport.width);
        full.height = Math.floor(viewport.height);
        const context = full.getContext('2d');
        if (!context) {
            return null;
        }

        await page.render({ canvasContext: context, viewport }).promise;

        const [a, b, c, d] = viewport.convertToViewportRectangle([rect.x0, rect.y0, rect.x1, rect.y1]);
        const left = Math.max(0, Math.floor(Math.min(a, c)));
        const top = Math.max(0, Math.floor(Math.min(b, d)));
        const width = Math.min(full.width - left, Math.ceil(Math.abs(c - a)));
        const height = Math.min(full.height - top, Math.ceil(Math.abs(d - b)));
        if (width < 4 || height < 4) {
            return null;
        }

        const crop = document.createElement('canvas');
        crop.width = width;
        crop.height = height;
        crop.getContext('2d')?.drawImage(full, left, top, width, height, 0, 0, width, height);

        return crop;
    }

    function destroy(): void {
        doc.value?.destroy();
        doc.value = null;
    }

    return { numPages, loading, error, load, renderPage, renderRegion, destroy };
}
