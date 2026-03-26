import "./bootstrap";
import { initTheme } from './components/theme';
import { setupPlugins } from './plugins/setup';

// Initialisations immédiates
initTheme();
setupPlugins();

// Imports des composants
import "./components/news-filter";
import "./components/footer";
import scrollAnimations from './components/scroll-animations';
import priceCalculator from './components/price-calculator';
import contactForm from './components/contact-form';
import eventFilters from './components/event-filters';
import navigation from './components/navigation';

// Enregistrement Alpine
// On s'assure qu'Alpine est disponible (généralement via bootstrap.js ou import direct)
document.addEventListener('alpine:init', () => {
    Alpine.data("scrollAnimations", scrollAnimations);
    Alpine.data("priceCalculator", priceCalculator);
    Alpine.data("contactForm", contactForm);
    Alpine.data("eventFilters", eventFilters);
    Alpine.data("navigation", navigation);
});