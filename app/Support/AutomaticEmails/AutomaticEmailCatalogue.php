<?php

declare(strict_types=1);

namespace App\Support\AutomaticEmails;

use App\Domains\Bar\Notifications\BarInventoryValidatedNotification;
use App\Domains\Bar\Notifications\BarRestockingDigestNotification;
use App\Domains\Bar\Services\CashSheetService;
use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportAcceptedNotification;
use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportPaidNotification;
use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportRejectedNotification;
use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportsDigestNotification;
use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportsToArchiveNotification;
use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportSubmittedNotification;
use App\Domains\ClubAdmin\ExternalParticipants\Notifications\ExternalCampCancelledNotification;
use App\Domains\ClubAdmin\ExternalParticipants\Notifications\ExternalRefundRequestedNotification;
use App\Domains\ClubAdmin\Feedback\Notifications\CampaignInvitationNotification;
use App\Domains\ClubAdmin\Feedback\Notifications\CampaignSummaryNotification;
use App\Domains\ClubAdmin\Feedback\Notifications\HelpOfferedNotification;
use App\Domains\ClubAdmin\Feedback\Notifications\NewFeedbackNotification;
use App\Domains\ClubAdmin\Finance\Notifications\FinancialExportFailedNotification;
use App\Domains\ClubAdmin\Finance\Notifications\FinancialExportReadyNotification;
use App\Domains\ClubAdmin\Fines\Notifications\FineCancelledNotification;
use App\Domains\ClubAdmin\Fines\Notifications\FineIssuedNotification;
use App\Domains\ClubAdmin\Payment\Notifications\RefundRequestedNotification;
use App\Domains\ClubAdmin\Payment\Notifications\WeeklyRefundReminderNotification;
use App\Domains\ClubAdmin\Users\Notifications\GdprErasureRequestedNotification;
use App\Domains\Competitions\Interclub\Notifications\CaptainLineupReminderNotification;
use App\Domains\Competitions\Interclub\Notifications\InterclubAvailabilityRequestNotification;
use App\Domains\Competitions\Interclub\Notifications\InterclubChangeNotification;
use App\Domains\Competitions\Interclub\Notifications\InterclubChangesHeldNotification;
use App\Domains\Competitions\Interclub\Notifications\InterclubLineupBroadcastNotification;
use App\Domains\Competitions\Interclub\Notifications\InterclubOwnForfeitAlertNotification;
use App\Domains\Competitions\Interclub\Notifications\InterclubPlayerRemovedNotification;
use App\Domains\Competitions\Interclub\Notifications\InterclubSelectionNotification;
use App\Domains\Competitions\Interclub\Notifications\MemberLeftTeamNotification;
use App\Domains\Competitions\Interclub\Notifications\TeamCreatedNotification;
use App\Domains\Competitions\Interclub\Notifications\WeeklySelectionsNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentCancelledNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentConfirmationExpiredNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentDebtReminderNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentInvitationNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentPairNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentPaymentReminderNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentRegistrationCancelledNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentRegistrationConfirmedNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentUnexcusedAbsenceNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentUpdatedNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentWaitlistRemovedNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentWaitlistSpotOpenedNotification;
use App\Domains\Competitions\Tournament\Notifications\UserUnregisteredFromTournament;
use App\Domains\Meetings\Notifications\MeetingCancelledNotification;
use App\Domains\Meetings\Notifications\MeetingDatePollNotification;
use App\Domains\Meetings\Notifications\MeetingInvitationNotification;
use App\Domains\Meetings\Notifications\MeetingMinutesNotification;
use App\Domains\Meetings\Notifications\MeetingPostponedNotification;
use App\Domains\Meetings\Notifications\MeetingRsvpConfirmationNotification;
use App\Domains\Shared\Enums\EmailTrigger;
use App\Domains\Shared\Enums\Feature;
use App\Domains\Subscriptions\Notifications\RenewalReminderNotification;
use App\Domains\Subscriptions\Notifications\SubscriptionCancelledNotification;
use App\Domains\Subscriptions\Notifications\SubscriptionCreatedNotification;
use App\Domains\Subscriptions\Notifications\SubscriptionFormulaChangedNotification;
use App\Domains\Subscriptions\Notifications\SubscriptionRefundRequestedNotification;
use App\Domains\Subscriptions\Notifications\SubscriptionRejectedNotification;
use App\Domains\Subscriptions\Notifications\TrainingPackRejectedNotification;
use App\Domains\Trainings\Notifications\TrainingCampEnrolledNotification;
use App\Domains\Trainings\Notifications\TrainingPackAddedByClubNotification;
use App\Domains\Trainings\Notifications\TrainingPackCancelledNotification;
use App\Domains\Trainings\Notifications\TrainingPackDiscontinuedNotification;
use App\Domains\Trainings\Notifications\TrainingPackEnrolmentCancelledNotification;
use App\Domains\Trainings\Notifications\TrainingPackMovedNotification;
use App\Domains\Trainings\Notifications\TrainingPackRequestedNotification;
use App\Domains\Trainings\Notifications\TrainingPackScheduleChangedNotification;
use App\Domains\Trainings\Notifications\TrainingSessionCancelledForStaffNotification;
use App\Domains\Trainings\Notifications\TrainingSessionCancelledNotification;
use App\Domains\Trainings\Notifications\TrainingSessionHandedOverNotification;
use App\Domains\Trainings\Notifications\TrainingSparringAssignedNotification;
use App\Domains\Trainings\Notifications\TrainingSparringReleasedNotification;
use App\Domains\Trainings\Notifications\TrainingWaitlistJoinedNotification;
use App\Domains\Trainings\Notifications\TrainingWaitlistOfferExpiredNotification;
use App\Domains\Trainings\Notifications\TrainingWaitlistSpotOfferedNotification;
use App\Mail\CommunicationMail;
use App\Mail\ContactFormConfirmationEmail;
use App\Mail\ContactFormNotificationEmail;
use App\Mail\CustomEmail;
use App\Mail\ExternalCampEnrolmentEmail;
use App\Mail\InviteGuardianMail;
use App\Mail\InviteNewUserMail;
use App\Mail\MemberWelcomeMail;
use App\Mail\PaymentInvitationEmail;
use App\Mail\QueueStalledMail;
use App\Mail\TournamentRegistrationMail;
use App\Mail\TournamentResultsMail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;

