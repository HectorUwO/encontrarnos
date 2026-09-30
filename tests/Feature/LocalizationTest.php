<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->setLocale('es');
    }

    public function test_the_spanish_files_translate_every_message_of_the_framework(): void
    {
        $english = base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en');

        foreach (['auth', 'passwords', 'pagination', 'validation'] as $file) {
            // «custom» y «attributes» son espacios para que cada app agregue lo suyo.
            $missing = array_diff_key(
                Arr::dot(Arr::except(require "{$english}/{$file}.php", ['custom', 'attributes'])),
                Arr::dot(Arr::except(require lang_path("es/{$file}.php"), ['custom', 'attributes'])),
            );

            $this->assertSame([], array_keys($missing), "Faltan traducciones en lang/es/{$file}.php");
        }
    }

    public function test_a_failed_login_is_explained_in_spanish(): void
    {
        $this->post(route('login'), ['email' => 'nadie@example.test', 'password' => 'clave-incorrecta'])
            ->assertSessionHasErrors(['email' => 'Estas credenciales no coinciden con nuestros registros.']);
    }

    public function test_validation_messages_call_the_fields_by_their_spanish_name(): void
    {
        $this->post(route('login'), [])
            ->assertSessionHasErrors([
                'email' => 'El campo correo electrónico es obligatorio.',
                'password' => 'El campo contraseña es obligatorio.',
            ]);
    }
}
