<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

#[Signature('admin:create {email : Correo de la persona administradora} {--name= : Nombre} {--password= : Contraseña (si se omite, se pide)}')]
#[Description('Crea una cuenta de administrador, o convierte una existente, con el correo ya verificado')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('El correo no es válido.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();
        $password = $this->option('password') ?: ($user ? null : $this->secret('Contraseña'));

        if (! $user && ! $password) {
            $this->error('Hace falta una contraseña para crear la cuenta.');

            return self::FAILURE;
        }

        $user ??= new User(['email' => $email]);
        $user->name = $this->option('name') ?: ($user->name ?: 'Administrador');

        if ($password) {
            $user->password = Hash::make($password);
        }

        $user->email_verified_at ??= now();
        $user->is_admin = true;
        $user->save();

        $this->info("{$email} ahora es administrador.");

        return self::SUCCESS;
    }
}
