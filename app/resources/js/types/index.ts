import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User | null;
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

export interface UiTranslations {
    targets: {
        target_list: string;
        empty_targets: string;
        target_saved: string;
        add_target: string;
        modal_title: string;
        username_label: string;
        username_placeholder: string;
        type_label: string;
        type_user: string;
        type_org: string;
        target_found: string;
        target_not_found: string;
        invalid_token: string;
        access_restricted: string;
        request_failed: string;
    };
    github: {
        menu: string;
        page_title: string;
        description: string;
        username_label: string;
        token_label: string;
        token_placeholder: string;
        saved: string;
    };
    common: { cancel: string; add: string; save: string; delete: string };
    index: { title: string; description1: string; description2: string };
    nav: { login: string; register: string; logout: string; home: string; targets: string };
}

export interface SharedData {
    [key: string]: unknown;
    lang: UiTranslations;
    flash: { targetResult: string | null };
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    github_username: string | null;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface GithubTarget {
    id: number;
    name: string;
    type: 'user' | 'org';
    sync_status: 'pending' | 'syncing' | 'synced' | 'failed';
    repositories_count: number;
    created_at: string | null;
}
