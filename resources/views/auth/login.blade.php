<x-guest-layout>
<div class="auth-login-content">
    <div class="auth-login-heading">
        <span class="auth-login-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><path d="M12 3.5 19 7v5.1c0 4.1-2.8 7.1-7 8.4-4.2-1.3-7-4.3-7-8.4V7l7-3.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
        <p class="auth-login-kicker">SELAMAT DATANG KEMBALI</p>
        <h1>Masuk ke Prime<span>.</span>Com</h1>
        <p class="auth-login-description">Masuk untuk melanjutkan aktivitas toko Anda.</p>
    </div>

    <x-auth-session-status class="auth-login-status" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="auth-login-form">
        @csrf

        <div class="auth-field">
            <label for="email">Email atau nomor telepon</label>
            <input id="email" type="text" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="Email atau nomor telepon">
            <x-input-error :messages="$errors->get('email')" class="auth-field-error" />
        </div>

        <div class="auth-field">
            <div class="auth-password-label">
                <label for="password">Kata sandi</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}">Lupa kata sandi?</a>
                @endif
            </div>
            <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Masukkan kata sandi">
            <x-input-error :messages="$errors->get('password')" class="auth-field-error" />
        </div>

        <label for="remember_me" class="auth-remember">
            <input id="remember_me" type="checkbox" name="remember">
            <span>Ingat saya di perangkat ini</span>
        </label>

        <button type="submit" class="auth-submit">
            <span>Masuk ke dashboard</span>
            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10h11m-4-4 4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
    </form>

    <div class="auth-login-note"><span class="auth-note-lock" aria-hidden="true">⌑</span><span>Akses aman untuk tim Prime.Com</span></div>
</div>
</x-guest-layout>
