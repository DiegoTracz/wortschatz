/**
 * Código de cores pedagógico para os gêneros do alemão, parte da identidade
 * visual do app: der = azul, die = rosa, das = verde.
 */
const ARTICLE_COLORS: Record<string, string> = {
    der: 'text-sky-600 dark:text-sky-400',
    die: 'text-rose-600 dark:text-rose-400',
    das: 'text-emerald-600 dark:text-emerald-400',
};

export interface ArticleParts {
    article: string | null;
    rest: string;
    color: string;
}

/** Separa o artigo (der/die/das) do restante da frente do cartão. */
export function splitArticle(front: string): ArticleParts {
    const match = front.trim().match(/^(der|die|das)\s+(.+)$/i);

    if (!match) {
        return { article: null, rest: front, color: '' };
    }

    return {
        article: match[1],
        rest: match[2],
        color: ARTICLE_COLORS[match[1].toLowerCase()] ?? '',
    };
}
