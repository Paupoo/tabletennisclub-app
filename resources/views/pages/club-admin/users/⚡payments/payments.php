<?php

use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public string $search = '';

    public string $statusFilter = 'pending'; // 'pending' ou 'paid'

    // État de la modal d'importation
    public bool $importModal = false;

    // Pour le fichier CSV (nécessite le trait WithFileUploads)
    public $csvFile;

    public function payments(): Collection
    {
        $data = collect([
            (object) ['id' => 1, 'family' => 'Dupont', 'amount' => 280, 'comm' => '+++123/4567/89012+++', 'status' => 'pending', 'date' => '2023-09-01'],
            (object) ['id' => 2, 'family' => 'Martin', 'amount' => 110, 'comm' => '+++987/6543/21098+++', 'status' => 'pending', 'date' => '2023-09-02'],
            (object) ['id' => 3, 'family' => 'Peeters', 'amount' => 150, 'comm' => '+++111/2222/33334+++', 'status' => 'paid', 'date' => '2023-08-25'],
        ]);

        return $data
            ->filter(fn ($p) => $p->status === $this->statusFilter)
            ->when($this->search, function ($collection) {
                return $collection->filter(
                    fn ($p) => str_contains(strtolower($p->family), strtolower($this->search)) ||
                        str_contains($p->comm, $this->search)
                );
            });
    }

    public function headers(): array
    {
        return [
            ['key' => 'comm', 'label' => __('Reference')],
            ['key' => 'family', 'label' => __('Family')],
            ['key' => 'amount', 'label' => __('Amount')],
            ['key' => 'date', 'label' => __('Date')],
        ];
    }

    public function markAsPaid(int $id): void
    {
        // Logique de mise à jour en BDD...
        $this->success('Paiement validé avec succès.');
    }

    public function render(): View
    {
        return $this->view([
            'headers' => $this->headers(),
            'payments' => $this->payments(),
            'statusFilter' => $this->statusFilter,
            'breadcrumbs' => Breadcrumb::make()->home()->add(__('Payments'), route('admin.users.payments'))
            ->toArray(),
        ]);
    }
};