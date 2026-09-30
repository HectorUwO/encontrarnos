<?php

namespace App\Models;

use App\Enums\PersonRequestStatus;
use App\Enums\PersonRequestType;
use Database\Factories\PersonRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'type',
    'status',
    'name',
    'place',
    'description',
    'contact_email',
    'photo_path',
])]
#[Hidden(['contact_email'])]
class PersonRequest extends Model
{
    /** @use HasFactory<PersonRequestFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PersonRequestType::class,
            'status' => PersonRequestStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Solicitudes revisadas y visibles para el público.
     */
    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', PersonRequestStatus::Approved);
    }

    /**
     * Referencia pública de la solicitud, por ejemplo SOL-000012.
     */
    public function reference(): string
    {
        return sprintf('SOL-%06d', $this->id);
    }

    public function hasPhoto(): bool
    {
        return filled($this->photo_path);
    }
}
