<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     *
     * Inertia's default version() hashes mix-manifest.json (Laravel Mix) —
     * this project builds with Vite, which never produces that file, so the
     * inherited implementation always returned null. Without a real,
     * changing version, Inertia can never detect that the client's JS
     * bundle is stale, so it never auto-reloads on navigation — any Page
     * component added after a tab was opened 404s client-side
     * (`resolvePageComponent`'s import.meta.glob was frozen at the old
     * bundle) instead of the browser silently doing a full reload. Hashing
     * Vite's own manifest fixes this for every future deploy, not just
     * this one.
     */
    public function version(Request $request): ?string
    {
        foreach ([
            public_path('build/.vite/manifest.json'), // Vite 5 default manifest location
            public_path('build/manifest.json'),        // older laravel-vite-plugin layout
        ] as $manifestPath) {
            if (is_file($manifestPath)) {
                return md5_file($manifestPath) ?: null;
            }
        }

        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'locale' => app()->getLocale(),
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
