import { chromium } from 'playwright';

import { NOTEBOOK_URL, STORAGE_STATE_PATH } from './config.js';
import { selectors } from './selectors.js';

const browser = await chromium.launch({ headless: false });
const context = await browser.newContext();
const page = await context.newPage();

console.log(`Abrindo ${NOTEBOOK_URL} — faça login na sua conta Amazon (inclusive 2FA, se pedir).`);
console.log('Quando a sua lista de livros aparecer, a sessão será salva automaticamente.');

await page.goto(NOTEBOOK_URL);

// Até 5 minutos para o usuário completar o login manualmente.
await page.waitForSelector(selectors.library, { timeout: 300_000 });

await context.storageState({ path: STORAGE_STATE_PATH });
console.log('Sessão salva. Agora rode: npm run scrape (ou npm run scrape:dry para inspecionar).');

await browser.close();
