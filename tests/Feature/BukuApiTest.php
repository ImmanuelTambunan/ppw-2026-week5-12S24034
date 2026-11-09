<?php

namespace Tests\Feature;

use App\Models\Buku;
use App\Models\Kategori;
use App\Models\Peminjaman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BukuApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private Kategori $kategori;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
        $this->kategori = Kategori::factory()->create();
    }

    public function test_get_bukus_index_returns_paginated_collection_with_hateoas(): void
    {
        Buku::factory()->count(6)->create(['kategori_id' => $this->kategori->id]);

        $response = $this->getJson('/api/v1/bukus?per_page=3');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'isbn',
                    'judul',
                    'penulis',
                    'penerbit',
                    'tahun_terbit',
                    'stok',
                    'status_ketersediaan',
                    '_links',
                ],
            ],
            'meta' => ['current_page', 'per_page', 'total_items', 'total_pages', 'links'],
        ]);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_get_bukus_search_and_filters(): void
    {
        Buku::factory()->create([
            'judul' => 'Sistem Terdistribusi Lanjutan',
            'kategori_id' => $this->kategori->id,
            'stok' => 5,
        ]);
        Buku::factory()->create([
            'judul' => 'Arsitektur Komputer',
            'kategori_id' => $this->kategori->id,
            'stok' => 0,
        ]);

        // Search test
        $searchRes = $this->getJson('/api/v1/bukus?search=Terdistribusi');
        $searchRes->assertStatus(200);
        $this->assertCount(1, $searchRes->json('data'));

        // Filter tersedia test
        $availRes = $this->getJson('/api/v1/bukus?tersedia=1');
        $availRes->assertStatus(200);
        $this->assertCount(1, $availRes->json('data'));
        $this->assertEquals('Sistem Terdistribusi Lanjutan', $availRes->json('data.0.judul'));
    }

    public function test_get_buku_detail_success_and_404(): void
    {
        $buku = Buku::factory()->create([
            'isbn' => '9781234567890',
            'kategori_id' => $this->kategori->id,
            'stok' => 4,
        ]);

        // Access via ID
        $resId = $this->getJson("/api/v1/bukus/{$buku->id}");
        $resId->assertStatus(200);
        $resId->assertJsonPath('data.isbn', '9781234567890');
        $this->assertArrayHasKey('pinjam_buku', $resId->json('data._links'));

        // Access via ISBN
        $resIsbn = $this->getJson("/api/v1/bukus/{$buku->isbn}");
        $resIsbn->assertStatus(200);

        // Access non-existent
        $res404 = $this->getJson('/api/v1/bukus/999999');
        $res404->assertStatus(404);
        $res404->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_post_buku_creates_resource_with_location_header(): void
    {
        $payload = [
            'isbn' => '9780132350884',
            'judul' => 'Clean Code',
            'penulis' => 'Robert C. Martin',
            'penerbit' => 'Prentice Hall',
            'tahun_terbit' => 2008,
            'kategori_id' => $this->kategori->id,
            'stok' => 10,
            'sinopsis' => 'Buku rekayasa perangkat lunak',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/bukus', $payload);

        $response->assertStatus(201);
        $this->assertNotNull($response->headers->get('Location'));
        $response->assertJsonPath('data.judul', 'Clean Code');
    }

    public function test_put_requires_full_replacement(): void
    {
        $buku = Buku::factory()->create([
            'kategori_id' => $this->kategori->id,
            'sinopsis' => 'Deskripsi lama',
        ]);

        // Missing fields in PUT -> 422
        $failRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/v1/bukus/{$buku->id}", [
                'stok' => 20,
            ]);
        $failRes->assertStatus(422);

        // Full replacement without sinopsis (resets sinopsis to null)
        $putPayload = [
            'isbn' => $buku->isbn,
            'judul' => 'Judul Baru Diganti Penuh',
            'penulis' => 'Penulis Baru',
            'penerbit' => 'Penerbit Baru',
            'tahun_terbit' => 2024,
            'kategori_id' => $this->kategori->id,
            'stok' => 25,
        ];

        $successRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/v1/bukus/{$buku->id}", $putPayload);

        $successRes->assertStatus(200);
        $successRes->assertJsonPath('data.judul', 'Judul Baru Diganti Penuh');
        $this->assertNull($successRes->json('data.sinopsis'));
    }

    public function test_patch_allows_partial_update(): void
    {
        $buku = Buku::factory()->create([
            'kategori_id' => $this->kategori->id,
            'judul' => 'Judul Asli Tidak Berubah',
            'stok' => 5,
        ]);

        // Empty PATCH -> 400 Bad Request
        $emptyRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/v1/bukus/{$buku->id}", []);
        $emptyRes->assertStatus(400);

        // Valid partial PATCH
        $patchRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/v1/bukus/{$buku->id}", [
                'stok' => 45,
            ]);

        $patchRes->assertStatus(200);
        $patchRes->assertJsonPath('data.stok', 45);
        $patchRes->assertJsonPath('data.judul', 'Judul Asli Tidak Berubah');
    }

    public function test_delete_buku_conflict_with_active_loans_and_success_204(): void
    {
        $buku = Buku::factory()->create(['kategori_id' => $this->kategori->id]);
        Peminjaman::create([
            'buku_id' => $buku->id,
            'nim_peminjam' => '12S24034',
            'nama_peminjam' => 'Immanuel Tambunan',
            'tanggal_pinjam' => now()->toDateString(),
            'durasi_hari' => 7,
            'status' => 'dipinjam',
        ]);

        // Attempt delete when active loan exists -> 409 Conflict
        $conflictRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/v1/bukus/{$buku->id}");
        $conflictRes->assertStatus(409);
        $conflictRes->assertHeader('Content-Type', 'application/problem+json');

        // Delete another book with no active loans -> 204 No Content
        $cleanBook = Buku::factory()->create(['kategori_id' => $this->kategori->id]);
        $successRes = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/v1/bukus/{$cleanBook->id}");
        $successRes->assertStatus(204);
    }

    public function test_hateoas_dynamic_pinjam_buku_link_depends_on_stock(): void
    {
        $bukuStok = Buku::factory()->create(['kategori_id' => $this->kategori->id, 'stok' => 5]);
        $bukuHabis = Buku::factory()->create(['kategori_id' => $this->kategori->id, 'stok' => 0]);

        $resStok = $this->getJson("/api/v1/bukus/{$bukuStok->id}");
        $this->assertArrayHasKey('pinjam_buku', $resStok->json('data._links'));

        $resHabis = $this->getJson("/api/v1/bukus/{$bukuHabis->id}");
        $this->assertArrayNotHasKey('pinjam_buku', $resHabis->json('data._links'));
    }
}
