<?php

namespace App\Providers {

    use App\View\Components\GuestLayout;
    use Illuminate\Support\Facades\Blade;
    use Illuminate\Support\ServiceProvider;

    class AppServiceProvider extends ServiceProvider
    {
        /**
         * Register any application services.
         */
        public function register(): void
        {
            //
        }

        /**
         * Bootstrap any application services.
         */
        public function boot(): void
        {
            Blade::component(GuestLayout::class, 'guest-layout');
            Blade::directive('rupiah', function ($expression) {
                return "<?php echo \\format_rupiah({$expression}); ?>";
            });
            Blade::directive('money', function ($expression) {
                return "<?php echo \\format_rupiah({$expression}, false); ?>";
            });
        }
    }
}

namespace {
    if (! function_exists('format_rupiah')) {
        function format_rupiah($value, bool $withPrefix = true): string
        {
            $n = is_numeric($value) ? (float) $value : 0;
            $formatted = number_format($n, 0, ',', '.');

            return $withPrefix ? 'Rp '.$formatted : $formatted;
        }
    }
}
