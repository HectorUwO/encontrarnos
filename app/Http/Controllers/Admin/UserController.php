<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $term = trim((string) $request->query('q', ''));

        $users = User::query()
            ->when($term !== '', function ($query) use ($term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->latest()
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'verified' => $user->hasVerifiedEmail(),
                'is_admin' => (bool) $user->is_admin,
                'created_at' => $user->created_at?->format('d/m/Y'),
            ]);

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'filters' => ['q' => $term],
        ]);
    }

    /**
     * Da o quita el rol de administrador. Nadie puede quitarse el suyo.
     */
    public function toggleAdmin(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 403, 'No puedes cambiar tu propio rol.');

        $user->forceFill(['is_admin' => ! $user->is_admin])->save();

        return back()->with('status', $user->is_admin ? 'admin-granted' : 'admin-revoked');
    }

    public function resendVerification(User $user): RedirectResponse
    {
        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('status', 'verification-sent');
    }

    public function verify(User $user): RedirectResponse
    {
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return back()->with('status', 'user-verified');
    }
}