/**
 * Every email the application sends on its own — what, when, to whom, on
 * what condition — for the « Automatic emails » page of the supervision.
 *
 * Hand-written on purpose: no class can say in words who it writes to or why
 * it sometimes stays silent. What would drift is guarded elsewhere — a test
 * fails as soon as a notification or a mailable is neither listed here nor
 * excluded by name, or a listed command stops being scheduled — and the
 * cadence is never written here: it is read from the schedule.
 *
 * Decided on 2026-10-31: every email the application writes, whether a clock,
 * a member or a button sets it off; none whose words a person types.
 */
final class AutomaticEmailCatalogue
{
    /**
     * @return array<int, AutomaticEmail>
     */
    public static function emails(): array
    {
        return [
            ...self::tournaments(),
            ...self::interclubs(),
            ...self::trainings(),
            ...self::affiliations(),
            ...self::accounts(),
            ...self::treasury(),
            ...self::expenseReports(),
            ...self::meetings(),
            ...self::feedback(),
            ...self::fines(),
            ...self::contacts(),
            ...self::bar(),
            ...self::supervision(),
        ];
    }

    /**
     * The notifications and mailables deliberately left out, each with why —
     * for whoever adds an email and meets the coverage test, so untranslated.
     *
     * @return array<class-string, string>
     */
    public static function excluded(): array
    {
        return [
            CommunicationMail::class => 'Written by a committee member: the application only delivers it.',
            CustomEmail::class => 'Written by a committee member: the application only delivers it.',
            ExpenseReportSubmittedNotification::class => 'Rings the bell only, never sends an email.',
            TournamentRegistrationMail::class => 'The body of the tournament registration email, listed once under its notification.',
            TournamentResultsMail::class => 'Written by the organiser at the end of the tournament: the application only adds the rankings.',
            UserUnregisteredFromTournament::class => 'Never sent: nothing raises the event that would send it.',
        ];
    }

