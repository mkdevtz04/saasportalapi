<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks Swahili or English for the customer portal and the API calls it makes.
 *
 * In order: a language chosen with ?lang=, the X-Portal-Lang header the page sends with every
 * call, the language remembered in a cookie, the ISP's default, the phone's own language, then English.
 */
class SetPortalLocale
{
    public const SUPPORTED = ['sw', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $chosen = $this->supported($request->query('lang'));

        App::setLocale(
            $chosen
            ?? $this->supported($request->header('X-Portal-Lang'))
            ?? $this->supported($request->cookie('portal_lang'))
            ?? $this->supported(tenant()?->settings?->default_language)
            ?? $request->getPreferredLanguage(self::SUPPORTED)
            ?? 'en'
        );

        $response = $next($request);

        // Remember an explicit choice so the next visit opens in the same language.
        if ($chosen !== null && $request->hasSession()) {
            $response->headers->setCookie(cookie('portal_lang', $chosen, 60 * 24 * 365));
        }

        return $response;
    }

    private function supported(mixed $locale): ?string
    {
        return is_string($locale) && in_array($locale, self::SUPPORTED, true) ? $locale : null;
    }
}
