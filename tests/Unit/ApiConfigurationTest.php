<?php

namespace Tests\Unit;

use App\Models\User;
use Laravel\Sanctum\HasApiTokens;
use Tests\TestCase;

class ApiConfigurationTest extends TestCase
{
    public function test_postgresql_and_runtime_defaults_are_configured(): void
    {
        $this->assertSame('pgsql', config('database.connections.pgsql.driver'));
        $this->assertSame('5432', (string) config('database.connections.pgsql.port'));
        $this->assertSame('database', config('queue.connections.database.driver'));
        $this->assertArrayHasKey('public', config('filesystems.disks'));
    }

    public function test_user_supports_sanctum_personal_access_tokens(): void
    {
        $traits = class_uses_recursive(User::class);

        $this->assertArrayHasKey(HasApiTokens::class, $traits);
    }
}
