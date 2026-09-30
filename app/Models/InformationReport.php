<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'person_record_id', 'person_request_id', 'message', 'phone', 'attended_at'])]
class InformationReport extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function personRecord(): BelongsTo
    {
        return $this->belongsTo(PersonRecord::class);
    }

    public function personRequest(): BelongsTo
    {
        return $this->belongsTo(PersonRequest::class);
    }
}
