<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Livewire\Component;

class NotificationBell extends Component
{
    public function markAsRead(string $id): void
    {
        $notification = auth()->user()->notifications()
            ->find($id);

        if ($notification) {
            $notification->markAsRead();

            if (isset($notification->data['url']) && $notification->data['url'] !== '#') {
                $this->redirect($notification->data['url']);
            }
        }
    }

    public function markAllAsRead(): void
    {
        auth()->user()->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $this->dispatch('notifications-updated');
    }

    public function render()
    {
        $unreadCount = auth()->user()->notifications()
            ->whereNull('read_at')
            ->count();

        $notifications = auth()->user()->notifications()
            ->latest()
            ->limit(10)
            ->get();

        return view('livewire.admin.notification-bell', [
            'unreadCount' => $unreadCount,
            'notifications' => $notifications,
        ]);
    }
}
