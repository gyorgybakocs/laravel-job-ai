export function formatDate(value: string | null, emptyValue: string): string {
    if (!value) return emptyValue;
    return new Date(value).toLocaleDateString(document.documentElement.lang || navigator.language, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
