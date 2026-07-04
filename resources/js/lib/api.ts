/**
 * POST JSON para rotas web (sessão + CSRF via cookie XSRF-TOKEN).
 * Usado nos endpoints que respondem JSON (revisão de estudo e tradução),
 * onde um reload de página do Inertia atrapalharia o fluxo.
 */
export async function postJson<T>(url: string, data: Record<string, unknown>): Promise<T> {
    const xsrf = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrf ? decodeURIComponent(xsrf) : '',
        },
        body: JSON.stringify(data),
    });

    if (!response.ok) {
        const body = await response.json().catch(() => null);
        throw new Error(body?.message ?? `Erro ${response.status}`);
    }

    return response.json();
}
