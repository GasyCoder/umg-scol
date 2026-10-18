import type { HttpRequestConfig } from '@inertiajs/core';

/** PowerBoost interrupts native PATCH requests over HTTP/2; Laravel supports POST method overrides. */
export function laravelMutationTransport(
    request: HttpRequestConfig,
): HttpRequestConfig {
    if (!['put', 'patch', 'delete'].includes(request.method)) return request;

    return {
        ...request,
        method: 'post',
        headers: {
            ...request.headers,
            'X-HTTP-Method-Override': request.method.toUpperCase(),
        },
    };
}
