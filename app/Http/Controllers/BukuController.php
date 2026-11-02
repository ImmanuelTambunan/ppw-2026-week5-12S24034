<?php

namespace App\Http\Controllers;

use App\Models\Buku;
use App\Models\Kategori;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class BukuController extends Controller
{
    /**
     * Menampilkan katalog buku dengan 4 kartu statistik Query Builder (DB::table),
     * eager loading relasi kategori, agregasi withCount('peminjamans'),
     * serta filter pencarian dinamis when().
     */
    public function index(Request $request)
    {
        // 1. Simpan riwayat pencarian terakhir ke Sesi Pengguna
        if ($request->filled('search')) {
            session(['last_book_search' => $request->search]);
        }

        // 2. Fluent Query Builder (DB::table) untuk Kartu Analitik Statistik
        $stats = [
            'total_judul'     => DB::table('bukus')->count(),
            'total_stok'      => DB::table('bukus')->sum('stok') ?? 0,
            'sedang_dipinjam' => DB::table('peminjamans')->where('status', 'dipinjam')->count(),
            'total_kembali'   => DB::table('peminjamans')->where('status', 'dikembalikan')->count(),
        ];

        // 3. Eloquent ORM: Eager Loading + withCount + Pencarian Dinamis when()
        $bukus = Buku::with('kategori')
            ->withCount('peminjamans')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('judul', 'like', "%{$search}%")
                      ->orWhere('penulis', 'like', "%{$search}%")
                      ->orWhere('isbn', 'like', "%{$search}%");
                });
            })
            ->when($request->kategori_id, function ($query, $kategoriId) {
                $query->where('kategori_id', $kategoriId);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $kategoris = Kategori::orderBy('nama_kategori')->get();

        return view('buku.index', compact('bukus', 'stats', 'kategoris'));
    }

    public function create()
    {
        $kategoris = Kategori::orderBy('nama_kategori')->get();
        return view('buku.create', compact('kategoris'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'isbn'         => 'required|string|unique:bukus,isbn',
            'judul'        => 'required|string|min:5|max:255',
            'penulis'      => 'required|string|max:150',
            'penerbit'     => 'required|string|max:100',
            'tahun_terbit' => 'required|digits:4|integer|min:1900|max:' . date('Y'),
            'kategori_id'  => 'required|exists:kategoris,id',
            'stok'         => 'required|integer|min:0',
            'sinopsis'     => 'nullable|string',
        ]);

        Buku::create($validated);
        return redirect()->route('buku.index')->with('success', 'Buku berhasil ditambahkan.');
    }

    public function show(Buku $buku)
    {
        return view('buku.show', compact('buku'));
    }

    public function edit(Buku $buku)
    {
        $kategoris = Kategori::orderBy('nama_kategori')->get();
        return view('buku.edit', compact('buku', 'kategoris'));
    }

    public function update(Request $request, Buku $buku)
    {
        $validated = $request->validate([
            'isbn'         => ['required', 'string', Rule::unique('bukus', 'isbn')->ignore($buku->id)],
            'judul'        => 'required|string|min:5|max:255',
            'penulis'      => 'required|string|max:150',
            'penerbit'     => 'required|string|max:100',
            'tahun_terbit' => 'required|digits:4|integer|min:1900|max:' . date('Y'),
            'kategori_id'  => 'required|exists:kategoris,id',
            'stok'         => 'required|integer|min:0',
            'sinopsis'     => 'nullable|string',
        ]);

        $buku->update($validated);
        return redirect()->route('buku.index')->with('success', 'Buku berhasil diperbarui.');
    }

    public function destroy(Buku $buku)
    {
        $buku->delete();
        return redirect()->route('buku.index')->with('success', 'Buku berhasil dihapus.');
    }
}   