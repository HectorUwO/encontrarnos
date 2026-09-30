<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class AuthEmailsTest extends TestCase
{
    use RefreshDatabase;

    private function sentEmail(): Email
    {
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();

        return $messages->last()->getOriginalMessage();
    }

    public function test_verification_email_uses_the_branded_template(): void
    {
        $user = User::factory()->unverified()->create(['name' => 'Ana']);

        $user->notify(new VerifyEmailNotification);

        $email = $this->sentEmail();
        $html = $email->getHtmlBody();

        $this->assertStringContainsString('Verifica tu correo', $email->getSubject());
        $this->assertStringContainsString('Hola, Ana.', $html);
        $this->assertStringContainsString('/verify-email/'.$user->id, $html);
        $this->assertStringContainsString('#0a0a0a', $html);
        // El logo viaja dentro del correo, no depende de una URL pública.
        $this->assertStringContainsString('cid:', $html);
        $this->assertStringNotContainsString('3.png', $html);
        $this->assertNotEmpty($email->getTextBody());
        $this->assertStringContainsString('/verify-email/'.$user->id, $email->getTextBody());
        $this->assertSame('1', $email->getHeaders()->get('X-Priority')?->getBodyAsString()[0] ?? null);
    }

    public function test_password_reset_email_uses_the_branded_template(): void
    {
        $user = User::factory()->create(['name' => 'Ana']);

        $user->notify(new ResetPasswordNotification('token-abc'));

        $email = $this->sentEmail();

        $this->assertStringContainsString('Restablece tu contraseña', $email->getSubject());
        $this->assertStringContainsString('token-abc', $email->getHtmlBody());
        $this->assertStringContainsString('token-abc', $email->getTextBody());
        $this->assertStringContainsString('#0a0a0a', $email->getHtmlBody());
    }

    public function test_password_reset_request_sends_the_branded_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_resending_the_verification_link_notifies_the_user(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post('/email/verification-notification')
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }
}
