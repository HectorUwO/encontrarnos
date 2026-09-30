<?php

namespace App\Services\PhotoSearch;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class NullPhotoMatcher implements PhotoMatcher
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function match(UploadedFile $photo): Collection
    {
        return collect();
    }
}
