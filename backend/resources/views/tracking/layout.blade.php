{{--
    THE PUBLIC TRACKING PORTAL SHELL (FR-092, Rule 31, Rule 32).

    SELF-CONTAINED BY CONSTRUCTION. There is no <script> tag, no remote font, no
    remote stylesheet, no image request, no analytics, no marketing pixel, no
    session recorder, and no third-party embed anywhere in this layout or the two
    pages that extend it. The CSP set by PublicTrackingHeaders forbids all of them
    at the transport layer too, so the two controls back each other up (Rule 31
    hard rule 10, Rule 32 hard rule 26).

    THE ROBOTS META IS NOT REDUNDANT with the X-Robots-Tag header. A header can be
    stripped by a proxy or a CDN; the meta tag travels inside the document. The
    token is in the URL, so an indexed page is a PERMANENT public leak of a working
    credential — belt and braces is proportionate here (FR-092, TRK-006).

    ACCESSIBILITY AND DEVICE ASSUMPTIONS (Rule 27, Rule 31): system font stack so
    nothing is fetched and text renders immediately; relative units throughout so
    large system font scaling reflows rather than truncates; a 320px-first layout;
    contrast chosen for a cheap screen in daylight, not a dark room; every status
    carried by TEXT, never by colour alone.
--}}<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
    <meta name="referrer" content="no-referrer">
    <title>Lacak Cucian — Aish Laundry App</title>
    <style>
        :root {
            /* Step 2 palette roles: white surface, soft blue, dark blue, restrained
               gold accent. Values are inline because a separate stylesheet would be
               a second request on the product's most latency-sensitive surface. */
            --surface: #ffffff;
            --surface-muted: #f4f7fb;
            --border: #d6e0ee;
            --text: #17233a;
            --text-muted: #4a5a75;
            --accent: #1b4f8f;
            --accent-soft: #e8f0fa;
            --gold: #b6883a;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 1rem;
            background: var(--surface-muted);
            color: var(--text);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 1rem;
            line-height: 1.55;
        }
        .wrap { max-width: 34rem; margin: 0 auto; }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }
        h1 { font-size: 1.25rem; margin: 0 0 0.25rem; }
        h2 { font-size: 1rem; margin: 0 0 0.75rem; color: var(--text-muted); font-weight: 600; }
        .brandmark { font-weight: 700; color: var(--accent); letter-spacing: 0.01em; }
        .muted { color: var(--text-muted); }
        .small { font-size: 0.875rem; }
        dl { margin: 0; }
        dt { font-size: 0.8125rem; color: var(--text-muted); margin-top: 0.75rem; }
        dd { margin: 0.125rem 0 0; font-weight: 600; overflow-wrap: anywhere; }
        .status {
            display: inline-block;
            border: 1px solid var(--accent);
            background: var(--accent-soft);
            color: var(--accent);
            border-radius: 999px;
            padding: 0.35rem 0.85rem;
            font-weight: 700;
            /* The TEXT is the status. Colour only reinforces it (Rule 27 hard
               rule 3) — this page is readable in greyscale and in sunlight. */
        }
        ol.timeline { list-style: none; margin: 0; padding: 0; }
        ol.timeline li {
            border-left: 3px solid var(--border);
            padding: 0 0 0.9rem 0.85rem;
            position: relative;
        }
        ol.timeline li:last-child { padding-bottom: 0; }
        ol.timeline li strong { display: block; }
        .amount { font-size: 1.25rem; font-weight: 700; }
        .notice {
            border-left: 4px solid var(--gold);
            background: #fdf8ef;
            padding: 0.75rem 0.9rem;
            border-radius: 0.35rem;
        }
        footer { margin-top: 1.25rem; }
    </style>
</head>
<body>
<main class="wrap">
    <p class="brandmark">Aish Laundry App</p>
    @yield('content')
    <footer class="small muted">
        {{-- Rule 32 hard rule 8: the portal states its own limits plainly. --}}
        <p>Halaman ini hanya menampilkan ringkasan pesanan Anda. Alamat lengkap, nomor telepon lengkap,
            dan catatan internal tidak pernah ditampilkan di sini.</p>
        <p>Jangan bagikan tautan ini kepada orang yang tidak berkepentingan.</p>
    </footer>
</main>
</body>
</html>
