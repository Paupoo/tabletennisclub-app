<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ProtectAgainstSpam
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): \Symfony\Component\HttpFoundation\Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isSpam($request)) {
            return $this->blockSpam($request);
        }

        return $next($request);
    }

    /**
     * Gérer un cas suspect de spam (log + réponse silencieuse).
     */
    private function blockSpam(Request $request): Response
    {
        // Logging basique (tu peux pousser ça en DB si besoin)
        Log::warning('Spam attempt detected', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'inputs' => $request->except(['password']), // ne jamais loguer les mdp
        ]);

        // Réponse silencieuse : on redirige avec un message générique
        return redirect()
            ->back()
            ->with('success', 'Votre demande est en cours de traitement.');
    }

    /**
     * Détection spam par honeypot ou temps minimum.
     */
    private function isSpam(Request $request): bool
    {
        // Honeypot
        if ($request->filled('website')) {
            return true;
        }

        // Temps minimum
        $formStart = (int) $request->input('form_start', 0);
        if ($formStart === 0 || (time() - $formStart < 3)) {
            return true;
        }

        return false;
    }
}
