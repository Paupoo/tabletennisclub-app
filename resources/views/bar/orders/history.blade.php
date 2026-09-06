@php
    use App\Support\Breadcrumb;

    $trail = Breadcrumb::make()->home()->bar()->current('Historique')->toArray();
@endphp

<x-app-layout>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$trail" separator="o-slash" />
    </x-slot:breadcrumbs>

    <div class="space-y-4">

        <div>
            <h1 class="text-2xl font-bold tracking-tight">Historique</h1>
            <p class="text-muted mt-1">Toutes les commandes, filtrées par période et par statut.</p>
        </div>

        {{-- Filtres --}}
        <x-card class="shadow-sm">
            <div class="space-y-2.5">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-muted w-16 text-xs font-bold uppercase tracking-widest">Période</span>
                    @foreach ($periodLabels as $key => $label)
                        <a href="{{ route('bar.orders.history', ['period' => $key, 'status' => $status ?? 'all']) }}"
                            @class([
                                'btn btn-sm rounded-full tap-min',
                                'btn-primary' => (string) $key === (string) ($period ?? 'today'),
                                'btn-outline' => (string) $key !== (string) ($period ?? 'today'),
                            ])>
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-muted w-16 text-xs font-bold uppercase tracking-widest">Statut</span>
                    @foreach ($statusLabels as $key => $label)
                        <a href="{{ route('bar.orders.history', ['period' => $period ?? 'today', 'status' => $key]) }}"
                            @class([
                                'btn btn-sm rounded-full tap-min',
                                'btn-primary' => $key === ($status ?? 'all'),
                                'btn-outline' => $key !== ($status ?? 'all'),
                            ])>
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>
        </x-card>

        {{-- Chiffres de la période --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-admin.shared.stat-card label="Commandes" :value="$orderCount" icon="o-archive-box" color="primary" />
            <x-admin.shared.stat-card label="Encaissé" :value="euros($totalRevenue)" icon="o-banknotes" color="success" />
            <x-admin.shared.stat-card label="Impayé" :value="euros($totalRevenueUnpaid)" icon="o-exclamation-triangle" color="warning" />
            <x-admin.shared.stat-card label="Offert" :value="euros($totalRevenueOffered)" icon="o-gift" />
        </div>

        {{-- Détail --}}
        <x-card class="shadow-sm">
            <h2 class="text-muted mb-3 text-xs font-bold uppercase tracking-widest">Détail des commandes</h2>

            @if (empty($orders) || $orders->isEmpty())
                <p class="text-muted py-6 text-center text-sm">Aucune commande pour les filtres sélectionnés.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Commande</th>
                                <th>Articles</th>
                                <th class="text-end">Total</th>
                                <th>Statut</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($orders as $order)
                                <tr>
                                    <td class="align-top">
                                        <span class="font-bold tabular-nums">#{{ $order->id }}</span>
                                        <p class="text-subtle text-xs tabular-nums">{{ $order->created_at->format('d/m/Y') }}</p>
                                    </td>

                                    <td class="align-top">
                                        @forelse ($order->items as $item)
                                            <div class="text-xs">{{ $item->product->name }} × {{ $item->quantity }}</div>
                                        @empty
                                            <span class="text-subtle text-xs">Aucun article</span>
                                        @endforelse
                                    </td>

                                    <td class="text-end align-top font-bold tabular-nums">{{ euros($order->total_price) }}</td>

                                    <td class="align-top">
                                        @if ($order->payment_method === 'offered')
                                            <span class="badge badge-sm badge-ghost font-bold">Offert</span>
                                            @if ($order->reason)
                                                <p class="text-subtle mt-1 text-xs">{{ $order->reason }}</p>
                                            @endif
                                        @elseif ($order->is_paid)
                                            <span class="badge badge-sm badge-success badge-soft font-bold">
                                                Payé{{ $order->payment_method ? ' · ' . $order->payment_method : '' }}
                                            </span>
                                        @else
                                            <span class="badge badge-sm badge-error badge-soft font-bold">Non payé</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>

                        <tfoot>
                            <tr class="bg-base-200">
                                <td colspan="2" class="font-bold">Total de la période</td>
                                <td class="text-end font-black tabular-nums">
                                    {{ euros($totalRevenue + $totalRevenueUnpaid) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </x-card>

    </div>
</x-app-layout>
