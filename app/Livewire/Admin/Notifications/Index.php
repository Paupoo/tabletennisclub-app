<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Notifications;

use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use HasBreadcrumbs, WithPagination;

    #[Url]
    public string $filter = 'all';

    public function markAllAsRead(): void
    {
        auth()->user()->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $this->dispatch('notifications-updated');
    }

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

    public function render(): View
    {
        $query = auth()->user()->notifications();

        if ($this->filter === 'unread') {
            $query = $query->whereNull('read_at');
        }

        $notifications = $query
            ->latest()
            ->paginate(20);

        $unreadCount = auth()->user()->notifications()
            ->whereNull('read_at')
            ->count();

        return view('livewire.admin.notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'breadcrumbs' => $this->getBreadcrumbs(),
        ])->layout('layouts.app');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Notifications'));
    }
}
