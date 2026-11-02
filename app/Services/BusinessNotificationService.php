<?php

namespace App\Services;

use App\Models\Service;
use App\Models\User;
use App\Notifications\BusinessNotification;
use Illuminate\Database\Eloquent\Builder;

class BusinessNotificationService
{
    /**
     * @param  iterable<mixed>  $recipients
     */
    public function notifyUsers(
        iterable $recipients,
        User $actor,
        string $title,
        string $message,
        string $url,
    ): void {
        collect($recipients)
            ->filter(fn ($recipient) => $recipient instanceof User
                && $recipient->status === 'AKTIF'
                && $recipient->id !== $actor->id)
            ->unique('id')
            ->each(fn (User $recipient) => $recipient->notify(
                new BusinessNotification($title, $message, $url),
            ));
    }

    public function notifyUsersWithPermission(
        string $permission,
        User $actor,
        string $title,
        string $message,
        string $url,
    ): void {
        $recipients = User::query()
            ->where('status', 'AKTIF')
            ->whereHas('role', fn (Builder $role) => $role
                ->where('is_active', true)
                ->whereHas('permissions', fn (Builder $permissions) => $permissions->where('name', $permission)))
            ->get();

        $this->notifyUsers($recipients, $actor, $title, $message, $url);
    }

    public function notifyServiceStakeholders(
        Service $service,
        User $actor,
        string $title,
        string $message,
    ): void {
        $service->loadMissing(['teknisi', 'pelanggan.user']);
        $this->notifyUsers(
            [$service->teknisi, $service->pelanggan?->user],
            $actor,
            $title,
            $message,
            route('service.show', $service, false),
        );
    }
}
