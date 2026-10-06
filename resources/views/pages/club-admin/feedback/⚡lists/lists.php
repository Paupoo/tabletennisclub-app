<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Feedback\Models\FeedbackTheme;
use App\Domains\ClubAdmin\Feedback\Models\HelpTask;
use App\Domains\Shared\Enums\Permission;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

/*
 * The two lists the forms offer: what a feedback is about, and what a member
 * may offer to do. Entries are hidden rather than deleted, so that past
 * feedback and offers keep naming what they were about; the permanent entry
 * of each list closes it and cannot be hidden.
 */
new class extends Component
{
    use HasBreadcrumbs, Toast;

    public string $newTask = '';

    public string $newTheme = '';

    /** @var array<int, string> */
    public array $taskNames = [];

    /** @var array<int, string> */
    public array $themeNames = [];

    public function addTask(): void
    {
        $this->authorizeManage();
        $this->validate(['newTask' => ['required', 'string', 'max:120']]);

        $this->append(HelpTask::class, $this->newTask);
        $this->reset('newTask');
    }

    public function addTheme(): void
    {
        $this->authorizeManage();
        $this->validate(['newTheme' => ['required', 'string', 'max:80']]);

        $this->append(FeedbackTheme::class, $this->newTheme);
        $this->reset('newTheme');
    }

    public function mount(): void
    {
        $this->authorizeManage();
    }

    public function moveTask(int $id, string $direction): void
    {
        $this->authorizeManage();
        $this->move(HelpTask::class, $id, $direction);
    }

    public function moveTheme(int $id, string $direction): void
    {
        $this->authorizeManage();
        $this->move(FeedbackTheme::class, $id, $direction);
    }

    public function renameTask(int $id): void
    {
        $this->authorizeManage();
        $this->validate(["taskNames.{$id}" => ['required', 'string', 'max:120']]);

        HelpTask::findOrFail($id)->update(['name' => trim($this->taskNames[$id])]);
        $this->success(__('Renamed.'));
    }

    public function renameTheme(int $id): void
    {
        $this->authorizeManage();
        $this->validate(["themeNames.{$id}" => ['required', 'string', 'max:80']]);

        FeedbackTheme::findOrFail($id)->update(['name' => trim($this->themeNames[$id])]);
        $this->success(__('Renamed.'));
    }

    /**
     * @return Collection<int, HelpTask>
     */
    #[Computed]
    public function tasks(): Collection
    {
        return $this->ordered(HelpTask::class)->loadCount('offers');
    }

    /**
     * @return Collection<int, FeedbackTheme>
     */
    #[Computed]
    public function themes(): Collection
    {
        return $this->ordered(FeedbackTheme::class)->loadCount('entries');
    }

    public function toggleTask(int $id): void
    {
        $this->authorizeManage();
        $this->toggle(HelpTask::findOrFail($id));
    }

    public function toggleTheme(int $id): void
    {
        $this->authorizeManage();
        $this->toggle(FeedbackTheme::findOrFail($id));
    }

    public function with(): array
    {
        $this->themeNames += $this->themes->pluck('name', 'id')->all();
        $this->taskNames += $this->tasks->pluck('name', 'id')->all();

        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->add(__('Feedback and suggestions'), route('admin.feedback.index'))
            ->current(__('Themes and help tasks'));
    }

    /**
     * @param  class-string<FeedbackTheme|HelpTask>  $model
     */
    private function append(string $model, string $name): void
    {
        $added = $model::create(['name' => trim($name), 'position' => 0]);

        $this->renumber($model, $this->ordered($model)->reject(fn (FeedbackTheme|HelpTask $entry): bool => $entry->is($added))->push($added));
    }

    private function authorizeManage(): void
    {
        Gate::authorize(Permission::FeedbackManage->value);
    }

    /**
     * Swap an entry with its neighbour. The permanent entry stays last, so
     * nothing moves past it and it never moves itself.
     *
     * @param  class-string<FeedbackTheme|HelpTask>  $model
     */
    private function move(string $model, int $id, string $direction): void
    {
        $entries = $this->ordered($model)->reject(fn (FeedbackTheme|HelpTask $entry): bool => $entry->is_permanent)->values();
        $index = $entries->search(fn (FeedbackTheme|HelpTask $entry): bool => $entry->id === $id);
        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || $target < 0 || $target >= $entries->count()) {
            return;
        }

        $items = $entries->all();
        [$items[$index], $items[$target]] = [$items[$target], $items[$index]];

        $this->renumber($model, collect($items));
    }

    /**
     * Every entry of a list, hidden ones included, in the order the forms use.
     *
     * @param  class-string<FeedbackTheme|HelpTask>  $model
     * @return Collection<int, FeedbackTheme|HelpTask>
     */
    private function ordered(string $model): Collection
    {
        return $model::query()->orderBy('position')->orderBy('id')->get();
    }

    /**
     * Write positions 1…n in the given order, the permanent entry last.
     *
     * @param  class-string<FeedbackTheme|HelpTask>  $model
     * @param  SupportCollection<int, FeedbackTheme|HelpTask>  $entries
     */
    private function renumber(string $model, SupportCollection $entries): void
    {
        $ordered = $entries->reject(fn (FeedbackTheme|HelpTask $entry): bool => $entry->is_permanent)
            ->values()
            ->concat($this->ordered($model)->filter(fn (FeedbackTheme|HelpTask $entry): bool => $entry->is_permanent));

        DB::transaction(function () use ($ordered): void {
            $ordered->values()->each(fn (FeedbackTheme|HelpTask $entry, int $index) => $entry->update(['position' => $index + 1]));
        });

        unset($this->themes, $this->tasks);
    }

    private function toggle(FeedbackTheme|HelpTask $entry): void
    {
        if ($entry->is_permanent) {
            return;
        }

        $entry->update(['hidden_at' => $entry->hidden_at ? null : now()]);
        unset($this->themes, $this->tasks);
    }
};
