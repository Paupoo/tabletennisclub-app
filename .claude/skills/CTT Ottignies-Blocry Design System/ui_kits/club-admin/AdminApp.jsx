// CTT Ottignies-Blocry — Club admin (back office) recreation.
// daisyUI "bumblebee" surfaces + maryUI-style sidebar. Composes the DS bundle.
(function () {
  const DS = window.CTTOttigniesBlocryDesignSystem_a28edf;
  const { Badge, Avatar, Button, DashboardTile } = DS;
  const Icon = window.Icon;
  const e = React.createElement;

  // ----------------------------------------------------------------- Shell
  const NAV = [
    { section: null, items: [{ id: 'dashboard', icon: 'home', label: 'Tableau de bord' }, { id: 'notifications', icon: 'bell', label: 'Notifications', badge: 3 }] },
    { section: 'Membres', items: [{ id: 'members', icon: 'users', label: 'Utilisateurs' }, { id: 'registrations', icon: 'cash', label: 'Affiliations' }] },
    { section: 'Interclubs', items: [{ id: 'teams', icon: 'userGroup', label: 'Nos équipes' }, { id: 'results', icon: 'trophy', label: 'Résultats' }, { id: 'planning', icon: 'calendar', label: 'Calendrier' }] },
    { section: 'Site web', items: [{ id: 'articles', icon: 'newspaper', label: 'Articles' }, { id: 'contacts', icon: 'mail', label: 'Contacts' }] },
  ];

  function Shell({ active, onNav, children, title, crumbs }) {
    return e('div', { style: { display: 'flex', minHeight: '100vh', background: 'var(--base-200)', fontFamily: 'var(--font-sans)' } },
      // Sidebar
      e('aside', { style: { width: 252, flexShrink: 0, background: 'var(--base-100)', borderRight: '1px solid var(--base-300)', display: 'flex', flexDirection: 'column', position: 'sticky', top: 0, height: '100vh', overflowY: 'auto' } },
        e('div', { style: { display: 'flex', alignItems: 'center', gap: 10, padding: '18px 20px' } },
          e('img', { src: '../../assets/logo-club.svg', alt: 'CTT', style: { height: 30 } }),
          e('div', { style: { lineHeight: 1.05 } },
            e('div', { style: { fontSize: 15, fontWeight: 700, color: 'var(--club-blue)' } }, 'CTT Ottignies'),
            e('div', { style: { fontSize: 10, color: 'var(--text-faint)', letterSpacing: '.1em', textTransform: 'uppercase' } }, 'Espace club')),
        ),
        // user
        e('div', { style: { margin: '4px 12px 8px', padding: '10px 12px', background: 'var(--base-200)', borderRadius: 'var(--radius-lg)', display: 'flex', alignItems: 'center', gap: 10 } },
          e(Avatar, { name: 'Aurélien Paulus', size: 36 }),
          e('div', { style: { minWidth: 0 } },
            e('div', { style: { fontSize: 13, fontWeight: 700, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' } }, 'Aurélien Paulus'),
            e('div', { style: { fontSize: 11, color: 'var(--text-faint)' } }, 'Administrateur')),
        ),
        e('nav', { style: { padding: '4px 12px 20px', display: 'flex', flexDirection: 'column', gap: 2 } },
          NAV.map((grp, gi) => e(React.Fragment, { key: gi },
            grp.section && e('div', { style: { fontSize: 10, fontWeight: 700, letterSpacing: '.1em', textTransform: 'uppercase', color: 'var(--text-faint)', padding: '14px 12px 6px' } }, grp.section),
            grp.items.map((it) => {
              const on = active === it.id;
              return e('a', { key: it.id, href: '#', onClick: (ev) => { ev.preventDefault(); onNav(it.id); },
                style: { display: 'flex', alignItems: 'center', gap: 11, padding: '8px 12px', borderRadius: 'var(--radius-lg)', textDecoration: 'none', fontSize: 13.5, fontWeight: on ? 600 : 500,
                  color: on ? 'var(--club-blue)' : 'var(--text-body)', background: on ? '#eef2ff' : 'transparent' } },
                e(Icon, { name: it.icon, size: 18 }),
                e('span', { style: { flex: 1 } }, it.label),
                it.badge && e('span', { style: { background: 'var(--error)', color: '#fff', fontSize: 10, fontWeight: 700, minWidth: 16, height: 16, borderRadius: 8, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', padding: '0 4px' } }, it.badge),
              );
            }),
          )),
        ),
      ),
      // Main
      e('div', { style: { flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column' } },
        e('header', { style: { height: 60, background: 'var(--base-100)', borderBottom: '1px solid var(--base-300)', display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 28px', position: 'sticky', top: 0, zIndex: 10 } },
          e('div', null,
            e('div', { style: { fontSize: 11, color: 'var(--text-faint)' } }, crumbs || 'Accueil'),
            e('div', { style: { fontSize: 18, fontWeight: 700 } }, title)),
          e('div', { style: { display: 'flex', alignItems: 'center', gap: 14 } },
            e('div', { style: { display: 'flex', alignItems: 'center', gap: 8, background: 'var(--base-200)', borderRadius: 'var(--radius-full)', padding: '7px 14px', color: 'var(--text-muted)', fontSize: 13 } },
              e(Icon, { name: 'search', size: 16 }), e('span', null, 'Rechercher…')),
            e('button', { style: { position: 'relative', background: 'none', border: 'none', cursor: 'pointer', color: 'var(--text-body)' } },
              e(Icon, { name: 'bell', size: 20 }),
              e('span', { style: { position: 'absolute', top: -2, right: -2, width: 8, height: 8, background: 'var(--error)', borderRadius: '50%' } })),
            e(Avatar, { name: 'Aurélien Paulus', size: 34 }),
          ),
        ),
        e('main', { style: { padding: 28, flex: 1 } }, children),
      ),
    );
  }

  // ----------------------------------------------------------------- Dashboard
  function Dashboard() {
    const stats = [
      { label: 'Membres actifs', value: '142', delta: '+8', up: true, color: 'var(--club-blue)' },
      { label: 'Cotisations payées', value: '118', delta: '83%', up: true, color: '#10b981' },
      { label: 'Équipes interclubs', value: '6', delta: '2 div.', up: true, color: '#f59e0b' },
      { label: 'Contacts en attente', value: '4', delta: 'à traiter', up: false, color: '#f1576c' },
    ];
    const tiles = [
      { icon: 'users', label: 'Membres', sub: '142 actifs', color: 'blue' },
      { icon: 'cash', label: 'Trésorerie', sub: '€ 4 280', color: 'emerald' },
      { icon: 'userGroup', label: 'Sélections', sub: 'Semaine 12', color: 'indigo' },
      { icon: 'trophy', label: 'Tournois', sub: '2 à venir', color: 'amber' },
      { icon: 'newspaper', label: 'Articles', sub: '18 publiés', color: 'violet' },
      { icon: 'academicCap', label: 'Entraînements', sub: '4 / semaine', color: 'teal' },
    ];
    const activity = [
      { who: 'Marie Dubois', what: 'a payé sa cotisation 2026', when: 'il y a 12 min', tone: 'success', tag: 'Paiement' },
      { who: 'Équipe A', what: 'a remporté le match contre Wavre', when: 'il y a 2 h', tone: 'primary', tag: 'Interclub' },
      { who: 'Tom Vasseur', what: 's\u2019est inscrit au tournoi de printemps', when: 'il y a 5 h', tone: 'secondary', tag: 'Tournoi' },
      { who: 'Nouveau contact', what: 'a envoyé un message via le site', when: 'hier', tone: 'info', tag: 'Site web' },
    ];
    return e('div', { style: { display: 'flex', flexDirection: 'column', gap: 24 } },
      // stats
      e('div', { style: { display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 16 } },
        stats.map((s, i) => e('div', { key: i, style: { background: 'var(--base-100)', border: '1px solid var(--base-300)', borderRadius: 'var(--radius-xl)', padding: 18 } },
          e('div', { style: { fontSize: 13, color: 'var(--text-muted)', marginBottom: 6 } }, s.label),
          e('div', { style: { display: 'flex', alignItems: 'baseline', gap: 8 } },
            e('div', { style: { fontSize: 30, fontWeight: 700, color: s.color } }, s.value),
            e('span', { style: { fontSize: 12, fontWeight: 600, color: s.up ? 'var(--success-fg)' : 'var(--text-faint)' } }, s.delta)),
        )),
      ),
      // quick actions
      e(Panel, { title: 'Accès rapides' },
        e('div', { style: { display: 'grid', gridTemplateColumns: 'repeat(6,1fr)', gap: 14 } },
          tiles.map((t, i) => e(DashboardTile, { key: i, ...t, icon: e(Icon, { name: t.icon, size: 20 }) })),
        ),
      ),
      // two col: activity + next matches
      e('div', { style: { display: 'grid', gridTemplateColumns: '1.4fr 1fr', gap: 20 } },
        e(Panel, { title: 'Activité récente' },
          e('div', { style: { display: 'flex', flexDirection: 'column' } },
            activity.map((a, i) => e('div', { key: i, style: { display: 'flex', alignItems: 'center', gap: 12, padding: '12px 0', borderTop: i ? '1px solid var(--base-300)' : 'none' } },
              e(Avatar, { name: a.who, size: 36 }),
              e('div', { style: { flex: 1, minWidth: 0 } },
                e('div', { style: { fontSize: 14 } }, e('b', null, a.who), ' ', a.what),
                e('div', { style: { fontSize: 12, color: 'var(--text-faint)' } }, a.when)),
              e(Badge, { tone: a.tone, size: 'sm' }, a.tag),
            )),
          ),
        ),
        e(Panel, { title: 'Prochains matchs' },
          e('div', { style: { display: 'flex', flexDirection: 'column', gap: 10 } },
            [['A', 'CTT Ottignies A', 'TT Wavre', 'sam. 21/06'], ['B', 'Jette', 'CTT Ottignies B', 'dim. 22/06'], ['V', 'CTT Ottignies V', 'Braine', 'sam. 28/06']].map((m, i) =>
              e('div', { key: i, style: { display: 'flex', alignItems: 'center', gap: 12, padding: '10px 12px', background: 'var(--base-200)', borderRadius: 'var(--radius-lg)' } },
                e('div', { style: { width: 30, height: 30, borderRadius: '50%', background: '#dbeafe', color: 'var(--club-blue)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 700, fontSize: 13, flexShrink: 0 } }, m[0]),
                e('div', { style: { flex: 1, fontSize: 13 } }, e('div', { style: { fontWeight: 600 } }, m[1] + ' vs ' + m[2])),
                e('span', { style: { fontSize: 12, color: 'var(--text-muted)', whiteSpace: 'nowrap' } }, m[3]),
              )),
          ),
        ),
      ),
    );
  }

  function Panel({ title, action, children }) {
    return e('section', { style: { background: 'var(--base-100)', border: '1px solid var(--base-300)', borderRadius: 'var(--radius-xl)', padding: 20 } },
      e('div', { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16 } },
        e('h3', { style: { margin: 0, fontSize: 15, fontWeight: 700 } }, title),
        action || null),
      children,
    );
  }

  // ------------------------------------------------------------------- Members
  function Members() {
    const rows = [
      { name: 'Aurélien Paulus', email: 'aurelien@ctt.be', cat: 'Senior', idx: 'C2', paid: true, role: 'Admin' },
      { name: 'Marie Dubois', email: 'marie.dubois@ctt.be', cat: 'Senior', idx: 'D4', paid: true, role: 'Joueuse' },
      { name: 'Tom Vasseur', email: 'tom.v@ctt.be', cat: 'Jeune', idx: 'E0', paid: false, role: 'Joueur' },
      { name: 'Sophie Lambert', email: 's.lambert@ctt.be', cat: 'Vétéran', idx: 'C4', paid: true, role: 'Capitaine' },
      { name: 'Lucas Martin', email: 'lucas.m@ctt.be', cat: 'Senior', idx: 'B6', paid: true, role: 'Joueur' },
      { name: 'Emma Renard', email: 'emma.r@ctt.be', cat: 'Jeune', idx: 'NC', paid: false, role: 'Joueuse' },
    ];
    return e('div', { style: { display: 'flex', flexDirection: 'column', gap: 18 } },
      e('div', { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 16, flexWrap: 'wrap' } },
        e('div', { style: { display: 'flex', alignItems: 'center', gap: 8, background: 'var(--base-100)', border: '1px solid var(--base-300)', borderRadius: 'var(--radius-field)', padding: '8px 14px', color: 'var(--text-muted)', fontSize: 14, minWidth: 280 } },
          e(Icon, { name: 'search', size: 16 }), e('span', null, 'Rechercher un membre…')),
        e('div', { style: { display: 'flex', gap: 8 } },
          e(Button, { variant: 'outline', size: 'sm' }, 'Filtres'),
          e(Button, { variant: 'primary', size: 'sm', icon: e(Icon, { name: 'plus', size: 15 }) }, 'Nouveau membre')),
      ),
      e('div', { style: { background: 'var(--base-100)', border: '1px solid var(--base-300)', borderRadius: 'var(--radius-xl)', overflow: 'hidden' } },
        e('table', { style: { width: '100%', borderCollapse: 'collapse', fontSize: 14 } },
          e('thead', null, e('tr', { style: { background: 'var(--base-200)', textAlign: 'left' } },
            ['Membre', 'Catégorie', 'Classement', 'Rôle', 'Cotisation', ''].map((h, i) =>
              e('th', { key: i, style: { padding: '11px 18px', fontSize: 11, fontWeight: 700, letterSpacing: '.05em', textTransform: 'uppercase', color: 'var(--text-faint)' } }, h)))),
          e('tbody', null, rows.map((r, i) => e('tr', { key: i, style: { borderTop: '1px solid var(--base-300)' } },
            e('td', { style: { padding: '12px 18px' } }, e('div', { style: { display: 'flex', alignItems: 'center', gap: 10 } },
              e(Avatar, { name: r.name, size: 34 }),
              e('div', null, e('div', { style: { fontWeight: 600 } }, r.name), e('div', { style: { fontSize: 12, color: 'var(--text-faint)' } }, r.email)))),
            e('td', { style: { padding: '12px 18px' } }, e(Badge, { tone: 'neutral', size: 'sm' }, r.cat)),
            e('td', { style: { padding: '12px 18px', fontWeight: 600, fontVariantNumeric: 'tabular-nums' } }, r.idx),
            e('td', { style: { padding: '12px 18px', color: 'var(--text-body)' } }, r.role),
            e('td', { style: { padding: '12px 18px' } }, e(Badge, { tone: r.paid ? 'success' : 'warning', size: 'sm' }, r.paid ? 'Payée' : 'En attente')),
            e('td', { style: { padding: '12px 18px', textAlign: 'right' } }, e('div', { style: { display: 'inline-flex', gap: 6, color: 'var(--text-faint)' } },
              e(Icon, { name: 'eye', size: 17 }), e(Icon, { name: 'pencil', size: 17 }))),
          ))),
        ),
      ),
    );
  }

  // --------------------------------------------------------------------- Teams
  function Teams() {
    const teams = [
      { letter: 'A', name: 'CTT Ottignies A', div: 'Division 2 — Nationale', cat: 'Hommes', captain: 'Aurélien Paulus', count: 5, next: 'sam. 21 juin' },
      { letter: 'B', name: 'CTT Ottignies B', div: 'Division 4 — Provinciale', cat: 'Hommes', captain: 'Lucas Martin', count: 4, next: 'dim. 22 juin' },
      { letter: 'V', name: 'CTT Ottignies V', div: 'Vétérans — Série 1', cat: 'Vétérans', captain: 'Sophie Lambert', count: 4, next: 'sam. 28 juin' },
      { letter: 'D', name: 'CTT Ottignies D', div: 'Dames — Série 2', cat: 'Dames', captain: '—', count: 3, next: null },
    ];
    const palette = { Hommes: ['#eff6ff', 'var(--club-blue)', '#dbeafe'], Vétérans: ['#fffbeb', '#b45309', '#fef3c7'], Dames: ['#fdf2f8', '#be185d', '#fce7f3'] };
    return e('div', { style: { display: 'grid', gridTemplateColumns: 'repeat(2,1fr)', gap: 18 } },
      teams.map((t, i) => {
        const [bg, fg, dot] = palette[t.cat] || ['var(--base-200)', 'var(--text-body)', 'var(--base-300)'];
        return e('div', { key: i, style: { background: 'var(--base-100)', border: '1px solid var(--base-300)', borderRadius: 'var(--radius-xl)', overflow: 'hidden' } },
          e('div', { style: { display: 'flex', alignItems: 'center', gap: 12, padding: '14px 18px', background: bg } },
            e('div', { style: { width: 40, height: 40, borderRadius: '50%', background: dot, color: fg, display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 700, fontSize: 18 } }, t.letter),
            e('div', null, e('div', { style: { fontWeight: 700, color: fg } }, t.name), e('div', { style: { fontSize: 12, color: fg, opacity: .8 } }, t.div))),
          e('div', { style: { padding: '4px 18px' } },
            [['Capitaine', t.captain === '—' ? null : t.captain], ['Noyau', t.count + ' joueur' + (t.count > 1 ? 's' : '')], ['Prochain match', t.next]].map((row, j) =>
              e('div', { key: j, style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '10px 0', borderTop: j ? '1px solid var(--base-300)' : 'none' } },
                e('span', { style: { fontSize: 11, fontWeight: 600, letterSpacing: '.04em', textTransform: 'uppercase', color: 'var(--text-faint)' } }, row[0]),
                row[1] ? e('span', { style: { fontSize: 14, fontWeight: 500 } }, row[1]) : e('span', { style: { fontSize: 13, fontStyle: 'italic', color: 'var(--text-faint)' } }, 'Non défini'))),
          ),
          e('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '10px 14px', background: 'var(--base-200)', borderTop: '1px solid var(--base-300)' } },
            e('div', { style: { display: 'flex', gap: 4, color: 'var(--text-faint)' } }, e(Icon, { name: 'eye', size: 17 }), e(Icon, { name: 'pencil', size: 17 })),
            e(Badge, { tone: t.cat === 'Hommes' ? 'primary' : t.cat === 'Vétérans' ? 'warning' : 'error', size: 'sm' }, t.cat)),
        );
      }),
    );
  }

  window.AdminApp = { Shell, Dashboard, Members, Teams };
})();
