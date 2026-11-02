<x-dashboard-layout title="Notifikasi">
    <div class="mx-auto max-w-4xl space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Pusat informasi</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950">Notifikasi</h1>
                <p class="mt-1 text-sm text-slate-500">Pembaruan aktivitas yang terkait dengan akun Anda.</p>
            </div>
            @if($notifications->whereNull('read_at')->isNotEmpty())
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Tandai semua dibaca</button>
                </form>
            @endif
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            @forelse($notifications as $notification)
                <a href="{{ route('notifications.open', $notification->id) }}" @class([
                    'flex gap-3 border-b border-slate-100 px-4 py-4 transition last:border-b-0 hover:bg-slate-50 sm:px-5',
                    'bg-emerald-50/60' => $notification->unread(),
                ])>
                    <span @class([
                        'mt-1 h-2.5 w-2.5 shrink-0 rounded-full',
                        'bg-emerald-600' => $notification->unread(),
                        'bg-slate-200' => $notification->read(),
                    ])></span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold text-slate-900">{{ $notification->data['title'] ?? 'Pembaruan' }}</span>
                        <span class="mt-1 block text-sm text-slate-600">{{ $notification->data['message'] ?? '' }}</span>
                        <span class="mt-2 block text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
                    </span>
                </a>
            @empty
                <div class="px-5 py-12 text-center">
                    <x-nav-icon name="bell" class="mx-auto h-8 w-8 text-slate-300"/>
                    <p class="mt-3 font-semibold text-slate-700">Belum ada notifikasi</p>
                    <p class="mt-1 text-sm text-slate-500">Pembaruan alur bisnis akan muncul di sini.</p>
                </div>
            @endforelse
        </div>

        {{ $notifications->links() }}
    </div>
</x-dashboard-layout>
