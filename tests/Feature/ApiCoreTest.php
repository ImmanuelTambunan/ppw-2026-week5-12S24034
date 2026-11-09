<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_404_returns_rfc_7807_problem_details(): void
    {
        $response = $this->getJson('/api/v1/non-existent-endpoint');

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJsonStructure([
            'type',
            'title',
            'status',
            'detail',
            'instance',
        ]);
        $response->assertJson([
            'title' => 'Not Found',
            'status' => 404,
        ]);
    }

    public function test_api_unauthenticated_returns_rfc_7807_problem_details(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJsonStructure([
            'type',
            'title',
            'status',
            'detail',
            'instance',
        ]);
        $response->assertJson([
            'title' => 'Unauthorized',
            'status' => 401,
        ]);
    }
}
