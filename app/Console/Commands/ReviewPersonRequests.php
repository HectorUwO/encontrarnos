<?php

namespace App\Console\Commands;

use App\Enums\PersonRequestStatus;
use App\Models\PersonRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('requests:review {reference? : Referencia de la solicitud, con o sin el prefijo SOL-} {decision? : approve para publicarla o reject para rechazarla}')]
#[Description('Lista las solicitudes pendientes y aprueba o rechaza una')]
class ReviewPersonRequests extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->argument('reference') === null) {
            return $this->listPending();
        }

        $status = match ($this->argument('decision')) {
            'approve' => PersonRequestStatus::Approved,
            'reject' => PersonRequestStatus::Rejected,
            default => null,
        };

        if ($status === null) {
            $this->components->error('Indica la decisión: approve o reject.');

            return self::FAILURE;
        }

        $reference = (string) $this->argument('reference');
        $request = PersonRequest::query()->find((int) preg_replace('/\D/', '', $reference));

        if ($request === null) {
            $this->components->error("No existe la solicitud {$reference}.");

            return self::FAILURE;
        }

        $previous = $request->status;
        $request->update(['status' => $status]);

        $this->components->info(sprintf('%s: %s → %s.', $request->reference(), $previous->label(), $status->label()));

        return self::SUCCESS;
    }

    private function listPending(): int
    {
        $pending = PersonRequest::query()
            ->where('status', PersonRequestStatus::Pending)
            ->oldest()
            ->oldest('id')
            ->get();

        if ($pending->isEmpty()) {
            $this->components->info('No hay solicitudes pendientes.');

            return self::SUCCESS;
        }

        $this->table(
            ['Referencia', 'Tipo', 'Nombre', 'Lugar', 'Contacto', 'Foto', 'Descripción'],
            $pending->map(fn (PersonRequest $request): array => [
                $request->reference(),
                $request->type->label(),
                $request->name ?? '—',
                $request->place ?? '—',
                $request->contact_email ?? '—',
                $request->hasPhoto() ? 'sí' : 'no',
                Str::limit($request->description, 60),
            ])->all(),
        );

        $this->components->info('Aprueba o rechaza con: php artisan requests:review SOL-000001 approve|reject');

        return self::SUCCESS;
    }
}
