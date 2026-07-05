import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    native: boolean;
    flash: {
        success: string | null;
        import_result: { imported: number; skipped: number; books: number } | null;
        import_error: string | null;
    };
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface HighlightCardRef {
    id: number;
    front: string;
}

export interface HighlightData {
    id: number;
    type: 'highlight' | 'note';
    content: string;
    location: string | null;
    page: string | null;
    highlighted_at: string | null;
    cards: HighlightCardRef[];
}

export interface CardData {
    id: number;
    front: string;
    back: string;
    context: string | null;
    mnemonic: string | null;
    interval_days: number;
    repetitions: number;
    due_at: string;
    is_due: boolean;
}

export interface StudyCard {
    id: number;
    front: string;
    back: string;
    context: string | null;
    mnemonic: string | null;
    book: string | null;
    repetitions: number;
    interval_days: number;
    is_due: boolean;
    previews: Record<number, number>;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
