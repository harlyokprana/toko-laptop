@props(['name'])
<svg {{ $attributes->merge(['class' => 'h-5 w-5 shrink-0', 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'aria-hidden' => 'true']) }}>
    @switch($name)
        @case('home')<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>@break
        @case('cart')<circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/><path d="M2 3h2l2.4 12.2a2 2 0 0 0 2 1.6h9.8a2 2 0 0 0 2-1.6L22 8H5"/>@break
        @case('receipt')<path d="M5 3h14v18l-3-2-4 2-4-2-3 2z"/><path d="M8 8h8M8 12h8M8 16h4"/>@break
        @case('service')<path d="M14 6a5 5 0 0 0-6.5 6.5L3 17l4 4 4.5-4.5A5 5 0 0 0 18 10l-3 3-4-4z"/>@break
        @case('cash')<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M7 9h.01M17 15h.01"/>@break
        @case('deposit')<path d="M4 7h16M6 7V4h12v3M5 7v13h14V7M9 12h6M12 9v6"/>@break
        @case('purchase')<path d="M4 7h16l-1 13H5L4 7z"/><path d="M9 7V5a3 3 0 0 1 6 0v2M8 11h8"/>@break
        @case('chart')<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>@break
        @case('stock')<path d="m12 3 9 5-9 5-9-5zM3 12l9 5 9-5M3 16l9 5 9-5"/>@break
        @case('people')<circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1zM16 5a3 3 0 0 1 0 6M18 14a5 5 0 0 1 3 5v1h-3"/>@break
        @case('settings')<circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.6a8 8 0 0 1-1.5.9l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.5-.9l-1.7.6-1.4-2.4 1.4-1.1a7 7 0 0 1 0-1.8l-1.4-1.1 1.4-2.4 1.7.6a8 8 0 0 1 1.5-.9l.3-1.8h2.8l.3 1.8a8 8 0 0 1 1.5.9l1.7-.6 1.4 2.4-1.4 1.1a7 7 0 0 1-.1 1.7z" transform="translate(-2 -2)"/>@break
        @case('spark')<path d="m12 3 1.7 5.3L19 10l-5.3 1.7L12 17l-1.7-5.3L5 10l5.3-1.7zM19 16l.8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8z"/>@break
        @case('search')<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>@break
        @case('filter')<path d="M4 7h16M7 12h10m-7 5h4"/><circle cx="8" cy="7" r="1.5" fill="currentColor"/><circle cx="15" cy="12" r="1.5" fill="currentColor"/>@break
        @case('barcode')<path d="M4 5v14M7 5v14M10 5v14M13 5v14M17 5v14M20 5v14"/><path d="M2 8v8m20-8v8"/>@break
        @case('bell')<path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>@break
        @default<circle cx="12" cy="12" r="9"/><path d="M12 8v4l3 2"/>
    @endswitch
</svg>
