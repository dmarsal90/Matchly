<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Middleware;

use App\Modules\Shared\Infrastructure\Localization\SupportedLocales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public const SESSION_KEY = 'locale';

    public function __construct(private readonly SupportedLocales $locales) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $candidates = [
            $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null,
            $request->getPreferredLanguage($this->locales->all()),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $this->locales->supports($candidate)) {
                return $candidate;
            }
        }

        return $this->locales->default();
    }
}
