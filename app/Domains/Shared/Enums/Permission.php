<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

use Database\Seeders\RoleSeeder;

/**
 * The atomic rights the application checks.
 *
 * Application code always asks for one of these — never for a role. Roles are
 * only bundles, assembled in {@see RoleSeeder}, so that
 * changing who may do something is a matter of moving a permission between
 * bundles rather than grepping the codebase.
 *
 * Relational rules ("this team", "this cash register", "my own profile") are
 * NOT expressible here: a permission grants the right in general, and the
 * matching Policy narrows it down to the record at hand.
 */
enum Permission: string
{
    // Accès et droits
    case AccessManage = 'access.manage';

    // Supervision technique
    case AuditLogView = 'audit_log.view';

    // Bar
    case BarAccess = 'bar.access';
    case BarCashSheetSend = 'bar.cash_sheet.send';
    case BarCategoriesManage = 'bar.categories.manage';
    case BarOrdersClose = 'bar.orders.close';
    case BarOrdersManage = 'bar.orders.manage';
    case BarOrdersPay = 'bar.orders.pay';
    case BarOrdersTakeover = 'bar.orders.takeover';
    case BarProductsManage = 'bar.products.manage';
    case BarStockManage = 'bar.stock.manage';
    case CashRegisterEntryCreate = 'cash_register.entry.create';
    case CashRegisterHolderChange = 'cash_register.holder.change';
    case CashRegisterManage = 'cash_register.manage';

    // Caisse
    case CashRegisterView = 'cash_register.view';
    case ClubsManage = 'clubs.manage';
    case ClubUpdate = 'club.update';
    case CoachAreaAccess = 'coach_area.access';
    case ContactsManage = 'contacts.manage';

    // Contacts et CRM
    case ContactsView = 'contacts.view';
    case EquipmentHolderUpdate = 'equipment.holder.update';
    case EventPostsManage = 'event_posts.manage';
    case FinesCancel = 'fines.cancel';
    case FinesIssue = 'fines.issue';

    // Amendes
    case FinesView = 'fines.view';
    case InterclubsManage = 'interclubs.manage';

    // Interclubs
    case InterclubsView = 'interclubs.view';
    case LeaguesManage = 'leagues.manage';
    case MeetingsManage = 'meetings.manage';
    case MeetingsMinutesManage = 'meetings.minutes.manage';

    // Réunions
    case MeetingsView = 'meetings.view';
    case NewsPostsManage = 'news_posts.manage';

    // Site vitrine
    case NewsPostsView = 'news_posts.view';
    case PaymentsReconcile = 'payments.reconcile';
    case PaymentsRefund = 'payments.refund';
    case PaymentsRemind = 'payments.remind';
    // Trésorerie
    case PaymentsView = 'payments.view';
    case QueueManage = 'queue.manage';
    case QueueView = 'queue.view';
    case ResultsManage = 'results.manage';

    // Installations
    case RoomsManage = 'rooms.manage';
    case SeasonsManage = 'seasons.manage';

    // Saisons
    case SeasonsView = 'seasons.view';

    // Sélections (le périmètre exact reste vérifié par la policy : un capitaine
    // porteur de ce droit ne l'exerce que sur ses propres équipes)
    case SelectionsManage = 'selections.manage';
    case SpamsManage = 'spams.manage';
    case SubscriptionsManage = 'subscriptions.manage';
    case SubscriptionsView = 'subscriptions.view';
    case TablesManage = 'tables.manage';
    case TeamsManage = 'teams.manage';
    case TournamentsLiveManage = 'tournaments.live.manage';
    case TournamentsManage = 'tournaments.manage';

    // Tournois
    case TournamentsView = 'tournaments.view';
    case TrainingPlansManage = 'training_plans.manage';
    case TrainingsManage = 'trainings.manage';

    // Entraînements
    case TrainingsView = 'trainings.view';
    case TransactionsDelete = 'transactions.delete';
    case TransactionsImport = 'transactions.import';
    case TransactionsView = 'transactions.view';
    case UsersAnonymize = 'users.anonymize';
    case UsersCreate = 'users.create';
    case UsersDelete = 'users.delete';
    case UsersImport = 'users.import';
    case UsersInvite = 'users.invite';
    case UsersUpdate = 'users.update';

    // Membres et affiliations
    case UsersView = 'users.view';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $permission): string => $permission->value, self::cases());
    }
}
