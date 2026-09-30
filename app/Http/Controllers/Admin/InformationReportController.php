<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InformationReport;
use Inertia\Inertia;
use Inertia\Response;

class InformationReportController extends Controller
{
    /**
     * Información que la gente compartió sobre fichas y solicitudes.
     */
    public function index(): Response
    {
        $reports = InformationReport::query()
            ->with(['user:id,name,email', 'personRecord:id,folio,name,authority', 'personRequest:id,name,contact_email'])
            ->latest()
            ->latest('id')
            ->paginate(15)
            ->through(fn (InformationReport $report): array => [
                'id' => $report->id,
                'message' => $report->message,
                'phone' => $report->phone,
                'sender' => $report->user?->name,
                'sender_email' => $report->user?->email,
                'created_at' => $report->created_at?->format('d/m/Y H:i'),
                'target' => $report->personRecord
                    ? [
                        'kind' => 'Ficha de desaparecido',
                        'label' => $report->personRecord->folio.' · '.($report->personRecord->name ?? 'Sin nombre'),
                        'url' => route('records', ['q' => $report->personRecord->folio], absolute: false),
                        'extra' => $report->personRecord->authority,
                    ]
                    : ($report->personRequest
                        ? [
                            'kind' => 'Solicitud',
                            'label' => $report->personRequest->reference().' · '.($report->personRequest->name ?? 'Sin nombre'),
                            'url' => route('requests.show', $report->personRequest, absolute: false),
                            'extra' => $report->personRequest->contact_email,
                        ]
                        : null),
            ]);

        return Inertia::render('Admin/Information', ['reports' => $reports]);
    }
}
