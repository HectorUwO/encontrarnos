<?php

namespace Tests\Feature;

use App\Jobs\SyncPersonFace;
use App\Models\PersonRecord;
use App\Models\PersonRequest;
use App\Services\PhotoSearch\FaceIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompreFaceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    public function test_searches_both_catalogs_in_similarity_order_without_exposing_contacts(): void
    {
        $record = PersonRecord::factory()->withPhoto()->create();
        $request = PersonRequest::factory()->approved()->withPhoto()->create(['contact_email' => 'private@example.com']);
        $this->enable();
        Http::fake(['http://compreface.test/api/v1/recognition/recognize*' => Http::response([
            'result' => [['subjects' => [
                ['subject' => FaceIndexer::subject($record), 'similarity' => 0.92],
                ['subject' => FaceIndexer::subject($request), 'similarity' => 0.97],
            ]]],
        ])]);

        $this->post(route('photo-search.store'), ['photo' => UploadedFile::fake()->image('face.png')])
            ->assertInertia(fn (Assert $page) => $page
                ->where('search.available', true)
                ->has('search.matches', 2)
                ->where('search.matches.0.source', 'request')
                ->where('search.matches.0.similarity', 0.97)
                ->where('search.matches.0.url', route('requests.show', $request, absolute: false))
                ->missing('search.matches.0.contact_email')
                ->where('search.matches.1.folio', $record->folio));
        Http::assertSent(fn (Request $sent): bool => $sent->hasHeader('x-api-key', 'server-only-key')
            && $sent->hasFile('file') && str_contains($sent->url(), 'prediction_count=100'));
    }

    public function test_excludes_unpublished_closed_pending_stale_and_low_similarity_subjects(): void
    {
        $hidden = PersonRecord::factory()->unpublished()->withPhoto()->create();
        $closed = PersonRequest::factory()->approved()->withPhoto()->create(['closed_at' => now()]);
        $pending = PersonRequest::factory()->withPhoto()->create();
        $rejected = PersonRequest::factory()->rejected()->withPhoto()->create();
        $record = PersonRecord::factory()->withPhoto()->create();
        $this->enable();
        Http::fake(['http://compreface.test/api/v1/recognition/recognize*' => Http::response([
            'result' => [['subjects' => [
                ['subject' => FaceIndexer::subject($hidden), 'similarity' => 0.99],
                ['subject' => FaceIndexer::subject($closed), 'similarity' => 0.99],
                ['subject' => FaceIndexer::subject($pending), 'similarity' => 0.99],
                ['subject' => FaceIndexer::subject($rejected), 'similarity' => 0.99],
                ['subject' => FaceIndexer::subject($record, 'old-photo.jpg'), 'similarity' => 0.99],
                ['subject' => FaceIndexer::subject($record), 'similarity' => 0.29999],
                ['subject' => 'unknown-person', 'similarity' => 0.99],
            ]]],
        ])]);

        $this->post(route('photo-search.store'), ['photo' => UploadedFile::fake()->image('face.png')])
            ->assertInertia(fn (Assert $page) => $page->where('search.available', true)
                ->has('search.matches', 0)->has('search.other_matches', 0));
        Http::assertSentCount(1);
    }

    public function test_accepts_threshold_boundary_and_deduplicates_subjects(): void
    {
        $record = PersonRecord::factory()->withPhoto()->create();
        $this->enable();
        Http::fake(['http://compreface.test/api/v1/recognition/recognize*' => Http::response([
            'result' => [['subjects' => [
                ['subject' => FaceIndexer::subject($record), 'similarity' => 0.85],
                ['subject' => FaceIndexer::subject($record), 'similarity' => 0.85],
            ]]],
        ])]);

        $this->post(route('photo-search.store'), ['photo' => UploadedFile::fake()->image('face.png')])
            ->assertInertia(fn (Assert $page) => $page->has('search.matches', 1)->where('search.matches.0.similarity', 0.85));
        Http::assertSentCount(1);
    }

    public function test_no_face_response_returns_a_helpful_validation_error(): void
    {
        $this->enable();
        Http::fake(['http://compreface.test/api/v1/recognition/recognize*' => Http::response(['code' => 28], 400)]);

        $this->post(route('photo-search.store'), ['photo' => UploadedFile::fake()->image('face.png')])
            ->assertInvalid(['photo' => 'No se detectó un único rostro.']);
        Http::assertSentCount(1);
    }

    public function test_groups_similarity_boundaries_without_rounding_low_scores_into_the_primary_results(): void
    {
        $records = PersonRecord::factory()->count(5)->withPhoto()->create();
        $this->enable();
        Http::fake(['http://compreface.test/api/v1/recognition/recognize*' => Http::response([
            'result' => [['subjects' => [
                ['subject' => FaceIndexer::subject($records[0]), 'similarity' => 0.30],
                ['subject' => FaceIndexer::subject($records[1]), 'similarity' => 0.29999],
                ['subject' => FaceIndexer::subject($records[2]), 'similarity' => 0.80],
                ['subject' => FaceIndexer::subject($records[3]), 'similarity' => 0.80001],
                ['subject' => FaceIndexer::subject($records[4]), 'similarity' => 1.1],
            ]]],
        ])]);

        $this->post(route('photo-search.store'), ['photo' => UploadedFile::fake()->image('face.png')])
            ->assertInertia(fn (Assert $page) => $page
                ->has('search.matches', 1)
                ->where('search.matches.0.folio', $records[3]->folio)
                ->where('search.matches.0.similarity', 0.80001)
                ->has('search.other_matches', 2)
                ->where('search.other_matches.0.folio', $records[2]->folio)
                ->where('search.other_matches.0.similarity', 0.80)
                ->where('search.other_matches.1.folio', $records[0]->folio)
                ->where('search.other_matches.1.similarity', 0.30));
        Http::assertSentCount(1);
    }

    public function test_returns_ten_primary_matches_and_ninety_distinct_additional_matches_at_most(): void
    {
        $records = PersonRecord::factory()->count(104)->withPhoto()->create();
        $predictions = $records->map(fn (PersonRecord $record, int $index): array => [
            'subject' => FaceIndexer::subject($record),
            'similarity' => 0.99 - ($index * 0.006),
        ])->reverse()->values()->all();
        $this->enable();
        Http::fake(['http://compreface.test/api/v1/recognition/recognize*' => Http::response([
            'result' => [['subjects' => $predictions]],
        ])]);

        $this->post(route('photo-search.store'), ['photo' => UploadedFile::fake()->image('face.png')])
            ->assertInertia(fn (Assert $page) => $page
                ->has('search.matches', 10)
                ->where('search.matches.0.folio', $records[0]->folio)
                ->where('search.matches.9.folio', $records[9]->folio)
                ->has('search.other_matches', 90)
                ->where('search.other_matches.0.folio', $records[10]->folio)
                ->where('search.other_matches.89.folio', $records[99]->folio)
                ->missing('search.other_matches.0.contact_email'));
        Http::assertSentCount(1);
    }

    public function test_multiple_faces_are_rejected_instead_of_selecting_one_person(): void
    {
        $this->enable();
        Http::fake(['http://compreface.test/api/v1/recognition/recognize*' => Http::response([
            'result' => [['subjects' => []], ['subjects' => []]],
        ])]);

        $this->post(route('photo-search.store'), ['photo' => UploadedFile::fake()->image('face.png')])
            ->assertInvalid(['photo' => 'Usa una fotografía con un solo rostro visible y bien iluminado.']);
        Http::assertSentCount(1);
    }

    public function test_connection_failure_is_reported_as_unavailable(): void
    {
        $this->enable();
        Http::fake(['http://compreface.test/api/v1/recognition/recognize*' => Http::failedConnection()]);

        $this->post(route('photo-search.store'), ['photo' => UploadedFile::fake()->image('face.png')])
            ->assertInertia(fn (Assert $page) => $page->where('search.available', false)->has('search.matches', 0));
    }

    public function test_invalid_service_key_does_not_expose_backend_errors(): void
    {
        $this->enable();
        Http::fake(['http://compreface.test/api/v1/recognition/recognize*' => Http::response(['message' => 'bad key'], 401)]);

        $this->post(route('photo-search.store'), ['photo' => UploadedFile::fake()->image('face.png')])
            ->assertInertia(fn (Assert $page) => $page->where('search.available', false)->has('search.matches', 0));
        Http::assertSentCount(1);
    }

    public function test_missing_key_does_not_send_the_uploaded_photo(): void
    {
        $this->enable();
        config(['services.compreface.key' => '']);

        $this->post(route('photo-search.store'), ['photo' => UploadedFile::fake()->image('face.png')])
            ->assertInertia(fn (Assert $page) => $page->where('search.available', false));
        Http::assertNothingSent();
    }

    public function test_queries_never_store_uploaded_photos(): void
    {
        Storage::fake('local');
        $this->enable();
        Http::fake(['http://compreface.test/api/v1/recognition/recognize*' => Http::response(['result' => [['subjects' => []]]])]);

        $this->post(route('photo-search.store'), ['photo' => UploadedFile::fake()->image('face.png')])
            ->assertInertia(fn (Assert $page) => $page->where('search.available', true)->has('search.matches', 0));

        $this->assertSame([], Storage::disk('local')->allFiles());
        Http::assertSentCount(1);
    }

    public function test_indexing_replaces_the_previous_photo_and_registers_the_new_subject(): void
    {
        Storage::fake('record_photos');
        $record = PersonRecord::factory()->withPhoto()->create();
        Storage::disk('record_photos')->put($record->photo_path, UploadedFile::fake()->image('face.jpg')->getContent());
        $subject = FaceIndexer::subject($record);
        $previous = FaceIndexer::subject($record, 'previous.jpg');
        $this->enable();
        Http::fake([
            'http://compreface.test/api/v1/recognition/subjects/*' => Http::response([], 404),
            'http://compreface.test/api/v1/recognition/faces*' => Http::response(['image_id' => 'test-image']),
        ]);

        $indexed = $this->app->make(FaceIndexer::class)->sync('record', $record->id, $previous);

        $this->assertTrue($indexed);
        Http::assertSent(fn (Request $sent): bool => $sent->method() === 'DELETE' && str_ends_with($sent->url(), $previous));
        Http::assertSent(fn (Request $sent): bool => $sent->method() === 'POST'
            && str_contains($sent->url(), 'subject='.$subject) && $sent->hasFile('file'));
        Http::assertSentCount(3);
    }

    public function test_indexing_closed_request_removes_the_biometric_subject(): void
    {
        $person = PersonRequest::factory()->approved()->withPhoto()->create(['closed_at' => now()]);
        $this->enable();
        Http::fake(['http://compreface.test/api/v1/recognition/subjects/*' => Http::response([])]);

        $indexed = $this->app->make(FaceIndexer::class)->sync('request', $person->id);

        $this->assertFalse($indexed);
        Http::assertSent(fn (Request $sent): bool => $sent->method() === 'DELETE'
            && str_ends_with($sent->url(), FaceIndexer::subject($person)));
        Http::assertSentCount(1);
    }

    public function test_indexing_deleted_person_removes_the_previous_subject(): void
    {
        $this->enable();
        Http::fake(['http://compreface.test/api/v1/recognition/subjects/*' => Http::response([])]);

        $indexed = $this->app->make(FaceIndexer::class)->sync('record', 99, 'record-99-0123456789abcdef');

        $this->assertFalse($indexed);
        Http::assertSent(fn (Request $sent): bool => $sent->method() === 'DELETE'
            && str_ends_with($sent->url(), 'record-99-0123456789abcdef'));
    }

    public function test_new_photo_dispatches_a_job_on_the_faces_queue(): void
    {
        $this->enable();
        Queue::fake([SyncPersonFace::class]);

        $record = PersonRecord::factory()->withPhoto()->create();

        Queue::assertPushedOn('faces', SyncPersonFace::class, fn (SyncPersonFace $job): bool => $job->type === 'record' && $job->id === $record->id);
    }

    public function test_photo_change_dispatches_the_old_subject_for_removal(): void
    {
        $person = PersonRequest::factory()->approved()->withPhoto()->create();
        $previous = FaceIndexer::subject($person);
        $this->enable();
        Queue::fake([SyncPersonFace::class]);

        $person->update(['photo_path' => 'person-requests/new.jpg']);

        Queue::assertPushed(SyncPersonFace::class, fn (SyncPersonFace $job): bool => $job->type === 'request' && $job->id === $person->id && $job->previousSubject === $previous);
    }

    public function test_deleting_a_person_dispatches_biometric_removal(): void
    {
        $record = PersonRecord::factory()->withPhoto()->create();
        $subject = FaceIndexer::subject($record);
        $this->enable();
        Queue::fake([SyncPersonFace::class]);

        $record->delete();

        Queue::assertPushed(SyncPersonFace::class, fn (SyncPersonFace $job): bool => $job->previousSubject === $subject);
    }

    public function test_prune_removes_only_obsolete_application_subjects(): void
    {
        $record = PersonRecord::factory()->withPhoto()->create();
        $hidden = PersonRecord::factory()->unpublished()->withPhoto()->create();
        $this->enable();
        Http::fake([
            'http://compreface.test/api/v1/recognition/subjects' => Http::response([
                'subjects' => [FaceIndexer::subject($record), FaceIndexer::subject($hidden), 'external-subject'],
            ]),
            'http://compreface.test/api/v1/recognition/subjects/*' => Http::response([]),
        ]);

        $removed = $this->app->make(FaceIndexer::class)->prune();

        $this->assertSame(1, $removed);
        Http::assertSent(fn (Request $sent): bool => $sent->method() === 'DELETE'
            && str_ends_with($sent->url(), FaceIndexer::subject($hidden)));
        Http::assertSentCount(2);
    }

    public function test_record_catalog_limit_counts_successful_faces_and_reports_timings(): void
    {
        Storage::fake('record_photos');
        $records = PersonRecord::factory()->withPhoto()->count(3)->create();
        $request = PersonRequest::factory()->approved()->withPhoto()->create();
        foreach ($records as $record) {
            Storage::disk('record_photos')->put($record->photo_path, UploadedFile::fake()->image('face.jpg')->getContent());
        }
        $this->enable();
        Http::fake([
            'http://compreface.test/api/v1/recognition/subjects' => Http::response(['subjects' => []]),
            'http://compreface.test/api/v1/recognition/subjects/*' => Http::response([], 404),
            'http://compreface.test/api/v1/recognition/faces*' => Http::sequence()
                ->push(['code' => 28], 400)
                ->push(['image_id' => 'first'], 201)
                ->push(['image_id' => 'second'], 201),
        ]);

        $this->artisan('faces:index', ['--catalog' => 'records', '--limit' => 2, '--timings' => true])
            ->expectsOutputToContain('Rostros indexados: 2. Retirados: 0. Fotos rechazadas: 1. Sin archivo: 0.')
            ->expectsOutputToContain('Indexación completa por imagen:')
            ->assertSuccessful();

        Http::assertNotSent(fn (Request $sent): bool => str_contains($sent->url(), FaceIndexer::subject($request)));
        Http::assertSentCount(7);
    }

    public function test_index_command_fails_if_not_enough_available_photos_exist(): void
    {
        Storage::fake('record_photos');
        PersonRecord::factory()->withPhoto()->create();
        $this->enable();
        Http::fake([
            'http://compreface.test/api/v1/recognition/subjects' => Http::response(['subjects' => []]),
            'http://compreface.test/api/v1/recognition/subjects/*' => Http::response([], 404),
        ]);

        $this->artisan('faces:index', ['--catalog' => 'records', '--limit' => 1])
            ->expectsOutputToContain('Rostros indexados: 0. Retirados: 0. Fotos rechazadas: 0. Sin archivo: 1.')
            ->assertFailed();

        Http::assertSentCount(2);
    }

    public function test_index_command_rejects_unknown_catalogs(): void
    {
        $this->enable();

        $this->artisan('faces:index', ['--catalog' => 'other'])->assertFailed();

        Http::assertNothingSent();
    }

    private function enable(): void
    {
        config([
            'services.compreface.enabled' => true,
            'services.compreface.host' => 'http://compreface.test',
            'services.compreface.key' => 'server-only-key',
        ]);
    }
}
