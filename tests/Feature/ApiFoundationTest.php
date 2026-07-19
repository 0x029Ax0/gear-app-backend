<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiFoundationTest extends TestCase
{
    public function test_v1_health_endpoint_returns_json_data(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('data.status', 'ok');
    }

    public function test_unknown_api_routes_return_a_stable_not_found_error(): void
    {
        $response = $this->getJson('/api/v1/does-not-exist');

        $response->assertNotFound()
            ->assertJson([
                'code' => 'RESOURCE_NOT_FOUND',
                'message' => 'The requested resource was not found.',
            ])
            ->assertJsonMissingPath('exception');
    }

    public function test_api_routes_do_not_render_html_errors(): void
    {
        $response = $this->get('/api/v1/does-not-exist');

        $response->assertHeader('content-type', 'application/json');
    }
}
