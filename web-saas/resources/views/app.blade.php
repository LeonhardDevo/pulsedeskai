<!DOCTYPE html>
<html lang="ru" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PulseDesk AI — Merchant Admin & Operator Workspace</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">
    <div id="pulsedesk-vue-root"></div>
    <script>
        window.__PULSEDESK_STATE__ = @json($initialState);
    </script>
</body>
</html>
