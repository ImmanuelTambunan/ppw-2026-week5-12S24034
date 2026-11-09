<?php

namespace Tests\Feature;

use App\Models\Buku;
use App\Models\Kategori;
use App\Models\Peminjaman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeminjamanApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private Kategori $kategori;
    private Buku $buku;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
        $this->kategori = Kategori::factory()->create();
        $this->buku = Buku::factory()->create([
            'kategori_id' => $this->kategori->id,
            'stok' => 2,
        ]);
    }

    public function test_get_peminjamans_requires_auth_and_returns_paginated_list(): void
    {
        // Unauthenticated -> 401
        $this->getJson('/api/v1/peminjamans')->assertStatus(401);

        Peminjaman::create([
            'buku_id' => $this->buku->id,
            'nim_peminjam' => '12S24034',
            'nama_peminjam' => 'Immanuel Tambunan',
            'tanggal_pinjam' => now()->toDateString(),
            'durasi_hari' => 7,
            'status' => 'dipinjam',
        ]);

        $res = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/peminjamans');

        $res->assertStatus(200);
        $res->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'buku_id',
                    'nim_peminjam',
                    'nama_peminjam',
                    'tanggal_pinjam',
                    'status',
                    '_links',
                ],
            ],
            'meta' => ['current_page', 'total_items'],
        ]);
    }

    public function test_post_peminjaman_success_decrements_stock(): void
    {
        $payload = [
            'buku_id' => $this->buku->id,
            'nim_peminjam' => '12S24034',
            'nama_peminjam' => 'Immanuel Tambunan',
            'durasi_hari' => 7,
        ];

        $res = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/peminjamans', $payload);

        $res->assertStatus(201);
        $this->assertNotNull($res->headers->get('Location'));
        $this->assertEquals(1, $this->buku->fresh()->stok);

        $loanId = $res->json('data.id');
        $this->assertDatabaseHas('peminjamans', [
            'id' => $loanId,
            'nim_peminjam' => '12S24034',
            'status' => 'dipinjam',
        ]);
    }

    public function test_post_peminjaman_rejects_duplicate_active_loan_with_409(): void
    {
        Peminjaman::create([
            'buku_id' => $this->buku->id,
            'nim_peminjam' => '12S24034',
            'nama_peminjam' => 'Immanuel Tambunan',
            'tanggal_pinjam' => now()->toDateString(),
            'durasi_hari' => 7,
            'status' => 'dipinjam',
        ]);

        $payload = [
            'buku_id' => $this->buku->id,
            'nim_peminjam' => '12S24034',
            'nama_peminjam' => 'Immanuel Tambunan',
        ];

        $res = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/peminjamans', $payload);

        $res->assertStatus(409);
        $res->assertHeader('Content-Type', 'application/problem+json');
        $res->assertJson([
            'title' => 'Duplicate Active Loan Conflict',
            'status' => 409,
        ]);
    }

    public function test_post_peminjaman_rejects_out_of_stock_with_422(): void
    {
        $bukuHabis = Buku::factory()->create([
            'kategori_id' => $this->kategori->id,
            'stok' => 0,
        ]);

        $payload = [
            'buku_id' => $bukuHabis->id,
            'nim_peminjam' => '12S24099',
            'nama_peminjam' => 'Mahasiswa Lain',
        ];

        $res = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/peminjamans', $payload);

        $res->assertStatus(422);
        $res->assertHeader('Content-Type', 'application/problem+json');
        $res->assertJson([
            'title' => 'Book Out of Stock',
            'status' => 422,
        ]);
    }

    public function test_patch_kembalikan_success_increments_stock_and_prevents_re_return(): void
    {
        $peminjaman = Peminjaman::create([
            'buku_id' => $this->buku->id,
            'nim_peminjam' => '12S24034',
            'nama_peminjam' => 'Immanuel Tambunan',
            'tanggal_pinjam' => now()->subDays(2)->toDateString(),
            'durasi_hari' => 7,
            'status' => 'dipinjam',
        ]);
        $stokAwal = $this->buku->stok;

        // Return book -> 200 OK
        $res = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/v1/peminjamans/{$peminjaman->id}/kembalikan");

        $res->assertStatus(200);
        $res->assertJsonPath('data.status', 'dikembalikan');
        $this->assertEquals($stokAwal + 1, $this->buku->fresh()->stok);
        $this->assertNotNull($res->json('data.tanggal_kembali'));

        // Return again -> 409 Conflict
        $reReturn = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/v1/peminjamans/{$peminjaman->id}/kembalikan");

        $reReturn->assertStatus(409);
        $reReturn->assertHeader('Content-Type', 'application/problem+json');
        $reReturn->assertJson([
            'title' => 'Loan Already Returned',
            'status' => 409,
        ]);
    }

    public function test_hateoas_dynamic_kembalikan_link_depends_on_status(): void
    {
        $pinjamAktif = Peminjaman::create([
            'buku_id' => $this->buku->id,
            'nim_peminjam' => '12S24034',
            'nama_peminjam' => 'Immanuel Tambunan',
            'tanggal_pinjam' => now()->toDateString(),
            'status' => 'dipinjam',
        ]);

        $resAktif = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/peminjamans/{$pinjamAktif->id}");
        $this->assertArrayHasKey('kembalikan_buku', $resAktif->json('data._links'));

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->patchJson("/api/v1/peminjamans/{$pinjamAktif->id}/kembalikan");

        $resKembali = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/peminjamans/{$pinjamAktif->id}");
        $this->assertArrayNotHasKey('kembalikan_buku', $resKembali->json('data._links'));
    }
}
