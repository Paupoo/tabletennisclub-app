<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Le Bar - CTT Ottignies-Blocry</title>

    <link rel="stylesheet" href="{{ asset('assets/bar/bar.css') }}">
</head>

<body class="pos">
    {{-- Top Navigation --}}
    <header class="header">
        {{-- Logo / Title --}}
        <div class="header-logo">🍺 Le Bar - CTT Ottignies-Blocry</div>
        
        {{-- Navigation --}}
        <nav class="header-nav" aria-label="Navigation principale">
            <a class="nav-link" href="{{ route('bar.index') }}">🏠 Accueil</a>
            <a class="nav-link" href="#">📋 Commandes</a>
            <a class="nav-link" href="#">📜 Historique</a>
            <a class="nav-link" href="{{ route('bar.products.index') }}">🍺 Produits</a>
            <a class="nav-link" href="{{ route('bar.categories.index') }}">🏷️ Catégories</a>
            <a class="nav-link" href="#">💵 Feuille de caisse</a>
            <a class="nav-link" href="#">👤 Utilisateurs</a>
        </nav>
        <div class="header-right">
            @auth
            <div class="header-badge">👋 {{ auth()->user()->first_name }}</div>
             <!-- <pre>{{ print_r(auth()->user(), true) }}</pre> -->
            @endauth



    
        <button class="hamburger" type="button" aria-label="Menu" onclick="toggleMobileNav()">☰</button>
    </div>
        
    </header>

    <div id="navMobile" class="nav-mobile" aria-label="Menu mobile">
        <a class="nav-mobile-link" href="{{ route('bar.index') }}">🏠 Accueil</a>
        <a class="nav-mobile-link" href="#">📋 Commandes</a>
        <a class="nav-mobile-link" href="#">📜 Historique</a>
        <a class="nav-mobile-link" href="{{ route('bar.products.index') }}">🍺 Produits</a>
        <a class="nav-mobile-link" href="{{ route('bar.categories.index') }}">🏷️ Catégories</a>
        <a class="nav-mobile-link" href="#">💵 Feuille de caisse</a>
        <a class="nav-mobile-link" href="#">👤 Utilisateurs</a>
        <a class="nav-mobile-link" href="#">🚪 Quitter</a>
        <a class="nav-mobile-link" href="#">🔒 Connexion</a>
</div>

    {{-- Main Content --}}
    <main>
        @yield('content')
    </main>

    {{-- Footer --}}
    <!-- <footer class="mt-12 py-6 text-center text-sm text-gray-500">
        © {{ date('Y') }} Bar Management — All rights reserved.
    </footer> -->
</body>
</html>
