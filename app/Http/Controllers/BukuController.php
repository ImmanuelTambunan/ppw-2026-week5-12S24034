namespace App\Http\Controllers;

use App\Models\Buku;
use App\Models\Kategori;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB; // Pastikan DB Facade diimpor

class BukuController extends Controller
{
    public function index(Request $request)
    {
        // Simpan riwayat kata kunci pencarian terakhir ke sesi (Manajemen Sesi)
        if ($request->filled('search')) {
            session(['last_book_search' => $request->search]);
        }

        // 1. Fluent Query Builder (DB::table) untuk 4 Kartu Statistik Perpustakaan
        $stats = [
            'total_judul'       => DB::table('bukus')->count(),
            'total_stok'        => DB::table('bukus')->sum('stok') ?? 0,
            'sedang_dipinjam'   => DB::table('peminjamans')->where('status', 'dipinjam')->count(),
            'total_kembali'     => DB::table('peminjamans')->where('status', 'dikembalikan')->count(),
        ];

        // 2. Eloquent dengan withCount('peminjamans'), filter dinamis when(), & withQueryString()
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

    // Method create, store, show, edit, update, destroy tetap sama seperti sebelumnya