    /**
     * @return array<int, AutomaticEmail>
     */
    private static function accounts(): array
    {
        $domain = __('Accounts');

        return [
            new AutomaticEmail(
                source: MemberWelcomeMail::class,
                name: __('Welcome to the club'),
                domain: $domain,
                trigger: EmailTrigger::Event,
                recipients: __('The new member, or their guardians'),
                when: __('When an account is created'),
            ),
            new AutomaticEmail(
                source: VerifyEmail::class,
                name: __('Email address verification'),
                domain: $domain,
                trigger: EmailTrigger::Event,
                recipients: __('The account holder'),
                when: __('When an account is created or its address changes'),
            ),
            new AutomaticEmail(
                source: ResetPassword::class,
                name: __('Password reset'),
                domain: $domain,
                trigger: EmailTrigger::Event,
                recipients: __('The account holder'),
                when: __('When someone asks to reset the password'),
            ),
            new AutomaticEmail(
                source: InviteNewUserMail::class,
                name: __('Invitation to finish the registration'),
                domain: $domain,
                trigger: EmailTrigger::Action,
                recipients: __('The member invited'),
                when: __('When the secretariat invites a member'),
            ),
            new AutomaticEmail(
                source: InviteGuardianMail::class,
                name: __('Guardian invitation'),
                domain: $domain,
                trigger: EmailTrigger::Action,
                recipients: __('The guardian, once for all their children'),
                when: __('When the secretariat invites a guardian'),
            ),
            new AutomaticEmail(
                source: GdprErasureRequestedNotification::class,
                name: __('Data erasure requested'),
                domain: $domain,
                trigger: EmailTrigger::Event,
                recipients: __('The administrators and the secretary'),
                when: __('When a member asks for their data to be erased'),
            ),
        ];
    }

    /**
     * @return array<int, AutomaticEmail>
     */
    private static function affiliations(): array
    {
        $domain = __('Affiliations');

        return [
            new AutomaticEmail(
                source: SubscriptionCreatedNotification::class,
                name: __('Affiliation request recorded'),
                domain: $domain,
                trigger: EmailTrigger::Event,
                recipients: __('The member, or their guardians'),
                when: __('When a member asks to be affiliated'),
            ),
            new AutomaticEmail(
                source: SubscriptionRejectedNotification::class,
                name: __('Affiliation request rejected'),
                domain: $domain,
                trigger: EmailTrigger::Action,
                recipients: __('The member, or their guardians'),
                when: __('When the secretariat rejects the request'),
            ),
            new AutomaticEmail(
                source: SubscriptionFormulaChangedNotification::class,
                name: __('Affiliation formula changed'),
                domain: $domain,
                trigger: EmailTrigger::Action,
                recipients: __('The member, or their guardians'),
                when: __('When the secretariat changes the formula of an affiliation'),
                condition: __('Only once the affiliation has been invoiced'),
            ),
            new AutomaticEmail(
                source: SubscriptionCancelledNotification::class,
                name: __('Affiliation cancelled'),
                domain: $domain,
                trigger: EmailTrigger::Action,
                recipients: __('The member, or their guardians'),
                when: __('When the secretariat cancels an affiliation'),
            ),
            new AutomaticEmail(
                source: SubscriptionRefundRequestedNotification::class,
                name: __('Affiliation refund to process'),
                domain: $domain,
                trigger: EmailTrigger::Event,
                recipients: __('The treasurers'),
                when: __('When an affiliation is cancelled or lowered after it was paid'),
            ),
            new AutomaticEmail(
                source: RenewalReminderNotification::class,
                name: __('Renewal reminder'),
                domain: $domain,
                trigger: EmailTrigger::Action,
                recipients: __('The members of last season who have not renewed'),
                when: __('When the secretariat sends the reminder from the member list'),
                condition: __('Never twice to the same member within a week'),
            ),
            new AutomaticEmail(
                source: PaymentInvitationEmail::class,
                name: __('Invitation to pay'),
                domain: $domain,
                trigger: EmailTrigger::Action,
                recipients: __('The member, or their guardians'),
                when: __('When a committee member invites a member to pay or reminds them'),
            ),
        ];
    }

