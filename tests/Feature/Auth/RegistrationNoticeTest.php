<?php

namespace Tests\Feature\Auth;

use App\Listeners\NotifyAdminOfRegistration;
use App\Mail\NewUserMail;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class RegistrationNoticeTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $email = 'nueva@example.com'): void
    {
        $this->post('/register', [
            'name' => 'Laura Pérez',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
    }

    /**
     * @return list<Email>
     */
    private function sentTo(string $address): array
    {
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();

        return $messages
            ->map(fn ($sent) => $sent->getOriginalMessage())
            ->filter(fn ($email) => $email->getTo()[0]->getAddress() === $address)
            ->values()
            ->all();
    }

    public function test_admin_is_emailed_when_someone_registers(): void
    {
        config(['mail.admin_notification' => 'admin@example.com']);

        $this->register();

        $emails = $this->sentTo('admin@example.com');
        $this->assertCount(1, $emails);
        $this->assertStringContainsString('Laura Pérez', $emails[0]->getSubject());
        $this->assertStringContainsString('nueva@example.com', $emails[0]->getHtmlBody());
        $this->assertStringContainsString('nueva@example.com', $emails[0]->getTextBody());
        $this->assertStringContainsString('#0a0a0a', $emails[0]->getHtmlBody());
        $this->assertStringNotContainsString('Hola.', $emails[0]->getHtmlBody());
    }

    public function test_the_new_user_still_gets_their_own_verification_email(): void
    {
        config(['mail.admin_notification' => 'admin@example.com']);

        $this->register();

        $this->assertCount(1, $this->sentTo('nueva@example.com'));
    }

    public function test_no_notice_is_sent_when_no_address_is_configured(): void
    {
        config(['mail.admin_notification' => null]);

        $this->register();

        $this->assertCount(0, $this->sentTo('admin@example.com'));
        $this->assertAuthenticated();
    }

    public function test_a_failed_notice_never_blocks_the_registration(): void
    {
        config(['mail.admin_notification' => 'admin@example.com']);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Resend caído'));

        (new NotifyAdminOfRegistration)->handle(new Registered(User::factory()->create()));

        $this->assertTrue(true);
    }

    public function test_the_notice_mailable_renders(): void
    {
        $user = User::factory()->create(['name' => 'Ana']);

        $html = (new NewUserMail($user))->render();

        $this->assertStringContainsString('Ana', $html);
        $this->assertStringContainsString('admin/usuarios', $html);
    }
}
