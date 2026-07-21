<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

use Database\Seeders\RoleSeeder;

/**
 * The Spatie roles, and the permissions each one bundles.
 *
 * Two of them form the base layer ({@see self::ADMINISTRATOR}, {@see self::COMMITTEE});
 * the rest are *délégations* — operational duties that may be handed to anyone,
 * committee member or not, and that stack freely.
 *
 * A délégation is not a statutory title: the `committee_role` column keeps holding
 * president/secretary/treasurer/vice-president, it is displayed and never decides
 * an access. Nor is it an entrusted item: `has_key` and the held cash registers
 * record objects handed over, and grant nothing by themselves.
 *
 * This matrix is the single source of truth for who may do what. It is versioned,
 * reviewed in PR, applied by {@see RoleSeeder} and rendered by
 * `php artisan docs:permissions`.
 */
enum Role: string
{
    // Kept alphabetical by Pint; the socle/délégation split is carried by
    // isDelegation(), not by the ordering here.
    case ADMINISTRATOR = 'administrateur';
    case BAR = 'bar';
    case CASH_REGISTER = 'caisse';
    case COACH = 'coach';
    case COMMITTEE = 'comite';
    case CONTACTS = 'contacts';
    case FACILITIES = 'installations';
    case FINES = 'amendes';
    case INTERCLUBS = 'interclubs';
    case MEETINGS = 'reunions';
    case MEMBERS = 'membres';
    case SEASONS = 'saisons';
    case SELECTIONS = 'selections';
    case SUPERVISION = 'supervision';
    case TOURNAMENTS = 'tournois';
    case TRAININGS = 'entrainements';

    // Délégations
    case TREASURY = 'tresorerie';
    case WEBSITE = 'site-web';

