<x-layout>
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="fw-bold text-del mb-0">Formulir Peminjaman Buku</h3>
                <a href="{{ route('peminjaman.index') }}" class="btn btn-outline-secondary btn-sm">&larr; Kembali</a>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <form action="{{ route('peminjaman.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Pilih Buku</label>
                            <select name="buku_id" class="form-select @error('buku_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Buku yang Tersedia --</option>
                                @foreach($bukus as $buku)
                                    <option value="{{ $buku->id }}" {{ old('buku_id') == $buku->id ? 'selected' : '' }}>
                                        {{ $buku->judul }} (Sisa Stok: {{ $buku->stok }})
                                    </option>
                                @endforeach
                            </select>
                            @error('buku_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">NIM Peminjam</label>
                                <input type="text" name="nim_peminjam" class="form-control @error('nim_peminjam') is-invalid @enderror" value="{{ old('nim_peminjam', session('last_borrower_nim')) }}" placeholder="Contoh: 12S24034" required>
                                @error('nim_peminjam')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Nama Mahasiswa</label>
                                <input type="text" name="nama_peminjam" class="form-control @error('nama_peminjam') is-invalid @enderror" value="{{ old('nama_peminjam') }}" placeholder="Nama Lengkap" required>
                                @error('nama_peminjam')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Tanggal Pinjam</label>
                                <input type="date" name="tanggal_pinjam" class="form-control @error('tanggal_pinjam') is-invalid @enderror" value="{{ old('tanggal_pinjam', date('Y-m-d')) }}" required>
                                @error('tanggal_pinjam')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Durasi Pinjam (Hari)</label>
                                <input type="number" name="durasi_hari" class="form-control @error('durasi_hari') is-invalid @enderror" value="{{ old('durasi_hari', 7) }}" min="1" max="14" required>
                                <small class="text-muted">Maksimal peminjaman 14 hari</small>
                                @error('durasi_hari')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-del px-4">Proses Peminjaman (ACID)</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layout>