<?php

namespace Tests\Feature;

use Tests\TestCase;

class CanonicalHostTest extends TestCase
{
    public function test_domain_without_www_redirects_to_the_canonical_host_keeping_the_path(): void
    {
        config(['app.url' => 'https://www.example.test']);

        $this->get('http://example.test/base-de-datos?q=ana')
            ->assertRedirect('https://www.example.test/base-de-datos?q=ana')
            ->assertStatus(301);
    }

    public function test_other_hosts_are_left_alone(): void
    {
        config(['app.url' => 'https://www.example.test']);

        $this->get('http://www.example.test/up')->assertOk();
        $this->get('http://localhost/up')->assertOk();
    }

    public function test_hosts_are_left_alone_when_app_url_has_no_www(): void
    {
        config(['app.url' => 'http://localhost']);

        $this->get('http://localhost/up')->assertOk();
    }
}
