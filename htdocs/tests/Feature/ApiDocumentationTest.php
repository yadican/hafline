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

    public function test_field_haul_flow_documents_response_schemas(): void
    {
        $this->artisan('l5-swagger:generate')->assertSuccessful();

        $docs = $this->getJson('/docs')->assertOk()->json();
        $schemaRef = fn (string $path, string $method, string $status): ?string => $docs['paths'][$path][$method]['responses'][$status]['content']['application/json']['schema']['$ref'] ?? null;

        $this->assertSame('#/components/schemas/HaulOptionsResponse', $schemaRef('/api/v1/hauls/options', 'get', '200'));
        $this->assertSame('#/components/schemas/DumpSitesResponse', $schemaRef('/api/v1/hauls/dump-sites', 'get', '200'));
        $this->assertSame('#/components/schemas/FieldSessionResponse', $schemaRef('/api/v1/field-sessions', 'post', '201'));
        $this->assertSame('#/components/schemas/CurrentFieldSessionResponse', $schemaRef('/api/v1/field-sessions/current', 'get', '200'));
        $this->assertSame('#/components/schemas/PlateScanResult', $schemaRef('/api/v1/hauls/scan', 'post', '200'));
        $this->assertSame('#/components/schemas/HaulTicketResponse', $schemaRef('/api/v1/hauls', 'post', '201'));
        $this->assertSame('#/components/schemas/HaulTicketResponse', $schemaRef('/api/v1/hauls/{haul}', 'get', '200'));
        $this->assertSame('uuid', $docs['components']['schemas']['User']['properties']['id']['format'] ?? null);
    }
}
