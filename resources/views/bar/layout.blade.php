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
            <a class="nav-link" href="{{ route('bar.orders.index') }}">📋 Commandes</a>
            <a class="nav-link" href="{{ route('bar.orders.history') }}">📜 Historique</a>
            <a class="nav-link" href="{{ route('bar.products.index') }}">🍺 Produits</a>
            <a class="nav-link" href="{{ route('bar.categories.index') }}">🏷️ Catégories</a>
            <a class="nav-link" href="{{ route('bar.cashSheet.index') }}">💵 Feuille de caisse</a>
        </nav>
        <div class="header-right">
            @auth
            <div class="header-badge">👋 {{ auth()->user()->first_name }}</div>
            @endauth
            
            <button id="menuToggle" class="hamburger">☰</button>
        </div>
</header>

<div id="navMobile" class="nav-mobile" aria-label="Menu mobile">
    <a class="nav-mobile-link" href="{{ route('bar.index') }}">🏠 Accueil</a>
    <a class="nav-mobile-link" href="{{ route('bar.orders.index') }}">📋 Commandes</a>
    <a class="nav-mobile-link" href="{{ route('bar.orders.history') }}">📜 Historique</a>
    <a class="nav-mobile-link" href="{{ route('bar.products.index') }}">🍺 Produits</a>
    <a class="nav-mobile-link" href="{{ route('bar.categories.index') }}">🏷️ Catégories</a>
    <a class="nav-mobile-link" href="{{ route('bar.cashSheet.index') }}">💵 Feuille de caisse</a>
    <!-- <a class="nav-mobile-link" href="{{ route('bar.logout') }}">🚪 Quitter</a> -->
     <form method="POST" action="{{ route('bar.logout') }}">
        @csrf
        <button type="submit" class="nav-mobile-link">
            🚪 Quitter
        </button>
    </form>
</div>

    {{-- Main Content --}}
    <main>
        @yield('content')
    </main>
</body>
</html>
<script>
document.addEventListener('DOMContentLoaded', () => {

    const button = document.getElementById('menuToggle');
    const nav = document.getElementById('navMobile');

    if (!button || !nav) return;

    button.addEventListener('click', (e) => {
        e.stopPropagation();

        const rect = button.getBoundingClientRect();

        // Position menu relative to button
        nav.style.top = rect.bottom + "px";
        nav.style.right = (window.innerWidth - rect.right) + "px";
        nav.style.left = "auto"; // important (reset)
        nav.classList.toggle('open');
    });

    document.addEventListener('click', (e) => {
        if (!nav.contains(e.target) && !button.contains(e.target)) {
            nav.classList.remove('open');
        }
    });

});
</script>