import { fileURLToPath } from 'node:url';

export const NOTEBOOK_URL = process.env.NOTEBOOK_URL ?? 'https://ler.amazon.com.br/notebook';
export const APP_URL = (process.env.APP_URL ?? 'http://localhost:8000').replace(/\/$/, '');
export const IMPORT_TOKEN = process.env.IMPORT_TOKEN ?? '';

// Sessão da Amazon salva pelo `npm run login`; fica fora do git.
export const STORAGE_STATE_PATH = fileURLToPath(new URL('../storage-state.json', import.meta.url));
