<?php

namespace App\Http\Controllers;

use App\Enums\PhotoSize;
use App\Models\PersonRecord;
use App\Services\Photos\PhotoCache;
use App\Services\Photos\PhotoThumbnails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PersonRecordPhotoController extends Controller
{
    /**
     * Fotografía de una ficha publicada. Con `?size=thumb` o `?size=medium` se
     * sirve una versión reducida; sin él, o si no se pudo reducir, la original.
     * El nombre del archivo es su huella (sha256), así que el contenido nunca
     * cambia y puede guardarse en caché (ver PhotoCache).
     */
    public function show(Request $request, PersonRecord $personRecord, PhotoThumbnails $thumbnails): StreamedResponse
    {
        $disk = Storage::disk('record_photos');

        abort_unless(
            $personRecord->published_at !== null
                && $personRecord->hasPhoto()
                && $disk->exists($personRecord->photo_path),
            404,
        );

        $size = PhotoSize::tryFrom($request->string('size')->toString());
        $path = $size === null ? null : $thumbnails->pathFor('record_photos', $personRecord->photo_path, $size);
        $headers = PhotoCache::headers($request, $personRecord->photo_path);

        return $path === null
            ? $disk->response($personRecord->photo_path, null, $headers)
            : Storage::disk('record_thumbnails')->response($path, null, $headers);
    }
}
