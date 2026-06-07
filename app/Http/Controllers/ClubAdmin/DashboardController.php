<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubAdmin;

use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $role = $user->committee_role;
        $isAdmin = $user->is_admin;
        $isCaptain = Team::where('captain_id', $user->id)->exists();

        $showSecretary = $isAdmin || in_array($role, [
            CommitteeRolesEnum::SECRETARY,
            CommitteeRolesEnum::PRESIDENT,
            CommitteeRolesEnum::VICE_PRESIDENT,
        ]);
        $showTreasurer = $isAdmin || $role === CommitteeRolesEnum::TREASURER;
        $showCaptain = $isAdmin || $isCaptain;
        $showCommittee = $isAdmin || in_array($role, [
            CommitteeRolesEnum::PRESIDENT,
            CommitteeRolesEnum::VICE_PRESIDENT,
            CommitteeRolesEnum::ADMINISTRATOR,
        ]);

        $alerts = $this->buildAlerts($isAdmin, $showSecretary, $showTreasurer, $showCaptain);
        $recentActivity = $this->buildActivityFeed();

        return view('clubAdmin.dashboard', compact(
            'showSecretary',
            'showTreasurer',
            'showCaptain',
            'showCommittee',
            'alerts',
            'recentActivity',
        ));
    }

    /**
     * @return array<int, array{type: string, label: string, time: string}>
     */
    private function buildActivityFeed(): array
    {
        $feed = collect();

        User::latest()->take(3)->get()->each(function (User $u) use ($feed): void {
            $feed->push([
                'type' => 'member',
                'label' => "{$u->first_name} {$u->last_name} a rejoint le club",
                'time' => $u->created_at?->diffForHumans() ?? '',
                'sort_at' => $u->created_at,
            ]);
        });

        Contact::latest()->take(3)->get()->each(function (Contact $c) use ($feed): void {
            $feed->push([
                'type' => 'contact',
                'label' => "Message de {$c->first_name} {$c->last_name}",
                'time' => $c->created_at?->diffForHumans() ?? '',
                'sort_at' => $c->created_at,
            ]);
        });

        Interclub::whereNotNull('result')
            ->latest('updated_at')
            ->take(2)
            ->get()
            ->each(function (Interclub $i) use ($feed): void {
                $score = $i->score ? " : {$i->score}" : '';
                $feed->push([
                    'type' => 'match',
                    'label' => 'Match du ' . $i->start_date_time->translatedFormat('d M') . $score,
                    'time' => $i->updated_at?->diffForHumans() ?? '',
                    'sort_at' => $i->updated_at,
                ]);
            });

        return $feed
            ->sortByDesc('sort_at')
            ->take(8)
            ->map(fn (array $item): array => [
                'type' => $item['type'],
                'label' => $item['label'],
                'time' => $item['time'],
            ])
            ->values()
            ->toArray();
    }

    /**
     * @return array<int, array{type: string, icon: string, label: string, route: string}>
     */
    private function buildAlerts(bool $isAdmin, bool $showSecretary, bool $showTreasurer, bool $showCaptain): array
    {
        $alerts = [];

        if ($showSecretary || $isAdmin) {
            $unpaidCount = User::where('has_paid', false)->where('is_active', true)->count();
            if ($unpaidCount > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'o-exclamation-triangle',
                    'label' => $unpaidCount === 1 ? '1 cotisation impayée' : "{$unpaidCount} cotisations impayées",
                    'route' => route('admin.users.index'),
                ];
            }

            $pendingContacts = Contact::byStatus('pending')->count();
            if ($pendingContacts > 0) {
                $alerts[] = [
                    'type' => 'info',
                    'icon' => 'o-envelope',
                    'label' => $pendingContacts === 1 ? '1 message en attente' : "{$pendingContacts} messages en attente",
                    'route' => route('admin.website.contacts.index'),
                ];
            }
        }

        if ($showTreasurer || $isAdmin) {
            $pendingPayments = Payment::where('status', 'pending')->count();
            if ($pendingPayments > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'o-banknotes',
                    'label' => $pendingPayments === 1 ? '1 paiement en attente' : "{$pendingPayments} paiements en attente",
                    'route' => route('admin.treasury.payments'),
                ];
            }
        }

        if ($showCaptain || $isAdmin) {
            $pendingSelections = Interclub::where('start_date_time', '>', now())
                ->whereDoesntHave('users')
                ->count();
            if ($pendingSelections > 0) {
                $alerts[] = [
                    'type' => 'error',
                    'icon' => 'o-clipboard-document-check',
                    'label' => $pendingSelections === 1 ? '1 sélection manquante' : "{$pendingSelections} sélections manquantes",
                    'route' => route('admin.interclubs.captain-selection'),
                ];
            }
        }

        return $alerts;
    }
}
