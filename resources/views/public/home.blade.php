<x-guest-layout title="Accueil - CTT Ottignies">
    <section id="home">
        <x-public.hero />
    </section>
    
    <section id="about">
        <x-public.about-section />
    </section>
    
    <section id="join">
        <x-public.join-section />
    </section>

    <section id="news">
        <x-public.news-section :articles="$articles ?? []" />
    </section>

    <section id="schedules">
        <x-public.schedule-section :schedules="$schedules ?? []"/>
    </section>
    
    <section id="contact">
        <x-public.contact-section :club="$club"/>
    </section>
    
    <section id="sponsors">
        <x-public.sponsors-section :sponsors="$sponsors ?? []" />
    </section>
</x-guest-layout>
