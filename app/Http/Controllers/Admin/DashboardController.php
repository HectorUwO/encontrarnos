<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MailCampaign;
use App\Models\PersonRequest;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Resumen para administradores: cuentas, solicitudes y correos enviados.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Index', [
            'totals' => [
                'users' => User::count(),
                'verified' => User::whereNotNull('email_verified_at')->count(),
                'admins' => User::where('is_admin', true)->count(),
                'requests' => PersonRequest::count(),
                'emails_sent' => (int) MailCampaign::sum('sent_count'),
            ],
            'latestUsers' => User::query()->latest()->latest('id')->limit(6)->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'verified' => $user->hasVerifiedEmail(),
                    'is_admin' => (bool) $user->is_admin,
                    'created_at' => $user->created_at?->format('d/m/Y'),
                ]),
        ]);
    }
}
