<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite('resources/css/app.css')
</head>

<body class="bg-white">
    <div class="container">
        <div class="flex flex-row items-center justify-end w-screen h-20 px-4 text-yellow-400 bg-slate-800">
            <div class="flex flex-row items-center justify-between w-64 mr-auto">
                <div><img src="{{ asset('images/logo.svg') }}" alt="" width="50"></div>
                <div class="text-3xl font-bold">CTT Ottignies</div>
            </div>
            <nav class="flex flex-row">
                {{-- Big screen menu --}}
                <ul class="flex flex-row items-center justify-around text-lg font-bold h-11">
                    <li class="hidden mr-4 xl:flex"><a href="">Le club</a></li>
                    <li class="hidden mr-4 xl:flex"><a href="">Calendrier</a></li>
                    <li class="hidden mr-4 xl:flex"><a href="">Résultats</a></li>
                    <li class="hidden mr-4 xl:flex"><a href="">Contact</a></li>
                    <button
                        class="items-center hidden h-8 px-2 py-2 ml-8 font-normal text-justify text-blue-900 bg-yellow-400 border-0 rounded-md lg:flex hover:bg-yellow-200">Nous
                        rejoindre</button>
                    <button
                        class="items-center hidden h-8 px-2 py-2 mx-4 font-normal text-justify text-yellow-400 bg-blue-500 border-0 rounded-md lg:flex hover:bg-blue-300">Login</button>
                </ul>
                {{-- Hamburger menu --}}
                <div class="flex items-center mx-4 xl:hidden">
                    <button>
                        <?xml version="1.0" encoding="UTF-8"?><svg width="24px" height="24px" stroke-width="1.5"
                            viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" color="#facc15">
                            <path d="M3 5H21" stroke="#facc15" stroke-width="1.5" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M3 12H21" stroke="#facc15" stroke-width="1.5" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M3 19H21" stroke="#facc15" stroke-width="1.5" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg>
                    </button>
                </div>
                {{-- Search box --}}
                <div
                    class="items-center justify-end hidden w-64 h-10 bg-yellow-100 border-0 sm:flex align-center rounded-xl">
                    {{-- <label for="search" class="inline-flex items-center h-full pl-2 text-gray-800 bg-yellow-500 rounded-l-xl w-fit">Recherche : </label> --}}
                    <input type="search" aria-label="Recherche" id="search" placeholder="Recherche"
                        class="w-full text-gray-900 bg-yellow-100 border-0 rounded-l-xl">
                    <button class="w-10 mx-2"><img src="{{ asset('images/search.svg') }}" alt="Recherche"></button>
                </div>
                <div class="w-10 sm:hidden">
                    <?xml version="1.0" encoding="UTF-8"?><svg width="24px" height="24px" viewBox="0 0 24 24"
                        stroke-width="1.5" fill="none" xmlns="http://www.w3.org/2000/svg" color="#000000">
                        <path d="M17 17L21 21" stroke="#facc15" stroke-width="1.5" stroke-linecap="round"
                            stroke-linejoin="round"></path>
                        <path
                            d="M3 11C3 15.4183 6.58172 19 11 19C13.213 19 15.2161 18.1015 16.6644 16.6493C18.1077 15.2022 19 13.2053 19 11C19 6.58172 15.4183 3 11 3C6.58172 3 3 6.58172 3 11Z"
                            stroke="#facc15" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </div>
            </nav>

        </div>

        {{-- Section 1 --}}
        <div class="w-screen pt-12 bg-gradient-to-b from-slate-800 to-indigo-900">
            <div class="flex flex-row m-auto max-w-7xl">
                <div class="relative w-5/12 max-w-lg m-auto leading-normal text-gray-300">
                    {{-- <hr class="mb-4"> --}}
                    <h1 class="text-6xl leading-snug text-left">Bienvenue</h1>
                    <p class="text-3xl leading-snug text-right">dans notre club a taille <span
                            class="font-semibold text-yellow-400">humaine</span>
                    <p class="text-2xl leading-snug text-left">à Ottignies-Louvain-La-Neuve</p>
                    <hr class="mt-6">
                    <div class="flex flex-row justify-around w-full mt-10">
                        <button
                            class="flex flex-row px-6 py-2 font-semibold duration-200 ease-in bg-yellow-400 rounded-full text-slate-900 hover:bg-indigo-600 hover:text-white hover:scale-125">
                            <div class="mr-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25" />
                                </svg>

                            </div>
                            <div>
                                Nous rejoindre
                            </div>
                        </button>
                        <button
                            class="flex flex-row px-6 py-2 font-semibold duration-200 ease-in bg-yellow-400 rounded-full text-slate-900 hover:bg-indigo-600 hover:text-white hover:scale-125">
                            <div class="mr-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M21.75 9v.906a2.25 2.25 0 01-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 001.183 1.981l6.478 3.488m8.839 2.51l-4.66-2.51m0 0l-1.023-.55a2.25 2.25 0 00-2.134 0l-1.022.55m0 0l-4.661 2.51m16.5 1.615a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V8.844a2.25 2.25 0 011.183-1.98l7.5-4.04a2.25 2.25 0 012.134 0l7.5 4.04a2.25 2.25 0 011.183 1.98V19.5z" />
                                </svg>

                            </div>
                            <div>
                                Nous contacter
                            </div>
                        </button>
                    </div>
                </div>
                <div class="hidden transition-all xl:block xl:m-auto xl:bottom-0">
                    <img src="{{ asset('images/Groupe-1.jpg') }}" alt=""
                        class="object-cover translate-y-28 rotate-6 rounded-3xl">
                </div>
            </div>
        </div>

        {{-- Section 2 --}}
        <div class="mt-44">
            <div class="container flex flex-col">

                <h2 class="justify-center text-3xl font-semibold align-middle">Où sommes-nous ?</h2>
                <div class="flex items-center justify-center gap-9">
                    <div class="p-4 bg-yellow-100 rounded-2xl w-96">
                        <p class="text-xl font-medium underline decoration-dotted">Centre sportif Jean-Demeester</p>
                        <p><br>Rue de l'Invasion 80<br>1340 Ottignies-Louvain-la-Neuve</p>
                    </div>
                    <div class="p-4 bg-yellow-100 rounded-2xl w-96">
                        <p class="text-xl font-medium underline decoration-dotted">Centre sportif Blocry</p>
                        <p><br>Place des Sports 1<br>1348 Ottignies-Louvain-la-Neuve</p>
                    </div>
                </div>
            </div>
        </div>
</body>

</html>
