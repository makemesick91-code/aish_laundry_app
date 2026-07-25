<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * STEP 7 — THE PORTAL TRANSPORT CONTRACT (FR-092), applied in ONE place.
 *
 * Every header below is set by this middleware rather than by each handler,
 * because a header that each controller must remember is a header that will
 * eventually be forgotten on exactly the response that needed it.
 *
 * WHY EACH ONE IS HERE
 * --------------------
 * `X-Robots-Tag: noindex` — FR-092. An indexed tracking page is a PERMANENT public
 * leak: the token is in the URL, so an indexed page hands a working credential to
 * anyone who searches. The portal page also carries a `<meta name="robots">` tag,
 * because a header can be stripped by an intermediary and the meta tag cannot.
 *
 * `Referrer-Policy: no-referrer` — LOAD-BEARING, not hygiene. The token is IN THE
 * URL PATH. Any outbound request from the page under a permissive referrer policy
 * would hand a third party a working credential in the `Referer` header. This is
 * why the CSP below also forbids every remote origin: with no remote subresource
 * there is nothing to send a referrer to, and the two controls back each other up.
 *
 * `Cache-Control: no-store` — a shared device at a warung or a family phone must
 * not keep the page. Combined with `private` and `Pragma: no-cache` for old
 * intermediaries.
 *
 * `Content-Security-Policy: default-src 'none'` — makes Rule 31 hard rule 10 and
 * Rule 32 hard rule 26 STRUCTURAL: no remote font, no icon CDN, no analytics
 * script, no marketing pixel, no session recorder, no third-party embed can load,
 * whatever a future template says. `style-src 'self'` permits the first-party
 * stylesheet; `img-src 'self' data:` permits a first-party or inlined image and
 * nothing remote. There is no `script-src`, so `default-src 'none'` blocks all
 * script — the portal needs none.
 *
 * `frame-ancestors 'none'` + `X-Frame-Options: DENY` — the portal is not
 * embeddable. A clickjacked tracking page could be framed next to a fake "confirm
 * your address" prompt.
 */
final class PublicTrackingHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet, noimageindex');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; "
            ."base-uri 'none'; form-action 'self'; frame-ancestors 'none'"
        );
        $response->headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=(), payment=()');

        return $response;
    }
}
