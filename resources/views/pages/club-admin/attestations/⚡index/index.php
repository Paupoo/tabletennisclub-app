<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Attestations\InstallAttestationTemplate;
use App\Actions\ClubAdmin\Attestations\RevokeAttestation;
use App\Actions\ClubAdmin\Attestations\StoreAttestationImage;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationSetting;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationTemplate;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\MutualAttestation;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\AttestationAvailability;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\Mutuality;
use App\Domains\Shared\Enums\Permission;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The office's side of the attestations: how they are signed, what they are
 * laid over, and what has been issued.
 *
 * Three tabs rather than three screens because they are read together: the
 * secretary who uploads a seal wants to see straight away that the readiness
 * gate has opened, and the one who replaces a form wants to see whether its
 * labels still resolve.
 */
new class extends Component
{
    use HasBreadcrumbs, WithFileUploads;

    public string $clubPhone = '';

    public string $discipline = '';

    public string $federationName = '';

    public ?string $revocationReason = null;

    public ?int $revoking = null;

    public $sealUpload;

    public int $sealWidth = 30;

    public ?int $signatoryUserId = null;

    public $signatureUpload;

    public int $signatureWidth = 42;

    public string $tab = 'settings';

    public string $templateFor = '';

    public $templateUpload;

    #[Computed]
    public function availability(): AttestationAvailability
    {
        return app(AttestationAvailability::class);
    }

    public function mount(): void
    {
        $settings = AttestationSetting::current();

        $this->signatoryUserId = $settings->signatory_user_id;
        $this->sealWidth = $settings->seal_width_mm;
        $this->signatureWidth = $settings->signature_width_mm;
        $this->federationName = $settings->federation_name;
        $this->discipline = $settings->discipline;
        $this->clubPhone = (string) Club::ourClub()->first()?->phone_contact;
    }

    public function revoke(RevokeAttestation $revoke): void
    {
        $attestation = MutualAttestation::findOrFail($this->revoking);

        $this->authorize('revoke', $attestation);

        $this->validate(
            ['revocationReason' => ['required', 'string', 'min:3', 'max:255']],
            ['revocationReason.required' => __('A reason is required to revoke an attestation.')],
        );

        $revoke($attestation, (string) $this->revocationReason, Auth::user());

        $this->reset(['revoking', 'revocationReason']);
    }

    public function saveSettings(): void
    {
        $this->authorize(Permission::AttestationsConfigure->value);

        $this->validate([
            'signatoryUserId' => ['required', 'integer', 'exists:users,id'],
            'sealWidth' => ['required', 'integer', 'min:10', 'max:80'],
            'signatureWidth' => ['required', 'integer', 'min:10', 'max:90'],
            'federationName' => ['required', 'string', 'max:60'],
            'discipline' => ['required', 'string', 'max:60'],
            'clubPhone' => ['nullable', 'string', 'max:40'],
        ]);

        AttestationSetting::current()->update([
            'signatory_user_id' => $this->signatoryUserId,
            'seal_width_mm' => $this->sealWidth,
            'signature_width_mm' => $this->signatureWidth,
            'federation_name' => $this->federationName,
            'discipline' => $this->discipline,
        ]);

        // The club record, because Solidaris prints the club's telephone number
        // and nobody else was ever going to think to fill it in.
        Club::ourClub()->first()?->update(['phone_contact' => $this->clubPhone ?: null]);

        $this->dispatch('toast', message: __('Settings saved.'));
    }

    public function uploadMark(StoreAttestationImage $store, string $kind): void
    {
        $this->authorize(Permission::AttestationsConfigure->value);

        $file = $kind === StoreAttestationImage::SEAL ? $this->sealUpload : $this->signatureUpload;

        if ($file === null) {
            return;
        }

        try {
            $store($file, $kind);
        } catch (ValidationException $refused) {
            $this->addError($kind . 'Upload', $refused->getMessage());

            return;
        }

        $this->reset([$kind . 'Upload']);
    }

    public function uploadTemplate(InstallAttestationTemplate $install): void
    {
        $this->authorize(Permission::AttestationsConfigure->value);

        $this->validate([
            'templateFor' => ['required', 'string'],
            'templateUpload' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $mutuality = Mutuality::from($this->templateFor);

        $install(
            $mutuality,
            $this->templateUpload->getRealPath(),
            $this->templateUpload->getClientOriginalName(),
            Auth::id(),
        );

        $this->reset(['templateUpload', 'templateFor']);
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'history' => MutualAttestation::with(['user', 'season'])->latest('issued_at')->paginate(20),
            'missing' => $this->availability()->missing(),
            'offered' => $this->availability()->offered(),
            'ready' => $this->availability()->isReady(),
            'settings' => AttestationSetting::current(),
            'signatories' => User::query()->orderBy('last_name')->get(['id', 'first_name', 'last_name']),
            'templates' => AttestationTemplate::all()->keyBy(fn (AttestationTemplate $t): string => $t->mutuality->value),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Mutual attestations'));
    }
};
