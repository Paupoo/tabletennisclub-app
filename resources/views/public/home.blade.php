<x-guest-layout title="Accueil - CTT Ottignies">
    <section id="home">
        <x-hero />
    </section>
    
    <section id="about">
        <x-about-section />
    </section>
    
    <section id="join">
        <x-join-section />
    </section>

    <section id="news">
        <x-news-section :articles="$articles ?? []" />
    </section>

    <section id="schedules">
        <x-schedule-section :schedules="$schedules ?? []"/>
    </section>
    
    <section id="contact">
        <x-contact-section />
    </section>
    
    <section id="sponsors">
        <x-sponsors-section :sponsors="$sponsors ?? []" />
    </section>
</x-guest-layout>
