<!DOCTYPE html>
<html lang="fr" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <title>Portail étudiant — Université de Mahajanga</title>

    {{-- Same theme detection as the internal portal: applied before paint to avoid a flash. --}}
    <script>
        (function() {
            const appearance = '{{ $appearance ?? "system" }}';

            if (appearance === 'system') {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                if (prefersDark) {
                    document.documentElement.classList.add('dark');
                }
            }
        })();
    </script>

    {{-- HTML background follows the theme tokens of app.css (--background). --}}
    <style>
        html {
            background-color: #f4f6fb;
        }

        html.dark {
            background-color: #0a0f1c;
        }
    </style>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @fonts
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/bourse/app.tsx'])
</head>
<body class="bg-background font-sans text-foreground antialiased">
    <div id="bourse-root"></div>
</body>
</html>
