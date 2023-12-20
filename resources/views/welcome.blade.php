<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name') }}</title>

    @vite('resources/css/app.css')

</head>

<body class="w-screen bg-neutral-100 dark:bg-neutral-900">
    <div class="mx-auto max-w-7xl max-sm:w-full">

        {{-- HEADER --}}
        <header
            class="sticky top-0 z-10 flex flex-row items-center justify-between h-24 px-12 py-8 mt-4 text-blue-800 bg-yellow-400 border-b border-blue-600 border-dotted shadow-sm max-sm:py-2 max-sm:px-4">

            <div class="grid h-12 grid-cols-3 mx-4 w-fit">
                <div class="col-span-1 w-36 max-sm:w-fit">
                    <x-logo />
                </div>
                <div class="flex flex-row items-center justify-start col-span-2">
                    <p class="text-3xl font-bold max-sm:text-base max-sm:h-8">{{ config('app.name') }}</p>
                </div>
            </div>


            <nav class="flex items-center w-auto h-12 mx-4">

                {{-- Normal Menu --}}
                <ul class="flex flex-row gap-8 max-lg:hidden">
                    <li>
                        <x-button class="text-lg font-semibold ">{{ __('Sport Offer') }}</x-button>
                    </li>
                    <li>
                        <x-button class="text-lg font-semibold">{{ __('Our Club') }}</x-button>
                    </li>
                    <li>
                        <x-button class="text-lg font-semibold"> {{ __('Contact') }}</x-button>
                    </li>
                    <li>
                        <form action="{{ route('dashboard') }}" method="GET">
                            <x-button class="text-lg font-semibold text-yellow-400 bg-indigo-800">
                                {{ __('Login') }}</x-button>
                        </form>

                    </li>
                </ul>

                {{-- Hamburger Menu --}}
                <x-icons.hamburger class="lg:hidden" />
            </nav>
        </header>

        {{-- MAIN --}}
        <main class="grid grid-flow-row z-1 top-24 bg-neutral-100 dark:bg-neutral-900 dark:text-slate-400">

            {{-- Kiosk --}}
            <section
                class="relative grid grid-cols-2 gap-4 px-12 py-8 bg-yellow-400 rounded-b-sm max-lg:grid-cols-1 max-sm:px-8 mx-0min-lg:w-lg h-fix">

                <div class="absolute left-0 p-1 mx-2 top-1/2">
                    <x-button class="h-8 rounded-md hover:bg-opacity-50" type="button"> <img
                            src="{{ asset('images/icons/arrow_left.png') }}" alt="Previous" class="h-full">
                    </x-button>
                </div>

                {{-- Image --}}
                <div class="px-4 max-lg:row-start-2 ">
                    <img src="{{ asset('images/table-tennis-background2.jpg') }}" alt=""
                        class="w-full rounded-lg shadow-lg">
                </div>

                {{-- Call to action --}}
                <div class="flex flex-col justify-around gap-4 px-4 mb-2 max-lg:row-start-1">
                    <h1 class="text-3xl font-bold text-left">{{ __('Welcome') }}</h1>
                    <p>{{ __('Lorem ipsum dolor sit amet consectetur, adipisicing elit. Dicta, voluptatum consectetur? Ut,
                                                                delectus
                                                                quod eveniet autem laborum ullam reiciendis deserunt a consequuntur dolor quis cumque quibusdam
                                                                esse
                                                                nulla odit dolorum.') }}
                    </p>
                    <div class="flex flex-row items-center self-center">
                        <x-button
                            class="py-2 mr-4 font-semibold text-yellow-400 bg-blue-800 shadow-2xl w-36 shadow-blue-800"
                            type="button">{{ __('Join us') }}</x-button>
                        <a class="ml-4 text-lg font-semibold" href="">
                            {{ 'Learn more' }}
                            <span class="text-3xl font-extrabold">&rarr;</span>
                        </a>
                    </div>
                </div>
                <div class="absolute right-0 p-1 mx-2 top-1/2">
                    <x-button class="h-8 rounded-md hover:bg-opacity-50" type="button">
                        <img src="{{ asset('images/icons/arrow_right.png') }}" alt="Next" class="h-full">
                    </x-button>
                </div>

            </section>

            {{-- Events & results --}}
            <section class="flex flex-row flex-wrap justify-around gap-4 px-8 bg-opacity-30">

                {{-- Upcoming events --}}
                <div>
                    <h1 class="mt-4 text-2xl font-bold indent-4">{{ __('Upcoming events') }}</h1>
                    <div class="container">
                        <div class="relative p-4 my-4 bg-white rounded-lg pb-14 max-sm:w-fit w-96">

                            <div class="flex flex-row items-start gap-2 p-2 bg-gray-100 rounded-md hover:bg-gray-200">
                                <div
                                    class="w-3 h-3 my-auto bg-yellow-600 rounded-full shadow-xl ring-2 ring-yellow-500 ring-opacity-50">
                                </div>
                                <div class="w-full">
                                    <div class="flex flex-row justify-between">
                                        <div class="text-sm text-left font-base">Lundi 08 janvier 2024</div>
                                        <div class="text-sm text-right font-extralight">18:00 - 20:00 </div>
                                    </div>
                                    <div class="flex flex-row justify-between">
                                        <div class="text-sm italic text-left font-base">Entraînement jeunes</div>
                                        <div class="text-sm text-right font-extralight">Blocry /G3</div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex flex-row items-start gap-2 p-2 mt-2 bg-gray-100 rounded-md hover:bg-gray-200">
                                <div
                                    class="w-3 h-3 my-auto bg-blue-800 rounded-full shadow-xl ring-2 ring-blue-500 ring-opacity-50">
                                </div>
                                <div class="w-full">
                                    <div class="flex flex-row justify-between">
                                        <div class="text-sm text-left font-base">Lundi 08 janvier 2024</div>
                                        <div class="text-sm text-right font-extralight">18:00 - 22:00</div>
                                    </div>
                                    <div class="flex flex-row justify-between">
                                        <div class="text-sm italic text-left font-base">Entraînement libre</div>
                                        <div class="text-sm text-right font-extralight">Demeester /0</div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex flex-row items-start gap-2 p-2 mt-2 bg-gray-100 rounded-md hover:bg-gray-200">
                                <div
                                    class="w-3 h-3 my-auto bg-blue-800 rounded-full shadow-xl ring-2 ring-blue-500 ring-opacity-50">
                                </div>
                                <div class="w-full">
                                    <div class="flex flex-row justify-between">
                                        <div class="text-sm text-left font-base">Lundi 08 janvier 2024</div>
                                        <div class="text-sm text-right font-extralight">20:30 - 22:00</div>
                                    </div>
                                    <div class="flex flex-row justify-between">
                                        <div class="text-sm italic text-left font-base">Entraînement Libre</div>
                                        <div class="text-sm text-right font-extralight">Demeester /-1</div>
                                    </div>
                                </div>
                            </div>


                            <div
                                class="flex flex-row items-start gap-2 p-2 mt-2 bg-gray-100 rounded-md hover:bg-gray-200">
                                <div
                                    class="w-3 h-3 my-auto bg-green-600 rounded-full shadow-xl ring-2 ring-greend-500 ring-opacity-50">
                                </div>
                                <div class="w-full">
                                    <div class="flex flex-row justify-between">
                                        <div class="text-sm text-left font-base">Lundi 08 janvier 2024</div>
                                        <div class="text-sm text-right font-extralight">20:00 - 22:00 </div>
                                    </div>
                                    <div class="flex flex-row justify-between">
                                        <div class="text-sm italic text-left font-base">Entraînement libre</div>
                                        <div class="text-sm text-right font-extralight">Demeester /-1</div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex flex-row items-start gap-2 p-2 mt-2 bg-gray-100 rounded-md hover:bg-gray-200">
                                <div
                                    class="w-3 h-3 my-auto bg-red-800 rounded-full shadow-xl ring-2 ring-red-500 ring-opacity-50">
                                </div>
                                <div class="w-full">
                                    <div class="flex flex-row justify-between">
                                        <div class="text-sm text-left font-base">Mardi 09 janvier 2024</div>
                                        <div class="text-sm text-right font-extralight">20:30 - 22:00 </div>
                                    </div>
                                    <div class="flex flex-row justify-between">
                                        <div class="text-sm italic text-left font-base">Entraînement adultes</div>
                                        <div class="text-sm text-right font-extralight">Demeester /-1</div>
                                    </div>
                                </div>
                            </div>

                            <x-button
                                class="absolute py-2 text-sm font-medium text-blue-900 -translate-x-1/2 bg-indigo-300 w-36 bottom-2 left-1/2">
                                More
                            </x-button>
                        </div>
                    </div>
                </div>

                {{-- Results --}}
                <div>
                    <h1 class="mt-4 text-2xl font-bold indent-4">{{ __('Lastest results') }}</h1>
                    <div class="container">
                        <div class="relative p-4 pb-12 my-4 bg-white rounded-lg w-96 max-sm:w-fit">
                            <div
                                class="flex flex-row items-start gap-2 p-2 my-2 bg-green-100 rounded-md hover:bg-gray-200">

                                <div class="w-full">
                                    <div class="relative flex flex-row justify-between">
                                        <div class="text-sm text-left font-base">Ottignies A</div>
                                        <div class="absolute text-lg font-semibold -translate-x-1/2 -top-1 left-1/2">VS
                                        </div>
                                        <div class="text-sm text-left font-base">Auderghem E</div>
                                    </div>
                                    <div class="flex flex-row items-center justify-center gap-2">
                                        <div class="text-sm font-medium text-right">9</div>
                                        <div class="text-lg font-semibold"> - </div>
                                        <div class="text-sm font-medium text-right">7</div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex flex-row items-start gap-2 p-2 my-2 bg-red-200 rounded-md hover:bg-gray-200">

                                <div class="w-full">
                                    <div class="relative flex flex-row justify-between">
                                        <div class="text-sm text-left font-base">Limal D</div>
                                        <div class="absolute text-lg font-semibold -translate-x-1/2 -top-1 left-1/2">VS
                                        </div>
                                        <div class="text-sm text-left font-base">Ottignies B</div>
                                    </div>
                                    <div class="flex flex-row items-center justify-center gap-2">
                                        <div class="text-sm font-medium text-right">11</div>
                                        <div class="text-lg font-semibold"> - </div>
                                        <div class="text-sm font-medium text-right">5</div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex flex-row items-start gap-2 p-2 my-2 rounded-md bg-neutral-100 hover:bg-gray-200">

                                <div class="w-full">
                                    <div class="relative flex flex-row justify-between">
                                        <div class="text-sm text-left font-base">Ottignies D</div>
                                        <div class="absolute text-lg font-semibold -translate-x-1/2 -top-1 left-1/2">VS
                                        </div>
                                        <div class="text-sm text-left font-base">Alpa Schaerb. Woluwe A</div>
                                    </div>
                                    <div class="flex flex-row items-center justify-center gap-2">
                                        <div class="text-sm font-medium text-right">8</div>
                                        <div class="text-lg font-semibold"> - </div>
                                        <div class="text-sm font-medium text-right">8</div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex flex-row items-start gap-2 p-2 my-2 bg-green-100 rounded-md hover:bg-gray-200">

                                <div class="w-full">
                                    <div class="relative flex flex-row justify-between">
                                        <div class="text-sm text-left font-base">Ottignies E</div>
                                        <div class="absolute text-lg font-semibold -translate-x-1/2 -top-1 left-1/2">VS
                                        </div>
                                        <div class="text-sm text-left font-base">Clabecq F E</div>
                                    </div>
                                    <div class="flex flex-row items-center justify-center gap-2">
                                        <div class="text-sm font-medium text-right">14</div>
                                        <div class="text-lg font-semibold"> - </div>
                                        <div class="text-sm font-medium text-right">2</div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex flex-row items-start gap-2 p-2 my-2 bg-green-100 rounded-md hover:bg-gray-200">

                                <div class="w-full">
                                    <div class="relative flex flex-row justify-between">
                                        <div class="text-sm text-left font-base">Ottignies F</div>
                                        <div class="absolute text-lg font-semibold -translate-x-1/2 -top-1 left-1/2">VS
                                        </div>
                                        <div class="text-sm text-left font-base">Braine-l'Alleud E</div>
                                    </div>
                                    <div class="flex flex-row items-center justify-center gap-2">
                                        <div class="text-sm font-medium text-right">9</div>
                                        <div class="text-lg font-semibold"> - </div>
                                        <div class="text-sm font-medium text-right">7</div>
                                    </div>
                                </div>
                            </div>

                            <x-button
                                class="absolute py-2 text-sm font-medium text-blue-900 -translate-x-1/2 bg-indigo-300 w-36 bottom-2 left-1/2">
                                More
                            </x-button>
                        </div>
                    </div>
                </div>
            </section>

            {{-- News --}}
            <section class="px-8">
                <h1 class="mt-4 text-2xl font-bold indent-4">{{ __('News of the club') }}</h1>
                <div class="grid grid-flow-row grid-cols-3 gap-6 my-4 max-lg:grid-cols-2 max-sm:grid-cols-1">

                    @for ($i = 0; $i < 6; $i++)
                        <x-article>
                            <h2 class="text-lg font-semibold text-center">{{ fake()->words(4, true) }}</h2>
                            <p class="mt-2 text-justify indent-3">{{ fake()->realText() }} </p>
                            <div class="grid items-end grid-cols-4 gap-2">
                                <x-published-date-indicator class="col-start-1 col-end-3">Published :
                                    {{ fake()->date() }}</x-published-date-indicator>
                                <x-button type="button"
                                    class="col-start-4 col-end-5 px-4 py-2 mt-2 text-sm font-medium text-blue-900 bg-indigo-300 place-self-end w-36">Read
                                    more</x-button>
                            </div>
                        </x-article>
                    @endfor

                    <x-button>Older news</x-button>


                </div>
            </section>



        </main>


        {{-- Footer --}}
        <footer class="px-12 py-8 text-yellow-500 bg-blue-900 rounded-t-sm max-sm:px-8 dark:bg-neutral-400">

            <div class="flex flex-wrap justify-around">

                <div class="block my-4 text-sm italic font-thin">Copyright &copy; 2024 CTT Ottignies-Blocy. Alls rights
                    reserved.</div>
                <div class="flex flex-col justify-between gap-3 mx-8 w-fit">
                    <div class="flex flex-row items-center justify-start gap-4">
                        <x-icons.email class="h-6 my-auto mt-4 fill-yellow-500" />
                        <p>{{ __('Send us an email') }}</p>
                    </div>

                    {{-- <div>
                    <hr class="w-1/2 mx-auto border-0.5 border-yellow-500 border-dashed">
                </div> --}}

                    <div class="flex justify-start gap-4">
                        <x-icons.phone class="h-6 my-auto mt-4 fill-yellow-500" />
                        <ul class="">
                            <li>Manon Patigny : 0474 123 456</li>
                            <li>Augstin Docquier : 0471 654 321</li>
                        </ul>
                    </div>
                    {{-- <div>
                    <hr class="w-1/2 mx-auto border-0.5 border-yellow-500 border-dashed">
                </div> --}}
                    <div class="flex justify-start gap-4">
                        <x-icons.map class="h-6 my-auto mt-4 fill-yellow-500" />
                        <div>
                            <p>Centre Sportif Jean Demeester</p>
                            <address>Rue de l'Invasion 80, 1340 Ottignies-Louvain-la-Neuve</address>
                        </div>
                    </div>
                </div>
            </div>

            <div class="container mx-auto mt-12">
                <hr class="border-yellow-500 border-dashed">
                <h2 class="mx-auto my-4 text-xl font-bold w-fit">{{ __('They are proudly supporting us :') }}</h2>
                <div class="flex flex-row flex-wrap justify-around w-full p-4">
                    <img class="h-24" src="{{ asset('images/sponsors/malou.png') }}" alt="">
                    <img class="h-24" src="{{ asset('images/sponsors/gd_tax_account.png') }}" alt="">
                    <img class="h-24" src="{{ asset('images/sponsors/ericfilee.png') }}" alt="">
                </div>
            </div>

        </footer>
    </div>

</body>
