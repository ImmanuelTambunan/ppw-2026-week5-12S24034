<x-layout>
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                {{-- Header Card --}}
                <div class="card-header text-white text-center py-4" style="background-color: #4C1D95;">
                    <h4 class="fw-bold mb-1">🔐 Login Pustakawan</h4>
                    <small class="opacity-75">Sistem Informasi Perpustakaan - Institut Teknologi Del</small>
                </div>

                <div class="card-body p-4">
                    {{-- Form Login --}}
                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        {{-- Input Email (auto-fill dari cookie jika ada) --}}
                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Email Pustakawan</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i>@</i></span>
                                <input
                                    type="email"
                                    name="email"
                                    id="email"
                                    class="form-control @error('email') is-invalid @enderror"
                                    placeholder="pustakawan@del.ac.id"
                                    value="{{ old('email', request()->cookie('del_last_user_email')) }}"
                                    required
                                    autofocus
                                >
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Input Password --}}
                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">Password</label>
                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="form-control @error('password') is-invalid @enderror"
                                placeholder="Masukkan password"
                                required
                            >
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Remember Me Checkbox --}}
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="remember" id="remember" class="form-check-input" {{ old('remember') ? 'checked' : '' }}>
                            <label for="remember" class="form-check-label text-muted">Ingat saya di perangkat ini</label>
                        </div>

                        {{-- Tombol Login --}}
                        <button type="submit" class="btn btn-del w-100 py-2 fw-semibold shadow-sm">
                            Masuk ke Sistem
                        </button>
                    </form>
                </div>

                {{-- Footer Card --}}
                <div class="card-footer bg-light text-center py-3 border-0">
                    <small class="text-muted">
                        &copy; {{ date('Y') }} SIPUS-Del &bull; Institut Teknologi Del
                    </small>
                </div>
            </div>
        </div>
    </div>
</x-layout>
