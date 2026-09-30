<?php

namespace Tests\Feature;

use App\Mail\NoticeMail;
use App\Models\InformationReport;
use App\Models\PersonRecord;
use App\Models\PersonRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class InformationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.admin_notification' => 'admin@example.com']);
    }

    /**
     * @return list<Email>
     */
    private function sentTo(string $address): array
    {
        return Mail::mailer('array')->getSymfonyTransport()->messages()
            ->map(fn ($sent) => $sent->getOriginalMessage())
            ->filter(fn (Email $email) => $email->getTo()[0]->getAddress() === $address)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function newRequest(): array
    {
        return [
            'type' => 'search',
            'name' => 'Ana Torres',
            'state' => 'nayarit',
            'municipality' => 'Tepic',
            'description' => 'Cabello negro y cicatriz en la ceja.',
            'contact_email' => 'familia@example.com',
        ];
    }

    public function test_the_admin_is_emailed_when_a_request_is_submitted(): void
    {
        $this->post(route('requests.store'), $this->newRequest());

        $emails = $this->sentTo('admin@example.com');
        $this->assertCount(1, $emails);
        $this->assertStringContainsString('Nueva solicitud pendiente', $emails[0]->getSubject());
        $this->assertStringContainsString('Ana Torres', $emails[0]->getHtmlBody());
        $this->assertStringContainsString('/admin/solicitudes', $emails[0]->getHtmlBody());
    }

    public function test_approving_a_request_publishes_it_and_emails_the_requester_once(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $request = PersonRequest::factory()->create(['contact_email' => 'familia@example.com']);

        $this->actingAs($admin)->patch(route('admin.requests.update', $request), ['status' => 'approved'])->assertRedirect();
        // Repetir la misma decisión no vuelve a avisar.
        $this->actingAs($admin)->patch(route('admin.requests.update', $request), ['status' => 'approved']);

        $this->assertSame('approved', $request->fresh()->status->value);
        $emails = $this->sentTo('familia@example.com');
        $this->assertCount(1, $emails);
        $this->assertStringContainsString('ya está publicada', $emails[0]->getSubject());
        $this->assertStringContainsString(route('requests.show', $request), $emails[0]->getHtmlBody());
    }

    public function test_rejecting_a_request_emails_the_requester(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $request = PersonRequest::factory()->create(['contact_email' => 'familia@example.com']);

        $this->actingAs($admin)->patch(route('admin.requests.update', $request), ['status' => 'rejected']);

        $this->assertStringContainsString('Sobre tu solicitud', $this->sentTo('familia@example.com')[0]->getSubject());
    }

    public function test_only_admins_can_review_requests(): void
    {
        $request = PersonRequest::factory()->create();

        $this->actingAs(User::factory()->create())->patch(route('admin.requests.update', $request), ['status' => 'approved'])->assertForbidden();
        $this->assertSame('pending', $request->fresh()->status->value);
        $this->actingAs(User::factory()->create())->get(route('admin.requests'))->assertForbidden();
    }

    public function test_admin_review_page_lists_pending_requests_with_their_contact(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $request = PersonRequest::factory()->create(['contact_email' => 'familia@example.com']);
        PersonRequest::factory()->approved()->create();

        $this->withoutVite()->actingAs($admin)->get(route('admin.requests'))
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Requests')
                ->has('requests.data', 1)
                ->where("requests.contacts.{$request->id}.email", 'familia@example.com')
                ->where('counts.pending', 1)
                ->where('counts.approved', 1));
    }

    public function test_someone_with_information_writes_to_the_requester_and_the_admin_gets_a_copy(): void
    {
        $request = PersonRequest::factory()->approved()->create(['name' => 'Ana Torres', 'contact_email' => 'familia@example.com']);
        $sender = User::factory()->create(['name' => 'Luis Vega', 'email' => 'luis@example.com']);

        $this->actingAs($sender)->post(route('requests.offer', $request), [
            'message' => 'La vi ayer en la central de autobuses de Tepic.',
            'phone' => '311 555 0000',
        ])->assertRedirect()->assertSessionHas('status', 'information-sent');

        $toFamily = $this->sentTo('familia@example.com');
        $this->assertCount(1, $toFamily);
        $this->assertStringContainsString('central de autobuses', $toFamily[0]->getHtmlBody());
        $this->assertStringContainsString('luis@example.com', $toFamily[0]->getHtmlBody());
        $this->assertSame('luis@example.com', $toFamily[0]->getReplyTo()[0]->getAddress());

        $toAdmin = $this->sentTo('admin@example.com');
        $this->assertCount(1, $toAdmin);
        $this->assertStringContainsString('Información recibida', $toAdmin[0]->getSubject());
        $this->assertStringContainsString('familia@example.com', $toAdmin[0]->getHtmlBody());

        $this->assertDatabaseHas('information_reports', [
            'user_id' => $sender->id,
            'person_request_id' => $request->id,
            'phone' => '311 555 0000',
        ]);
    }

    public function test_offering_information_requires_a_verified_account(): void
    {
        $request = PersonRequest::factory()->approved()->create();
        $payload = ['message' => 'Tengo información importante.'];

        $this->post(route('requests.offer', $request), $payload)->assertRedirect('/login');
        $this->actingAs(User::factory()->unverified()->create())->post(route('requests.offer', $request), $payload)
            ->assertRedirect(route('verification.notice'));

        $this->assertSame(0, InformationReport::count());
    }

    public function test_information_cannot_be_offered_on_an_unpublished_request(): void
    {
        $request = PersonRequest::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('requests.offer', $request), ['message' => 'Tengo información importante.'])
            ->assertNotFound();
    }

    public function test_the_message_is_validated(): void
    {
        $request = PersonRequest::factory()->approved()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('requests.offer', $request), [])
            ->assertInvalid(['message' => 'Escribe la información que quieres compartir.']);
        $this->actingAs($user)->post(route('requests.offer', $request), ['message' => 'corto'])
            ->assertInvalid('message');
        $this->actingAs($user)->post(route('requests.offer', $request), ['message' => 'Tengo información.', 'phone' => 'abc'])
            ->assertInvalid(['phone' => 'Escribe un teléfono válido.']);
    }

    public function test_offers_are_limited_per_hour(): void
    {
        $request = PersonRequest::factory()->approved()->create();
        $user = User::factory()->create();

        foreach (range(1, 8) as $attempt) {
            $this->actingAs($user)->post(route('requests.offer', $request), ['message' => 'Tengo información número '.$attempt])->assertRedirect();
        }

        $this->actingAs($user)->post(route('requests.offer', $request), ['message' => 'Tengo un mensaje más'])->assertTooManyRequests();
    }

    public function test_information_about_a_missing_person_goes_to_the_admin(): void
    {
        $record = PersonRecord::factory()->create(['folio' => 'EN-000123', 'name' => 'MARIA LOPEZ', 'authority' => 'Fiscalía de Jalisco']);
        $sender = User::factory()->create(['name' => 'Luis Vega']);

        $this->actingAs($sender)->post(route('records.information', $record), ['message' => 'Creo haberla visto en Guadalajara.'])
            ->assertRedirect()->assertSessionHas('status', 'information-sent');

        $emails = $this->sentTo('admin@example.com');
        $this->assertCount(1, $emails);
        $this->assertStringContainsString('EN-000123', $emails[0]->getSubject());
        $this->assertStringContainsString('Creo haberla visto en Guadalajara.', $emails[0]->getHtmlBody());
        $this->assertStringContainsString('Fiscalía de Jalisco', $emails[0]->getHtmlBody());
        $this->assertDatabaseHas('information_reports', ['user_id' => $sender->id, 'person_record_id' => $record->id]);
    }

    public function test_information_about_a_missing_person_requires_a_verified_account_and_a_public_record(): void
    {
        $record = PersonRecord::factory()->create();
        $hidden = PersonRecord::factory()->unpublished()->create();
        $payload = ['message' => 'Tengo información importante.'];

        $this->post(route('records.information', $record), $payload)->assertRedirect('/login');
        $this->actingAs(User::factory()->unverified()->create())->post(route('records.information', $record), $payload)
            ->assertRedirect(route('verification.notice'));
        $this->actingAs(User::factory()->create())->post(route('records.information', $hidden), $payload)->assertNotFound();

        $this->assertSame(0, InformationReport::count());
    }

    public function test_admin_sees_the_information_received(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $record = PersonRecord::factory()->create(['folio' => 'EN-000123']);
        InformationReport::create(['user_id' => User::factory()->create()->id, 'person_record_id' => $record->id, 'message' => 'La vi en el centro.']);

        $this->withoutVite()->actingAs($admin)->get(route('admin.information'))
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Information')
                ->has('reports.data', 1)
                ->where('reports.data.0.message', 'La vi en el centro.')
                ->where('reports.data.0.target.kind', 'Ficha de desaparecido'));

        $this->actingAs(User::factory()->create())->get(route('admin.information'))->assertForbidden();
    }

    public function test_a_failing_mail_never_breaks_the_flow(): void
    {
        $request = PersonRequest::factory()->approved()->create();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Resend caído'));

        $this->actingAs(User::factory()->create())->post(route('requests.offer', $request), ['message' => 'Tengo información importante.'])
            ->assertRedirect()->assertSessionHas('status', 'information-sent');

        $this->assertSame(1, InformationReport::count());
    }

    public function test_the_notice_template_renders_both_headline_lines(): void
    {
        $html = (new NoticeMail('Asunto', 'AVISO', 'Primera|segunda.', ['Un párrafo.'], 'Abrir', 'https://encontrarnos.lat'))->render();

        $this->assertStringContainsString('Primera', $html);
        $this->assertStringContainsString('segunda.', $html);
        $this->assertStringContainsString('https://encontrarnos.lat', $html);
    }
}
