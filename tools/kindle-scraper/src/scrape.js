import { existsSync } from 'node:fs';

import { chromium } from 'playwright';

import { APP_URL, IMPORT_TOKEN, NOTEBOOK_URL, STORAGE_STATE_PATH } from './config.js';
import { selectors } from './selectors.js';

const dryRun = process.argv.includes('--dry-run');

if (!existsSync(STORAGE_STATE_PATH)) {
    console.error('Sessão da Amazon não encontrada. Rode primeiro: npm run login');
    process.exit(1);
}

if (!dryRun && IMPORT_TOKEN === '') {
    console.error('IMPORT_TOKEN vazio no .env — gere um com: php artisan clippings:token');
    process.exit(1);
}

const normalizeSpace = (text) => text.replace(/\s+/g, ' ').trim();

const cleanAuthor = (text) => {
    const cleaned = normalizeSpace(text).replace(/^(por|by|von|de):?\s*/i, '');

    return cleaned || null;
};

// A paginação carrega ao rolar; segue até o token de próxima página esvaziar
// ou nenhuma linha nova aparecer (proteção contra loop infinito).
async function loadAllAnnotations(page) {
    for (let i = 0; i < 200; i++) {
        const token = await page
            .locator(selectors.nextPageToken)
            .first()
            .inputValue()
            .catch(() => '');

        if (!token) {
            return;
        }

        const before = await page.locator(selectors.annotationRow).count();
        await page.locator(selectors.annotationRow).last().scrollIntoViewIfNeeded();
        await page
            .waitForFunction(
                ({ sel, before }) => document.querySelectorAll(sel).length > before,
                { sel: selectors.annotationRow, before },
                { timeout: 10_000 },
            )
            .catch(() => {});

        if ((await page.locator(selectors.annotationRow).count()) === before) {
            return;
        }
    }
}

async function waitForBookLoaded(page, asin) {
    await page
        .waitForFunction(
            ({ sel, asin }) => document.querySelector(sel)?.value === asin,
            { sel: selectors.annotationsAsin, asin },
            { timeout: 30_000 },
        )
        .catch(() => page.waitForTimeout(3000)); // fallback: melhor extrair algo do que abortar
}

async function sendEntries(entries) {
    const totals = { imported: 0, skipped: 0 };

    // O endpoint aceita até 5000 entradas por requisição; envia em lotes.
    for (let i = 0; i < entries.length; i += 1000) {
        const response = await fetch(`${APP_URL}/api/importar/destaques`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                Authorization: `Bearer ${IMPORT_TOKEN}`,
            },
            body: JSON.stringify({ entries: entries.slice(i, i + 1000) }),
        });

        if (!response.ok) {
            throw new Error(`Falha no envio (HTTP ${response.status}): ${await response.text()}`);
        }

        const result = await response.json();
        totals.imported += result.imported;
        totals.skipped += result.skipped;
    }

    return totals;
}

const browser = await chromium.launch();

try {
    const context = await browser.newContext({ storageState: STORAGE_STATE_PATH });
    const page = await context.newPage();

    await page.goto(NOTEBOOK_URL, { waitUntil: 'domcontentloaded' });

    if (await page.locator(selectors.signInForm).first().isVisible().catch(() => false)) {
        throw new Error('Sessão expirada: a Amazon pediu login de novo. Rode: npm run login');
    }

    try {
        await page.waitForSelector(selectors.library, { timeout: 30_000 });
    } catch {
        throw new Error(
            'A biblioteca de destaques não carregou — sessão expirada ou a Amazon mudou o layout ' +
                '(confira NOTEBOOK_URL e os seletores em src/selectors.js). Tente: npm run login',
        );
    }

    const bookCount = await page.locator(selectors.book).count();
    console.error(`${bookCount} livro(s) na biblioteca de destaques.`);

    const entries = [];

    for (let i = 0; i < bookCount; i++) {
        const book = page.locator(selectors.book).nth(i);
        const asin = await book.getAttribute('id');
        const title = normalizeSpace(await book.locator(selectors.bookTitle).innerText());
        const author = cleanAuthor(
            await book
                .locator(selectors.bookAuthor)
                .innerText()
                .catch(() => ''),
        );

        await book.click();
        await waitForBookLoaded(page, asin);
        await loadAllAnnotations(page);

        let count = 0;

        for (const row of await page.locator(selectors.annotationRow).all()) {
            const location =
                normalizeSpace(
                    await row
                        .locator(selectors.annotationLocation)
                        .first()
                        .inputValue()
                        .catch(() => ''),
                ) || null;
            const highlight = normalizeSpace(
                await row
                    .locator(selectors.highlightText)
                    .first()
                    .innerText()
                    .catch(() => ''),
            );
            const note = normalizeSpace(
                await row
                    .locator(selectors.noteText)
                    .first()
                    .innerText()
                    .catch(() => ''),
            );

            // Uma nota anexada a um destaque vira uma entrada própria, como o
            // Kindle faz no My Clippings.txt.
            if (highlight) {
                entries.push({ title, author, type: 'highlight', content: highlight, location });
                count++;
            }

            if (note) {
                entries.push({ title, author, type: 'note', content: note, location });
                count++;
            }
        }

        console.error(`  ${title}: ${count} entrada(s)`);
    }

    if (dryRun) {
        console.log(JSON.stringify({ entries }, null, 2));
    } else if (entries.length === 0) {
        console.log('Nenhum destaque encontrado — nada a enviar.');
    } else {
        const totals = await sendEntries(entries);
        console.log(`Importados: ${totals.imported} · Ignorados (já existiam): ${totals.skipped}`);
    }
} catch (error) {
    console.error(error instanceof Error ? error.message : error);
    process.exitCode = 1;
} finally {
    await browser.close();
}