    /**
     * The délégations, i.e. every role that can be handed to any member.
     *
     * @return array<int, self>
     */
    public static function delegations(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $role): bool => $role->isDelegation(),
        ));
    }

    /**
     * Délégations an admin would normally hand over along with a statutory title.
     *
     * Only a suggestion: the form pre-checks them, and they stay editable. A
     * treasurer who does not handle the cash box is a legitimate situation.
     *
     * @return array<int, self>
     */
    public static function suggestedFor(CommitteeRolesEnum $committeeRole): array
    {
        return match ($committeeRole) {
            CommitteeRolesEnum::PRESIDENT => [self::MEMBERS, self::CONTACTS, self::MEETINGS, self::SEASONS, self::SUPERVISION],
            CommitteeRolesEnum::VICE_PRESIDENT => [self::MEMBERS, self::CONTACTS, self::MEETINGS],
            CommitteeRolesEnum::TREASURER => [self::TREASURY, self::CASH_REGISTER, self::FINES],
            CommitteeRolesEnum::SECRETARY => [self::MEMBERS, self::CONTACTS, self::MEETINGS, self::WEBSITE],
            CommitteeRolesEnum::ADMINISTRATOR => [self::SUPERVISION],
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ADMINISTRATOR => __('Unrestricted access to the whole application.'),
            self::COMMITTEE => __('Baseline back-office access: consult the club data without managing it.'),
            self::TREASURY => __('Reconcile payments, import bank statements, handle refunds.'),
            self::CASH_REGISTER => __('Hold and balance the cash register, record movements.'),
            self::FINES => __('Issue and cancel disciplinary fines.'),
            self::MEMBERS => __('Create and update members, handle affiliations and enrolments.'),
            self::CONTACTS => __('Handle incoming leads, reply templates and spam.'),
            self::WEBSITE => __('Write news articles and publish public events.'),
            self::INTERCLUBS => __('Manage teams, divisions, opposing clubs, fixtures and results.'),
            self::SELECTIONS => __('Compose the lineups (a captain only for their own teams).'),
            self::TOURNAMENTS => __('Organise tournaments and run the live centre.'),
            self::TRAININGS => __('Build the training offer, packs and season planning.'),
            self::COACH => __('Lead training sessions and record attendance.'),
            self::MEETINGS => __('Convene meetings, write and publish the minutes.'),
            self::BAR => __('Manage the bar: products, stock, orders and cash sheet.'),
            self::FACILITIES => __('Manage rooms, tables and entrusted equipment.'),
            self::SEASONS => __('Open, close and provision the seasons.'),
            self::SUPERVISION => __('Read the audit log, monitor the queue, edit club settings.'),
        };
    }

    public function isDelegation(): bool
    {
        return ! in_array($this, [self::ADMINISTRATOR, self::COMMITTEE], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::ADMINISTRATOR => __('Administrator'),
            self::COMMITTEE => __('Committee member'),
            self::TREASURY => __('Treasury'),
            self::CASH_REGISTER => __('Cash register'),
            self::FINES => __('Fines'),
            self::MEMBERS => __('Members'),
            self::CONTACTS => __('Contacts'),
            self::WEBSITE => __('Website'),
            self::INTERCLUBS => __('Interclubs'),
            self::SELECTIONS => __('Team selections'),
            self::TOURNAMENTS => __('Tournaments'),
            self::TRAININGS => __('Training offer'),
            self::COACH => __('Coach'),
            self::MEETINGS => __('Meetings'),
            self::BAR => __('Bar'),
            self::FACILITIES => __('Facilities'),
            self::SEASONS => __('Seasons'),
            self::SUPERVISION => __('Technical supervision'),
        };
    }

    /**
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            // Administrators hold every permission, granted explicitly rather than
            // through a Gate::before() bypass: several policies encode rules that
            // must survive an admin — UserPolicy::delete() stops an admin deleting
            // their own account, GuardianPolicy forbids forceDelete to everyone —
            // and a blanket short-circuit would silently override them.
            self::ADMINISTRATOR => Permission::cases(),

            self::COMMITTEE => [
                Permission::UsersView,
                Permission::SubscriptionsView,
                Permission::PaymentsView,
                // Deliberately not FinesView: fines are named disciplinary records.
                // The club restricted them before this refactor, and a security
                // refactor must not widen access as a side effect. Move it here if
                // the committee should see them — it is a one-line decision.
                Permission::ContactsView,
                Permission::NewsPostsView,
                Permission::InterclubsView,
                Permission::TournamentsView,
                Permission::TrainingsView,
                Permission::MeetingsView,
                Permission::SeasonsView,
            ],

            self::TREASURY => [
                Permission::PaymentsView,
                Permission::PaymentsReconcile,
                Permission::PaymentsRefund,
                Permission::PaymentsRemind,
                Permission::TransactionsView,
                Permission::TransactionsImport,
                Permission::TransactionsDelete,
            ],

            self::CASH_REGISTER => [
                Permission::CashRegisterView,
                Permission::CashRegisterManage,
                Permission::CashRegisterEntryCreate,
                Permission::CashRegisterHolderChange,
            ],

            self::FINES => [
                Permission::FinesView,
                Permission::FinesIssue,
                Permission::FinesCancel,
            ],

            self::MEMBERS => [
                Permission::UsersView,
                Permission::UsersCreate,
                Permission::UsersUpdate,
                Permission::UsersDelete,
                Permission::UsersAnonymize,
                Permission::UsersInvite,
                Permission::SubscriptionsView,
                Permission::SubscriptionsManage,
            ],

            self::CONTACTS => [
                Permission::ContactsView,
                Permission::ContactsManage,
                Permission::ContactsTemplatesManage,
                Permission::SpamsManage,
            ],

            self::WEBSITE => [
                Permission::NewsPostsView,
                Permission::NewsPostsManage,
                Permission::EventPostsManage,
            ],

            self::INTERCLUBS => [
                Permission::InterclubsView,
                Permission::InterclubsManage,
                Permission::TeamsManage,
                Permission::LeaguesManage,
                Permission::ClubsManage,
                Permission::ResultsManage,
                Permission::SelectionsManage,
            ],

            self::SELECTIONS => [
                Permission::InterclubsView,
                Permission::SelectionsManage,
            ],

            self::TOURNAMENTS => [
                Permission::TournamentsView,
                Permission::TournamentsManage,
                Permission::TournamentsLiveManage,
            ],

            self::TRAININGS => [
                Permission::TrainingsView,
                Permission::TrainingsManage,
                Permission::TrainingPlansManage,
            ],

            self::COACH => [
                Permission::TrainingsView,
                Permission::CoachAreaAccess,
            ],

            self::MEETINGS => [
                Permission::MeetingsView,
                Permission::MeetingsManage,
                Permission::MeetingsMinutesManage,
            ],

            self::BAR => [
                Permission::BarAccess,
                Permission::BarProductsManage,
                Permission::BarOrdersManage,
                Permission::BarCashSheetSend,
            ],

            self::FACILITIES => [
                Permission::RoomsManage,
                Permission::TablesManage,
                Permission::EquipmentHolderUpdate,
            ],

            self::SEASONS => [
                Permission::SeasonsView,
                Permission::SeasonsManage,
            ],

            self::SUPERVISION => [
                Permission::AuditLogView,
                Permission::QueueView,
                Permission::QueueManage,
                Permission::ClubUpdate,
            ],
        };
    }
}
