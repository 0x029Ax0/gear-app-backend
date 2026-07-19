<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_server_rendered_root_route_is_not_registered(): void
    {
        $this->get('/')->assertNotFound();
    }
}
