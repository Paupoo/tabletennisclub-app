// CTT Ottignies-Blocry — Public website sections (recreation of the Laravel/Blade marketing site)
// Composes the design-system bundle + window.Icon. Exports sections to window.
(function () {
  const DS = window.CTTOttigniesBlocryDesignSystem_a28edf;
  const { Button, Badge, NewsCard, Card } = DS;
  const Icon = window.Icon;
  const e = React.createElement;

  const CONTAINER = { maxWidth: 1180, margin: '0 auto', padding: '0 24px' };

  // ---------------------------------------------------------------- Navigation
  function Nav({ onNav, active = 'home' }) {
    const [open, setOpen] = React.useState(false);
    const links = [
      ['home', 'Accueil'], ['results', 'Résultats'], ['events', 'Événements'],
      ['news', 'Nouvelles'], ['contact', 'Contact'],
    ];
    return e('nav', { style: {
      position: 'sticky', top: 0, zIndex: 50, width: '100%',
      background: 'rgba(255,255,255,0.95)', backdropFilter: 'blur(6px)',
      boxShadow: 'var(--shadow-xs)', borderBottom: '1px solid var(--border-default)',
    }},
      e('div', { style: { ...CONTAINER, display: 'flex', alignItems: 'center', justifyContent: 'space-between', height: 64 } },
        e('a', { href: '#', onClick: (ev) => { ev.preventDefault(); onNav('home'); }, style: { display: 'flex', alignItems: 'center', gap: 10, textDecoration: 'none' } },
          e('img', { src: '../../assets/logo-club.svg', alt: 'CTT', style: { height: 34, width: 'auto' } }),
          e('span', { style: { fontSize: 20, fontWeight: 700, color: 'var(--club-blue)', letterSpacing: '-0.01em' } }, 'CTT Ottignies-Blocry'),
        ),
        e('div', { style: { display: 'flex', alignItems: 'center', gap: 4 } },
          links.map(([id, label]) => e('a', {
            key: id, href: '#', onClick: (ev) => { ev.preventDefault(); onNav(id); },
            style: {
              fontSize: 14, fontWeight: 500, padding: '8px 12px', borderRadius: 6, textDecoration: 'none',
              color: active === id ? 'var(--club-blue)' : 'var(--gray-900)',
            },
          }, label)),
          e('div', { style: { width: 8 } }),
          e(Button, { variant: 'primary', size: 'sm', onClick: () => onNav('contact') }, 'Rejoindre'),
          e(Button, { variant: 'secondary', size: 'sm', onClick: () => onNav('login') }, 'Connexion'),
        ),
      ),
    );
  }

  // ----------------------------------------------------------------------- Hero
  function Hero({ onNav }) {
    return e('section', { style: { position: 'relative', overflow: 'hidden', color: '#fff' } },
      e('img', { src: '../../assets/images/background_home.webp', alt: 'Tennis de table', style: { position: 'absolute', inset: 0, width: '100%', height: '100%', objectFit: 'cover' } }),
      e('div', { style: { position: 'absolute', inset: 0, background: 'linear-gradient(135deg, rgba(30,64,175,.88), rgba(30,64,175,.82) 50%, rgba(59,130,246,.85))' } }),
      e('div', { style: { ...CONTAINER, position: 'relative', padding: '110px 24px', textAlign: 'center' } },
        e('h1', { style: { fontSize: 'clamp(40px, 6vw, 72px)', fontWeight: 700, lineHeight: 1.05, margin: 0, letterSpacing: '-0.02em', textShadow: '0 2px 12px rgba(0,0,0,.25)' } }, 'CTT Ottignies-Blocry'),
        e('p', { style: { fontSize: 'clamp(18px,2.2vw,24px)', maxWidth: 720, margin: '24px auto 36px', opacity: 0.92, lineHeight: 1.5 } },
          'Rejoignez notre communauté passionnée de joueurs de tennis de table. Des débutants aux champions, tout le monde est le bienvenu au sein de notre club.'),
        e('div', { style: { display: 'flex', gap: 16, justifyContent: 'center', flexWrap: 'wrap' } },
          e(Button, { variant: 'secondary', size: 'lg', onClick: () => onNav('contact') }, 'Rejoindre le Club'),
          e(Button, { variant: 'outline', size: 'lg', onClick: () => onNav('about'), style: { color: '#fff', borderColor: 'rgba(255,255,255,.7)', background: 'rgba(255,255,255,.06)' } }, 'En Savoir Plus'),
        ),
      ),
    );
  }

  // ---------------------------------------------------------------------- About
  function SectionHead({ eyebrow, title, sub, dark }) {
    return e('div', { style: { textAlign: 'center', maxWidth: 720, margin: '0 auto 56px' } },
      eyebrow && e('div', { style: { fontSize: 14, fontWeight: 700, letterSpacing: '.15em', textTransform: 'uppercase', color: 'var(--club-blue)', marginBottom: 12 } }, eyebrow),
      e('h2', { style: { fontSize: 'clamp(28px,3.5vw,38px)', fontWeight: 700, margin: 0, color: dark ? '#fff' : 'var(--text-strong)', letterSpacing: '-0.01em' } }, title),
      sub && e('p', { style: { fontSize: 20, color: dark ? 'rgba(255,255,255,.8)' : 'var(--text-body)', marginTop: 16, lineHeight: 1.5 } }, sub),
    );
  }

  function About() {
    const feats = [
      { emoji: '🏆', bg: 'var(--club-blue)', title: 'Excellence compétitive', body: 'Participez à des tournois locaux et régionaux avec nos équipes compétitives.' },
      { emoji: '👥', bg: 'var(--club-yellow)', title: 'Communauté accueillante', body: 'Rejoignez des joueurs de tous niveaux dans un environnement amical et solidaire.' },
      { emoji: '🎯', bg: 'var(--club-blue)', title: 'Coaching professionnel', body: 'Apprenez avec des entraîneurs expérimentés et progressez grâce à un encadrement structuré.' },
    ];
    return e('section', { style: { padding: '88px 0', background: 'var(--gray-50)' } },
      e('div', { style: CONTAINER },
        e(SectionHead, { title: 'Pourquoi choisir le CTT Ottignies-Blocry\u00a0?', sub: 'Nous sommes plus qu\u2019un simple club \u2013 nous sommes une communauté dédiée au sport que nous aimons.' }),
        e('div', { style: { display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 32 } },
          feats.map((f, i) => e('div', { key: i, style: { textAlign: 'center', padding: 24 } },
            e('div', { style: { width: 64, height: 64, borderRadius: '50%', background: f.bg, display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 16px', fontSize: 28 } }, f.emoji),
            e('h3', { style: { fontSize: 20, fontWeight: 600, margin: '0 0 8px' } }, f.title),
            e('p', { style: { color: 'var(--text-body)', margin: 0, lineHeight: 1.55 } }, f.body),
          )),
        ),
      ),
    );
  }

  // --------------------------------------------------------------- Featured events
  function Events() {
    const evs = [
      { type: 'TOURNOI', bar: 'var(--club-yellow)', tone: 'secondary', emoji: '🏓', title: 'Tournoi interne de printemps', date: 'samedi 21 juin 2026', time: '13h00', loc: 'Centre sportif de Blocry', price: '5 €' },
      { type: 'INTERCLUB', bar: 'var(--club-blue)', tone: 'primary', emoji: '🏆', title: 'Finale provinciale — Équipe A', date: 'dimanche 29 juin 2026', time: '10h00', loc: 'Wavre', price: 'Entrée libre' },
      { type: 'VIE DU CLUB', bar: '#10b981', tone: 'success', emoji: '🍻', title: 'Barbecue de fin de saison', date: 'vendredi 4 juillet 2026', time: '18h30', loc: 'Club house', price: '12 €' },
    ];
    return e('section', { style: { padding: '88px 0', background: 'var(--gray-50)', borderTop: '1px solid var(--border-default)' } },
      e('div', { style: CONTAINER },
        e(SectionHead, { eyebrow: 'Prochains événements', title: 'À ne pas manquer', sub: 'Notez ces dates — ces rendez-vous du club valent le déplacement.' }),
        e('div', { style: { display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 24 } },
          evs.map((ev, i) => e(Card, { key: i, accent: ev.bar, padding: '0', hoverable: true },
            e('div', { style: { padding: '20px 24px' } },
              e('div', { style: { display: 'flex', alignItems: 'center', gap: 10, marginBottom: 16 } },
                e('span', { style: { fontSize: 20 } }, ev.emoji),
                e(Badge, { tone: ev.tone, size: 'sm' }, ev.type),
              ),
              e('h3', { style: { fontSize: 20, fontWeight: 700, margin: '0 0 16px', lineHeight: 1.3 } }, ev.title),
              e('div', { style: { display: 'flex', flexDirection: 'column', gap: 8, borderTop: '1px solid var(--border-default)', paddingTop: 14, fontSize: 14, color: 'var(--text-body)' } },
                e(MetaRow, { icon: 'calendar', children: ev.date + ' · ' + ev.time }),
                e(MetaRow, { icon: 'mapPin', children: ev.loc }),
                e(MetaRow, { icon: 'cash', children: ev.price }),
              ),
            ),
          )),
        ),
      ),
    );
  }
  function MetaRow({ icon, children }) {
    return e('div', { style: { display: 'flex', alignItems: 'center', gap: 8 } },
      e(Icon, { name: icon, size: 16, style: { color: 'var(--text-faint)', flexShrink: 0 } }),
      e('span', null, children),
    );
  }

  // ----------------------------------------------------------------------- News
  function News() {
    const items = [
      { image: '../../assets/images/background_news.webp', category: 'Compétition', date: '14 juin 2026', title: 'Victoire de l\u2019équipe A en D2', excerpt: 'Un week-end décisif pour nos joueurs, qui s\u2019imposent 10-6 face à Wavre et consolident leur place dans le haut du classement.' },
      { image: '../../assets/images/background_events.webp', category: 'Formation', date: '2 juin 2026', title: 'Stage jeunes pendant les vacances', excerpt: 'Cinq jours d\u2019entraînement encadré pour les 8-14 ans, tous niveaux confondus. Les inscriptions sont désormais ouvertes.' },
      { image: '../../assets/images/background_results.webp', category: 'Vie du club', date: '20 mai 2026', title: 'Assemblée générale 2026', excerpt: 'Retour sur une belle saison et présentation des projets pour l\u2019année à venir, suivis du verre de l\u2019amitié.' },
    ];
    return e('section', { style: { padding: '88px 0', background: 'var(--white)' } },
      e('div', { style: CONTAINER },
        e(SectionHead, { title: 'Dernières nouvelles', sub: 'Résultats, événements et vie du club.' }),
        e('div', { style: { display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 24 } },
          items.map((it, i) => e(NewsCard, { key: i, ...it, href: '#' })),
        ),
      ),
    );
  }

  // ------------------------------------------------------------------- Schedule
  function Schedule() {
    const rows = [
      { type: 'Dirigé', accent: '#3b82f6', tone: 'info', day: 'Lundi', time: '18h00 – 20h00', activity: 'Entraînement dirigé', coach: 'Coach Vasseur', level: 'Tous niveaux', levelTone: 'info' },
      { type: 'Jeunes', accent: '#15803d', tone: 'success', day: 'Mercredi', time: '14h00 – 16h00', activity: 'École de jeunes', coach: 'Coach Marie', level: 'Jeunes', levelTone: 'success' },
      { type: 'Compétition', accent: '#b91c1c', tone: 'error', day: 'Jeudi', time: '19h00 – 22h00', activity: 'Préparation interclubs', coach: 'Coach Paulus', level: 'Compétition', levelTone: 'error' },
      { type: 'Libre', accent: 'var(--gray-300)', tone: 'neutral', day: 'Samedi', time: '10h00 – 13h00', activity: 'Jeu libre', coach: null, level: 'Tous niveaux', levelTone: 'info' },
    ];
    return e('section', { style: { padding: '88px 0', background: 'var(--gray-50)' } },
      e('div', { style: { ...CONTAINER, maxWidth: 860 } },
        e(SectionHead, { title: 'Horaires des entraînements', sub: 'Retrouvez-nous au Centre sportif de Blocry tout au long de la semaine.' }),
        e('div', { style: { display: 'flex', flexDirection: 'column', gap: 12 } },
          rows.map((r, i) => e(Card, { key: i, accent: r.accent, accentSide: 'left', padding: '16px 20px', radius: 'var(--radius-lg)' },
            e('div', { style: { display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 16 } },
              e('div', null,
                e('div', { style: { display: 'flex', alignItems: 'center', gap: 8, marginBottom: 4, flexWrap: 'wrap' } },
                  e(Badge, { tone: r.tone, size: 'sm' }, r.type),
                  e('span', { style: { fontWeight: 700, fontSize: 15 } }, r.day + ' · ' + r.activity),
                ),
                e('div', { style: { display: 'flex', gap: 14, flexWrap: 'wrap', fontSize: 14, color: 'var(--text-muted)' } },
                  e('span', { style: { fontWeight: 500, color: 'var(--text-body)' } }, r.time),
                  r.coach && e('span', { style: { display: 'flex', alignItems: 'center', gap: 4 } }, e(Icon, { name: 'users', size: 14 }), r.coach),
                ),
              ),
              e(Badge, { tone: r.levelTone, size: 'sm' }, r.level),
            ),
          )),
        ),
      ),
    );
  }

  // -------------------------------------------------------------------- Contact
  function Contact({ onSubmit }) {
    const [sent, setSent] = React.useState(false);
    const info = [
      { icon: 'mapPin', bg: 'var(--club-blue)', title: 'Adresse', lines: ['Centre sportif de Blocry', 'Place des Sports 1', '1348 Ottignies-Louvain-la-Neuve'] },
      { icon: 'phone', bg: 'var(--club-blue)', title: 'Téléphone', lines: ['+32 10 12 34 56', 'Lun-Ven : 16h-20h'] },
      { icon: 'mail', bg: 'var(--club-yellow)', title: 'Email', lines: ['info@cttottigniesblocry.be', 'Réponse sous 48h'] },
    ];
    return e('section', { style: { padding: '88px 0', background: 'var(--white)' } },
      e('div', { style: { ...CONTAINER, display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 48, alignItems: 'start' } },
        e('div', null,
          e('h2', { style: { fontSize: 38, fontWeight: 700, margin: '0 0 16px', letterSpacing: '-0.01em' } }, 'Contactez-nous'),
          e('p', { style: { fontSize: 20, color: 'var(--text-body)', margin: '0 0 32px', lineHeight: 1.5 } }, 'Des questions ? Envie de nous rendre visite ? Nous serions ravis de vous entendre !'),
          e('div', { style: { display: 'flex', flexDirection: 'column', gap: 22 } },
            info.map((it, i) => e('div', { key: i, style: { display: 'flex', gap: 16, alignItems: 'flex-start' } },
              e('div', { style: { flexShrink: 0, width: 48, height: 48, borderRadius: 'var(--radius-lg)', background: it.bg, color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center' } }, e(Icon, { name: it.icon, size: 22 })),
              e('div', null,
                e('h3', { style: { margin: '0 0 2px', fontSize: 17, fontWeight: 600 } }, it.title),
                it.lines.map((l, j) => e('p', { key: j, style: { margin: 0, color: j === 0 ? 'var(--text-body)' : 'var(--text-muted)', fontSize: j === 0 ? 15 : 13 } }, l)),
              ),
            )),
          ),
        ),
        e(Card, { padding: '32px' },
          e('h3', { style: { fontSize: 24, fontWeight: 700, margin: '0 0 24px' } }, 'Envoyez-nous un message'),
          sent
            ? e('div', { style: { padding: '16px', background: '#dcfce7', border: '1px solid #bbf7d0', borderRadius: 'var(--radius-lg)', display: 'flex', gap: 10, color: 'var(--success-fg)', alignItems: 'center' } },
                e(Icon, { name: 'check', size: 20 }), e('span', { style: { fontWeight: 500 } }, 'Merci ! Votre message a bien été envoyé.'))
            : e('form', { onSubmit: (ev) => { ev.preventDefault(); setSent(true); onSubmit && onSubmit(); }, style: { display: 'flex', flexDirection: 'column', gap: 16 } },
                e('div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 } },
                  e(DS.Input, { label: 'Prénom', placeholder: 'Marie', required: true }),
                  e(DS.Input, { label: 'Nom', placeholder: 'Dubois', required: true }),
                ),
                e(DS.Input, { label: 'Email', type: 'email', placeholder: 'vous@exemple.be', required: true }),
                e('div', { style: { display: 'flex', flexDirection: 'column', gap: 6 } },
                  e('label', { style: { fontSize: 14, fontWeight: 600 } }, 'Message'),
                  e('textarea', { rows: 4, placeholder: 'Votre message…', style: { font: 'var(--font-body)', padding: '10px 14px', border: '1px solid var(--border-strong)', borderRadius: 'var(--radius-field)', resize: 'vertical', outline: 'none' } }),
                ),
                e(Button, { variant: 'primary', type: 'submit' }, 'Envoyer le message'),
              ),
        ),
      ),
    );
  }

  // --------------------------------------------------------------------- Footer
  function Footer() {
    return e('footer', { style: { background: 'var(--gray-900)', color: '#fff', padding: '56px 0 32px' } },
      e('div', { style: CONTAINER },
        e('div', { style: { display: 'grid', gridTemplateColumns: '1.4fr 1fr 1fr', gap: 32, paddingBottom: 32 } },
          e('div', null,
            e('div', { style: { display: 'flex', alignItems: 'center', gap: 10, marginBottom: 14 } },
              e('img', { src: '../../assets/logo-club.svg', alt: 'CTT', style: { height: 30, filter: 'brightness(0) invert(1)' } }),
              e('span', { style: { fontSize: 20, fontWeight: 700 } }, 'CTT Ottignies-Blocry')),
            e('p', { style: { color: 'var(--gray-400)', lineHeight: 1.6, margin: 0, maxWidth: 320 } }, 'Votre destination de choix pour le tennis de table à Ottignies et environs. Rejoignez notre communauté dès aujourd\u2019hui !'),
          ),
          e(FootCol, { title: 'Liens rapides', links: ['Accueil', 'Résultats', 'Événements', 'Contact'] }),
          e(FootCol, { title: 'Le club', links: ['Horaires', 'Nos équipes', 'Devenir membre', 'Sponsors'] }),
        ),
        e('div', { style: { borderTop: '1px solid var(--gray-800)', paddingTop: 24, display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap', gap: 12, color: 'var(--gray-400)', fontSize: 13 } },
          e('span', null, '© 2026 CTT Ottignies-Blocry. Tous droits réservés.'),
          e('span', null, 'Made with ♥ — Laravel · TailwindCSS · Alpine.js'),
        ),
      ),
    );
  }
  function FootCol({ title, links }) {
    return e('div', null,
      e('h4', { style: { fontSize: 16, fontWeight: 600, margin: '0 0 14px' } }, title),
      e('ul', { style: { listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: 8 } },
        links.map((l, i) => e('li', { key: i }, e('a', { href: '#', style: { color: 'var(--gray-400)', textDecoration: 'none', fontSize: 14 } }, l))),
      ),
    );
  }

  window.PublicSite = { Nav, Hero, About, Events, News, Schedule, Contact, Footer };
})();
