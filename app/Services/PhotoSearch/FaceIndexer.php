<?php

namespace App\Services\PhotoSearch;

use App\Enums\PersonRequestStatus;
use App\Models\PersonRecord;
use App\Models\PersonRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class FaceIndexer
{
    public function __construct(private CompreFaceClient $client) {}

    public static function subject(PersonRecord|PersonRequest $person, ?string $photoPath = null): string
    {
        return ($person instanceof PersonRecord ? 'record' : 'request').'-'.$person->id.'-'
            .substr(hash('sha256', $photoPath ?? $person->photo_path ?? ''), 0, 16);
    }

    public static function isPublished(PersonRecord|PersonRequest $person): bool
    {
        return $person instanceof PersonRecord
            ? $person->published_at !== null
            : $person->status === PersonRequestStatus::Approved && $person->closed_at === null;
    }

    public static function disk(PersonRecord|PersonRequest $person): string
    {
        return $person instanceof PersonRecord ? 'record_photos' : config('filesystems.default');
    }

    public function sync(string $type, int $id, ?string $previousSubject = null): bool
    {
        return Cache::lock('face-index:'.$type.':'.$id, 120)->block(3, function () use ($type, $id, $previousSubject): bool {
            $person = ($type === 'record' ? PersonRecord::class : PersonRequest::class)::query()->find($id);
            $subject = $person === null ? null : self::subject($person);
            if ($previousSubject !== null && $previousSubject !== $subject) {
                $this->client->remove($previousSubject);
            }
            if ($person === null) {
                return false;
            }
            $this->client->remove($subject);
            $disk = Storage::disk(self::disk($person));
            if (! self::isPublished($person) || ! $person->hasPhoto() || ! $disk->exists($person->photo_path)) {
                return false;
            }
            $this->client->add($subject, $disk->get($person->photo_path), basename($person->photo_path));

            return true;
        });
    }

    public function prune(): int
    {
        $removed = 0;
        foreach ($this->client->subjects() as $subject) {
            if (preg_match('/^(record|request)-([1-9][0-9]*)-[a-f0-9]{16}$/', $subject, $parts) !== 1) {
                continue;
            }
            $person = ($parts[1] === 'record' ? PersonRecord::class : PersonRequest::class)::query()->find((int) $parts[2]);
            if ($person === null || ! self::isPublished($person) || ! $person->hasPhoto() || self::subject($person) !== $subject) {
                $this->client->remove($subject);
                $removed++;
            }
        }

        return $removed;
    }
}
