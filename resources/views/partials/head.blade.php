<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="color-scheme" content="light" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Kost Putri Kirana') : config('app.name', 'Kost Putri Kirana') }}
</title>

<link rel="icon" href="/favicon.svg" type="image/svg+xml">

@vite(['resources/css/app.css', 'resources/js/app.js'])
