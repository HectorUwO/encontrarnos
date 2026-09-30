<?php

namespace Tests\Feature;

use App\Jobs\SendCampaignEmail;
use App\Mail\CampaignMail;
use App\Models\MailCampaign;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /**
     * @return array<string, string>
     */
    private function campaign(array $overrides = []): array
    {
        return [
            'audience' => 'verified',
            'subject' => 'Novedades',
            'heading' => 'Hola comunidad',
            'body' => "Primer párrafo.\n\nSegundo párrafo.",
            ...$overrides,
        ];
    }

    public function test_only_admins_can_open_the_admin_area(): void
    {
        $this->get('/admin')->assertRedirect('/login');

        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin/correos')->assertForbidden();
        $this->actingAs(User::factory()->create())->post('/admin/correos', $this->campaign())->assertForbidden();

        $this->actingAs($this->admin())->get('/admin')->assertOk();
        $this->actingAs($this->admin())->get('/admin/usuarios')->assertOk();
        $this->actingAs($this->admin())->get('/admin/correos')->assertOk();
    }

    public function test_unverified_admins_must_verify_their_email_first(): void
    {
        $admin = User::factory()->unverified()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin')->assertRedirect(route('verification.notice'));
    }

    public function test_admin_role_cannot_be_self_assigned_through_registration_or_profile(): void
    {
        $this->post('/register', [
            'name' => 'Test',
            'email' => 'nuevo@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'is_admin' => true,
        ]);
        $this->assertFalse(User::where('email', 'nuevo@example.com')->firstOrFail()->is_admin);

        $user = User::factory()->create();
        $this->actingAs($user)->patch('/profile', ['name' => 'X', 'email' => $user->email, 'is_admin' => true]);
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_admin_can_toggle_roles_but_not_their_own(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin)->patch("/admin/usuarios/{$user->id}/admin")->assertRedirect();
        $this->assertTrue($user->fresh()->is_admin);

        $this->actingAs($admin)->patch("/admin/usuarios/{$user->id}/admin");
        $this->assertFalse($user->fresh()->is_admin);

        $this->actingAs($admin)->patch("/admin/usuarios/{$admin->id}/admin")->assertForbidden();
        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_admin_can_verify_or_resend_verification_for_a_user(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $pending = User::factory()->unverified()->create();

        $this->actingAs($admin)->post("/admin/usuarios/{$pending->id}/reenviar");
        Notification::assertSentTo($pending, VerifyEmailNotification::class);

        $this->actingAs($admin)->post("/admin/usuarios/{$pending->id}/verificar");
        $this->assertTrue($pending->fresh()->hasVerifiedEmail());
    }

    public function test_user_search_filters_by_name_or_email(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Laura Pérez', 'email' => 'laura@example.com']);
        User::factory()->create(['name' => 'Otro', 'email' => 'otro@example.com']);

        $this->actingAs($admin)->get('/admin/usuarios?q=laura')
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Users')
                ->has('users.data', 1)
                ->where('users.data.0.email', 'laura@example.com'));
    }

    public function test_campaign_is_queued_once_per_verified_user(): void
    {
        Queue::fake();
        $admin = $this->admin();
        User::factory()->count(2)->create();
        User::factory()->unverified()->create();

        $this->actingAs($admin)->post('/admin/correos', $this->campaign())
            ->assertRedirect(route('admin.mail'));

        // Las dos cuentas verificadas y la administradora; la pendiente no recibe.
        Queue::assertPushed(SendCampaignEmail::class, 3);
        $campaign = MailCampaign::firstOrFail();
        $this->assertSame(3, $campaign->recipients_count);
        $this->assertSame($admin->id, $campaign->user_id);
    }

    public function test_campaign_emails_use_the_branded_template_and_count_as_sent(): void
    {
        $admin = $this->admin();
        $recipient = User::factory()->create(['name' => 'Ana', 'email' => 'ana@example.com']);

        $this->actingAs($admin)->post('/admin/correos', $this->campaign([
            'audience' => 'custom',
            'custom_emails' => 'ana@example.com',
            'button_label' => 'Ver más',
            'button_url' => 'https://encontrarnos.lat/novedades',
        ]));

        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $email = $messages->last()->getOriginalMessage();
        $html = $email->getHtmlBody();

        $this->assertSame('Novedades', $email->getSubject());
        $this->assertStringContainsString('Hola, Ana.', $html);
        $this->assertStringContainsString('Primer párrafo.', $html);
        $this->assertStringContainsString('Segundo párrafo.', $html);
        $this->assertStringContainsString('https://encontrarnos.lat/novedades', $html);
        $this->assertStringContainsString('#0a0a0a', $html);
        $this->assertStringContainsString('Segundo párrafo.', $email->getTextBody());
        $this->assertSame(1, MailCampaign::firstOrFail()->sent_count);
        $this->assertSame($recipient->email, $email->getTo()[0]->getAddress());
    }

    public function test_campaign_validation(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/correos', [])
            ->assertSessionHasErrors(['audience', 'subject', 'heading', 'body']);

        $this->actingAs($admin)->post('/admin/correos', $this->campaign(['audience' => 'custom', 'custom_emails' => 'no-es-correo']))
            ->assertSessionHasErrors('custom_emails');

        $this->actingAs($admin)->post('/admin/correos', $this->campaign(['button_label' => 'Ir']))
            ->assertSessionHasErrors('button_url');

        $this->actingAs($admin)->post('/admin/correos', $this->campaign(['button_label' => 'Ir', 'button_url' => 'javascript:alert(1)']))
            ->assertSessionHasErrors('button_url');

        $this->assertSame(0, MailCampaign::count());
    }

    public function test_preview_returns_the_rendered_email_without_sending_it(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->admin())->postJson('/admin/correos/vista-previa', $this->campaign());

        $response->assertOk();
        $this->assertStringContainsString('Hola comunidad', $response->json('html'));
        $this->assertStringContainsString('data:image/png;base64', $response->json('html'));
        Queue::assertNothingPushed();
        $this->assertSame(0, MailCampaign::count());
    }

    public function test_test_send_only_goes_to_the_admin(): void
    {
        $admin = $this->admin();
        User::factory()->count(2)->create();

        $this->actingAs($admin)->post('/admin/correos/prueba', $this->campaign())->assertRedirect();

        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertSame($admin->email, $messages->first()->getOriginalMessage()->getTo()[0]->getAddress());
        $this->assertSame(0, MailCampaign::count());
    }

    public function test_create_admin_command_creates_a_verified_admin(): void
    {
        $this->artisan('admin:create', ['email' => 'jefa@example.com', '--name' => 'Jefa', '--password' => 'secreta-123'])
            ->assertSuccessful();

        $user = User::where('email', 'jefa@example.com')->firstOrFail();
        $this->assertTrue($user->is_admin);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(password_verify('secreta-123', $user->password));

        // Convierte también una cuenta existente sin tocar su contraseña.
        $existing = User::factory()->unverified()->create();
        $hash = $existing->password;
        $this->artisan('admin:create', ['email' => $existing->email])->assertSuccessful();
        $this->assertTrue($existing->fresh()->is_admin);
        $this->assertSame($hash, $existing->fresh()->password);
    }

    public function test_campaign_mail_paragraph_parsing(): void
    {
        $this->assertSame(['a', 'b c'], CampaignMail::paragraphs("a\n\n\nb c\n\n"));
    }
}
