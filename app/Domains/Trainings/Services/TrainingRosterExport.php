<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Services;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionTrainingPack;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Everybody tied to a training pack of a season, on one sheet: the whole
 * training offer read at a glance, one row per member and per pack — a member
 * in two packs shows twice, which is how the committee sees who takes several.
 *
 * Tied means enrolled, waiting for approval, in the queue or offered a spot.
 * A member who left the pack, or whose enrolment was cancelled, is no longer
 * tied to it and stays out.
 *
 * A minor or a managed account is reached through their guardians, as in the
 * communications: often the only address the family has.
 */
final readonly class TrainingRosterExport
{
    /** The statuses that tie a member to a pack, in the order the sheet reads them. */
    private const array STATUS_ORDER = ['enrolled' => 0, 'pending' => 1, 'offered' => 2, 'waiting' => 3];

    /** Supported formats mapped to their PhpSpreadsheet writer, as for the training plan. */
    private const array WRITERS = ['csv' => 'Csv', 'xlsx' => 'Xlsx'];

    public function __construct(private TrainingAttendanceReport $attendance) {}

    /**
     * @return array{filename: string, contents: string, mime: string}
     */
    public function export(Season $season, string $format): array
    {
        $format = strtolower(trim($format));

        if (! isset(self::WRITERS[$format])) {
            throw new \InvalidArgumentException("Unsupported export format: {$format}");
        }

        $spreadsheet = $this->spreadsheet($season);
        $writer = IOFactory::createWriter($spreadsheet, self::WRITERS[$format]);

        ob_start();
        $writer->save('php://output');
        $contents = (string) ob_get_clean();

        $spreadsheet->disconnectWorksheets();

        return [
            'filename' => Str::slug(__('Training enrolments') . ' ' . $season->name) . ".{$format}",
            'contents' => $contents,
            'mime' => $format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    /**
     * The rows of the sheet, sorted by pack, then status — the queue in its
     * order of call, an offer under way first — then name.
     *
     * @return list<array{pack: string, level: string, slot: string, coach: string, status: string, status_label: string, position: int|null, last_name: string, first_name: string, age: int|null, ranking: string, emails: string, phone: string, since: string, paid: bool, attendance: int|null}>
     */
    public function rows(Season $season): array
    {
        $packs = TrainingPack::query()
            ->where('season_id', $season->id)
            ->with(['level', 'trainer'])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $rows = [];

        $ties = SubscriptionTrainingPack::query()
            ->whereIn('training_pack_id', $packs->modelKeys())
            ->whereIn('status', array_keys(self::STATUS_ORDER))
            ->get();

        // The pack lives on the affiliation: a cancelled one ties nobody.
        $subscriptions = Subscription::query()
            ->whereKey($ties->pluck('subscription_id')->unique()->all())
            ->affiliated()
            ->with('user.guardians')
            ->get()
            ->keyBy('id');

        foreach ($packs as $pack) {
            $ordered = $ties
                ->where('training_pack_id', $pack->id)
                ->filter(fn (SubscriptionTrainingPack $tie): bool => $subscriptions->has($tie->subscription_id))
                ->sortBy([
                    fn (SubscriptionTrainingPack $a, SubscriptionTrainingPack $b): int => self::STATUS_ORDER[$a->status] <=> self::STATUS_ORDER[$b->status],
                    fn (SubscriptionTrainingPack $a, SubscriptionTrainingPack $b): int => ($a->waitlist_position ?? PHP_INT_MAX) <=> ($b->waitlist_position ?? PHP_INT_MAX),
                    fn (SubscriptionTrainingPack $a, SubscriptionTrainingPack $b): int => $this->sortName($subscriptions[$a->subscription_id]->user) <=> $this->sortName($subscriptions[$b->subscription_id]->user),
                ]);

            foreach ($ordered as $tie) {
                $rows[] = $this->row($pack, $tie, $subscriptions[$tie->subscription_id]);
            }
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function headers(): array
    {
        return [
            __('Pack'), __('Level'), __('Slot'), __('Coach'), __('Status'), __('Position in the queue'),
            __('Last name'), __('First name'), __('Age'), __('Ranking'), __('Email(s)'), __('Phone'),
            __('Since'), __('Affiliation paid'), __('Attendance'),
        ];
    }

    /**
     * Whom to call: the member, or for a minor or a managed account the
     * guardians — their phone, or the number noted on the member's file.
     */
    private function phone(User $member): string
    {
        if ($member->email !== null && ! $member->isMinor()) {
            return (string) $member->phone_number;
        }

        // toBase() first: with no guardian linked, map() leaves an empty
        // Eloquent collection, and unique() then asks each phone number for a
        // model key.
        $phones = $member->guardians
            ->toBase()
            ->map(fn (Guardian $guardian): string => $guardian->phone)
            ->push($member->guardian_phone_number, $member->phone_number)
            ->filter(fn (?string $phone): bool => filled($phone))
            ->unique()
            ->values();

        return $phones->implode(', ');
    }

    /**
     * @return array{pack: string, level: string, slot: string, coach: string, status: string, status_label: string, position: int|null, last_name: string, first_name: string, age: int|null, ranking: string, emails: string, phone: string, since: string, paid: bool, attendance: int|null}
     */
    private function row(TrainingPack $pack, SubscriptionTrainingPack $tie, Subscription $subscription): array
    {
        $member = $subscription->user;
        $status = $tie->status;
        $since = $tie->starts_on ?? $pack->pack_start_date;

        return [
            'pack' => $pack->name,
            'level' => $pack->level?->label ?? '',
            'slot' => $this->slot($pack),
            'coach' => $pack->trainer?->full_name ?? '',
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'position' => in_array($status, ['waiting', 'offered'], true) ? $tie->waitlist_position : null,
            'last_name' => $member->last_name,
            'first_name' => $member->first_name,
            'age' => $member->birthdate?->age,
            'ranking' => (string) $member->ranking?->getLabel(),
            'emails' => implode(', ', $member->contactEmails()),
            'phone' => $this->phone($member),
            'since' => $since ? Carbon::parse($since)->format('d/m/Y') : '',
            'paid' => $subscription->status !== 'pending',
            'attendance' => $status === 'enrolled' ? $this->attendance->memberRate($pack, $member->id) : null,
        ];
    }

    private function slot(TrainingPack $pack): string
    {
        $days = ['', __('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat'), __('Sun')];

        return trim(($days[$pack->day_of_week ?? 0] ?? '') . ' ' . ($pack->start_time ? Carbon::parse($pack->start_time)->format('H:i') : ''));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function sortName(User $member): array
    {
        return [Str::lower(Str::ascii($member->last_name)), Str::lower(Str::ascii($member->first_name))];
    }

    private function spreadsheet(Season $season): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(Str::limit(__('Enrolments'), 31, ''));

        foreach ($this->headers() as $index => $header) {
            $sheet->setCellValue([$index + 1, 1], $header);
        }

        foreach ($this->rows($season) as $line => $row) {
            $values = [
                $row['pack'], $row['level'], $row['slot'], $row['coach'], $row['status_label'],
                $row['position'] === null ? '' : (string) $row['position'],
                $row['last_name'], $row['first_name'],
                $row['age'] === null ? '' : (string) $row['age'],
                $row['ranking'], $row['emails'], $row['phone'], $row['since'],
                $row['paid'] ? __('Yes') : __('No'),
                $row['attendance'] === null ? '' : $row['attendance'] . ' %',
            ];

            foreach ($values as $index => $value) {
                $sheet->setCellValueExplicit([$index + 1, $line + 2], $value, DataType::TYPE_STRING);
            }
        }

        return $spreadsheet;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'enrolled' => __('Enrolled'),
            'pending' => __('Request to approve'),
            'offered' => __('Spot offered'),
            'waiting' => __('On the waiting list'),
            default => $status,
        };
    }
}
