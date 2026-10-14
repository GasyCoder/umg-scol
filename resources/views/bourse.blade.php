<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Portail étudiant — Université de Mahajanga</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @fonts
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/bourse/app.tsx'])
</head>
<body class="bg-background font-sans text-foreground antialiased">
    <div id="bourse-root"></div>
</body>
</html>
