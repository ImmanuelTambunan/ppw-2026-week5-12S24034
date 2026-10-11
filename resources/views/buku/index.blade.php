<x-layout>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-del">Katalog Buku Perpustakaan</h2>
            <p class="text-muted">Kelola koleksi pustaka Institut Teknologi Del</p>
        </div>
        <a href="{{ route('buku.create') }}" class="btn btn-del shadow-sm">
            + Tambah Buku
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">ISBN</th>
                            <th>Judul & Penulis</th>
                            <th>Kategori</th>
                            <th>Tahun</th>
                            <th>Stok</th>
                            <th class="text-center pe-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bukus as $buku)
                            <tr>
                                <td class="ps-3"><span class="badge bg-light text-dark border">{{ $buku->isbn }}</span></td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $buku->judul }}</div>
                                    <small class="text-muted">{{ $buku->penulis }} &bull; {{ $buku->penerbit }}</small>
                                </td>
                                <td>
                                    <span class="badge badge-del">{{ $buku->kategori->nama_kategori }}</span>
                                </td>
                                <td>{{ $buku->tahun_terbit }}</td>
                                <td>
                                    <span class="badge {{ $buku->stok > 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ $buku->stok }} eks
                                    </span>
                                </td>
                                <td class="text-center pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('buku.show', $buku) }}" class="btn btn-outline-secondary">Detail</a>
                                        <a href="{{ route('buku.edit', $buku) }}" class="btn btn-outline-primary">Edit</a>
                                        <form action="{{ route('buku.destroy', $buku) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus buku ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <em>Belum ada data koleksi buku yang terdaftar.</em>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($bukus->hasPages())
            <div class="card-footer bg-white py-3 border-0">
                {{ $bukus->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</x-layout>