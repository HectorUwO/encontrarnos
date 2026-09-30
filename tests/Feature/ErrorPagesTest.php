<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    #[DataProvider('errorPages')]
    public function test_renders_branded_html_errors_without_vite(int $status, string $title): void
    {
        config(['app.debug' => false]);
        $this->withoutVite();
        Route::get('/_test/error', fn (): never => throw new HttpException($status, 'Private exception details'));

        $this->get('/_test/error')
            ->assertStatus($status)
            ->assertSee($title)
            ->assertSee('ERROR '.$status)
            ->assertSee('Consultar la base de datos')
            ->assertSee('noindex, nofollow')
            ->assertDontSee('Private exception details')
            ->assertDontSee('resources/js/app.tsx')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    #[DataProvider('errorPages')]
    public function test_returns_inertia_error_pages_with_the_original_status(int $status, string $title): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/error', fn (): never => throw new HttpException($status, 'Private exception details'));

        $this->get('/_test/error', ['X-Inertia' => 'true', 'Accept' => 'text/html'])
            ->assertStatus($status)
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Error')
            ->assertJsonPath('props.status', $status)
            ->assertJsonPath('props.title', $title)
            ->assertJsonStructure(['props' => ['description']])
            ->assertDontSee('Private exception details');
    }

    public function test_unknown_urls_show_the_custom_404_page(): void
    {
        $this->get('/esta-pagina-no-existe')
            ->assertNotFound()
            ->assertSee('Esta página no está aquí.');
    }

    public function test_json_requests_remain_json_even_with_an_inertia_header(): void
    {
        $this->getJson('/esta-pagina-no-existe', ['X-Inertia' => 'true'])
            ->assertNotFound()
            ->assertJsonStructure(['message'])
            ->assertHeaderMissing('X-Inertia')
            ->assertDontSee('error-page');
    }

    public function test_api_errors_remain_json_without_an_accept_header(): void
    {
        $this->get('/api/esta-pagina-no-existe')
            ->assertNotFound()
            ->assertJsonStructure(['message'])
            ->assertDontSee('error-page');
    }

    public function test_unexpected_server_failures_hide_details_when_debug_is_disabled(): void
    {
        config(['app.debug' => false]);
        Exceptions::fake();
        Route::get('/_test/error', fn (): never => throw new RuntimeException('Private database credentials'));

        $this->get('/_test/error')
            ->assertInternalServerError()
            ->assertSee('Algo no salió como esperábamos.')
            ->assertDontSee('Private database credentials');
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_debug_server_failures_keep_the_developer_response(): void
    {
        config(['app.debug' => true]);
        Route::get('/_test/error', fn (): never => throw new HttpException(500, 'Diagnostic error'));

        $this->get('/_test/error', ['X-Inertia' => 'true', 'Accept' => 'text/html'])
            ->assertInternalServerError()
            ->assertHeaderMissing('X-Inertia');
    }

    #[DataProvider('errorHeaders')]
    public function test_inertia_errors_preserve_retry_and_method_headers(int $status, string $header, string $value): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/error', fn (): never => throw new HttpException($status, '', null, [$header => $value]));

        $this->get('/_test/error', ['X-Inertia' => 'true', 'Accept' => 'text/html'])
            ->assertStatus($status)
            ->assertHeader($header, $value);
    }

    /** @return array<string, array{int, string}> */
    public static function errorPages(): array
    {
        return [
            'authentication' => [401, 'Necesitas iniciar sesión.'],
            'forbidden' => [403, 'No tienes acceso a esta página.'],
            'not found' => [404, 'Esta página no está aquí.'],
            'method not allowed' => [405, 'Esta acción no está disponible.'],
            'gone' => [410, 'Esta página ya no está disponible.'],
            'expired session' => [419, 'Tu sesión ha caducado.'],
            'too many requests' => [429, 'Un momento, por favor.'],
            'server error' => [500, 'Algo no salió como esperábamos.'],
            'unavailable' => [503, 'Volvemos en un momento.'],
            'client fallback' => [418, 'No pudimos completar tu solicitud.'],
            'server fallback' => [502, 'Hay un problema temporal.'],
        ];
    }

    /** @return array<string, array{int, string, string}> */
    public static function errorHeaders(): array
    {
        return [
            'rate limit' => [429, 'Retry-After', '60'],
            'maintenance' => [503, 'Retry-After', '120'],
            'method' => [405, 'Allow', 'GET, HEAD'],
            'authentication' => [401, 'WWW-Authenticate', 'Basic realm="private"'],
        ];
    }
}
