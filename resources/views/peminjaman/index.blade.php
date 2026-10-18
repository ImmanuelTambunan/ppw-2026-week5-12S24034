<x-layout>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-del">Sirkulasi Peminjaman Buku</h2>
            <p class="text-muted">Kelola transaksi peminjaman dan pengembalian buku secara atomik</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('peminjaman.export.csv') }}" class="btn btn-success shadow-sm">
                Unduh Laporan CSV
            </a>
            <a href="{{ route('peminjaman.create') }}" class="btn btn-del shadow-sm">
                + Catat Peminjaman
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Kode Pinjam</th>
                            <th>Buku</th>
                            <th>Peminjam (NIM & Nama)</th>
                            <th>Tgl Pinjam</th>
                            <th>Jatuh Tempo</th>
                            <th>Status</th>
                            <th class="text-center pe-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($peminjamans as $pinjam)
                            <tr>
                                <td class="ps-3">
                                    <span class="badge bg-secondary font-monospace">{{ $pinjam->kode_pinjam }}</span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $pinjam->buku->judul }}</div>
                                    <small class="text-muted">ISBN: {{ $pinjam->buku->isbn }}</small>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $pinjam->nama_peminjam }}</div>
                                    <small class="text-muted">{{ $pinjam->nim_peminjam }}</small>
                                </td>
                                <td>{{ \Carbon\Carbon::parse($pinjam->tanggal_pinjam)->translatedFormat('d M Y') }}</td>
                                <td>
                                    <span class="{{ \Carbon\Carbon::parse($pinjam->tanggal_jatuh_tempo)->isPast() && $pinjam->status === 'dipinjam' ? 'text-danger fw-bold' : '' }}">
                                        {{ \Carbon\Carbon::parse($pinjam->tanggal_jatuh_tempo)->translatedFormat('d M Y') }}
                                    </span>
                                </td>
                                <td>
                                    @if($pinjam->status === 'dipinjam')
                                        <span class="badge bg-warning text-dark">Dipinjam</span>
                                    @else
                                        <span class="badge bg-success">Dikembalikan</span>
                                    @endif
                                </td>
                                <td class="text-center pe-3">
                                    @if($pinjam->status === 'dipinjam')
                                        <form action="{{ route('peminjaman.kembalikan', $pinjam) }}" method="POST" class="d-inline" onsubmit="return confirm('Proses pengembalian buku ini? Stok akan otomatis bertambah.');">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-outline-success">
                                                Kembalikan
                                            </button>
                                        </form>
                                    @else
                                        <small class="text-muted">Kembali: {{ \Carbon\Carbon::parse($pinjam->tanggal_kembali)->translatedFormat('d M Y') }}</small>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <em>Belum ada transaksi peminjaman tercatat.</em>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($peminjamans->hasPages())
            <div class="card-footer bg-white py-3 border-0">
                {{ $peminjamans->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</x-layout>