<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubAdmin\Communications;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\InvitationTarget;
use App\Http\Controllers\Controller;
use App\Support\AccountProxy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Where the button of an invitation leads: "for whom?", then the existing
 * registration screen, from the right seat.
 *
 * A parent registers their child from the child's account — the proxy of
 * {@see AccountProxy} — so a link straight to the tournament would have them
 * register themself. Asking here, once, spares every registration screen from
 * learning about invitations. The link is the same for every reader, which is
 * what lets a copy-pasted message carry it too; a magic sign-in link was ruled
 * out, as a forwarded mail would hand over the account.
 */
class InvitationRedirectController extends Controller
{
    public function choose(Request $request, string $type, int $id): RedirectResponse
    {
        $target = $this->target($type, $id);
        $chosen = User::findOrFail($request->integer('user_id'));

        abort_unless($this->choices()->contains(fn (User $choice): bool => $choice->is($chosen)), 403);

        return $this->handOver($target, $chosen);
    }

    public function show(string $type, int $id): RedirectResponse|View
    {
        $target = $this->target($type, $id);
        $choices = $this->choices();

        if ($choices->count() === 1) {
            return $this->handOver($target, $choices->sole());
        }

        return view('clubAdmin.communications.invitation', [
            'target' => $target,
            'type' => $type,
            'id' => $id,
            'choices' => $choices,
        ]);
    }

    /**
     * The person who signed in — unless their account exists only to act for
     * their children — and every managed account they answer for.
     *
     * @return Collection<int, User>
     */
    private function choices(): Collection
    {
        /** @var User $person */
        $person = AccountProxy::origin() ?? Auth::user();

        $choices = $person->isGuardianOnlyAccount() ? collect() : collect([$person]);

        return $choices->merge($person->managedAccounts())->values();
    }

    /** Sits in the chosen seat, then leaves for the registration screen. */
    private function handOver(InvitationTarget $target, User $who): RedirectResponse
    {
        $person = AccountProxy::origin() ?? Auth::user();

        if ($who->is($person)) {
            AccountProxy::stop();
        } elseif (! $who->is(Auth::user())) {
            AccountProxy::start($who);
        }

        return redirect()->to($target->registrationUrl($who));
    }

    private function target(string $type, int $id): InvitationTarget
    {
        $target = InvitationTarget::tryFrom($type) ?? abort(404);

        $target->modelClass()::query()->findOrFail($id);

        return $target;
    }
}
