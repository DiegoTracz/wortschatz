/**
 * JSON para rotas web (sessão + CSRF via cookie XSRF-TOKEN).
 * Usado nos endpoints que respondem JSON (revisão de estudo, tradução, destaques
 * do leitor de PDF), onde um reload de página do Inertia atrapalharia o fluxo.
 */
async function sendJson<T>(method: string, url: string, data?: Record<string, unknown>): Promise<T> {
    const xsrf = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    const response = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrf ? decodeURIComponent(xsrf) : '',
        },
        body: data === undefined ? undefined : JSON.stringify(data),
    });

    if (!response.ok) {
        const body = await response.json().catch(() => null);
        throw new Error(body?.message ?? `Erro ${response.status}`);
    }

    return response.json();
}

export function postJson<T>(url: string, data: Record<string, unknown>): Promise<T> {
    return sendJson<T>('POST', url, data);
}

export function deleteJson<T>(url: string): Promise<T> {
    return sendJson<T>('DELETE', url);
}
