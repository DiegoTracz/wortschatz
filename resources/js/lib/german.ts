/**
 * Código de cores pedagógico para os gêneros do alemão, parte da identidade
 * visual do app: der = azul, die = rosa, das = verde — em tons dessaturados de
 * e-ink (tokens --der/--die/--das, que já se ajustam ao modo claro/escuro).
 */
const ARTICLE_COLORS: Record<string, string> = {
    der: 'text-der',
    die: 'text-die',
    das: 'text-das',
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
