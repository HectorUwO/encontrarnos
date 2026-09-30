<?php

namespace App\Services\PhotoSearch;

use App\Models\PersonRecord;
use App\Models\PersonRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class CompreFaceMatcher implements PhotoMatcher
{
    public function __construct(private CompreFaceClient $client) {}

    public function isAvailable(): bool
    {
        return $this->client->isConfigured();
    }

    /** @return Collection<int, PersonRecord|PersonRequest> */
    public function match(UploadedFile $photo): Collection
    {
        $predictions = collect($this->client->recognize($photo->getContent(), $photo->getClientOriginalName()))
            ->filter(fn (array $prediction): bool => is_numeric($prediction['similarity'] ?? null)
                && $prediction['similarity'] >= config('services.compreface.threshold')
                && $prediction['similarity'] <= 1
                && preg_match('/^(record|request)-[1-9][0-9]*-[a-f0-9]{16}$/', $prediction['subject'] ?? '') === 1)
            ->sortByDesc('similarity')->unique('subject');

        $recordIds = [];
        $requestIds = [];
        foreach ($predictions as $prediction) {
            [$type, $id] = explode('-', $prediction['subject']);
            if ($type === 'record') {
                $recordIds[] = (int) $id;
            } else {
                $requestIds[] = (int) $id;
            }
        }

        $records = PersonRecord::query()->published()->whereIn('id', $recordIds)->get()->keyBy('id');
        $requests = PersonRequest::query()->published()->whereIn('id', $requestIds)->get()->keyBy('id');

        return $predictions->map(function (array $prediction) use ($records, $requests): PersonRecord|PersonRequest|null {
            [$type, $id] = explode('-', $prediction['subject']);
            $person = ($type === 'record' ? $records : $requests)->get((int) $id);
            if ($person === null || ! $person->hasPhoto() || FaceIndexer::subject($person) !== $prediction['subject']) {
                return null;
            }
            $person->setAttribute('face_similarity', (float) $prediction['similarity']);

            return $person;
        })->filter()->take(config('services.compreface.limit'))->values();
    }
}
