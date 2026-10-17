<x-layout>
    <!-- 1. Header & Tombol Tambah Buku -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-del">Katalog Buku & Statistik Perpustakaan</h2>
            <p class="text-muted">Kelola koleksi pustaka dan analitik sirkulasi Institut Teknologi Del</p>
        </div>
        <a href="{{ route('buku.create') }}" class="btn btn-del shadow-sm">
            + Tambah Buku
        </a>
    </div>

    <!-- 2. Kartu Statistik Perpustakaan (Fluent Query Builder DB::table) -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-3 border-start border-4 border-primary">
                    <small class="text-muted text-uppercase fw-semibold d-block">Total Judul Buku</small>
                    <div class="fs-4 fw-bold text-dark mt-1">{{ number_format($stats['total_judul']) }} Judul</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-3 border-start border-4 border-success">
                    <small class="text-muted text-uppercase fw-semibold d-block">Total Stok Tersedia</small>
                    <div class="fs-4 fw-bold text-dark mt-1">{{ number_format($stats['total_stok']) }} Eks</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-3 border-start border-4 border-warning">
                    <small class="text-muted text-uppercase fw-semibold d-block">Sedang Dipinjam</small>
                    <div class="fs-4 fw-bold text-dark mt-1">{{ number_format($stats['sedang_dipinjam']) }} Buku</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-3 border-start border-4" style="border-color: #4C1D95 !important;">
                    <small class="text-muted text-uppercase fw-semibold d-block">Total Dikembalikan</small>
                    <div class="fs-4 fw-bold text-dark mt-1">{{ number_format($stats['total_kembali']) }} Transaksi</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Form Pencarian Dinamis (when()) & Filter Kategori -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="{{ route('buku.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted">&telrec;</span>
                        <input type="text" name="search" class="form-control" placeholder="Cari Judul, Penulis, atau ISBN..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="kategori_id" class="form-select">
                        <option value="">-- Semua Kategori --</option>
                        @foreach($kategoris as $kategori)
                            <option value="{{ $kategori->id }}" {{ request('kategori_id') == $kategori->id ? 'selected' : '' }}>
                                {{ $kategori->nama_kategori }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-del w-100">Cari</button>
                    @if(request('search') || request('kategori_id'))
                        <a href="{{ route('buku.index') }}" class="btn btn-outline-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- 4. Tabel Katalog Koleksi Buku (withCount('peminjamans')) -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">ISBN</th>
                            <th>Judul & Penulis</th>
                            <th>Kategori</th>
                            <th>Stok</th>
                            <th>Sirkulasi Pinjam</th>
                            <th class="text-center pe-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bukus as $buku)
                            <tr>
                                <td class="ps-3">
                                    <span class="badge bg-light text-dark border font-monospace">{{ $buku->isbn }}</span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $buku->judul }}</div>
                                    <small class="text-muted">{{ $buku->penulis }} &bull; {{ $buku->penerbit }} ({{ $buku->tahun_terbit }})</small>
                                </td>
                                <td>
                                    <span class="badge badge-del">{{ $buku->kategori->nama_kategori }}</span>
                                </td>
                                <td>
                                    <span class="badge {{ $buku->stok > 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' }}">
                                        {{ $buku->stok > 0 ? $buku->stok . ' eks' : 'Habis' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">
                                        {{ $buku->peminjamans_count }}x dipinjam
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
                                    <em>Tidak ada koleksi buku yang sesuai dengan kriteria pencarian.</em>
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