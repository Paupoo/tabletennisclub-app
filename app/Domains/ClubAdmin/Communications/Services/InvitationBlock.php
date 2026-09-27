<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Communications\Services;

use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\InvitationTarget;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Support\Carbon;

/**
 * The ready-made block "invite to…" drops into a message.
 *
 * Plain markdown on purpose: it reads the same in the message sent from the
 * application and once pasted into a mail client. The link leads to the
 * "for whom?" page, so a parent lands in their child's seat.
 */
class InvitationBlock
{
    public function markdown(InvitationTarget $target, int $id): string
    {
        /** @var Tournament|TrainingPack|Meeting $model */
        $model = $target->modelClass()::query()->findOrFail($id);

        $details = array_filter([
            $this->when($model),
            $this->where($model),
            $this->price($model),
        ]);

        return "**{$this->title($model)}**  \n"
            . ($details !== [] ? implode(' · ', $details) . "\n" : '')
            . "\n[" . __('Register') . '](' . route('communications.invitation', [$target->value, $model->id]) . ')';
    }

    /**
     * What members can still register for, in date order.
     *
     * @return list<array{id: int, name: string}>
     */
    public function options(InvitationTarget $target): array
    {
        $models = match ($target) {
            InvitationTarget::Tournament => Tournament::query()
                ->registrationsOpen()
                ->orderBy('start_date')
                ->orderBy('id')
                ->get(),
            InvitationTarget::TrainingPack => TrainingPack::query()
                ->where('season_id', Season::current()?->id)
                ->where('is_active', true)
                ->where('enrollments_open', true)
                ->orderBy('name')
                ->orderBy('id')
                ->get(),
            InvitationTarget::Meeting => Meeting::query()
                ->whereIn('status', [MeetingStatusEnum::PLANNING, MeetingStatusEnum::CONFIRMED])
                ->where('scheduled_at', '>=', now())
                ->orderBy('scheduled_at')
                ->orderBy('id')
                ->get(),
        };

        return $models
            ->map(fn (Tournament|TrainingPack|Meeting $model): array => [
                'id' => $model->id,
                'name' => $this->title($model) . (($when = $this->when($model)) !== null ? ' — ' . $when : ''),
            ])
            ->values()
            ->all();
    }

    /**
     * What a message invites to, read back from its "for whom?" links — as
     * "tournament:12", the form the reminder filter looks for.
     *
     * @return list<string>
     */
    public function targetsIn(string $markdown): array
    {
        $kinds = implode('|', array_map(fn (InvitationTarget $target): string => preg_quote($target->value, '#'), InvitationTarget::cases()));

        preg_match_all('#' . preg_quote(url('invitation'), '#') . '/(' . $kinds . ')/(\d+)#', $markdown, $matches, PREG_SET_ORDER);

        return array_values(array_unique(array_map(fn (array $match): string => $match[1] . ':' . $match[2], $matches)));
    }

    private function formatPrice(float $amount): string
    {
        return number_format($amount, 2, ',', ' ') . ' €';
    }

    private function price(Tournament|TrainingPack|Meeting $model): ?string
    {
        $amount = match (true) {
            $model instanceof Meeting => $model->has_meal && $model->meal_price_cents ? $model->meal_price_cents / 100 : null,
            default => $model->price !== null ? (float) $model->price : null,
        };

        return $amount !== null && $amount > 0 ? $this->formatPrice($amount) : null;
    }

    private function title(Tournament|TrainingPack|Meeting $model): string
    {
        return $model instanceof Meeting ? $model->title : $model->name;
    }

    private function when(Tournament|TrainingPack|Meeting $model): ?string
    {
        return match (true) {
            $model instanceof Tournament => $model->start_date?->format('d/m/Y H:i'),
            $model instanceof Meeting => $model->scheduled_at?->format('d/m/Y H:i'),
            $model instanceof TrainingPack => $model->pack_start_date !== null
                ? __('From :start to :end', [
                    'start' => Carbon::parse($model->pack_start_date)->format('d/m/Y'),
                    'end' => $model->pack_end_date !== null ? Carbon::parse($model->pack_end_date)->format('d/m/Y') : '…',
                ])
                : null,
        };
    }

    private function where(Tournament|TrainingPack|Meeting $model): ?string
    {
        return match (true) {
            $model instanceof TrainingPack => $model->room?->name,
            default => filled($model->location) ? $model->location : null,
        };
    }
}
