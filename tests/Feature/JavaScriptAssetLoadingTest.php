<?php

use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->originalPublicPath = public_path();
    $this->assetPublicPath = sys_get_temp_dir().'/umg-assets-'.bin2hex(random_bytes(8));
    File::makeDirectory($this->assetPublicPath.'/build', 0700, true);
    File::put($this->assetPublicPath.'/build/manifest.json', json_encode([
        'resources/css/app.css' => ['src' => 'resources/css/app.css', 'file' => 'assets/app-test.css', 'isEntry' => true],
        'resources/js/app.tsx' => ['src' => 'resources/js/app.tsx', 'file' => 'assets/app-test.js', 'isEntry' => true, 'imports' => ['_shared-test.js']],
        '_shared-test.js' => ['file' => 'assets/shared-test.js', 'css' => ['assets/shared-test.css']],
        '_shared-test.css' => ['file' => 'assets/shared-test.css'],
    ], JSON_THROW_ON_ERROR));
    app()->usePublicPath($this->assetPublicPath);
});

afterEach(function () {
    app()->usePublicPath($this->originalPublicPath);
    File::deleteDirectory($this->assetPublicPath);
});

it('keeps module scripts and styles without speculative JavaScript preload tags', function () {
    $html = (string) Vite::asset('resources/js/app.tsx');
    expect($html)->toEndWith('/build/assets/app-test.js');

    $tags = (string) Vite::getFacadeRoot()(['resources/css/app.css', 'resources/js/app.tsx']);
    expect($tags)
        ->toContain('type="module"', '/build/assets/app-test.js', '/build/assets/app-test.css', '/build/assets/shared-test.css')
        ->not->toContain('rel="modulepreload"');
});

it('does not reintroduce JavaScript preloads through the HTTP Link header', function () {
    $response = app(AddLinkHeadersForPreloadedAssets::class)->handle(Request::create('/login'), function () {
        return new Response((string) Vite::getFacadeRoot()(['resources/css/app.css', 'resources/js/app.tsx']));
    });

    $links = implode(', ', $response->headers->all('Link'));
    expect($links)->toContain('app-test.css', 'shared-test.css')
        ->not->toContain('app-test.js', 'shared-test.js', 'modulepreload');
});

it('keeps initial portal loading within five JavaScript files', function (array $entries) {
    $buildPath = $this->originalPublicPath.'/build/';
    if (! File::exists($buildPath.'manifest.json')) {
        $this->markTestSkipped('Run npm run build to check the production module graph.');
    }
    $manifest = json_decode(File::get($buildPath.'manifest.json'), true, flags: JSON_THROW_ON_ERROR);
    $visited = [];
    $visit = function (string $entry) use (&$visit, &$visited, $manifest, $buildPath): void {
        if (isset($visited[$entry])) {
            return;
        }
        expect($manifest)->toHaveKey($entry);
        $visited[$entry] = true;
        expect(File::exists($buildPath.$manifest[$entry]['file']))->toBeTrue();
        foreach ($manifest[$entry]['imports'] ?? [] as $dependency) {
            $visit($dependency);
        }
    };
    foreach ($entries as $entry) {
        $visit($entry);
    }

    expect(count($visited))->toBeLessThanOrEqual(5);
    // Recharts is only fetched on demand by the charts, never on the critical path.
    foreach (array_keys($visited) as $chunk) {
        expect($manifest[$chunk]['file'])->not->toContain('chart-renderers');
    }
})->with([
    'dashboard' => [['resources/js/app.tsx', 'resources/js/pages/dashboard.tsx']],
    'login' => [['resources/js/app.tsx', 'resources/js/pages/auth/login.tsx']],
    'student portal' => [['resources/js/bourse/app.tsx']],
]);
