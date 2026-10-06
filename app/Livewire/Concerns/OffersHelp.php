<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Domains\ClubAdmin\Feedback\Actions\OfferHelp;
use App\Domains\ClubAdmin\Feedback\Models\HelpOffer;
use App\Domains\ClubAdmin\Feedback\Models\HelpTask;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\HelpRhythm;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;

/**
 * The « fancy giving a hand? » block the feedback box and the yearly survey
 * both end with. It travels with the feedback but is stored apart, always in
 * the member's name.
 */
trait OffersHelp
{
    public string $helpMessage = '';

    public string $helpRhythm = 'occasional';

    /** @var array<int, int> */
    public array $helpTaskIds = [];

    abstract protected function helpVolunteer(): User;

    /**
     * Whether the form asks the member to give a hand: never a managed account
     * (its guardian offers from their own form), and not while an earlier
     * offer still waits for the club's answer.
     */
    #[Computed]
    public function asksForHelp(): bool
    {
        return ! $this->helpVolunteer()->isManagedAccount() && ! $this->openOffer instanceof HelpOffer;
    }

    /**
     * @return Collection<int, HelpTask>
     */
    #[Computed]
    public function helpTasks(): Collection
    {
        return HelpTask::offered()->get();
    }

    /**
     * The offer the club still owes this member an answer to, if any.
     */
    #[Computed]
    public function openOffer(): ?HelpOffer
    {
        return HelpOffer::open()->whereBelongsTo($this->helpVolunteer(), 'volunteer')->latest()->first();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function helpRules(): array
    {
        return [
            'helpRhythm' => [Rule::enum(HelpRhythm::class)],
            'helpTaskIds' => ['array'],
            'helpTaskIds.*' => ['integer'],
            'helpMessage' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function isOfferingHelp(): bool
    {
        return $this->asksForHelp && ($this->helpTaskIds !== [] || filled($this->helpMessage));
    }

    protected function offerHelpIfAsked(OfferHelp $offerHelp): void
    {
        if ($this->isOfferingHelp()) {
            $offerHelp($this->helpVolunteer(), HelpRhythm::from($this->helpRhythm), $this->helpTaskIds, $this->helpMessage);
        }

        $this->reset(['helpRhythm', 'helpTaskIds', 'helpMessage']);
        unset($this->openOffer, $this->asksForHelp);
    }
}
