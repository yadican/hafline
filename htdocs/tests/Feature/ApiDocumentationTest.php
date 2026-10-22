<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    public function test_swagger_ui_is_available(): void
    {
        $this->get('/api/documentation')
            ->assertOk();
    }

    public function test_openapi_json_includes_v1_paths(): void
    {
        $this->artisan('l5-swagger:generate')->assertSuccessful();

        $docs = $this->getJson('/docs')->assertOk()->json();

        $this->assertSame('Hafline API', $docs['info']['title'] ?? null);
        $this->assertArrayHasKey('/api/v1/auth/login', $docs['paths'] ?? []);
        $this->assertArrayHasKey('/api/v1/me', $docs['paths'] ?? []);
        $this->assertArrayHasKey('/api/v1/dashboard', $docs['paths'] ?? []);
        $this->assertArrayHasKey('/api/v1/auth/logout', $docs['paths'] ?? []);
    }
}
