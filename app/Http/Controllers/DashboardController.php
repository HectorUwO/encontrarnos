<?php

namespace App\Http\Controllers;

use App\Http\Resources\PersonRecordResource;
use App\Http\Resources\PersonRequestResource;
use App\Models\PersonRecord;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const LATEST_RECORDS = 6;

    private const LATEST_REQUESTS = 10;

    /**
     * Panel de la persona autenticada: resumen de la base y sus solicitudes.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'emailVerified' => session('status') === 'email-verified',
            'totals' => [
                'records' => PersonRecord::publishedCount(),
                'my_requests' => $user->personRequests()->count(),
            ],
            'latestRecords' => PersonRecordResource::collection(
                PersonRecord::query()->published()->latestEvents()->limit(self::LATEST_RECORDS)->get(),
            ),
            'myRequests' => PersonRequestResource::collection(
                $user->personRequests()->latest()->latest('id')->limit(self::LATEST_REQUESTS)->get(),
            ),
        ]);
    }
}
