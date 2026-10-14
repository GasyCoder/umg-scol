let csrf =
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';
export type Session = {
    authenticated: boolean;
    linked: boolean;
    csrf_token: string;
    google_enabled: boolean;
};
export async function api<T>(
    path: string,
    body?: Record<string, string>,
): Promise<T> {
    const response = await fetch(`/api/student/${path}`, {
        method: body === undefined ? 'GET' : 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(body === undefined
                ? {}
                : { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }),
        },
        ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });
    if (!response.ok) {
        const messages: Record<number, string> = {
            401: 'Votre session a expiré. Reconnectez-vous.',
            403: 'Votre compte ne permet pas cette action.',
            419: 'La session a expiré. Rechargez la page.',
            429: 'Trop de tentatives. Patientez avant de réessayer.',
            503: 'Le service de connexion est temporairement indisponible.',
        };
        if (response.status === 422) {
            const data = (await response.json()) as {
                errors?: Record<string, string[]>;
            };
            throw new Error(
                Object.values(data.errors ?? {})[0]?.[0] ??
                    'Vérifiez les informations saisies.',
            );
        }
        throw new Error(
            messages[response.status] ??
                'La demande n’a pas pu être traitée. Réessayez plus tard.',
        );
    }
    const data: unknown = await response.json();
    if (
        typeof data === 'object' &&
        data !== null &&
        'csrf_token' in data &&
        typeof data.csrf_token === 'string'
    )
        csrf = data.csrf_token;
    return data as T;
}
