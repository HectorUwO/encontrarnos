<?php

namespace App\Http\Controllers;

use App\Enums\PersonRequestStatus;
use App\Enums\PhotoSize;
use App\Models\PersonRequest;
use App\Services\Photos\PhotoCache;
use App\Services\Photos\PhotoThumbnails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PersonRequestPhotoController extends Controller
{
    /**
     * Fotografía de una solicitud aprobada, original o reducida (`?size=thumb`
     * o `?size=medium`). Mientras está pendiente o fue rechazada el archivo no
     * se sirve a nadie.
     */
    public function show(Request $request, PersonRequest $personRequest, PhotoThumbnails $thumbnails): StreamedResponse
    {
        $diskName = config('filesystems.default');
        $disk = Storage::disk($diskName);

        abort_unless(
            $personRequest->status === PersonRequestStatus::Approved
                && $personRequest->hasPhoto()
                && $disk->exists($personRequest->photo_path),
            404,
        );

        $size = PhotoSize::tryFrom($request->string('size')->toString());
        $path = $size === null ? null : $thumbnails->pathFor($diskName, $personRequest->photo_path, $size);
        $headers = PhotoCache::headers($request, $personRequest->photo_path);

        return $path === null
            ? $disk->response($personRequest->photo_path, null, $headers)
            : Storage::disk('record_thumbnails')->response($path, null, $headers);
    }
}
