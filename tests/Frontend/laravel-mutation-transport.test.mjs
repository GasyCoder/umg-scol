import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(
    new URL(
        '../../resources/js/lib/laravel-mutation-transport.ts',
        import.meta.url,
    ),
    'utf8',
);
const compiled = ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.ESNext },
}).outputText;
const { laravelMutationTransport } = await import(
    `data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`
);

for (const method of ['patch', 'put', 'delete']) {
    void test(`${method} uses POST while preserving Laravel routing, payload and request options`, () => {
        const data = { is_current: true, nested: { name: '2026-2027' } };
        const signal = new AbortController().signal;
        const onUploadProgress = () => {};
        const request = {
            method,
            url: '/references/academic-years/year-uuid/current',
            data,
            headers: { 'X-Inertia': true, 'X-CSRF-TOKEN': 'test-csrf' },
            params: { filter: 'active' },
            signal,
            onUploadProgress,
        };
        const result = laravelMutationTransport(request);
        assert.equal(result.method, 'post');
        assert.equal(
            result.headers['X-HTTP-Method-Override'],
            method.toUpperCase(),
        );
        assert.equal(result.data, data);
        assert.equal(result.signal, signal);
        assert.equal(result.onUploadProgress, onUploadProgress);
        assert.equal(result.headers['X-CSRF-TOKEN'], 'test-csrf');
        assert.equal(result.headers['X-Inertia'], true);
        assert.equal(result.params, request.params);
        assert.equal(result.url, request.url);
        assert.equal(request.method, method);
        assert.equal(request.headers['X-HTTP-Method-Override'], undefined);
    });
}

void test('multipart uploads retain their files and fields', () => {
    const data = new FormData();
    data.append('logo', new Blob(['image'], { type: 'image/png' }), 'logo.png');
    data.append('name', 'Université');
    const result = laravelMutationTransport({
        method: 'put',
        url: '/settings',
        data,
    });
    assert.equal(result.data, data);
    assert.equal(data.get('name'), 'Université');
    assert.equal(data.get('logo').name, 'logo.png');
});

for (const method of ['get', 'post', 'head', 'options']) {
    void test(`${method} requests retain their original method and body`, () => {
        const request = { method, url: '/dashboard', data: { name: 'test' } };
        assert.equal(laravelMutationTransport(request), request);
    });
}
