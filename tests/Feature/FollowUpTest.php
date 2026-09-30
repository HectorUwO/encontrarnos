<?php

namespace Tests\Feature;

use App\Models\InformationReport;
use App\Models\PersonRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class FollowUpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['mail.admin_notification' => 'admin@example.com']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'type' => 'search',
            'name' => 'Ana Torres',
            'state' => 'nayarit',
            'municipality' => 'Tepic',
            'description' => 'Descripción actualizada con más detalles.',
            'traits' => ['cabello' => 'Castaño'],
            'contact_email' => 'familia@example.com',
            ...$overrides,
        ];
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

    public function test_the_owner_can_open_the_edit_form_with_their_data(): void
    {
        $user = User::factory()->create();
        $request = PersonRequest::factory()->for($user)->create(['name' => 'Ana Torres', 'contact_email' => 'familia@example.com', 'traits' => ['cabello' => 'Negro']]);

        $this->actingAs($user)->get(route('mine.requests.edit', $request))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/SolicitudCrear')
                ->where('editing.name', 'Ana Torres')
                ->where('editing.contact_email', 'familia@example.com')
                ->where('editing.raw_traits.cabello', 'Negro')
                ->where('initialType', $request->type->value));
    }

    public function test_other_people_cannot_edit_close_reopen_or_delete_a_request(): void
    {
        $request = PersonRequest::factory()->approved()->for(User::factory())->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('mine.requests.edit', $request))->assertNotFound();
        $this->actingAs($stranger)->put(route('mine.requests.update', $request), $this->payload())->assertForbidden();
        $this->actingAs($stranger)->patch(route('mine.requests.close', $request), ['reason' => 'withdrawn'])->assertNotFound();
        $this->actingAs($stranger)->patch(route('mine.requests.reopen', $request))->assertNotFound();
        $this->actingAs($stranger)->delete(route('mine.requests.destroy', $request))->assertNotFound();

        $fresh = $request->fresh();
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->closed_at);
        $this->assertSame('approved', $fresh->status->value);
    }

    public function test_guests_are_sent_to_the_login(): void
    {
        $request = PersonRequest::factory()->create();

        $this->get(route('mine.requests.edit', $request))->assertRedirect('/login');
        $this->patch(route('mine.requests.close', $request), ['reason' => 'resolved'])->assertRedirect('/login');
    }

    public function test_editing_an_approved_request_updates_it_and_sends_it_back_to_review(): void
    {
        $user = User::factory()->create();
        $request = PersonRequest::factory()->approved()->for($user)->create(['name' => 'Ana', 'description' => 'Antes']);

        $this->actingAs($user)->put(route('mine.requests.update', $request), $this->payload())
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'request-updated-review');

        $request->refresh();
        $this->assertSame('Ana Torres', $request->name);
        $this->assertSame('Descripción actualizada con más detalles.', $request->description);
        $this->assertSame(['cabello' => 'Castaño'], $request->traits);
        $this->assertSame('Tepic, Nayarit', $request->place);
        $this->assertSame('pending', $request->status->value);

        $emails = $this->sentTo('admin@example.com');
        $this->assertCount(1, $emails);
        $this->assertStringContainsString('Solicitud editada', $emails[0]->getSubject());
    }

    public function test_editing_a_pending_request_does_not_notify_the_admin_again(): void
    {
        $user = User::factory()->create();
        $request = PersonRequest::factory()->for($user)->create();

        $this->actingAs($user)->put(route('mine.requests.update', $request), $this->payload())
            ->assertSessionHas('status', 'request-updated');

        $this->assertCount(0, $this->sentTo('admin@example.com'));
    }

    public function test_editing_validates_like_the_creation_form(): void
    {
        $user = User::factory()->create();
        $request = PersonRequest::factory()->for($user)->create(['name' => 'Ana']);

        $this->actingAs($user)->put(route('mine.requests.update', $request), $this->payload(['municipality' => '', 'contact_email' => 'x']))
            ->assertInvalid(['municipality', 'contact_email']);

        $this->assertSame('Ana', $request->fresh()->name);
    }

    public function test_the_photo_can_be_replaced_or_removed(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $request = PersonRequest::factory()->for($user)->create();
        Storage::disk('local')->put('person-requests/old.jpg', 'x');
        $request->update(['photo_path' => 'person-requests/old.jpg']);

        $this->actingAs($user)->put(route('mine.requests.update', $request), $this->payload([
            'photo' => UploadedFile::fake()->image('nueva.jpg'),
        ]));

        $request->refresh();
        Storage::disk('local')->assertMissing('person-requests/old.jpg');
        Storage::disk('local')->assertExists($request->photo_path);

        $newPath = $request->photo_path;
        $this->actingAs($user)->put(route('mine.requests.update', $request), $this->payload(['remove_photo' => true]));

        $this->assertNull($request->fresh()->photo_path);
        Storage::disk('local')->assertMissing($newPath);
    }

    public function test_a_request_can_be_taken_down_and_no_longer_appears_publicly(): void
    {
        $user = User::factory()->create();
        $request = PersonRequest::factory()->approved()->for($user)->create(['name' => 'Ana Torres']);

        $this->get(route('requests'))->assertInertia(fn (Assert $page) => $page->has('requests.data', 1));

        $this->actingAs($user)->patch(route('mine.requests.close', $request), ['reason' => 'withdrawn'])
            ->assertSessionHas('status', 'request-withdrawn');

        $request->refresh();
        $this->assertNotNull($request->closed_at);
        $this->assertSame('withdrawn', $request->closed_reason);
        $this->get(route('requests'))->assertInertia(fn (Assert $page) => $page->has('requests.data', 0)->where('totalPublished', 0));
    }

    public function test_a_closed_ficha_stays_visible_but_cannot_receive_information(): void
    {
        $request = PersonRequest::factory()->approved()->for(User::factory())->create(['closed_at' => now(), 'closed_reason' => 'resolved']);

        $this->get(route('requests.show', $request))
            ->assertInertia(fn (Assert $page) => $page->where('canOffer', false)->where('request.data.closed', true));

        $this->actingAs(User::factory()->create())
            ->post(route('requests.offer', $request), ['message' => 'Tengo información importante.'])
            ->assertNotFound();
    }

    public function test_a_request_can_be_marked_as_resolved_and_reopened(): void
    {
        $user = User::factory()->create();
        $request = PersonRequest::factory()->approved()->for($user)->create();

        $this->actingAs($user)->patch(route('mine.requests.close', $request), ['reason' => 'resolved'])
            ->assertSessionHas('status', 'request-resolved');
        $this->assertSame('resolved', $request->fresh()->closed_reason);

        $this->actingAs($user)->patch(route('mine.requests.reopen', $request))->assertSessionHas('status', 'request-reopened');

        $request->refresh();
        $this->assertNull($request->closed_at);
        $this->assertNull($request->closed_reason);
        $this->get(route('requests'))->assertInertia(fn (Assert $page) => $page->has('requests.data', 1));
    }

    public function test_closing_needs_a_valid_reason(): void
    {
        $user = User::factory()->create();
        $request = PersonRequest::factory()->approved()->for($user)->create();

        $this->actingAs($user)->patch(route('mine.requests.close', $request), ['reason' => 'otra'])->assertInvalid('reason');
        $this->assertNull($request->fresh()->closed_at);
    }

    public function test_a_request_can_be_deleted_together_with_its_photo(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Storage::disk('local')->put('person-requests/foto.jpg', 'x');
        $request = PersonRequest::factory()->for($user)->create(['photo_path' => 'person-requests/foto.jpg']);

        $this->actingAs($user)->delete(route('mine.requests.destroy', $request))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'request-deleted');

        $this->assertDatabaseMissing('person_requests', ['id' => $request->id]);
        Storage::disk('local')->assertMissing('person-requests/foto.jpg');
    }

    public function test_information_received_can_be_marked_as_attended_and_back(): void
    {
        $user = User::factory()->create();
        $request = PersonRequest::factory()->approved()->for($user)->create();
        $report = InformationReport::create(['user_id' => User::factory()->create()->id, 'person_request_id' => $request->id, 'message' => 'La vi ayer.']);

        $this->actingAs($user)->patch(route('mine.information.attend', $report))->assertSessionHas('status', 'information-attended');
        $this->assertNotNull($report->fresh()->attended_at);

        $this->actingAs($user)->patch(route('mine.information.attend', $report))->assertSessionHas('status', 'information-reopened');
        $this->assertNull($report->fresh()->attended_at);
    }

    public function test_information_about_other_peoples_requests_cannot_be_touched(): void
    {
        $request = PersonRequest::factory()->approved()->for(User::factory())->create();
        $report = InformationReport::create(['user_id' => User::factory()->create()->id, 'person_request_id' => $request->id, 'message' => 'Algo.']);

        $this->actingAs(User::factory()->create())->patch(route('mine.information.attend', $report))->assertNotFound();
        $this->assertNull($report->fresh()->attended_at);
    }

    public function test_a_guest_request_can_be_managed_by_the_account_with_the_same_verified_email(): void
    {
        $request = PersonRequest::factory()->approved()->create(['user_id' => null, 'contact_email' => 'familia@example.com']);
        $owner = User::factory()->create(['email' => 'familia@example.com']);
        $unverified = User::factory()->unverified()->create(['email' => 'otra@example.com']);

        $this->actingAs($owner)->patch(route('mine.requests.close', $request), ['reason' => 'withdrawn'])->assertRedirect();
        $this->assertNotNull($request->fresh()->closed_at);
        $this->assertNotNull($unverified->id);
    }
}