    /**
     * @return array<int, AutomaticEmail>
     */
    private static function bar(): array
    {
        $feature = Feature::Bar;

        return [
            new AutomaticEmail(
                source: BarRestockingDigestNotification::class,
                name: __('Restocking list'),
                domain: $feature->label(),
                trigger: EmailTrigger::Scheduled,
                recipients: __('The storekeepers'),
                condition: __('Only when something has to be bought or the automatic restocking changed a threshold'),
                commands: ['bar:restocking-digest'],
                feature: $feature,
            ),
            new AutomaticEmail(
                source: BarInventoryValidatedNotification::class,
                name: __('Inventory validated'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The treasurer and the storekeepers'),
                when: __('When a storekeeper validates an inventory'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: CashSheetService::class,
                name: __('Cash sheet'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The address typed in, the treasurer\'s by default'),
                when: __('When a bar manager sends the cash sheet'),
                feature: $feature,
            ),
        ];
    }

    /**
     * @return array<int, AutomaticEmail>
     */
    private static function contacts(): array
    {
        $feature = Feature::Contacts;

        return [
            new AutomaticEmail(
                source: ContactFormConfirmationEmail::class,
                name: __('Contact form acknowledgement'),
                domain: $feature->label(),
                trigger: EmailTrigger::Event,
                recipients: __('The person who wrote'),
                when: __('When someone writes through the contact form of the website'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: ContactFormNotificationEmail::class,
                name: __('New contact request'),
                domain: $feature->label(),
                trigger: EmailTrigger::Event,
                recipients: __('The club\'s address'),
                when: __('When someone writes through the contact form of the website'),
                feature: $feature,
            ),
        ];
    }

    /**
     * @return array<int, AutomaticEmail>
     */
    private static function expenseReports(): array
    {
        $feature = Feature::ExpenseReports;

        return [
            new AutomaticEmail(
                source: ExpenseReportsDigestNotification::class,
                name: __('Expense reports to decide'),
                domain: $feature->label(),
                trigger: EmailTrigger::Scheduled,
                recipients: __('Those who decide on expense reports'),
                condition: __('Only when at least one report is waiting'),
                commands: ['expense-reports:send-digest'],
                feature: $feature,
            ),
            new AutomaticEmail(
                source: ExpenseReportsToArchiveNotification::class,
                name: __('Expense reports to archive'),
                domain: $feature->label(),
                trigger: EmailTrigger::Scheduled,
                recipients: __('Those who can archive the paid reports'),
                condition: __('Only when paid reports are not archived yet'),
                commands: ['expense-reports:remind-archiving', 'expense-reports:remind-archiving --year-end'],
                cadence: __('Every quarter of the financial year, and on the 5th of the month after it closes'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: ExpenseReportAcceptedNotification::class,
                name: __('Expense report accepted'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The author of the report'),
                when: __('When a report is accepted'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: ExpenseReportRejectedNotification::class,
                name: __('Expense report rejected'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The author of the report'),
                when: __('When a report is rejected'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: ExpenseReportPaidNotification::class,
                name: __('Expense report paid'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The author of the report'),
                when: __('When the transfer is matched with the report'),
                feature: $feature,
            ),
        ];
    }

    /**
     * @return array<int, AutomaticEmail>
     */
    private static function feedback(): array
    {
        $domain = __('Feedback');

        return [
            new AutomaticEmail(
                source: CampaignInvitationNotification::class,
                name: __('Yearly survey'),
                domain: $domain,
                trigger: EmailTrigger::Scheduled,
                recipients: __('Every member, one email per address'),
                condition: __('Only on the day a survey opens, and once more halfway through'),
                commands: ['feedback:send-campaign-mailings'],
            ),
            new AutomaticEmail(
                source: CampaignSummaryNotification::class,
                name: __('Survey results'),
                domain: $domain,
                trigger: EmailTrigger::Scheduled,
                recipients: __('The committee'),
                condition: __('Only the day after a survey closes'),
                commands: ['feedback:send-campaign-mailings'],
            ),
            new AutomaticEmail(
                source: NewFeedbackNotification::class,
                name: __('New feedback'),
                domain: $domain,
                trigger: EmailTrigger::Event,
                recipients: __('The feedback delegation, or the committee when nobody holds it'),
                when: __('When a member writes through the feedback box'),
            ),
            new AutomaticEmail(
                source: HelpOfferedNotification::class,
                name: __('Offer of help'),
                domain: $domain,
                trigger: EmailTrigger::Event,
                recipients: __('The feedback delegation, or the committee when nobody holds it'),
                when: __('When a member offers to help'),
            ),
        ];
    }

    /**
     * @return array<int, AutomaticEmail>
     */
    private static function fines(): array
    {
        $domain = __('Fines');

        return [
            new AutomaticEmail(
                source: FineIssuedNotification::class,
                name: __('Fine issued'),
                domain: $domain,
                trigger: EmailTrigger::Action,
                recipients: __('The member fined'),
                when: __('When a fine is issued'),
            ),
            new AutomaticEmail(
                source: FineCancelledNotification::class,
                name: __('Fine cancelled'),
                domain: $domain,
                trigger: EmailTrigger::Action,
                recipients: __('The member fined'),
                when: __('When a fine is cancelled'),
            ),
        ];
    }

    /**
     * @return array<int, AutomaticEmail>
     */
    private static function interclubs(): array
    {
        $feature = Feature::Interclubs;

        return [
            new AutomaticEmail(
                source: WeeklySelectionsNotification::class,
                name: __('Selections of the week'),
                domain: $feature->label(),
                trigger: EmailTrigger::Scheduled,
                recipients: __('The players of every team, the captains and the committee'),
                condition: __('Only when a match is played in the next seven days'),
                commands: ['interclubs:send-weekly-selections'],
                feature: $feature,
            ),
            new AutomaticEmail(
                source: CaptainLineupReminderNotification::class,
                name: __('Lineup reminder'),
                domain: $feature->label(),
                trigger: EmailTrigger::Scheduled,
                recipients: __('Each captain concerned'),
                condition: __('Only while one of their lineups has not been sent'),
                commands: ['interclubs:remind-captains'],
                feature: $feature,
            ),
            new AutomaticEmail(
                source: InterclubChangeNotification::class,
                name: __('Match changed by the federation'),
                domain: $feature->label(),
                trigger: EmailTrigger::Scheduled,
                recipients: __('The players of the team and its captain'),
                when: __('Also when the interclubs delegation releases the changes held for review'),
                condition: __('Only when the federation changed a match still to come'),
                commands: ['interclubs:import-aftt'],
                feature: $feature,
                optOut: __('Selections and lineup announcements'),
            ),
            new AutomaticEmail(
                source: InterclubChangesHeldNotification::class,
                name: __('Calendar changes held for review'),
                domain: $feature->label(),
                trigger: EmailTrigger::Scheduled,
                recipients: __('The interclubs delegation'),
                condition: __('Only when one reading of the federation calendar changes too much at once'),
                commands: ['interclubs:import-aftt'],
                feature: $feature,
            ),
            new AutomaticEmail(
                source: InterclubOwnForfeitAlertNotification::class,
                name: __('Forfeit of one of our teams'),
                domain: $feature->label(),
                trigger: EmailTrigger::Scheduled,
                recipients: __('The interclubs delegation'),
                condition: __('Only when the federation records a forfeit of one of our teams'),
                commands: ['interclubs:import-aftt'],
                feature: $feature,
            ),
            new AutomaticEmail(
                source: InterclubAvailabilityRequestNotification::class,
                name: __('Availability request'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The players of the team'),
                when: __('When a captain asks the team for its availability'),
                feature: $feature,
                optOut: __('Interclub availability reminders'),
            ),
            new AutomaticEmail(
                source: InterclubSelectionNotification::class,
                name: __('Selected for a match'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The players selected'),
                when: __('When the captain sends the lineup'),
                feature: $feature,
                optOut: __('Selections and lineup announcements'),
            ),
            new AutomaticEmail(
                source: InterclubLineupBroadcastNotification::class,
                name: __('Lineup of the match'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The players of the team who are not selected'),
                when: __('When the captain sends the lineup'),
                feature: $feature,
                optOut: __('Selections and lineup announcements'),
            ),
            new AutomaticEmail(
                source: InterclubPlayerRemovedNotification::class,
                name: __('No longer selected'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The players taken off the lineup'),
                when: __('When the captain changes a lineup already sent'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: MemberLeftTeamNotification::class,
                name: __('A player left the club'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The captain of each of their teams, and the selector'),
                when: __('When the secretariat records the departure of a member'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TeamCreatedNotification::class,
                name: __('New team'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The administrators'),
                when: __('When a team is created'),
                feature: $feature,
            ),
        ];
    }

    /**
     * @return array<int, AutomaticEmail>
     */
    private static function meetings(): array
    {
        $feature = Feature::Meetings;

        return [
            new AutomaticEmail(
                source: MeetingInvitationNotification::class,
                name: __('Meeting invitation'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The participants of the meeting'),
                when: __('When a meeting is convened'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: MeetingDatePollNotification::class,
                name: __('Date poll'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The participants of the meeting'),
                when: __('When the organiser offers several dates'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: MeetingRsvpConfirmationNotification::class,
                name: __('Attendance confirmed'),
                domain: $feature->label(),
                trigger: EmailTrigger::Event,
                recipients: __('The participant who answered'),
                when: __('When a participant answers the invitation'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: MeetingPostponedNotification::class,
                name: __('Meeting postponed'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The participants of the meeting'),
                when: __('When the organiser postpones the meeting'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: MeetingCancelledNotification::class,
                name: __('Meeting cancelled'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The participants of the meeting'),
                when: __('When the organiser cancels the meeting'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: MeetingMinutesNotification::class,
                name: __('Minutes published'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The participants of the meeting'),
                when: __('When the minutes are published'),
                feature: $feature,
            ),
        ];
    }

    /**
     * @return array<int, AutomaticEmail>
     */
    private static function supervision(): array
    {
        $feature = Feature::Supervision;

        return [
            new AutomaticEmail(
                source: QueueStalledMail::class,
                name: __('Queue alert'),
                domain: $feature->label(),
                trigger: EmailTrigger::Scheduled,
                recipients: __('The administrators'),
                condition: __('Only when the queue worker looks down'),
                commands: ['queue:check-health'],
                feature: $feature,
            ),
        ];
    }

    /**
     * @return array<int, AutomaticEmail>
     */
    private static function tournaments(): array
    {
        $feature = Feature::Tournaments;

        return [
            new AutomaticEmail(
                source: TournamentPaymentReminderNotification::class,
                name: __('Tournament payment reminder'),
                domain: $feature->label(),
                trigger: EmailTrigger::Scheduled,
                recipients: __('The player registered, or their guardians'),
                condition: __('Only three days before the payment deadline and the day after it, while the entry is unpaid'),
                commands: ['tournament:process-deadlines'],
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TournamentWaitlistSpotOpenedNotification::class,
                name: __('Spot available on the waiting list'),
                domain: $feature->label(),
                trigger: EmailTrigger::Scheduled,
                recipients: __('The next player on the waiting list'),
                when: __('Also as soon as a registered player withdraws'),
                condition: __('Only when the previous player let the 48 hours pass'),
                commands: ['tournament:process-deadlines'],
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TournamentConfirmationExpiredNotification::class,
                name: __('Reserved spot expired'),
                domain: $feature->label(),
                trigger: EmailTrigger::Scheduled,
                recipients: __('The player who did not confirm'),
                condition: __('Only when a spot offered 48 hours ago was not confirmed'),
                commands: ['tournament:process-deadlines'],
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TournamentWaitlistRemovedNotification::class,
                name: __('Waiting list closed'),
                domain: $feature->label(),
                trigger: EmailTrigger::Scheduled,
                recipients: __('The players still on the waiting list'),
                when: __('Also when an organiser closes the registrations'),
                condition: __('Only when a tournament day has come with players still waiting'),
                commands: ['tournament:close-registrations'],
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TournamentInvitationNotification::class,
                name: __('Tournament invitation'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The members the tournament is open to'),
                when: __('When an organiser publishes the tournament'),
                feature: $feature,
                optOut: __('New tournaments and events'),
            ),
            new AutomaticEmail(
                source: TournamentRegistrationConfirmedNotification::class,
                name: __('Tournament registration recorded'),
                domain: $feature->label(),
                trigger: EmailTrigger::Event,
                recipients: __('The player registered, or their guardians'),
                when: __('When a member registers, or is registered by the committee'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TournamentRegistrationCancelledNotification::class,
                name: __('Tournament registration cancelled'),
                domain: $feature->label(),
                trigger: EmailTrigger::Event,
                recipients: __('The player registered, or their guardians'),
                when: __('When a registration is cancelled'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: RefundRequestedNotification::class,
                name: __('Tournament refund to process'),
                domain: $feature->label(),
                trigger: EmailTrigger::Event,
                recipients: __('The treasurer and the secretary'),
                when: __('When a player who had paid is unregistered'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TournamentPairNotification::class,
                name: __('Doubles partner'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The players concerned'),
                when: __('When an organiser forms or breaks a doubles pair'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TournamentUpdatedNotification::class,
                name: __('Tournament changed'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('Every player registered'),
                when: __('When an organiser changes an important detail of the tournament'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TournamentCancelledNotification::class,
                name: __('Tournament cancelled'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('Every player registered'),
                when: __('When an organiser cancels the tournament'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TournamentDebtReminderNotification::class,
                name: __('Unpaid entry reminder'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The player let in without paying'),
                when: __('The morning after the desk lets a player in without paying, at 9h00'),
                condition: __('Only if the entry is still unpaid by then'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TournamentUnexcusedAbsenceNotification::class,
                name: __('Unexcused absence'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The absent player'),
                when: __('When an organiser marks a player absent without excuse'),
                feature: $feature,
            ),
        ];
    }

    /**
     * @return array<int, AutomaticEmail>
     */
    private static function trainings(): array
    {
        $feature = Feature::Trainings;

        return [
            new AutomaticEmail(
                source: TrainingWaitlistSpotOfferedNotification::class,
                name: __('Training spot available'),
                domain: $feature->label(),
                trigger: EmailTrigger::Scheduled,
                recipients: __('The next member on the waiting list'),
                when: __('Also as soon as a member leaves the pack'),
                condition: __('Only when the previous member let the 48 hours pass'),
                commands: ['training:process-deadlines'],
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TrainingWaitlistOfferExpiredNotification::class,
                name: __('Training spot expired'),
                domain: $feature->label(),
                trigger: EmailTrigger::Scheduled,
                recipients: __('The member who did not confirm'),
                condition: __('Only when a spot offered 48 hours ago was not confirmed'),
                commands: ['training:process-deadlines'],
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TrainingPackRequestedNotification::class,
                name: __('Training request recorded'),
                domain: $feature->label(),
                trigger: EmailTrigger::Event,
                recipients: __('The member, or their guardians'),
                when: __('When a member asks to join a pack'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TrainingWaitlistJoinedNotification::class,
                name: __('On the training waiting list'),
                domain: $feature->label(),
                trigger: EmailTrigger::Event,
                recipients: __('The member, or their guardians'),
                when: __('When a member asks to join a full pack or camp'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TrainingCampEnrolledNotification::class,
                name: __('Camp spot confirmed'),
                domain: $feature->label(),
                trigger: EmailTrigger::Event,
                recipients: __('The member, or their guardians'),
                when: __('When a member enrols in a camp, or the club accepts their request'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: ExternalCampEnrolmentEmail::class,
                name: __('Camp enrolment of a non-member'),
                domain: $feature->label(),
                trigger: EmailTrigger::Event,
                recipients: __('The non-member enrolled'),
                when: __('When a non-member enrols in a camp, or a manager sends the confirmation again'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TrainingPackAddedByClubNotification::class,
                name: __('Enrolled by the club'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The member, or their guardians'),
                when: __('When a manager enrols a member in a pack'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TrainingPackMovedNotification::class,
                name: __('Moved to another pack'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The member, or their guardians'),
                when: __('When a manager moves a member to another pack'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TrainingPackCancelledNotification::class,
                name: __('Left a training pack'),
                domain: $feature->label(),
                trigger: EmailTrigger::Event,
                recipients: __('The member, or their guardians'),
                when: __('When a member leaves a pack'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TrainingPackEnrolmentCancelledNotification::class,
                name: __('Enrolment cancelled'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The member, or their guardians'),
                when: __('When a manager cancels an enrolment made by mistake'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TrainingPackRejectedNotification::class,
                name: __('Training request rejected'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The member, or their guardians'),
                when: __('When a manager rejects a training request'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TrainingPackScheduleChangedNotification::class,
                name: __('New training schedule'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The members of the pack'),
                when: __('When a manager changes the day, the hour or the room of a pack'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TrainingPackDiscontinuedNotification::class,
                name: __('Training stopped'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The members of the pack'),
                when: __('When the club stops a pack during the season'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: ExternalCampCancelledNotification::class,
                name: __('Camp cancelled for a non-member'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The non-members enrolled'),
                when: __('When the club stops a camp'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: ExternalRefundRequestedNotification::class,
                name: __('Non-member refund to process'),
                domain: $feature->label(),
                trigger: EmailTrigger::Event,
                recipients: __('The treasurers'),
                when: __('When a non-member who had paid is taken off a camp'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TrainingSessionCancelledNotification::class,
                name: __('Training session cancelled'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The members of the pack'),
                when: __('When a coach or a manager cancels a session'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TrainingSessionCancelledForStaffNotification::class,
                name: __('Training session cancelled, for the staff'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The sparrings and the replacement coach of the session'),
                when: __('When a coach or a manager cancels a session'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TrainingSessionHandedOverNotification::class,
                name: __('Session to coach'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The coach the session is handed over to'),
                when: __('When a session is handed over to another coach'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TrainingSparringAssignedNotification::class,
                name: __('Sparring expected'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The sparring'),
                when: __('When a sparring is assigned to a pack or a session'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: TrainingSparringReleasedNotification::class,
                name: __('Sparring no longer expected'),
                domain: $feature->label(),
                trigger: EmailTrigger::Action,
                recipients: __('The sparring'),
                when: __('When a sparring is taken off a pack or a session'),
                feature: $feature,
            ),
        ];
    }

    /**
     * @return array<int, AutomaticEmail>
     */
    private static function treasury(): array
    {
        $feature = Feature::Treasury;

        return [
            new AutomaticEmail(
                source: WeeklyRefundReminderNotification::class,
                name: __('Refunds to process'),
                domain: $feature->label(),
                trigger: EmailTrigger::Scheduled,
                recipients: __('Those who can refund payments'),
                condition: __('Only when a refund is waiting'),
                commands: ['payment:send-refund-reminder'],
                feature: $feature,
            ),
            new AutomaticEmail(
                source: FinancialExportReadyNotification::class,
                name: __('Financial export ready'),
                domain: $feature->label(),
                trigger: EmailTrigger::Event,
                recipients: __('The person who asked for it'),
                when: __('When an export asked for is ready'),
                feature: $feature,
            ),
            new AutomaticEmail(
                source: FinancialExportFailedNotification::class,
                name: __('Financial export failed'),
                domain: $feature->label(),
                trigger: EmailTrigger::Event,
                recipients: __('The person who asked for it'),
                when: __('When an export asked for could not be built'),
                feature: $feature,
            ),
        ];
    }
}
