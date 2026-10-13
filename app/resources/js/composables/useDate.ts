export function formatDate(value: string | null, emptyValue: string, includeTime = false): string {
    if (!value) return emptyValue;
    return new Date(value).toLocaleString(document.documentElement.lang || navigator.language, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        ...(includeTime ? ({ hour: '2-digit', minute: '2-digit', second: '2-digit' } as const) : {}),
    });
}
