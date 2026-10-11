<x-layout>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="fw-bold text-del mb-0">Detail Buku</h3>
                <a href="{{ route('buku.index') }}" class="btn btn-outline-secondary btn-sm">&larr; Kembali</a>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="badge badge-del fs-6">{{ $buku->kategori->nama_kategori }}</span>
                        <span class="text-muted">ISBN: {{ $buku->isbn }}</span>
                    </div>

                    <h2 class="fw-bold text-dark">{{ $buku->judul }}</h2>
                    <p class="text-muted mb-4">Ditulis oleh <strong>{{ $buku->penulis }}</strong> | Diterbitkan oleh <strong>{{ $buku->penerbit }}</strong> ({{ $buku->tahun_terbit }})</p>

                    <h5 class="fw-semibold">Sinopsis</h5>
                    <p class="text-secondary lh-base">{{ $buku->sinopsis ?? 'Sinopsis belum tersedia.' }}</p>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted d-block">Status Ketersediaan:</span>
                            <span class="badge {{ $buku->stok > 0 ? 'bg-success' : 'bg-danger' }}">
                                {{ $buku->stok > 0 ? "Tersedia ($buku->stok eks)" : 'Stok Habis' }}
                            </span>
                        </div>
                        <div>
                            <a href="{{ route('buku.edit', $buku) }}" class="btn btn-primary btn-sm">Edit Data</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layout>