<?php

namespace App\Jobs;

use App\Services\PhotoSearch\FaceIndexer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Validation\ValidationException;

class SyncPersonFace implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 75;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public string $type, public int $id, public ?string $previousSubject = null)
    {
        $this->onQueue('faces');
    }

    public function handle(FaceIndexer $indexer): void
    {
        try {
            $indexer->sync($this->type, $this->id, $this->previousSubject);
        } catch (ValidationException $exception) {
            $this->fail($exception);
        }
    }
}
