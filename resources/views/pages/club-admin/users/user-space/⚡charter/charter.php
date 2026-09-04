<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Club charter')] class extends Component
{
    use HasBreadcrumbs;

    public User $user;

    public function mount(User $user): void
    {
        abort_unless(Auth::user()->is($user), 403);

        $this->user = $user;
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'chapters' => $this->chapters(),
            'values' => $this->values(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Club charter'));
    }

    /**
     * The six chapters of the charter, in reading order.
     *
     * The committee owns this text: it is the club's own agreement, not a
     * federation document, so it is kept here rather than fetched from the
     * AFTTB. Each chapter opens with the reason it exists ("why"), because the
     * charter is meant to be understood rather than merely obeyed.
     *
     * @return array<int, array{
     *     anchor: string,
     *     icon: string,
     *     title: string,
     *     why: string,
     *     groups: array<int, array{title: string, items: array<int, string>}>,
     *     action?: array{label: string, route: string}
     * }>
     */
    protected function chapters(): array
    {
        return [
            [
                'anchor' => 'entrainements',
                'icon' => 'o-academic-cap',
                'title' => __('Trainings'),
                'why' => __('Trainings are the heart of our collective progress. They call for seriousness, discipline and mutual respect — towards the coach, your teammates and yourself.'),
                'groups' => [
                    [
                        'title' => __('Punctuality and preparation'),
                        'items' => [
                            __('Arrive 5 minutes before the starting time.'),
                            __('Be dressed and ready right on time, out of respect for the coach and to keep the exercises running smoothly.'),
                        ],
                    ],
                    [
                        'title' => __('Attitude and involvement'),
                        'items' => [
                            __('Respect the coach and listen carefully.'),
                            __('Take an active part in the exercises and do your best at every moment.'),
                            __('Work with your practice partner so that both of you learn as much as possible.'),
                        ],
                    ],
                ],
            ],
            [
                'anchor' => 'competitions',
                'icon' => 'o-trophy',
                'title' => __('Competitions and interclubs'),
                'why' => __('Competitions are the moment when our club represents itself. Good organisation guarantees respect for our opponents and a smooth run of the matches.'),
                'groups' => [
                    [
                        'title' => __('Opening the hall'),
                        'items' => [
                            __('Committee responsibility: make sure at least one person has the keys to the hall.'),
                        ],
                    ],
                    [
                        'title' => __('Getting ready before the competition'),
                        'items' => [
                            __('Arrival time: at least 30 minutes before the official start of the match.'),
                            __('Setup: put up the tables, the surrounds and the match balls.'),
                            __('Cleaning and tidying: clean or tidy the hall if something unexpected happens.'),
                        ],
                    ],
                    [
                        'title' => __('Match sheet'),
                        'items' => [
                            __('Fill it in, and have the opponents fill it in, 15 minutes before the start.'),
                        ],
                    ],
                    [
                        'title' => __('Player conduct'),
                        'items' => [
                            __('Players do not leave the hall before the opponents have left.'),
                        ],
                    ],
                ],
            ],
            [
                'anchor' => 'bar',
                'icon' => 'o-shopping-bag',
                'title' => __('Running the bar'),
                'why' => __('The bar is a key part of our club life. It is a service we provide to our members, but also a responsibility that calls for organisation.'),
                'groups' => [
                    [
                        'title' => __('Staff rotation (interclubs)'),
                        'items' => [
                            __('When: during interclubs only.'),
                            __('Who runs the bar: each team delegates one member in turn, once every six weeks.'),
                            __('Assignment: the captain appoints the bartender.'),
                        ],
                    ],
                    [
                        'title' => __('Prices and products'),
                        'items' => [
                            __('The price list lives in the app and is always up to date.'),
                        ],
                    ],
                    [
                        'title' => __('Orders and payments (interclubs)'),
                        'items' => [
                            __('Every order is recorded in the app.'),
                            __('Payment in cash or by QR code.'),
                        ],
                    ],
                    [
                        'title' => __('Committee responsibility'),
                        'items' => [
                            __('Stock management: do the shopping regularly.'),
                            __('The goal: always something to drink, a snack or a croque-monsieur.'),
                            __('Equipment: make sure there is what it takes to serve, to cook and to do the dishes.'),
                        ],
                    ],
                    [
                        'title' => __('During trainings'),
                        'items' => [
                            __('The bar may only be opened by a member who has the keys.'),
                            __('Responsible use, under supervision.'),
                            __('Payments by QR code only, no cash.'),
                        ],
                    ],
                ],
            ],
            [
                'anchor' => 'accueil',
                'icon' => 'o-heart',
                'title' => __('Welcome and hospitality'),
                'why' => __('Our opponents are our guests! Welcoming them shows that we respect the sport and that we build lasting bonds.'),
                'groups' => [
                    [
                        'title' => __('Who welcomes'),
                        'items' => [
                            __('The bartender of the evening is responsible for welcoming the opponents.'),
                            __('They stay available to answer questions and to create a welcoming atmosphere.'),
                        ],
                    ],
                    [
                        'title' => __('The friendly drink'),
                        'items' => [
                            __('At the end of every match, we offer a drink to our opponents.'),
                            __('Members pay for their own drinks as usual.'),
                        ],
                    ],
                    [
                        'title' => __('Conduct while hosting'),
                        'items' => [
                            __('Players only leave once all the opponents have left.'),
                            __('Everyone makes sure our guests have a positive experience.'),
                            __('One person stays until closing to check that everything is in order.'),
                        ],
                    ],
                ],
            ],
            [
                'anchor' => 'fermeture',
                'icon' => 'o-lock-closed',
                'title' => __('Tidying up and closing'),
                'why' => __('A tidy club is a club we respect. It is a matter of safety, of durability and of professionalism.'),
                'groups' => [
                    [
                        'title' => __('Before leaving'),
                        'items' => [
                            __('Tables and playing areas tidied and cleaned.'),
                            __('Surrounds put away, balls back in their place.'),
                            __('Bar cleaned and secured.'),
                            __('Bins checked.'),
                            __('Lighting and heating or air conditioning switched off.'),
                            __('Doors and windows closed.'),
                        ],
                    ],
                ],
            ],
            [
                'anchor' => 'rotation',
                'icon' => 'o-arrow-path',
                'title' => __('Rotating responsibilities'),
                'why' => __('No club without collective involvement! By sharing the responsibilities, we build a culture of mutual help and we secure the future of the club.'),
                'groups' => [
                    [
                        'title' => __('The principle'),
                        'items' => [
                            __('Closing duties are shared out fairly.'),
                            __('A schedule is drawn up and posted at the club.'),
                            __('Everyone confirms their presence and their availability.'),
                            __('If you cannot come, you find a replacement.'),
                            __('New recruits are brought in step by step.'),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * The three values the charter rests on, shown as its closing statement.
     *
     * @return array<int, array{icon: string, title: string, description: string}>
     */
    protected function values(): array
    {
        return [
            [
                'icon' => 'o-shield-check',
                'title' => __('Respect'),
                'description' => __('For our facilities, for our opponents, for one another.'),
            ],
            [
                'icon' => 'o-scale',
                'title' => __('Sharing'),
                'description' => __('A fair share of the responsibilities and of the efforts.'),
            ],
            [
                'icon' => 'o-hand-raised',
                'title' => __('Solidarity'),
                'description' => __('Everyone contributes so that the club thrives.'),
            ],
        ];
    }
};
