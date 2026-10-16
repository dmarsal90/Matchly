<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $cspEnabled = (bool) config('security.csp.enabled');

        if ($cspEnabled) {
            Vite::useCspNonce();
        }

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        // Only the CV preview may be framed, and only by this site (it is shown in a dialog).
        $frameable = $request->routeIs('applications.packages.resume-preview');
        $headers->set('X-Frame-Options', $frameable ? 'SAMEORIGIN' : 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if (config('security.hsts.enabled') && $request->isSecure()) {
            $headers->set(
                'Strict-Transport-Security',
                sprintf('max-age=%d; includeSubDomains', (int) config('security.hsts.max_age')),
            );
        }

        if ($cspEnabled) {
            $headerName = config('security.csp.report_only')
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';

            /** @var list<string> $relaxedPaths */
            $relaxedPaths = config('security.csp.relaxed_paths', []);

            $headers->set($headerName, $this->contentSecurityPolicy((string) Vite::cspNonce(), $request->is(...$relaxedPaths), $frameable));
        }

        return $response;
    }

    private function contentSecurityPolicy(string $nonce, bool $relaxed = false, bool $frameable = false): string
    {
        /** @var list<string> $connect */
        $connect = config('security.csp.connect_src', []);
        /** @var list<string> $fonts */
        $fonts = config('security.csp.font_src', []);
        /** @var list<string> $styles */
        $styles = config('security.csp.style_src', []);

        $directives = [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => [$frameable ? "'self'" : "'none'"],
            'object-src' => ["'none'"],
            'script-src' => $relaxed ? ["'self'", "'unsafe-inline'", "'unsafe-eval'"] : ["'self'", "'nonce-{$nonce}'"],
            // Vue transitions and Reka UI positioning write inline style attributes.
            'style-src' => ["'self'", "'unsafe-inline'", ...$styles],
            'font-src' => ["'self'", 'data:', ...$fonts],
            'img-src' => ["'self'", 'data:', 'https:'],
            'connect-src' => ["'self'", ...$connect],
        ];

        return collect($directives)
            ->map(fn (array $sources, string $directive): string => $directive.' '.implode(' ', $sources))
            ->implode('; ');
    }
}
