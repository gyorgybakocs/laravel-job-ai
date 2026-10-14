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
    repositories: {
        page_title: string;
        empty: string;
        empty_filtered: string;
        filter_archived: string;
        filter_active: string;
        filter_language: string;
        sort_label: string;
        search_placeholder: string;
        search_in: string;
        search_target: string;
        search_description: string;
        sort_target_asc: string;
        sort_target_desc: string;
        sort_name_asc: string;
        sort_name_desc: string;
        sort_updated_desc: string;
        sort_stars_desc: string;
        filter_ok: string;
        filter_reset: string;
        no_languages: string;
        col_name: string;
        col_target: string;
        col_description: string;
        col_language: string;
        col_stars: string;
        col_open_issues: string;
        col_archived: string;
        col_updated: string;
        archived_yes: string;
        archived_no: string;
    };
    targets: {
        users: string;
        organizations: string;
        no_users: string;
        no_orgs: string;
        last_synced: string;
        sync_now: string;
        no_repositories: string;
        col_name: string;
        col_language: string;
        col_stars: string;
        col_open_issues: string;
        col_updated: string;
        delete_target: string;
        pagination: string;
        sync_success: string;
        sync_queued: string;
        sync_already_queued: string;
        operation_queued: string;
        operation_running: string;
        operation_succeeded: string;
        operation_failed: string;
        status_pending: string;
        status_syncing: string;
        status_synced: string;
        status_failed: string;
        unknown_value: string;
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
    nav: { login: string; register: string; logout: string; repositories: string; home: string; targets: string };
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

export interface GithubRepository {
    id: number;
    name: string;
    full_name: string;
    description: string | null;
    url: string;
    language: string | null;
    stars: number;
    open_issues: number;
    archived: boolean;
    github_updated_at: string | null;
}

export interface GithubSyncOperation {
    id: number;
    status: 'queued' | 'running' | 'succeeded' | 'failed';
    started_at: string | null;
    finished_at: string | null;
}

export interface GithubTarget {
    id: number;
    name: string;
    type: 'user' | 'org';
    status: 'pending' | 'syncing' | 'synced' | 'failed';
    last_synced_at: string | null;
    last_error: string | null;
    sync_operation: GithubSyncOperation | null;
    repositories_count: number;
    repositories: GithubRepository[];
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginator<T> {
    data: T[];
    links: PaginationLink[];
    total: number;
    current_page: number;
    last_page: number;
}

export type RepositorySortValue = 'target_asc' | 'target_desc' | 'name_asc' | 'name_desc' | 'updated_desc' | 'stars_desc';
export type RepositoryStatus = 'archived' | 'active';
export type RepositorySearchField = 'target' | 'description';
export interface RepositoryFiltersData {
    status: RepositoryStatus | null;
    languages: string[];
    search: string;
    search_in: RepositorySearchField;
}
export interface RepositoryFilterOptions {
    statuses: RepositoryStatus[];
    sorts: RepositorySortValue[];
    searchFields: RepositorySearchField[];
}
export interface LanguageOption {
    name: string;
    count: number;
}
export interface RepositoryWithTarget extends GithubRepository {
    target: Pick<GithubTarget, 'id' | 'name' | 'type'>;
}
