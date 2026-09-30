<?php

namespace App\Http\Controllers;

use App\Enums\PersonRequestStatus;
use App\Http\Resources\PersonRequestResource;
use App\Models\InformationReport;
use App\Models\PersonRecord;
use App\Models\PersonRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Mi espacio: seguimiento de las solicitudes de la persona y de la
     * información que le llega.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $requests = PersonRequest::query()
            ->ownedBy($user)
            ->withCount([
                'informationReports as reports_count',
                'informationReports as unattended_count' => fn ($query) => $query->whereNull('attended_at'),
            ])
            ->latest()
            ->latest('id')
            ->get();

        $reports = InformationReport::query()
            ->whereIn('person_request_id', $requests->pluck('id'))
            ->with(['user:id,name,email', 'personRequest:id,name'])
            ->latest()
            ->latest('id')
            ->limit(30)
            ->get();

        return Inertia::render('Dashboard', [
            'emailVerified' => session('status') === 'email-verified',
            'summary' => [
                'total' => $requests->count(),
                'published' => $requests->filter(fn (PersonRequest $item): bool => $item->status === PersonRequestStatus::Approved && ! $item->isClosed())->count(),
                'pending' => $requests->where('status', PersonRequestStatus::Pending)->count(),
                'closed' => $requests->filter(fn (PersonRequest $item): bool => $item->isClosed())->count(),
                'unattended' => (int) $requests->sum('unattended_count'),
                'records' => PersonRecord::publishedCount(),
            ],
            'myRequests' => $requests->map(fn (PersonRequest $item): array => PersonRequestResource::make($item)->resolve($request) + [
                'reports_count' => $item->reports_count,
                'unattended_count' => $item->unattended_count,
                'updated_at_label' => $item->updated_at?->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
            ])->all(),
            'reports' => $reports->map(fn (InformationReport $report): array => [
                'id' => $report->id,
                'message' => $report->message,
                'phone' => $report->phone,
                'sender' => $report->user?->name ?? 'Cuenta eliminada',
                'sender_email' => $report->user?->email,
                'request_id' => $report->person_request_id,
                'request_name' => $report->personRequest?->name,
                'reference' => $report->personRequest?->reference(),
                'attended' => $report->attended_at !== null,
                'created_at_label' => $report->created_at?->locale('es')->isoFormat('D [de] MMMM, HH:mm'),
            ])->all(),
        ]);
    }
}
