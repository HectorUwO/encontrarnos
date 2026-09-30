<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'subject', 'heading', 'body', 'button_label', 'button_url', 'audience', 'recipients_count'])]
class MailCampaign extends Model
{
    public const AUDIENCES = ['verified', 'all', 'admins', 'custom'];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
