<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\EmailTemplate;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, Toast;

    /**
     * Starter templates seeded by {@see \Database\Seeders\EmailTemplateSeeder}.
     * Their `key` is referenced by the rest of the app, so it is rendered
     * read-only when editing — the rest of the template stays editable.
     *
     * @var list<string>
     */
    private const SYSTEM_KEYS = [
        'welcome',
        'membership_info',
        'request_info',
        'polite_decline',
        'info_request_questionnaire',
        'trial_invite',
    ];

    public bool $deleteModal = false;

    public ?int $deletingId = null;

    // ── Form (create + edit) ───────────────────────────────────────────────────

    public ?int $editingId = null;

    public string $formApplyStatus = '';

    public string $formBody = '';

    public bool $formIsQuestionnaire = false;

    public string $formKey = '';

    public bool $formModal = false;

    public string $formName = '';

    public string $formSubject = '';

    /** Whether the key field is locked (system starter template being edited). */
    public bool $keyLocked = false;

    // ── Lifecycle ──────────────────────────────────────────────────────────────

    public function mount(): void
    {
        Gate::authorize('manage-contacts');
    }

    // ── Computed ───────────────────────────────────────────────────────────────

    /** @return Collection<int, EmailTemplate> */
    #[Computed]
    public function templates(): Collection
    {
        return EmailTemplate::query()->orderBy('name')->get();
    }

    // ── Create ─────────────────────────────────────────────────────────────────

    public function openCreate(): void
    {
        Gate::authorize('manage-contacts');

        $this->resetForm();
        $this->formModal = true;
    }

    // ── Edit ───────────────────────────────────────────────────────────────────

    public function openEdit(int $id): void
    {
        Gate::authorize('manage-contacts');

        $template = EmailTemplate::findOrFail($id);

        $this->editingId = $template->id;
        $this->formName = $template->name;
        $this->formKey = $template->key;
        $this->formSubject = $template->subject;
        $this->formBody = $template->body;
        $this->formApplyStatus = $template->apply_status ?? '';
        $this->formIsQuestionnaire = $template->is_questionnaire;
        $this->keyLocked = in_array($template->key, self::SYSTEM_KEYS, true);
        $this->resetErrorBag();
        $this->formModal = true;
    }

    // ── Save (create or update) ────────────────────────────────────────────────

    public function saveTemplate(): void
    {
        Gate::authorize('manage-contacts');

        $template = $this->editingId ? EmailTemplate::findOrFail($this->editingId) : null;

        // Preserve a locked system key regardless of any tampering with the input.
        if ($template && $this->keyLocked) {
            $this->formKey = $template->key;
        }

        $keyRule = Rule::unique('email_templates', 'key');
        if ($template) {
            $keyRule = $keyRule->ignore($template->id);
        }

        $validated = $this->validate([
            'formName' => ['required', 'string', 'max:255'],
            'formKey' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/', $keyRule],
            'formSubject' => ['required', 'string', 'max:255'],
            'formBody' => ['required', 'string'],
            'formApplyStatus' => ['nullable', 'in:new,pending,processed,rejected'],
        ]);

        $attributes = [
            'name' => $validated['formName'],
            'key' => $validated['formKey'],
            'subject' => $validated['formSubject'],
            'body' => $validated['formBody'],
            'apply_status' => $validated['formApplyStatus'] === '' ? null : $validated['formApplyStatus'],
            'is_questionnaire' => $this->formIsQuestionnaire,
        ];

        if ($template) {
            $template->update($attributes);
            $this->success(__('Template updated.'), icon: 'o-check-circle');
        } else {
            EmailTemplate::create([...$attributes, 'is_active' => true]);
            $this->success(__('Template created.'), icon: 'o-envelope');
        }

        unset($this->templates);
        $this->formModal = false;
        $this->resetForm();
    }

    // ── Toggle active ──────────────────────────────────────────────────────────

    public function toggleActive(int $id): void
    {
        Gate::authorize('manage-contacts');

        $template = EmailTemplate::findOrFail($id);
        $template->update(['is_active' => ! $template->is_active]);

        unset($this->templates);
    }

    // ── Delete ─────────────────────────────────────────────────────────────────

    public function confirmDelete(int $id): void
    {
        Gate::authorize('manage-contacts');

        $this->deletingId = $id;
        $this->deleteModal = true;
    }

    public function deleteTemplate(): void
    {
        Gate::authorize('manage-contacts');

        if ($this->deletingId) {
            EmailTemplate::findOrFail($this->deletingId)->delete();
            unset($this->templates);
            $this->success(__('Template deleted.'), icon: 'o-trash');
        }

        $this->deleteModal = false;
        $this->deletingId = null;
    }

    // ── Render ─────────────────────────────────────────────────────────────────

    public function render(): View
    {
        return $this->view();
    }

    public function with(): array
    {
        $statusOptions = [
            ['id' => '', 'name' => __('No status applied')],
            ['id' => 'new', 'name' => __('New')],
            ['id' => 'pending', 'name' => __('Pending')],
            ['id' => 'processed', 'name' => __('Processed')],
            ['id' => 'rejected', 'name' => __('Rejected')],
        ];

        $headers = [
            ['key' => 'name', 'label' => __('Name')],
            ['key' => 'key', 'label' => __('Key'), 'class' => 'hidden sm:table-cell'],
            ['key' => 'apply_status', 'label' => __('Applied status'), 'class' => 'hidden md:table-cell'],
            ['key' => 'flags', 'label' => '', 'sortable' => false],
            ['key' => 'actions', 'label' => '', 'sortable' => false],
        ];

        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'templates' => $this->templates,
            'statusOptions' => $statusOptions,
            'headers' => $headers,
            'availableVariables' => [
                '{{first_name}}',
                '{{last_name}}',
                '{{full_name}}',
                '{{interest}}',
                '{{club_name}}',
            ],
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->websiteContacts(route('admin.website.contacts.index'))
            ->current(__('Email templates'));
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->formName = '';
        $this->formKey = '';
        $this->formSubject = '';
        $this->formBody = '';
        $this->formApplyStatus = '';
        $this->formIsQuestionnaire = false;
        $this->keyLocked = false;
        $this->resetErrorBag();
    }
};
