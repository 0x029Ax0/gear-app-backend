<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_public_landing_page_is_available(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Gear Tracker')
            ->assertSee('Explore the API');
    }

    public function test_openapi_document_is_publicly_available(): void
    {
        $this->get('/docs/openapi.yaml')
            ->assertOk()
            ->assertHeader('content-type', 'text/yaml; charset=UTF-8')
            ->assertSee('openapi: 3.0.3');
    }
}
