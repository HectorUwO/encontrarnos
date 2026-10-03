<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PersonRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\MailCampaign;
use App\Models\PersonRequest;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Resumen para administradores: primero lo que espera una decisión y después
     * el estado general de cuentas, solicitudes y correos.
     */
    public function __invoke(): Response
    {
        $pending = PersonRequest::where('status', PersonRequestStatus::Pending);

        return Inertia::render('Admin/Index', [
            'pending' => [
                'requests' => (clone $pending)->count(),
                'unverified' => User::whereNull('email_verified_at')->count(),
            ],
            'pendingRequests' => (clone $pending)->oldest()->oldest('id')->limit(5)->get()
                ->map(fn (PersonRequest $item): array => [
                    'id' => $item->id,
                    'reference' => $item->reference(),
                    'name' => $item->name,
                    'type_label' => $item->type->label(),
                    'place' => $item->placeLabel(),
                    'waiting' => $item->created_at?->locale('es')->diffForHumans(),
                ]),
            'totals' => [
                'users' => User::count(),
                'verified' => User::whereNotNull('email_verified_at')->count(),
                'admins' => User::where('is_admin', true)->count(),
                'requests' => PersonRequest::count(),
                'published' => PersonRequest::where('status', PersonRequestStatus::Approved)->count(),
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
