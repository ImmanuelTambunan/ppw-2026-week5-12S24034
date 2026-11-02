<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class AuthController extends Controller
{
    /**
     * Menampilkan halaman form login pustakawan.
     */
    public function loginForm()
    {
        return view('auth.login');
    }

    /**
     * Memproses autentikasi pustakawan.
     * - Validasi email & password
     * - Auth::attempt() untuk verifikasi kredensial
     * - session()->regenerate() untuk mencegah Session Fixation Attack
     * - Simpan timestamp login ke session
     * - Simpan cookie email terakhir (7 hari = 10080 menit)
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            // Regenerasi session ID untuk mencegah Session Fixation Attack
            $request->session()->regenerate();

            // Simpan timestamp login ke session (format standar dan format WIB modul)
            session([
                'login_at'        => now()->toDateTimeString(),
                'login_timestamp' => now()->format('d M Y, H:i:s') . ' WIB',
            ]);

            // Simpan cookie email terakhir login (10080 menit = 7 hari)
            Cookie::queue('del_last_user_email', $request->email, 10080);

            return redirect()->intended(route('buku.index'))
                ->with('success', 'Selamat datang, ' . Auth::user()->name . '!');
        }

        return back()->withErrors([
            'email' => 'Email atau password yang Anda masukkan salah.',
        ])->onlyInput('email');
    }

    /**
     * Memproses logout pustakawan.
     * - Auth::logout() untuk menghapus autentikasi
     * - session()->invalidate() untuk menghapus seluruh data session
     * - session()->regenerateToken() untuk mencegah CSRF token reuse
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Anda telah berhasil logout.');
    }
}
