<x-layout>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-del">Daftar Kategori Buku</h2>
            <p class="text-muted">Kelola klasifikasi koleksi pustaka</p>
        </div>
        <a href="{{ route('kategori.create') }}" class="btn btn-del shadow-sm">
            + Tambah Kategori
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Kode Kategori</th>
                            <th>Nama Kategori</th>
                            <th>Jumlah Buku</th>
                            <th class="text-center pe-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($kategoris as $kategori)
                            <tr>
                                <td class="ps-3"><span class="badge bg-secondary">{{ $kategori->kode_kategori }}</span></td>
                                <td class="fw-semibold text-dark">{{ $kategori->nama_kategori }}</td>
                                <td>
                                    <span class="badge badge-del">{{ $kategori->bukus_count }} Koleksi</span>
                                </td>
                                <td class="text-center pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('kategori.edit', $kategori) }}" class="btn btn-outline-primary">Edit</a>
                                        <form action="{{ route('kategori.destroy', $kategori) }}" method="POST" class="d-inline" onsubmit="return confirm('Menghapus kategori akan menghapus seluruh buku di dalamnya. Lanjutkan?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <em>Belum ada kategori yang terdaftar.</em>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($kategoris->hasPages())
            <div class="card-footer bg-white py-3 border-0">
                {{ $kategoris->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</x-layout>