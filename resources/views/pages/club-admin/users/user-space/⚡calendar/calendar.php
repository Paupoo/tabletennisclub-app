<?php

use App\Support\Breadcrumb;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    public ?string $selectedMonth = null;

    public ?string $selectedCategory = null;

    public function with(): array
    {
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->current('Calendar')
            ->toArray();
        return [
            'breadcrumbs' => $breadcrumbs,
            'months' => [
                [
                    'id' => 1,
                    'name' => now()->startOfMonth()->addMonth(-6)->format('F Y'),
                ],
                [
                    'id' => 2,
                    'name' => now()->startOfMonth()->addMonth(-5)->format('F Y'),
                ],
                [
                    'id' => 3,
                    'name' => now()->startOfMonth()->addMonth(-4)->format('F Y'),
                ],
                [
                    'id' => 4,
                    'name' => now()->startOfMonth()->addMonth(-3)->format('F Y'),
                ],
                [
                    'id' => 5,
                    'name' => now()->startOfMonth()->addMonth(-2)->format('F Y'),
                ],
                [
                    'id' => 6,
                    'name' => now()->startOfMonth()->addMonth(-1)->format('F Y'),
                ],
                [
                    'id' => 7,
                    'name' => now()->format('F Y'),
                ],
                [
                    'id' => 8,
                    'name' => now()->startOfMonth()->addMonth(1)->format('F Y'),
                ],
                [
                    'id' => 9,
                    'name' => now()->startOfMonth()->addMonth(2)->format('F Y'),
                ],
                [
                    'id' => 10,
                    'name' => now()->startOfMonth()->addMonth(3)->format('F Y'),
                ],
                [
                    'id' => 11,
                    'name' => now()->startOfMonth()->addMonth(4)->format('F Y'),
                ],
                [
                    'id' => 12,
                    'name' => now()->startOfMonth()->addMonth(5)->format('F Y'),
                ],
            ],
            'categories' => [
                [
                    'id' => 'training',
                    'name' => __('Training'),
                ],
                [
                    'id' => 'interclub',
                    'name' => __('Interclub'),
                ],
                [
                    'id' => 'meeting',
                    'name' => __('Meeting'),
                ],
                [
                    'id' => 'tournament',
                    'name' => __('Tournament'),
                ],
            ],
            'calendar' => [
                'February 2026' => [
                    [
                        'startDateTime' => Carbon::parse('third Tuesday of February 2026')
                            ->setHour(19)
                            ->setMinute(0)
                            ->format('d-m-Y H:i'),
                        'title' => 'Training: Advanced Drills',
                        'type' => 'training',
                        'color' => 'bg-success',
                    ],
                    [
                        'startDateTime' => Carbon::parse('third Friday of February 2026')
                            ->setHour(19)
                            ->setMinute(0)
                            ->format('d-m-Y H:i'),
                        'title' => 'Interclub: Ottignies B vs Wavre C',
                        'type' => 'interclub',
                        'color' => 'bg-primary',
                        'important' => true,
                    ],
                    [
                        'startDateTime' => Carbon::parse('third saturday of February 2026')
                            ->setHour(19)
                            ->setMinute(0)
                            ->format('d-m-Y H:i'),
                        'title' => 'Training: Starter Session',
                        'type' => 'training',
                        'color' => 'bg-success',
                    ],
                    [
                        'startDateTime' => Carbon::parse('last Thursday of February 2026')
                            ->setHour(19)
                            ->setMinute(0)
                            ->format('d-m-Y H:i'),
                        'title' => 'Committee Meeting',
                        'type' => 'meeting',
                        'color' => 'bg-warning',
                    ],
                ],
                'March 2026' => [
                    [
                        'startDateTime' => Carbon::parse('first Friday of February 2026')
                            ->setHour(19)
                            ->setMinute(45)
                            ->format('d-m-Y H:i'),
                        'title' => 'Interclub: Perwez A vs Ottignies B',
                        'type' => 'interclub',
                        'color' => 'bg-primary',
                    ],
                    [
                        'startDateTime' => Carbon::parse('second Saturday of March 2026')
                            ->setHour(19)
                            ->setMinute(45)
                            ->format('d-m-Y H:i'),
                        'title' => 'Interclub: Ottignies B vs Rixensart A',
                        'type' => 'interclub',
                        'color' => 'bg-neutral',
                    ],
                ],
                'April 2026' => [
                    [
                        'startDateTime' => Carbon::parse('third Sunday of April 2026')
                            ->setHour(10)
                            ->setMinute(0)
                            ->format('d-m-Y H:i'),
                        'title' => 'Double Tournament',
                        'type' => 'tournament',
                        'color' => 'bg-warning',
                    ],
                ],
                'June 2026' => [
                    [
                        'startDateTime' => Carbon::parse('third Monday of June 2026')
                            ->setHour(19)
                            ->setMinute(0)
                            ->format('d-m-Y H:i'),
                        'title' => 'AG de fin d\'année',
                        'type' => 'meeting',
                        'color' => 'bg-primary',
                    ],
                ],
            ],
        ];
    }

};