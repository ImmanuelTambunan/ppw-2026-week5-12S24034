<?php

namespace Tests\Feature;

use App\Models\Buku;
use App\Models\Kategori;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BukuReferensiTest extends TestCase
{
    use RefreshDatabase;

    private Kategori $kategori;
    private Buku $buku;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kategori = Kategori::factory()->create();
        $this->buku = Buku::factory()->create([
            'isbn' => '9780132350884',
            'judul' => 'Clean Code',
            'kategori_id' => $this->kategori->id,
        ]);
    }

    public function test_referensi_eksternal_returns_metadata_when_found(): void
    {
        Http::fake([
            'https://openlibrary.org/api/books*' => Http::response([
                'ISBN:9780132350884' => [
                    'title' => 'Clean Code',
                    'subtitle' => 'A Handbook of Agile Software Craftsmanship',
                    'authors' => [
                        ['name' => 'Robert C. Martin'],
                    ],
                    'publishers' => [
                        ['name' => 'Prentice Hall'],
                    ],
                    'publish_date' => 'August 1, 2008',
                    'number_of_pages' => 464,
                    'url' => 'https://openlibrary.org/books/OL12345M',
                    'cover' => [
                        'large' => 'https://covers.openlibrary.org/b/id/12345-L.jpg',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson("/api/v1/bukus/{$this->buku->id}/referensi-eksternal");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'status_eksternal' => 'FOUND',
                'sumber' => 'OpenLibrary REST API',
                'metadata_eksternal' => [
                    'title' => 'Clean Code',
                    'number_of_pages' => 464,
                    'authors' => ['Robert C. Martin'],
                ],
            ],
        ]);
    }

    public function test_referensi_eksternal_handles_not_found_in_openlibrary(): void
    {
        Http::fake([
            'https://openlibrary.org/api/books*' => Http::response([], 200),
        ]);

        $response = $this->getJson("/api/v1/bukus/{$this->buku->id}/referensi-eksternal");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'status_eksternal' => 'NOT_FOUND',
                'metadata_eksternal' => null,
            ],
        ]);
    }

    public function test_referensi_eksternal_handles_upstream_failure_with_502(): void
    {
        Http::fake([
            'https://openlibrary.org/api/books*' => Http::response('Server Error', 500),
        ]);

        $response = $this->getJson("/api/v1/bukus/{$this->buku->id}/referensi-eksternal");

        $response->assertStatus(502);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJson([
            'title' => 'Bad Gateway',
            'status' => 502,
        ]);
    }

    public function test_referensi_eksternal_returns_404_for_invalid_local_book_id(): void
    {
        $response = $this->getJson('/api/v1/bukus/999999/referensi-eksternal');

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
    }
}
