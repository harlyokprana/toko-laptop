<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @include('partials.theme-init')

        <title>{{ config('app.name', 'Prime.Com') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @include('partials.money-formatter')
    </head>
    <body class="guest-page font-sans antialiased">
        <main class="guest-shell">
            <section class="guest-brand-panel">
                <div class="guest-brand-top">
                    <a href="/" class="guest-brand-lockup" aria-label="Prime.Com beranda">
                        <span class="guest-brand-mark" aria-hidden="true">
                            <svg viewBox="0 0 32 32" fill="none">
                                <path d="M5 8.5h22v14H5z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/>
                                <path d="M2.8 26h26.4M11 22.5l-1.2 3.2m11.2-3.2 1.2 3.2M12 13h8m-8 4h5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <span>Prime<span class="guest-brand-dot">.</span>Com</span>
                    </a>
                    <span class="guest-brand-caption">LAPTOP · SERVICE · ACCESSORIES</span>
                </div>

                <div class="guest-hero-copy">
                    <p class="guest-eyebrow"><span></span> SOLUSI TEKNOLOGI ANDA</p>
                    <h1>Semua kebutuhan<br>laptop, <em>satu tempat.</em></h1>
                    <p class="guest-hero-description">Kelola penjualan, inventori, dan layanan dengan lebih mudah bersama Prime.Com.</p>
                </div>

                <div class="guest-device-art" aria-hidden="true">
                    <div class="guest-art-glow"></div>
                    <div class="guest-art-label"><span class="guest-live-dot"></span> PRIME SERVICE DESK</div>
                    <div class="guest-laptop">
                        <div class="guest-laptop-screen">
                            <div class="guest-screen-top"><span></span><span></span><span></span><b>prime.com / workspace</b></div>
                            <div class="guest-screen-content">
                                <div class="guest-screen-sidebar"><i></i><i></i><i></i><i></i></div>
                                <div class="guest-screen-main">
                                    <div class="guest-screen-heading"><span></span><b></b></div>
                                    <div class="guest-screen-cards"><i></i><i></i><i></i></div>
                                    <div class="guest-screen-chart"><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>
                                </div>
                            </div>
                        </div>
                        <div class="guest-laptop-base"></div>
                    </div>
                    <div class="guest-float-card guest-float-stock">
                        <span class="guest-float-icon">↗</span>
                        <span><b>Inventori terpantau</b><small>Lebih rapi, lebih terkendali</small></span>
                    </div>
                    <div class="guest-float-card guest-float-service">
                        <span class="guest-service-icon">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M14.7 6.3a5 5 0 0 0-6.4 6.4L3.5 17.5a2.1 2.1 0 1 0 3 3l4.8-4.8a5 5 0 0 0 6.4-6.4L14.2 13l-3.2-3.2 3.7-3.5Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <span><b>Service & perbaikan</b><small>Proses tercatat jelas</small></span>
                    </div>
                </div>

                <div class="guest-brand-footer">
                    <span>Teknologi yang dekat dengan kebutuhan Anda.</span>
                    <span class="guest-footer-mark">P<span>.</span></span>
                </div>
            </section>

            <section class="guest-form-panel">
                <div class="guest-form-topline"><span>PRIME.COM</span><span>PORTAL TOKO</span></div>
                <div class="guest-form-wrap">{{ $slot }}</div>
                <footer class="guest-form-footer"><span>© {{ now()->year }} Prime.Com</span><span>AMAN · TERPERCAYA · TERINTEGRASI</span></footer>
            </section>
        </main>
    </body>
</html>
