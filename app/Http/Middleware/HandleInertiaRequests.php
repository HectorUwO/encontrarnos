<?php

namespace App\Http\Middleware;

use App\Enums\PersonRequestStatus;
use App\Models\PersonRequest;
use Illuminate\Http\Request;
use Inertia\Middleware;

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
     */
    public function version(Request $request): ?string
    {
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
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
            // Pendientes para la insignia del menú: solo se calculan para administradores.
            'adminPending' => fn (): ?int => $request->user()?->is_admin
                ? PersonRequest::where('status', PersonRequestStatus::Pending)->count()
                : null,
        ];
    }
}
