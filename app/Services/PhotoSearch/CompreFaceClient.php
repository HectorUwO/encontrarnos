<?php

namespace App\Services\PhotoSearch;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class CompreFaceClient
{
    public function isConfigured(): bool
    {
        return config('services.compreface.enabled') && filled(config('services.compreface.key'));
    }

    public function request(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('services.compreface.host'), '/').'/api/v1/recognition')
            ->withHeaders(['x-api-key' => config('services.compreface.key')])
            ->acceptJson()->connectTimeout(3)->timeout(config('services.compreface.timeout'));
    }

    /** @return list<array{subject: string, similarity: float}> */
    public function recognize(string $contents, string $filename): array
    {
        $response = $this->request()->attach('file', $contents, $filename)
            ->post('/recognize?'.http_build_query([
                'limit' => 2,
                'prediction_count' => config('services.compreface.prediction_count'),
                'det_prob_threshold' => 0.8,
            ]));

        $this->validateFace($response);
        $faces = $response->throw()->json('result', []);

        if (count($faces) !== 1) {
            throw ValidationException::withMessages([
                'photo' => 'Usa una fotografía con un solo rostro visible y bien iluminado.',
            ]);
        }

        return $faces[0]['subjects'] ?? [];
    }

    public function add(string $subject, string $contents, string $filename): void
    {
        $response = $this->request()->attach('file', $contents, $filename)
            ->post('/faces?'.http_build_query(['subject' => $subject, 'det_prob_threshold' => 0.8]));
        $this->validateFace($response);
        $response->throw();
    }

    public function remove(string $subject): void
    {
        $response = $this->request()->delete('/subjects/'.rawurlencode($subject));
        if (! $response->notFound()) {
            $response->throw();
        }
    }

    /** @return list<string> */
    public function subjects(): array
    {
        return $this->request()->get('/subjects')->throw()->json('subjects', []);
    }

    private function validateFace(Response $response): void
    {
        if ($response->status() === 400 && in_array((int) $response->json('code'), [28, 31], true)) {
            throw ValidationException::withMessages([
                'photo' => 'No se detectó un único rostro. Prueba con una foto frontal, clara y sin otras personas.',
            ]);
        }
    }
}
