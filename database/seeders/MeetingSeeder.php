<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingActionItem;
use App\Domains\Meetings\Models\MeetingAgendaItem;
use App\Domains\Meetings\Models\MeetingDateProposal;
use App\Domains\Meetings\Models\MeetingDateVote;
use App\Domains\Meetings\Models\MeetingMinutes;
use App\Domains\Shared\Enums\MeetingDateVoteEnum;
use App\Domains\Shared\Enums\MeetingFormatEnum;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MeetingSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('is_admin', true)->first()
            ?? User::where('is_committee_member', true)->first()
            ?? User::first();

        $committeeMembers = User::where(fn ($q) => $q->where('is_admin', true)->orWhere('is_committee_member', true))
            ->get();

        $allActiveMembers = User::inRandomOrder()->take(30)->get();

        // ── 1. Completed past AG — season closing ─────────────────────────
        $agPast = Meeting::create([
            'title' => 'Assemblée générale de fin de saison 2024-2025',
            'type' => MeetingTypeEnum::GENERAL_ASSEMBLY,
            'status' => MeetingStatusEnum::COMPLETED,
            'format' => MeetingFormatEnum::PHYSICAL,
            'description' => 'Assemblée générale annuelle de clôture de la saison. Bilan sportif, financier et élection du nouveau comité.',
            'scheduled_at' => Carbon::parse('2025-05-20 19:30:00'),
            'ends_at' => Carbon::parse('2025-05-20 21:30:00'),
            'location' => 'Salle polyvalente du club, Avenue du Sport 12',
            'rsvp_deadline' => Carbon::parse('2025-05-17'),
            'has_meal' => true,
            'meal_description' => 'Buffet froid partagé — merci d\'apporter quelque chose !',
            'meal_price_cents' => 0,
            'quorum' => 15,
            'created_by' => $admin->id,
        ]);

        $this->seedAgenda($agPast, [
            ['title' => 'Ouverture et vérification du quorum', 'description' => 'Appel des membres et constatation du quorum statutaire.'],
            ['title' => 'Rapport d\'activités sportives 2024-2025', 'description' => 'Bilan interclubs, tournois internes et stages.'],
            ['title' => 'Rapport financier', 'description' => 'Présentation du bilan comptable et du budget prévisionnel.'],
            ['title' => 'Élection du comité 2025-2026', 'description' => 'Dépôt et vote des candidatures.'],
            ['title' => 'Questions diverses'],
        ]);

        $this->inviteAll($agPast, $allActiveMembers, MeetingUserStatusEnum::ATTENDED, 22);
        $this->inviteAll($agPast, $allActiveMembers->skip(22), MeetingUserStatusEnum::ABSENT);

        $this->seedMinutes($agPast, $admin->id, [
            'announcements' => [
                'Le quorum de 15 membres est atteint avec 22 présents.',
                'Le club accueillera un stage national les 12-13 juillet 2025.',
                'Renouvellement de la convention avec la commune pour l\'accès à la salle.',
            ],
            'decisions' => [
                'Budget 2025-2026 approuvé à l\'unanimité (recettes : 8 400 €, dépenses : 7 900 €).',
                'Reconduction du comité sortant avec l\'ajout de Sophie Martin au poste de secrétaire adjointe.',
                'Cotisation 2025-2026 maintenue à 120 €/adulte et 80 €/jeune.',
                'Participation au championnat provincial maintenue avec 3 équipes.',
            ],
            'notes' => "Réunion très constructive. Le président remercie les bénévoles pour leur engagement tout au long de la saison.\nLe buffet a été un succès, atmosphère conviviale.",
        ], sentToAll: true);

        $this->seedActionItems($agPast, $committeeMembers, [
            ['title' => 'Envoyer le procès-verbal aux membres', 'description' => 'Via email + affichage site web'],
            ['title' => 'Mettre à jour les signatures bancaires', 'description' => 'Suite à l\'élection du nouveau trésorier'],
            ['title' => 'Organiser la réunion de rentrée avant le 15 septembre'],
        ]);

        // ── 2. Upcoming AG — season opening ──────────────────────────────
        $agUpcoming = Meeting::create([
            'title' => 'Assemblée générale de reprise de saison 2025-2026',
            'type' => MeetingTypeEnum::GENERAL_ASSEMBLY,
            'status' => MeetingStatusEnum::CONFIRMED,
            'format' => MeetingFormatEnum::PHYSICAL,
            'description' => 'Assemblée générale annuelle de rentrée. Présentation du programme sportif de la saison, approbation du budget et informations pratiques.',
            'scheduled_at' => Carbon::parse('2025-09-16 19:30:00'),
            'ends_at' => Carbon::parse('2025-09-16 21:00:00'),
            'location' => 'Salle polyvalente du club, Avenue du Sport 12',
            'rsvp_deadline' => Carbon::parse('2025-09-13'),
            'has_meal' => false,
            'quorum' => 12,
            'created_by' => $admin->id,
        ]);

        $this->seedAgenda($agUpcoming, [
            ['title' => 'Ouverture et appel — vérification du quorum'],
            ['title' => 'Présentation du programme sportif 2025-2026', 'description' => 'Calendrier interclubs, tournois, stages.'],
            ['title' => 'Présentation du budget prévisionnel'],
            ['title' => 'Informations pratiques', 'description' => 'Accès salle, horaires d\'entraînement, nouvelles règles.'],
            ['title' => 'Présentation des nouveaux membres'],
            ['title' => 'Questions diverses'],
        ]);

        $this->inviteAll($agUpcoming, $allActiveMembers, MeetingUserStatusEnum::CONFIRMED, 14);
        $this->inviteAll($agUpcoming, $allActiveMembers->skip(14)->take(8), MeetingUserStatusEnum::INVITED);

        // ── 3. Completed committee meeting — physical ─────────────────────
        $committee1 = Meeting::create([
            'title' => 'Réunion de comité — Octobre 2024',
            'type' => MeetingTypeEnum::COMMITTEE,
            'status' => MeetingStatusEnum::COMPLETED,
            'format' => MeetingFormatEnum::PHYSICAL,
            'description' => 'Réunion mensuelle du comité. Points : résultats interclubs, logistique tournoi interne, trésorerie.',
            'scheduled_at' => Carbon::parse('2024-10-08 19:00:00'),
            'ends_at' => Carbon::parse('2024-10-08 21:00:00'),
            'location' => 'Bar Le Central, Place du Village 3 — salle du fond',
            'rsvp_deadline' => Carbon::parse('2024-10-06'),
            'has_meal' => true,
            'meal_description' => 'Pizzas partagées',
            'meal_price_cents' => 1200,
            'created_by' => $admin->id,
        ]);

        $this->seedAgenda($committee1, [
            ['title' => 'Résultats interclubs ronde 2', 'description' => 'Bilan des matchs et points de classement.'],
            ['title' => 'Préparation tournoi interne de novembre'],
            ['title' => 'Point trésorerie — cotisations en retard'],
            ['title' => 'Divers'],
        ]);

        $this->inviteAll($committee1, $committeeMembers, MeetingUserStatusEnum::ATTENDED);

        $this->seedMinutes($committee1, $admin->id, [
            'announcements' => [
                'L\'équipe 1 est en tête de sa division avec 3 victoires sur 3.',
                'Le tournoi interne est fixé au 23 novembre — inscription ouverte dès le 1er novembre.',
            ],
            'decisions' => [
                'Attribution de 200 € pour l\'achat de nouvelles balles ITTF.',
                'Rappel des cotisations par email aux 4 membres en retard.',
                'Planning des arbitrages : chaque membre du comité assure au minimum 1 journée.',
            ],
            'notes' => 'Ambiance détendue. Les pizzas étaient excellentes.',
        ], sentToCommittee: true);

        $this->seedActionItems($committee1, $committeeMembers, [
            ['title' => 'Commander les balles ITTF (200 €)', 'description' => 'Via le fournisseur habituel Tibhar'],
            ['title' => 'Envoyer rappel cotisations aux 4 membres en retard'],
            ['title' => 'Créer le formulaire d\'inscription au tournoi interne'],
        ]);

        // ── 4. Completed committee meeting — virtual ──────────────────────
        $committee2 = Meeting::create([
            'title' => 'Réunion de comité — Janvier 2025',
            'type' => MeetingTypeEnum::COMMITTEE,
            'status' => MeetingStatusEnum::COMPLETED,
            'format' => MeetingFormatEnum::VIRTUAL,
            'description' => 'Réunion de début d\'année. Bilan mi-saison et planification du second semestre.',
            'scheduled_at' => Carbon::parse('2025-01-14 19:30:00'),
            'ends_at' => Carbon::parse('2025-01-14 21:00:00'),
            'meeting_link' => 'https://meet.google.com/abc-defg-hij',
            'rsvp_deadline' => Carbon::parse('2025-01-12'),
            'has_meal' => false,
            'created_by' => $admin->id,
        ]);

        $this->seedAgenda($committee2, [
            ['title' => 'Bilan mi-saison interclubs'],
            ['title' => 'Planification du tournoi de printemps — date et format'],
            ['title' => 'Renouvellement de l\'affiliation AFTT', 'description' => 'Délai : 28 février.'],
            ['title' => 'Communication et réseaux sociaux'],
        ]);

        $this->inviteAll($committee2, $committeeMembers, MeetingUserStatusEnum::ATTENDED);

        $this->seedMinutes($committee2, $admin->id, [
            'announcements' => [
                'Toutes les équipes sont dans la première moitié de tableau à mi-saison.',
                'La page Facebook du club a été relancée avec succès (+120 abonnés en 2 semaines).',
            ],
            'decisions' => [
                'Tournoi de printemps fixé au samedi 5 avril — format simple.',
                'Renouvellement AFTT délégué au secrétaire avant le 15 février.',
                'Publication bi-hebdomadaire sur les réseaux sociaux — calendrier partagé sur Drive.',
            ],
            'notes' => 'Réunion productive malgré le format virtuel. Connexion parfaite pour tous les participants.',
        ], sentToCommittee: true);

        // ── 5. Confirmed upcoming committee meeting — physical ─────────────
        $committee3 = Meeting::create([
            'title' => 'Réunion de comité — Septembre 2025',
            'type' => MeetingTypeEnum::COMMITTEE,
            'status' => MeetingStatusEnum::CONFIRMED,
            'format' => MeetingFormatEnum::PHYSICAL,
            'description' => 'Première réunion de la nouvelle saison. Mise en place du comité, définition des priorités.',
            'scheduled_at' => Carbon::parse('2025-09-30 19:00:00'),
            'ends_at' => Carbon::parse('2025-09-30 21:00:00'),
            'location' => 'Salle du club — salle de réunion',
            'rsvp_deadline' => Carbon::parse('2025-09-28'),
            'has_meal' => false,
            'created_by' => $admin->id,
        ]);

        $this->seedAgenda($committee3, [
            ['title' => 'Répartition des rôles au sein du nouveau comité'],
            ['title' => 'Planning de la saison 2025-2026'],
            ['title' => 'Bilan de l\'AG de rentrée'],
            ['title' => 'Budget prévisionnel détaillé'],
            ['title' => 'Divers'],
        ]);

        $this->inviteAll($committee3, $committeeMembers, MeetingUserStatusEnum::CONFIRMED);

        // ── 6. Planning — date poll in progress ───────────────────────────
        $planning = Meeting::create([
            'title' => 'Réunion de comité — Octobre 2025',
            'type' => MeetingTypeEnum::COMMITTEE,
            'status' => MeetingStatusEnum::PLANNING,
            'format' => MeetingFormatEnum::VIRTUAL,
            'description' => 'Réunion mensuelle du comité. Sondage de disponibilités en cours.',
            'scheduled_at' => null,
            'meeting_link' => null,
            'created_by' => $admin->id,
        ]);

        $this->seedAgenda($planning, [
            ['title' => 'Résultats interclubs rondes 1 et 2'],
            ['title' => 'Mise à jour cotisations et adhésions'],
            ['title' => 'Préparation du tournoi interne de novembre'],
        ]);

        // Date proposals with votes
        $proposals = [];
        foreach ([
            Carbon::parse('2025-10-07 19:00:00'),
            Carbon::parse('2025-10-09 20:00:00'),
            Carbon::parse('2025-10-14 19:00:00'),
        ] as $date) {
            $proposals[] = MeetingDateProposal::create([
                'meeting_id' => $planning->id,
                'proposed_at' => $date,
                'is_selected' => false,
            ]);
        }

        $votes = [
            MeetingDateVoteEnum::AVAILABLE,
            MeetingDateVoteEnum::AVAILABLE,
            MeetingDateVoteEnum::MAYBE,
            MeetingDateVoteEnum::UNAVAILABLE,
        ];

        foreach ($committeeMembers->take(4) as $i => $member) {
            foreach ($proposals as $j => $proposal) {
                $voteIndex = ($i + $j) % count($votes);
                MeetingDateVote::create([
                    'meeting_date_proposal_id' => $proposal->id,
                    'user_id' => $member->id,
                    'vote' => $votes[$voteIndex]->value,
                ]);
            }
        }
    }

    private function inviteAll(Meeting $meeting, $users, MeetingUserStatusEnum $status, ?int $limit = null): void
    {
        $subset = $limit ? $users->take($limit) : $users;
        foreach ($subset as $user) {
            $meeting->users()->syncWithoutDetaching([
                $user->id => [
                    'status' => $status->value,
                    'invitation_sent_at' => $meeting->scheduled_at?->subDays(7),
                    'response_at' => in_array($status->value, ['confirmed', 'declined', 'attended', 'absent'])
                        ? $meeting->scheduled_at?->subDays(3)
                        : null,
                ],
            ]);
        }
    }

    private function seedActionItems(Meeting $meeting, $members, array $items): void
    {
        foreach ($items as $i => $item) {
            MeetingActionItem::create([
                'meeting_id' => $meeting->id,
                'title' => $item['title'],
                'description' => $item['description'] ?? null,
                'assigned_to_id' => $members->get($i)?->id,
                'due_date' => $meeting->scheduled_at?->addDays(14),
                'is_completed' => fake()->boolean(40),
            ]);
        }
    }

    private function seedAgenda(Meeting $meeting, array $items): void
    {
        foreach ($items as $i => $item) {
            MeetingAgendaItem::create([
                'meeting_id' => $meeting->id,
                'sort_order' => $i,
                'title' => $item['title'],
                'description' => $item['description'] ?? null,
            ]);
        }
    }

    private function seedMinutes(
        Meeting $meeting,
        int $publishedBy,
        array $data,
        bool $sentToCommittee = false,
        bool $sentToAll = false,
    ): void {
        MeetingMinutes::create([
            'meeting_id' => $meeting->id,
            'announcements' => $data['announcements'] ?? null,
            'decisions' => $data['decisions'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_published' => true,
            'published_at' => $meeting->scheduled_at?->addDay(),
            'published_by' => $publishedBy,
            'sent_to_committee_at' => $sentToCommittee ? $meeting->scheduled_at?->addDays(2) : null,
            'sent_to_all_at' => $sentToAll ? $meeting->scheduled_at?->addDays(3) : null,
        ]);
    }
}
