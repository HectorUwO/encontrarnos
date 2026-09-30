<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resuelve a quién se envía una campaña de correo.
 */
class CampaignAudience
{
    /**
     * @return array{verified: int, all: int, admins: int}
     */
    public function counts(): array
    {
        return [
            'verified' => User::whereNotNull('email_verified_at')->count(),
            'all' => User::count(),
            'admins' => User::where('is_admin', true)->count(),
        ];
    }

    /**
     * @param  list<string>  $customEmails
     * @return Collection<int, array{email: string, name: ?string}>
     */
    public function recipients(string $audience, array $customEmails = []): Collection
    {
        if ($audience === 'custom') {
            return collect($customEmails)
                ->map(fn (string $email): string => strtolower(trim($email)))
                ->filter()
                ->unique()
                ->map(fn (string $email): array => [
                    'email' => $email,
                    'name' => User::where('email', $email)->value('name'),
                ])
                ->values();
        }

        return User::query()
            ->when($audience === 'verified', fn ($query) => $query->whereNotNull('email_verified_at'))
            ->when($audience === 'admins', fn ($query) => $query->where('is_admin', true))
            ->orderBy('id')
            ->get(['name', 'email'])
            ->map(fn (User $user): array => ['email' => $user->email, 'name' => $user->name])
            ->values();
    }

    /**
     * Separa un texto con correos separados por comas, espacios o saltos de línea.
     *
     * @return list<string>
     */
    public function parseEmails(?string $raw): array
    {
        return array_values(array_filter(preg_split('/[\s,;]+/', (string) $raw) ?: []));
    }
}
