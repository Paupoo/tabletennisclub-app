<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\User\ToggleHasPaidMembershipAction;
use App\Enums\Ranking;
use App\Enums\Sex;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Team;
use App\Models\User;
use App\Services\ForceList;
use App\Support\Breadcrumb;
use Illuminate\Http\RedirectResponse;

class UserController extends Controller
{
    protected $forceList;

    public function __construct(ForceList $forceList)
    {
        $this->forceList = $forceList;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->Users()
            ->add('Create')
            ->toArray();

        $this->authorize('create', User::class);

        return View('admin.users.create', [
            'user' => new User,
            'teams' => Team::with('league')->get(),
            'rankings' => collect(Ranking::cases())->pluck('name')->toArray(),
            'sexes' => collect(Sex::cases())->pluck('name')->toArray(),
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function deleteForceList(): RedirectResponse
    {
        $this->authorize('deleteForceList', User::class);
        $this->forceList->delete();

        return redirect()->route('users.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', User::class);

        if ($user->tournaments()->whereIn('status', ['draft', 'open', 'pending'])->count() > 0) {
            return redirect()
                ->back()
                ->with('error', __('Cannot delete ' . $user->first_name . ' ' . $user->last_name . ' because he subscribed to one or more tournaments'));
        }

        $user->delete();

        $this->forceList->setOrUpdateAll();

        return redirect()
            ->route('users.index')
            ->with('success', 'User ' . $user->first_name . ' ' . $user->last_name . ' has been deleted');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->users()
            ->add('Edit')
            ->add($user->first_name . ' ' . $user->last_name)
            ->toArray();

        $this->authorize('update', User::class);

        return view('admin.users.edit', [
            'user' => $user,
            'teams' => Team::all(),
            'rankings' => array_column(Ranking::cases(), 'name'),
            'sexes' => array_column(Sex::cases(), 'name'),
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->users()
            ->toArray();

        $actions = [
            [
                'href' => '#',
                'icon' => 'plus',
                'textColor' => 'text-gray-700',
                'hoverColor' => 'text-gray-900',
                'text' => __('Create User')
            ],
        ];

        $this->authorize('index', User::class);

        return View('admin.users.index', [
            'user_model' => User::class,
            'breadcrumbs' => $breadcrumbs,
            'actions' => $actions,
        ]);
    }

    public function setForceList(): RedirectResponse
    {
        $this->authorize('setOrUpdateForceList', User::class);
        $this->forceList->setOrUpdateAll();

        return redirect()->route('users.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //
        $user->setAge();

        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->users()
            ->add('Profile')
            ->add($user->first_name . ' ' . $user->last_name)
            ->toArray();

        return view('admin.users.show', [
            'user' => $user,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request, ForceList $forceList)
    {
        $validated = $request->validated();

        $user = User::create($validated);

        // Attach a team (TO CHECK, need to be able to attach many teams)
        if (isset($validated['team_id'])) {
            $user->teams()->attach(Team::find($request['team_id']));
        }

        $forceList->setOrUpdateAll();

        return redirect()->route('users.create')
            ->with('success', __('New member ' . $user->first_name . ' ' . $user->last_name . ' created'));
    }

    public function toggleHasPaid(User $user): RedirectResponse
    {
        $this->authorize('update', User::class);
        $action = new ToggleHasPaidMembershipAction($user);
        $action->toggleHasPaid($user);

        return redirect()
            ->back();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();
        $user->update($validated);

        // Attach a team (TO CHECK, need to be able to attach many teams)
        if ($request['team_id'] !== null) {
            $user->teams()->attach(Team::find($request['team_id']));
        } else {
            $user->teams()->detach();
        }

        $this->forceList->setOrUpdateAll();

        return redirect()
            ->route('users.index')
            ->with('success', __('Member ' . $user->first_name . ' ' . $user->last_name . ' has been updated.'));
    }
}
