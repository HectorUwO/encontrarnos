<?php

namespace Tests\Feature\Console\Commands;

use App\Models\PersonRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplyRecordPublicationTest extends TestCase
{
    use RefreshDatabase;

    private function seedRecords(): void
    {
        PersonRecord::factory()->create(['folio' => 'EN-000001', 'registry_publish' => 'SI']);
        PersonRecord::factory()->unpublished()->create(['folio' => 'EN-000002', 'registry_publish' => 'SIN DATO']);
        PersonRecord::factory()->unpublished()->create(['folio' => 'EN-000003', 'registry_publish' => 'NO']);
        PersonRecord::factory()->unpublished()->create(['folio' => 'EN-000004', 'registry_publish' => null]);
    }

    public function test_all_publishes_every_record_and_keeps_the_registry_value(): void
    {
        $this->seedRecords();

        $this->artisan('records:publication', ['mode' => 'all'])->assertSuccessful();

        $this->assertSame(4, PersonRecord::query()->published()->count());
        $this->assertSame(['NO', 'SI', 'SIN DATO'], PersonRecord::query()->whereNotNull('registry_publish')->orderBy('registry_publish')->pluck('registry_publish')->all());
    }

    public function test_registry_hides_what_the_registry_does_not_authorize_and_can_be_reversed(): void
    {
        $this->seedRecords();
        $this->artisan('records:publication', ['mode' => 'all'])->assertSuccessful();

        $this->artisan('records:publication', ['mode' => 'registry'])->assertSuccessful();

        $this->assertSame(['EN-000001'], PersonRecord::query()->published()->pluck('folio')->all());

        $this->artisan('records:publication', ['mode' => 'all'])->assertSuccessful();
        $this->assertSame(4, PersonRecord::query()->published()->count());
    }

    public function test_the_mode_defaults_to_the_configured_one(): void
    {
        config(['services.records_publish' => 'all']);
        $this->seedRecords();

        $this->artisan('records:publication')->assertSuccessful();

        $this->assertSame(4, PersonRecord::query()->published()->count());
    }

    public function test_rejects_an_unknown_mode(): void
    {
        $this->artisan('records:publication', ['mode' => 'nada'])->assertFailed();
    }

    public function test_the_published_count_is_refreshed(): void
    {
        $this->seedRecords();
        $this->assertSame(1, PersonRecord::publishedCount());

        $this->artisan('records:publication', ['mode' => 'all'])->assertSuccessful();

        $this->assertSame(4, PersonRecord::publishedCount());
    }
}
