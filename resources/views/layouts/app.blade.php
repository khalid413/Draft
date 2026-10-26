<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="reverb-key" content="{{ config('broadcasting.connections.reverb.key') }}">
    <meta name="reverb-port" content="{{ config('broadcasting.connections.reverb.client_port', 8080) }}">
    <meta name="theme-color" content="#0B0F1A">
    <meta name="description" content="Two rivals. One screen. Draft original heroes, trust your instincts, and own the arena.">
    <title>Draft Duel — Trust your pick.</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|space-grotesk:400,500,600,700|jetbrains-mono:500,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="particles" aria-hidden="true"></div>
    @yield('content')
</body>
</html>
