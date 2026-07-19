<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_the_health_endpoint_returns_successful_response(): void
    {
        $this->get('/up')->assertOk();
    }
}
