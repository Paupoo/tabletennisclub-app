/* @ds-bundle: {"format":3,"namespace":"CTTOttigniesBlocryDesignSystem_a28edf","components":[{"name":"DashboardTile","sourcePath":"components/admin/DashboardTile.jsx"},{"name":"Avatar","sourcePath":"components/core/Avatar.jsx"},{"name":"Badge","sourcePath":"components/core/Badge.jsx"},{"name":"Button","sourcePath":"components/core/Button.jsx"},{"name":"Card","sourcePath":"components/core/Card.jsx"},{"name":"Input","sourcePath":"components/core/Input.jsx"},{"name":"NewsCard","sourcePath":"components/public/NewsCard.jsx"}],"sourceHashes":{"assets/heroicons.jsx":"ff2cc18a4201","components/admin/DashboardTile.jsx":"57a07cccf1f9","components/core/Avatar.jsx":"9426b5b3e4d3","components/core/Badge.jsx":"aebce27d0341","components/core/Button.jsx":"e74a39fdf19d","components/core/Card.jsx":"94fc87f5a290","components/core/Input.jsx":"8b84b472035c","components/public/NewsCard.jsx":"1e87e6579cdf","ui_kits/club-admin/AdminApp.jsx":"ed78d3c91ed0","ui_kits/public-website/PublicSite.jsx":"e76e8931b17b"},"inlinedExternals":[],"unexposedExports":[]} */

(() => {

const __ds_ns = (window.CTTOttigniesBlocryDesignSystem_a28edf = window.CTTOttigniesBlocryDesignSystem_a28edf || {});

const __ds_scope = {};

(__ds_ns.__errors = __ds_ns.__errors || []);

// assets/heroicons.jsx
try { (() => {
// CTT Ottignies-Blocry — inline Heroicons (outline) helper for UI kits.
// Matches the app's maryUI `o-*` icons. Exposes window.Icon and window.ICONS.
(function () {
  const P = {
    home: "M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25",
    users: "M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z",
    trophy: "M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 01-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0",
    calendar: "M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5",
    mapPin: "M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z",
    phone: "M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z",
    mail: "M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75",
    clock: "M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z",
    arrowRight: "M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3",
    arrowLeft: "M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18",
    chevronRight: "M8.25 4.5l7.5 7.5-7.5 7.5",
    bell: "M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0",
    search: "M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z",
    cash: "M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z",
    academicCap: "M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.62 48.62 0 0112 20.904a48.62 48.62 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5",
    newspaper: "M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 01-2.25 2.25M16.5 7.5V18a2.25 2.25 0 002.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 002.25 2.25h13.5M6 7.5h3v3H6v-3z",
    bars: "M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5",
    check: "M4.5 12.75l6 6 9-13.5",
    cog: "M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a6.759 6.759 0 010 .255c-.008.378.137.75.43.991l1.004.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.241.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.991l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z M15 12a3 3 0 11-6 0 3 3 0 016 0z",
    pencil: "M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125",
    eye: "M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z M15 12a3 3 0 11-6 0 3 3 0 016 0z",
    trash: "M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0",
    plus: "M12 4.5v15m7.5-7.5h-15",
    star: "M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z",
    building: "M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21",
    userGroup: "M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"
  };
  function Icon({
    name,
    size = 22,
    stroke = 1.7,
    style = {},
    className = ""
  }) {
    const d = P[name];
    return React.createElement("svg", {
      width: size,
      height: size,
      viewBox: "0 0 24 24",
      fill: "none",
      stroke: "currentColor",
      strokeWidth: stroke,
      strokeLinecap: "round",
      strokeLinejoin: "round",
      style,
      className,
      "aria-hidden": "true"
    }, React.createElement("path", {
      d
    }));
  }
  window.Icon = Icon;
  window.ICONS = P;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "assets/heroicons.jsx", error: String((e && e.message) || e) }); }

// components/admin/DashboardTile.jsx
try { (() => {
/**
 * CTT Ottignies-Blocry — DashboardTile
 * The admin dashboard quick-action tile: a colored icon chip over a label +
 * sublabel, in a white rounded-xl card that lifts on hover. Mirrors
 * clubAdmin/_dashboard_tile.blade.php (color-mapped icon backgrounds).
 */
function DashboardTile({
  icon,
  label,
  sub,
  color = 'blue',
  badge = null,
  href = '#',
  onClick,
  className = '',
  style = {}
}) {
  const colorMap = {
    blue: ['#dbeafe', '#2563eb'],
    cyan: ['#cffafe', '#0891b2'],
    teal: ['#ccfbf1', '#0d9488'],
    indigo: ['#e0e7ff', '#4f46e5'],
    violet: ['#ede9fe', '#7c3aed'],
    purple: ['#f3e8ff', '#9333ea'],
    rose: ['#ffe4e6', '#e11d48'],
    orange: ['#ffedd5', '#ea580c'],
    amber: ['#fef3c7', '#d97706'],
    yellow: ['#fef9c3', '#ca8a04'],
    emerald: ['#d1fae5', '#059669'],
    pink: ['#fce7f3', '#db2777'],
    slate: ['#f1f5f9', '#475569'],
    gray: ['var(--gray-100)', 'var(--gray-500)']
  };
  const [bg, fg] = colorMap[color] || colorMap.gray;
  const [hover, setHover] = React.useState(false);
  return /*#__PURE__*/React.createElement("a", {
    href: href,
    onClick: onClick,
    className: className,
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false),
    style: {
      position: 'relative',
      display: 'flex',
      flexDirection: 'column',
      alignItems: 'center',
      textAlign: 'center',
      gap: '0.5rem',
      background: 'var(--surface-card)',
      border: '1px solid var(--border-default)',
      borderRadius: 'var(--radius-xl)',
      padding: '1rem',
      textDecoration: 'none',
      boxShadow: hover ? 'var(--shadow-md)' : 'none',
      transition: 'box-shadow var(--dur-base) var(--ease-out), border-color var(--dur-fast) var(--ease-out)',
      ...style
    }
  }, badge != null && badge !== 0 && /*#__PURE__*/React.createElement("span", {
    style: {
      position: 'absolute',
      top: 8,
      right: 8,
      minWidth: 18,
      height: 18,
      padding: '0 5px',
      background: 'var(--error)',
      color: '#fff',
      fontSize: 11,
      fontWeight: 700,
      borderRadius: 'var(--radius-full)',
      display: 'inline-flex',
      alignItems: 'center',
      justifyContent: 'center',
      fontFamily: 'var(--font-sans)'
    }
  }, badge), /*#__PURE__*/React.createElement("span", {
    style: {
      background: bg,
      color: fg,
      borderRadius: 'var(--radius-xl)',
      padding: '0.625rem',
      display: 'inline-flex',
      width: 20,
      height: 20,
      alignItems: 'center',
      justifyContent: 'center',
      transform: hover ? 'scale(1.1)' : 'scale(1)',
      transition: 'transform var(--dur-base) var(--ease-out)'
    },
    "aria-hidden": "true"
  }, icon), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: '0.75rem',
      fontWeight: 600,
      color: 'var(--text-strong)',
      lineHeight: 1.2,
      fontFamily: 'var(--font-sans)'
    }
  }, label), sub && /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: '0.7rem',
      color: 'var(--text-faint)',
      lineHeight: 1.2,
      fontFamily: 'var(--font-sans)'
    }
  }, sub));
}
Object.assign(__ds_scope, { DashboardTile });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/admin/DashboardTile.jsx", error: String((e && e.message) || e) }); }

// components/core/Avatar.jsx
try { (() => {
/**
 * CTT Ottignies-Blocry — Avatar
 * Round member avatar with image or initials fallback on a club-blue tint.
 */
function Avatar({
  src,
  name = '',
  size = 40,
  ring = false,
  className = '',
  style = {}
}) {
  const initials = name.split(' ').filter(Boolean).slice(0, 2).map(w => w[0]?.toUpperCase()).join('');
  return /*#__PURE__*/React.createElement("div", {
    className: className,
    style: {
      width: size,
      height: size,
      borderRadius: 'var(--radius-full)',
      background: 'var(--club-blue)',
      color: '#fff',
      display: 'inline-flex',
      alignItems: 'center',
      justifyContent: 'center',
      fontFamily: 'var(--font-sans)',
      fontWeight: 600,
      fontSize: size * 0.4,
      overflow: 'hidden',
      flexShrink: 0,
      userSelect: 'none',
      boxShadow: ring ? '0 0 0 2px var(--white), 0 0 0 4px var(--club-yellow)' : 'none',
      ...style
    },
    title: name || undefined
  }, src ? /*#__PURE__*/React.createElement("img", {
    src: src,
    alt: name,
    style: {
      width: '100%',
      height: '100%',
      objectFit: 'cover'
    }
  }) : initials || '?');
}
Object.assign(__ds_scope, { Avatar });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Avatar.jsx", error: String((e && e.message) || e) }); }

// components/core/Badge.jsx
try { (() => {
/**
 * CTT Ottignies-Blocry — Badge
 * Rounded-full pill used for categories, levels and statuses across the site.
 * `tone` picks a preset palette; `solid` toggles filled vs soft-tinted.
 */
function Badge({
  children,
  tone = 'neutral',
  solid = false,
  size = 'md',
  icon = null,
  className = '',
  style = {}
}) {
  const palettes = {
    // solid fills (categories on the public site)
    primary: {
      solid: ['var(--club-blue)', '#fff'],
      soft: ['#dbeafe', 'var(--club-blue)']
    },
    secondary: {
      solid: ['var(--club-yellow)', 'var(--club-blue)'],
      soft: ['#fef3c7', '#b45309']
    },
    dark: {
      solid: ['var(--gray-800)', '#fff'],
      soft: ['var(--gray-100)', 'var(--gray-800)']
    },
    neutral: {
      solid: ['var(--gray-200)', 'var(--gray-800)'],
      soft: ['var(--gray-100)', 'var(--gray-600)']
    },
    success: {
      solid: ['var(--success)', '#fff'],
      soft: ['#dcfce7', 'var(--success-fg)']
    },
    warning: {
      solid: ['var(--warning)', '#fff'],
      soft: ['#ffedd5', 'var(--warning-fg)']
    },
    error: {
      solid: ['var(--error)', '#fff'],
      soft: ['#fee2e2', 'var(--error-fg)']
    },
    info: {
      solid: ['var(--info)', '#fff'],
      soft: ['#dbeafe', '#1d4ed8']
    }
  };
  const p = palettes[tone] || palettes.neutral;
  const [bg, fg] = solid ? p.solid : p.soft;
  const sizes = {
    sm: {
      fontSize: '0.6875rem',
      padding: '0.15rem 0.55rem'
    },
    md: {
      fontSize: '0.75rem',
      padding: '0.25rem 0.7rem'
    },
    lg: {
      fontSize: '0.8125rem',
      padding: '0.35rem 0.85rem'
    }
  };
  const s = sizes[size] || sizes.md;
  return /*#__PURE__*/React.createElement("span", {
    className: className,
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: '0.3rem',
      background: bg,
      color: fg,
      fontFamily: 'var(--font-sans)',
      fontWeight: 600,
      borderRadius: 'var(--radius-full)',
      whiteSpace: 'nowrap',
      ...s,
      ...style
    }
  }, icon && /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'inline-flex',
      width: '0.85em',
      height: '0.85em'
    },
    "aria-hidden": "true"
  }, icon), children);
}
Object.assign(__ds_scope, { Badge });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Badge.jsx", error: String((e && e.message) || e) }); }

// components/core/Button.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * CTT Ottignies-Blocry — Button
 * Brand button. Primary = club blue, Secondary = club yellow (dark text),
 * matching the marketing CTAs and daisyUI btn-primary in the app.
 */
function Button({
  children,
  variant = 'primary',
  size = 'md',
  icon = null,
  iconRight = false,
  disabled = false,
  as = 'button',
  href,
  onClick,
  type = 'button',
  className = '',
  style = {},
  ...rest
}) {
  const sizes = {
    sm: {
      fontSize: '0.8125rem',
      padding: '0.4rem 0.85rem',
      gap: '0.35rem',
      icon: 15
    },
    md: {
      fontSize: '0.9375rem',
      padding: '0.6rem 1.15rem',
      gap: '0.45rem',
      icon: 17
    },
    lg: {
      fontSize: '1.0625rem',
      padding: '0.85rem 1.75rem',
      gap: '0.55rem',
      icon: 20
    }
  };
  const s = sizes[size] || sizes.md;
  const variants = {
    primary: {
      background: 'var(--club-blue)',
      color: '#fff',
      border: '1px solid var(--club-blue)',
      '--hover-bg': 'var(--club-blue-light)',
      '--hover-bd': 'var(--club-blue-light)'
    },
    secondary: {
      background: 'var(--club-yellow)',
      color: 'var(--club-blue)',
      border: '1px solid var(--club-yellow)',
      '--hover-bg': 'var(--club-yellow-light)',
      '--hover-bd': 'var(--club-yellow-light)'
    },
    outline: {
      background: 'transparent',
      color: 'var(--club-blue)',
      border: '1px solid var(--border-strong)',
      '--hover-bg': 'var(--gray-50)',
      '--hover-bd': 'var(--club-blue)'
    },
    ghost: {
      background: 'transparent',
      color: 'var(--text-body)',
      border: '1px solid transparent',
      '--hover-bg': 'var(--gray-100)',
      '--hover-bd': 'transparent'
    },
    danger: {
      background: 'var(--error)',
      color: '#fff',
      border: '1px solid var(--error)',
      '--hover-bg': 'var(--error-fg)',
      '--hover-bd': 'var(--error-fg)'
    }
  };
  const v = variants[variant] || variants.primary;
  const baseStyle = {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: iconRight ? 'row-reverse' : 'row',
    gap: s.gap,
    fontFamily: 'var(--font-sans)',
    fontWeight: 600,
    fontSize: s.fontSize,
    lineHeight: 1.1,
    padding: s.padding,
    borderRadius: 'var(--radius-field)',
    cursor: disabled ? 'not-allowed' : 'pointer',
    textDecoration: 'none',
    whiteSpace: 'nowrap',
    userSelect: 'none',
    transition: 'background var(--dur-fast) var(--ease-out), border-color var(--dur-fast) var(--ease-out), transform var(--dur-base) var(--ease-out)',
    opacity: disabled ? 0.5 : 1,
    ...v,
    ...style
  };
  const onEnter = e => {
    if (disabled) return;
    e.currentTarget.style.background = v['--hover-bg'];
    e.currentTarget.style.borderColor = v['--hover-bd'];
  };
  const onLeave = e => {
    if (disabled) return;
    e.currentTarget.style.background = v.background;
    e.currentTarget.style.borderColor = v.border.split(' ').pop();
  };
  const iconEl = icon ? /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'inline-flex',
      width: s.icon,
      height: s.icon
    },
    "aria-hidden": "true"
  }, icon) : null;
  const content = /*#__PURE__*/React.createElement(React.Fragment, null, iconEl, children && /*#__PURE__*/React.createElement("span", null, children));
  if (as === 'a') {
    return /*#__PURE__*/React.createElement("a", _extends({
      href: href,
      className: className,
      style: baseStyle,
      onMouseEnter: onEnter,
      onMouseLeave: onLeave,
      onClick: onClick
    }, rest), content);
  }
  return /*#__PURE__*/React.createElement("button", _extends({
    type: type,
    className: className,
    style: baseStyle,
    disabled: disabled,
    onMouseEnter: onEnter,
    onMouseLeave: onLeave,
    onClick: onClick
  }, rest), content);
}
Object.assign(__ds_scope, { Button });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Button.jsx", error: String((e && e.message) || e) }); }

// components/core/Card.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * CTT Ottignies-Blocry — Card
 * The workhorse surface: white, 1px gray border, subtle hover. The border
 * brightens to club-blue on hover (public site) — toggle with `hoverable`.
 * `accent` adds a colored top bar (featured events) or left border (schedules).
 */
function Card({
  children,
  hoverable = false,
  accent = null,
  // color string for a top accent bar
  accentSide = 'top',
  // 'top' | 'left'
  padding = '1.5rem',
  radius = 'var(--radius-2xl)',
  className = '',
  style = {},
  ...rest
}) {
  const [hover, setHover] = React.useState(false);
  const accentBar = accent && accentSide === 'left' ? {
    borderLeft: `4px solid ${accent}`
  } : {};
  return /*#__PURE__*/React.createElement("div", _extends({
    className: className,
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false),
    style: {
      background: 'var(--surface-card)',
      border: '1px solid var(--border-default)',
      borderColor: hoverable && hover ? 'var(--border-hover)' : 'var(--border-default)',
      borderRadius: radius,
      boxShadow: hoverable && hover ? 'var(--shadow-md)' : 'var(--shadow-sm)',
      overflow: 'hidden',
      transition: 'border-color var(--dur-fast) var(--ease-out), box-shadow var(--dur-base) var(--ease-out)',
      ...accentBar,
      ...style
    }
  }, rest), accent && accentSide === 'top' && /*#__PURE__*/React.createElement("div", {
    style: {
      height: 4,
      background: accent
    },
    "aria-hidden": "true"
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      padding
    }
  }, children));
}
Object.assign(__ds_scope, { Card });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Card.jsx", error: String((e && e.message) || e) }); }

// components/core/Input.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * CTT Ottignies-Blocry — Input
 * Text field matching the app forms: white, gray border, club-blue focus ring.
 * Supports label, optional icon, hint and error states.
 */
function Input({
  label,
  type = 'text',
  placeholder = '',
  value,
  defaultValue,
  onChange,
  icon = null,
  hint,
  error,
  required = false,
  disabled = false,
  id,
  name,
  className = '',
  style = {},
  ...rest
}) {
  const [focus, setFocus] = React.useState(false);
  const inputId = id || name || (label ? label.toLowerCase().replace(/\s+/g, '-') : undefined);
  const hasError = Boolean(error);
  return /*#__PURE__*/React.createElement("div", {
    className: className,
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: '0.375rem',
      fontFamily: 'var(--font-sans)',
      ...style
    }
  }, label && /*#__PURE__*/React.createElement("label", {
    htmlFor: inputId,
    style: {
      fontSize: '0.875rem',
      fontWeight: 600,
      color: 'var(--text-strong)'
    }
  }, label, required && /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--error)',
      marginLeft: 2
    }
  }, "*")), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative',
      display: 'flex',
      alignItems: 'center'
    }
  }, icon && /*#__PURE__*/React.createElement("span", {
    style: {
      position: 'absolute',
      left: 12,
      width: 18,
      height: 18,
      color: 'var(--text-faint)',
      display: 'inline-flex',
      pointerEvents: 'none'
    },
    "aria-hidden": "true"
  }, icon), /*#__PURE__*/React.createElement("input", _extends({
    id: inputId,
    name: name,
    type: type,
    placeholder: placeholder,
    value: value,
    defaultValue: defaultValue,
    onChange: onChange,
    required: required,
    disabled: disabled,
    onFocus: () => setFocus(true),
    onBlur: () => setFocus(false),
    style: {
      width: '100%',
      boxSizing: 'border-box',
      font: 'var(--font-body)',
      color: 'var(--text-strong)',
      padding: icon ? '0.6rem 0.85rem 0.6rem 2.35rem' : '0.6rem 0.85rem',
      background: disabled ? 'var(--gray-50)' : 'var(--white)',
      border: `1px solid ${hasError ? 'var(--error)' : focus ? 'var(--club-blue)' : 'var(--border-strong)'}`,
      borderRadius: 'var(--radius-field)',
      outline: 'none',
      boxShadow: focus ? hasError ? '0 0 0 3px rgba(241,87,108,.12)' : 'var(--shadow-focus)' : 'none',
      transition: 'border-color var(--dur-fast) var(--ease-out), box-shadow var(--dur-fast) var(--ease-out)'
    }
  }, rest))), hasError ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: '0.8125rem',
      color: 'var(--error-fg)'
    }
  }, error) : hint ? /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: '0.8125rem',
      color: 'var(--text-muted)'
    }
  }, hint) : null);
}
Object.assign(__ds_scope, { Input });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Input.jsx", error: String((e && e.message) || e) }); }

// components/public/NewsCard.jsx
try { (() => {
/**
 * CTT Ottignies-Blocry — NewsCard
 * Public article/news card: 16:9 image, category pill, date, title, excerpt
 * and a "Lire la suite" link. Border brightens to club-blue on hover; the
 * image zooms slightly. Mirrors components/public/news-card.blade.php.
 */
const CATEGORY_TONE = {
  'Compétition': {
    tone: 'primary',
    solid: true
  },
  'Formation': {
    tone: 'secondary',
    solid: true
  },
  'Tournoi': {
    tone: 'primary',
    solid: true
  }
};
function NewsCard({
  image,
  category = 'Actualité',
  date,
  title,
  excerpt,
  href = '#',
  className = '',
  style = {}
}) {
  const [hover, setHover] = React.useState(false);
  const cat = CATEGORY_TONE[category] || {
    tone: 'neutral',
    solid: false
  };
  return /*#__PURE__*/React.createElement("article", {
    className: className,
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false),
    style: {
      background: 'var(--surface-card)',
      border: '1px solid var(--border-default)',
      borderColor: hover ? 'var(--border-hover)' : 'var(--border-default)',
      borderRadius: 'var(--radius-lg)',
      overflow: 'hidden',
      transition: 'border-color var(--dur-fast) var(--ease-out)',
      fontFamily: 'var(--font-sans)',
      display: 'flex',
      flexDirection: 'column',
      ...style
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      aspectRatio: '16 / 9',
      background: 'var(--gray-100)',
      overflow: 'hidden'
    }
  }, image && /*#__PURE__*/React.createElement("img", {
    src: image,
    alt: title,
    style: {
      width: '100%',
      height: '100%',
      objectFit: 'cover',
      transform: hover ? 'scale(1.05)' : 'scale(1)',
      transition: 'transform var(--dur-slow) var(--ease-out)'
    }
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: '1.5rem',
      display: 'flex',
      flexDirection: 'column',
      flex: 1
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      marginBottom: '0.75rem'
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Badge, {
    tone: cat.tone,
    solid: cat.solid,
    size: "sm"
  }, category), date && /*#__PURE__*/React.createElement("time", {
    style: {
      fontSize: '0.875rem',
      color: 'var(--text-muted)'
    }
  }, date)), /*#__PURE__*/React.createElement("h3", {
    style: {
      margin: 0,
      fontSize: '1.25rem',
      fontWeight: 700,
      lineHeight: 1.25,
      color: hover ? 'var(--club-blue)' : 'var(--text-strong)',
      transition: 'color var(--dur-fast) var(--ease-out)'
    }
  }, /*#__PURE__*/React.createElement("a", {
    href: href,
    style: {
      color: 'inherit',
      textDecoration: 'none'
    }
  }, title)), excerpt && /*#__PURE__*/React.createElement("p", {
    style: {
      margin: '0.75rem 0 1rem',
      color: 'var(--text-body)',
      fontSize: '0.95rem',
      lineHeight: 1.55,
      display: '-webkit-box',
      WebkitLineClamp: 3,
      WebkitBoxOrient: 'vertical',
      overflow: 'hidden'
    }
  }, excerpt), /*#__PURE__*/React.createElement("a", {
    href: href,
    style: {
      marginTop: 'auto',
      color: 'var(--club-blue)',
      fontWeight: 600,
      fontSize: '0.875rem',
      display: 'inline-flex',
      alignItems: 'center',
      gap: '0.3rem',
      textDecoration: 'none'
    }
  }, "Lire la suite", /*#__PURE__*/React.createElement("svg", {
    width: "14",
    height: "14",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: "2",
    viewBox: "0 0 24 24",
    "aria-hidden": "true"
  }, /*#__PURE__*/React.createElement("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    d: "M9 5l7 7-7 7"
  })))));
}
Object.assign(__ds_scope, { NewsCard });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/public/NewsCard.jsx", error: String((e && e.message) || e) }); }

// ui_kits/club-admin/AdminApp.jsx
try { (() => {
// CTT Ottignies-Blocry — Club admin (back office) recreation.
// daisyUI "bumblebee" surfaces + maryUI-style sidebar. Composes the DS bundle.
(function () {
  const DS = window.CTTOttigniesBlocryDesignSystem_a28edf;
  const {
    Badge,
    Avatar,
    Button,
    DashboardTile
  } = DS;
  const Icon = window.Icon;
  const e = React.createElement;

  // ----------------------------------------------------------------- Shell
  const NAV = [{
    section: null,
    items: [{
      id: 'dashboard',
      icon: 'home',
      label: 'Tableau de bord'
    }, {
      id: 'notifications',
      icon: 'bell',
      label: 'Notifications',
      badge: 3
    }]
  }, {
    section: 'Membres',
    items: [{
      id: 'members',
      icon: 'users',
      label: 'Utilisateurs'
    }, {
      id: 'registrations',
      icon: 'cash',
      label: 'Affiliations'
    }]
  }, {
    section: 'Interclubs',
    items: [{
      id: 'teams',
      icon: 'userGroup',
      label: 'Nos équipes'
    }, {
      id: 'results',
      icon: 'trophy',
      label: 'Résultats'
    }, {
      id: 'planning',
      icon: 'calendar',
      label: 'Calendrier'
    }]
  }, {
    section: 'Site web',
    items: [{
      id: 'articles',
      icon: 'newspaper',
      label: 'Articles'
    }, {
      id: 'contacts',
      icon: 'mail',
      label: 'Contacts'
    }]
  }];
  function Shell({
    active,
    onNav,
    children,
    title,
    crumbs
  }) {
    return e('div', {
      style: {
        display: 'flex',
        minHeight: '100vh',
        background: 'var(--base-200)',
        fontFamily: 'var(--font-sans)'
      }
    },
    // Sidebar
    e('aside', {
      style: {
        width: 252,
        flexShrink: 0,
        background: 'var(--base-100)',
        borderRight: '1px solid var(--base-300)',
        display: 'flex',
        flexDirection: 'column',
        position: 'sticky',
        top: 0,
        height: '100vh',
        overflowY: 'auto'
      }
    }, e('div', {
      style: {
        display: 'flex',
        alignItems: 'center',
        gap: 10,
        padding: '18px 20px'
      }
    }, e('img', {
      src: '../../assets/logo-club.svg',
      alt: 'CTT',
      style: {
        height: 30
      }
    }), e('div', {
      style: {
        lineHeight: 1.05
      }
    }, e('div', {
      style: {
        fontSize: 15,
        fontWeight: 700,
        color: 'var(--club-blue)'
      }
    }, 'CTT Ottignies'), e('div', {
      style: {
        fontSize: 10,
        color: 'var(--text-faint)',
        letterSpacing: '.1em',
        textTransform: 'uppercase'
      }
    }, 'Espace club'))),
    // user
    e('div', {
      style: {
        margin: '4px 12px 8px',
        padding: '10px 12px',
        background: 'var(--base-200)',
        borderRadius: 'var(--radius-lg)',
        display: 'flex',
        alignItems: 'center',
        gap: 10
      }
    }, e(Avatar, {
      name: 'Aurélien Paulus',
      size: 36
    }), e('div', {
      style: {
        minWidth: 0
      }
    }, e('div', {
      style: {
        fontSize: 13,
        fontWeight: 700,
        whiteSpace: 'nowrap',
        overflow: 'hidden',
        textOverflow: 'ellipsis'
      }
    }, 'Aurélien Paulus'), e('div', {
      style: {
        fontSize: 11,
        color: 'var(--text-faint)'
      }
    }, 'Administrateur'))), e('nav', {
      style: {
        padding: '4px 12px 20px',
        display: 'flex',
        flexDirection: 'column',
        gap: 2
      }
    }, NAV.map((grp, gi) => e(React.Fragment, {
      key: gi
    }, grp.section && e('div', {
      style: {
        fontSize: 10,
        fontWeight: 700,
        letterSpacing: '.1em',
        textTransform: 'uppercase',
        color: 'var(--text-faint)',
        padding: '14px 12px 6px'
      }
    }, grp.section), grp.items.map(it => {
      const on = active === it.id;
      return e('a', {
        key: it.id,
        href: '#',
        onClick: ev => {
          ev.preventDefault();
          onNav(it.id);
        },
        style: {
          display: 'flex',
          alignItems: 'center',
          gap: 11,
          padding: '8px 12px',
          borderRadius: 'var(--radius-lg)',
          textDecoration: 'none',
          fontSize: 13.5,
          fontWeight: on ? 600 : 500,
          color: on ? 'var(--club-blue)' : 'var(--text-body)',
          background: on ? '#eef2ff' : 'transparent'
        }
      }, e(Icon, {
        name: it.icon,
        size: 18
      }), e('span', {
        style: {
          flex: 1
        }
      }, it.label), it.badge && e('span', {
        style: {
          background: 'var(--error)',
          color: '#fff',
          fontSize: 10,
          fontWeight: 700,
          minWidth: 16,
          height: 16,
          borderRadius: 8,
          display: 'inline-flex',
          alignItems: 'center',
          justifyContent: 'center',
          padding: '0 4px'
        }
      }, it.badge));
    }))))),
    // Main
    e('div', {
      style: {
        flex: 1,
        minWidth: 0,
        display: 'flex',
        flexDirection: 'column'
      }
    }, e('header', {
      style: {
        height: 60,
        background: 'var(--base-100)',
        borderBottom: '1px solid var(--base-300)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        padding: '0 28px',
        position: 'sticky',
        top: 0,
        zIndex: 10
      }
    }, e('div', null, e('div', {
      style: {
        fontSize: 11,
        color: 'var(--text-faint)'
      }
    }, crumbs || 'Accueil'), e('div', {
      style: {
        fontSize: 18,
        fontWeight: 700
      }
    }, title)), e('div', {
      style: {
        display: 'flex',
        alignItems: 'center',
        gap: 14
      }
    }, e('div', {
      style: {
        display: 'flex',
        alignItems: 'center',
        gap: 8,
        background: 'var(--base-200)',
        borderRadius: 'var(--radius-full)',
        padding: '7px 14px',
        color: 'var(--text-muted)',
        fontSize: 13
      }
    }, e(Icon, {
      name: 'search',
      size: 16
    }), e('span', null, 'Rechercher…')), e('button', {
      style: {
        position: 'relative',
        background: 'none',
        border: 'none',
        cursor: 'pointer',
        color: 'var(--text-body)'
      }
    }, e(Icon, {
      name: 'bell',
      size: 20
    }), e('span', {
      style: {
        position: 'absolute',
        top: -2,
        right: -2,
        width: 8,
        height: 8,
        background: 'var(--error)',
        borderRadius: '50%'
      }
    })), e(Avatar, {
      name: 'Aurélien Paulus',
      size: 34
    }))), e('main', {
      style: {
        padding: 28,
        flex: 1
      }
    }, children)));
  }

  // ----------------------------------------------------------------- Dashboard
  function Dashboard() {
    const stats = [{
      label: 'Membres actifs',
      value: '142',
      delta: '+8',
      up: true,
      color: 'var(--club-blue)'
    }, {
      label: 'Cotisations payées',
      value: '118',
      delta: '83%',
      up: true,
      color: '#10b981'
    }, {
      label: 'Équipes interclubs',
      value: '6',
      delta: '2 div.',
      up: true,
      color: '#f59e0b'
    }, {
      label: 'Contacts en attente',
      value: '4',
      delta: 'à traiter',
      up: false,
      color: '#f1576c'
    }];
    const tiles = [{
      icon: 'users',
      label: 'Membres',
      sub: '142 actifs',
      color: 'blue'
    }, {
      icon: 'cash',
      label: 'Trésorerie',
      sub: '€ 4 280',
      color: 'emerald'
    }, {
      icon: 'userGroup',
      label: 'Sélections',
      sub: 'Semaine 12',
      color: 'indigo'
    }, {
      icon: 'trophy',
      label: 'Tournois',
      sub: '2 à venir',
      color: 'amber'
    }, {
      icon: 'newspaper',
      label: 'Articles',
      sub: '18 publiés',
      color: 'violet'
    }, {
      icon: 'academicCap',
      label: 'Entraînements',
      sub: '4 / semaine',
      color: 'teal'
    }];
    const activity = [{
      who: 'Marie Dubois',
      what: 'a payé sa cotisation 2026',
      when: 'il y a 12 min',
      tone: 'success',
      tag: 'Paiement'
    }, {
      who: 'Équipe A',
      what: 'a remporté le match contre Wavre',
      when: 'il y a 2 h',
      tone: 'primary',
      tag: 'Interclub'
    }, {
      who: 'Tom Vasseur',
      what: 's\u2019est inscrit au tournoi de printemps',
      when: 'il y a 5 h',
      tone: 'secondary',
      tag: 'Tournoi'
    }, {
      who: 'Nouveau contact',
      what: 'a envoyé un message via le site',
      when: 'hier',
      tone: 'info',
      tag: 'Site web'
    }];
    return e('div', {
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: 24
      }
    },
    // stats
    e('div', {
      style: {
        display: 'grid',
        gridTemplateColumns: 'repeat(4,1fr)',
        gap: 16
      }
    }, stats.map((s, i) => e('div', {
      key: i,
      style: {
        background: 'var(--base-100)',
        border: '1px solid var(--base-300)',
        borderRadius: 'var(--radius-xl)',
        padding: 18
      }
    }, e('div', {
      style: {
        fontSize: 13,
        color: 'var(--text-muted)',
        marginBottom: 6
      }
    }, s.label), e('div', {
      style: {
        display: 'flex',
        alignItems: 'baseline',
        gap: 8
      }
    }, e('div', {
      style: {
        fontSize: 30,
        fontWeight: 700,
        color: s.color
      }
    }, s.value), e('span', {
      style: {
        fontSize: 12,
        fontWeight: 600,
        color: s.up ? 'var(--success-fg)' : 'var(--text-faint)'
      }
    }, s.delta))))),
    // quick actions
    e(Panel, {
      title: 'Accès rapides'
    }, e('div', {
      style: {
        display: 'grid',
        gridTemplateColumns: 'repeat(6,1fr)',
        gap: 14
      }
    }, tiles.map((t, i) => e(DashboardTile, {
      key: i,
      ...t,
      icon: e(Icon, {
        name: t.icon,
        size: 20
      })
    })))),
    // two col: activity + next matches
    e('div', {
      style: {
        display: 'grid',
        gridTemplateColumns: '1.4fr 1fr',
        gap: 20
      }
    }, e(Panel, {
      title: 'Activité récente'
    }, e('div', {
      style: {
        display: 'flex',
        flexDirection: 'column'
      }
    }, activity.map((a, i) => e('div', {
      key: i,
      style: {
        display: 'flex',
        alignItems: 'center',
        gap: 12,
        padding: '12px 0',
        borderTop: i ? '1px solid var(--base-300)' : 'none'
      }
    }, e(Avatar, {
      name: a.who,
      size: 36
    }), e('div', {
      style: {
        flex: 1,
        minWidth: 0
      }
    }, e('div', {
      style: {
        fontSize: 14
      }
    }, e('b', null, a.who), ' ', a.what), e('div', {
      style: {
        fontSize: 12,
        color: 'var(--text-faint)'
      }
    }, a.when)), e(Badge, {
      tone: a.tone,
      size: 'sm'
    }, a.tag))))), e(Panel, {
      title: 'Prochains matchs'
    }, e('div', {
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: 10
      }
    }, [['A', 'CTT Ottignies A', 'TT Wavre', 'sam. 21/06'], ['B', 'Jette', 'CTT Ottignies B', 'dim. 22/06'], ['V', 'CTT Ottignies V', 'Braine', 'sam. 28/06']].map((m, i) => e('div', {
      key: i,
      style: {
        display: 'flex',
        alignItems: 'center',
        gap: 12,
        padding: '10px 12px',
        background: 'var(--base-200)',
        borderRadius: 'var(--radius-lg)'
      }
    }, e('div', {
      style: {
        width: 30,
        height: 30,
        borderRadius: '50%',
        background: '#dbeafe',
        color: 'var(--club-blue)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        fontWeight: 700,
        fontSize: 13,
        flexShrink: 0
      }
    }, m[0]), e('div', {
      style: {
        flex: 1,
        fontSize: 13
      }
    }, e('div', {
      style: {
        fontWeight: 600
      }
    }, m[1] + ' vs ' + m[2])), e('span', {
      style: {
        fontSize: 12,
        color: 'var(--text-muted)',
        whiteSpace: 'nowrap'
      }
    }, m[3])))))));
  }
  function Panel({
    title,
    action,
    children
  }) {
    return e('section', {
      style: {
        background: 'var(--base-100)',
        border: '1px solid var(--base-300)',
        borderRadius: 'var(--radius-xl)',
        padding: 20
      }
    }, e('div', {
      style: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: 16
      }
    }, e('h3', {
      style: {
        margin: 0,
        fontSize: 15,
        fontWeight: 700
      }
    }, title), action || null), children);
  }

  // ------------------------------------------------------------------- Members
  function Members() {
    const rows = [{
      name: 'Aurélien Paulus',
      email: 'aurelien@ctt.be',
      cat: 'Senior',
      idx: 'C2',
      paid: true,
      role: 'Admin'
    }, {
      name: 'Marie Dubois',
      email: 'marie.dubois@ctt.be',
      cat: 'Senior',
      idx: 'D4',
      paid: true,
      role: 'Joueuse'
    }, {
      name: 'Tom Vasseur',
      email: 'tom.v@ctt.be',
      cat: 'Jeune',
      idx: 'E0',
      paid: false,
      role: 'Joueur'
    }, {
      name: 'Sophie Lambert',
      email: 's.lambert@ctt.be',
      cat: 'Vétéran',
      idx: 'C4',
      paid: true,
      role: 'Capitaine'
    }, {
      name: 'Lucas Martin',
      email: 'lucas.m@ctt.be',
      cat: 'Senior',
      idx: 'B6',
      paid: true,
      role: 'Joueur'
    }, {
      name: 'Emma Renard',
      email: 'emma.r@ctt.be',
      cat: 'Jeune',
      idx: 'NC',
      paid: false,
      role: 'Joueuse'
    }];
    return e('div', {
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: 18
      }
    }, e('div', {
      style: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: 16,
        flexWrap: 'wrap'
      }
    }, e('div', {
      style: {
        display: 'flex',
        alignItems: 'center',
        gap: 8,
        background: 'var(--base-100)',
        border: '1px solid var(--base-300)',
        borderRadius: 'var(--radius-field)',
        padding: '8px 14px',
        color: 'var(--text-muted)',
        fontSize: 14,
        minWidth: 280
      }
    }, e(Icon, {
      name: 'search',
      size: 16
    }), e('span', null, 'Rechercher un membre…')), e('div', {
      style: {
        display: 'flex',
        gap: 8
      }
    }, e(Button, {
      variant: 'outline',
      size: 'sm'
    }, 'Filtres'), e(Button, {
      variant: 'primary',
      size: 'sm',
      icon: e(Icon, {
        name: 'plus',
        size: 15
      })
    }, 'Nouveau membre'))), e('div', {
      style: {
        background: 'var(--base-100)',
        border: '1px solid var(--base-300)',
        borderRadius: 'var(--radius-xl)',
        overflow: 'hidden'
      }
    }, e('table', {
      style: {
        width: '100%',
        borderCollapse: 'collapse',
        fontSize: 14
      }
    }, e('thead', null, e('tr', {
      style: {
        background: 'var(--base-200)',
        textAlign: 'left'
      }
    }, ['Membre', 'Catégorie', 'Classement', 'Rôle', 'Cotisation', ''].map((h, i) => e('th', {
      key: i,
      style: {
        padding: '11px 18px',
        fontSize: 11,
        fontWeight: 700,
        letterSpacing: '.05em',
        textTransform: 'uppercase',
        color: 'var(--text-faint)'
      }
    }, h)))), e('tbody', null, rows.map((r, i) => e('tr', {
      key: i,
      style: {
        borderTop: '1px solid var(--base-300)'
      }
    }, e('td', {
      style: {
        padding: '12px 18px'
      }
    }, e('div', {
      style: {
        display: 'flex',
        alignItems: 'center',
        gap: 10
      }
    }, e(Avatar, {
      name: r.name,
      size: 34
    }), e('div', null, e('div', {
      style: {
        fontWeight: 600
      }
    }, r.name), e('div', {
      style: {
        fontSize: 12,
        color: 'var(--text-faint)'
      }
    }, r.email)))), e('td', {
      style: {
        padding: '12px 18px'
      }
    }, e(Badge, {
      tone: 'neutral',
      size: 'sm'
    }, r.cat)), e('td', {
      style: {
        padding: '12px 18px',
        fontWeight: 600,
        fontVariantNumeric: 'tabular-nums'
      }
    }, r.idx), e('td', {
      style: {
        padding: '12px 18px',
        color: 'var(--text-body)'
      }
    }, r.role), e('td', {
      style: {
        padding: '12px 18px'
      }
    }, e(Badge, {
      tone: r.paid ? 'success' : 'warning',
      size: 'sm'
    }, r.paid ? 'Payée' : 'En attente')), e('td', {
      style: {
        padding: '12px 18px',
        textAlign: 'right'
      }
    }, e('div', {
      style: {
        display: 'inline-flex',
        gap: 6,
        color: 'var(--text-faint)'
      }
    }, e(Icon, {
      name: 'eye',
      size: 17
    }), e(Icon, {
      name: 'pencil',
      size: 17
    })))))))));
  }

  // --------------------------------------------------------------------- Teams
  function Teams() {
    const teams = [{
      letter: 'A',
      name: 'CTT Ottignies A',
      div: 'Division 2 — Nationale',
      cat: 'Hommes',
      captain: 'Aurélien Paulus',
      count: 5,
      next: 'sam. 21 juin'
    }, {
      letter: 'B',
      name: 'CTT Ottignies B',
      div: 'Division 4 — Provinciale',
      cat: 'Hommes',
      captain: 'Lucas Martin',
      count: 4,
      next: 'dim. 22 juin'
    }, {
      letter: 'V',
      name: 'CTT Ottignies V',
      div: 'Vétérans — Série 1',
      cat: 'Vétérans',
      captain: 'Sophie Lambert',
      count: 4,
      next: 'sam. 28 juin'
    }, {
      letter: 'D',
      name: 'CTT Ottignies D',
      div: 'Dames — Série 2',
      cat: 'Dames',
      captain: '—',
      count: 3,
      next: null
    }];
    const palette = {
      Hommes: ['#eff6ff', 'var(--club-blue)', '#dbeafe'],
      Vétérans: ['#fffbeb', '#b45309', '#fef3c7'],
      Dames: ['#fdf2f8', '#be185d', '#fce7f3']
    };
    return e('div', {
      style: {
        display: 'grid',
        gridTemplateColumns: 'repeat(2,1fr)',
        gap: 18
      }
    }, teams.map((t, i) => {
      const [bg, fg, dot] = palette[t.cat] || ['var(--base-200)', 'var(--text-body)', 'var(--base-300)'];
      return e('div', {
        key: i,
        style: {
          background: 'var(--base-100)',
          border: '1px solid var(--base-300)',
          borderRadius: 'var(--radius-xl)',
          overflow: 'hidden'
        }
      }, e('div', {
        style: {
          display: 'flex',
          alignItems: 'center',
          gap: 12,
          padding: '14px 18px',
          background: bg
        }
      }, e('div', {
        style: {
          width: 40,
          height: 40,
          borderRadius: '50%',
          background: dot,
          color: fg,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          fontWeight: 700,
          fontSize: 18
        }
      }, t.letter), e('div', null, e('div', {
        style: {
          fontWeight: 700,
          color: fg
        }
      }, t.name), e('div', {
        style: {
          fontSize: 12,
          color: fg,
          opacity: .8
        }
      }, t.div))), e('div', {
        style: {
          padding: '4px 18px'
        }
      }, [['Capitaine', t.captain === '—' ? null : t.captain], ['Noyau', t.count + ' joueur' + (t.count > 1 ? 's' : '')], ['Prochain match', t.next]].map((row, j) => e('div', {
        key: j,
        style: {
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          padding: '10px 0',
          borderTop: j ? '1px solid var(--base-300)' : 'none'
        }
      }, e('span', {
        style: {
          fontSize: 11,
          fontWeight: 600,
          letterSpacing: '.04em',
          textTransform: 'uppercase',
          color: 'var(--text-faint)'
        }
      }, row[0]), row[1] ? e('span', {
        style: {
          fontSize: 14,
          fontWeight: 500
        }
      }, row[1]) : e('span', {
        style: {
          fontSize: 13,
          fontStyle: 'italic',
          color: 'var(--text-faint)'
        }
      }, 'Non défini')))), e('div', {
        style: {
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          padding: '10px 14px',
          background: 'var(--base-200)',
          borderTop: '1px solid var(--base-300)'
        }
      }, e('div', {
        style: {
          display: 'flex',
          gap: 4,
          color: 'var(--text-faint)'
        }
      }, e(Icon, {
        name: 'eye',
        size: 17
      }), e(Icon, {
        name: 'pencil',
        size: 17
      })), e(Badge, {
        tone: t.cat === 'Hommes' ? 'primary' : t.cat === 'Vétérans' ? 'warning' : 'error',
        size: 'sm'
      }, t.cat)));
    }));
  }
  window.AdminApp = {
    Shell,
    Dashboard,
    Members,
    Teams
  };
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/club-admin/AdminApp.jsx", error: String((e && e.message) || e) }); }

// ui_kits/public-website/PublicSite.jsx
try { (() => {
// CTT Ottignies-Blocry — Public website sections (recreation of the Laravel/Blade marketing site)
// Composes the design-system bundle + window.Icon. Exports sections to window.
(function () {
  const DS = window.CTTOttigniesBlocryDesignSystem_a28edf;
  const {
    Button,
    Badge,
    NewsCard,
    Card
  } = DS;
  const Icon = window.Icon;
  const e = React.createElement;
  const CONTAINER = {
    maxWidth: 1180,
    margin: '0 auto',
    padding: '0 24px'
  };

  // ---------------------------------------------------------------- Navigation
  function Nav({
    onNav,
    active = 'home'
  }) {
    const [open, setOpen] = React.useState(false);
    const links = [['home', 'Accueil'], ['results', 'Résultats'], ['events', 'Événements'], ['news', 'Nouvelles'], ['contact', 'Contact']];
    return e('nav', {
      style: {
        position: 'sticky',
        top: 0,
        zIndex: 50,
        width: '100%',
        background: 'rgba(255,255,255,0.95)',
        backdropFilter: 'blur(6px)',
        boxShadow: 'var(--shadow-xs)',
        borderBottom: '1px solid var(--border-default)'
      }
    }, e('div', {
      style: {
        ...CONTAINER,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        height: 64
      }
    }, e('a', {
      href: '#',
      onClick: ev => {
        ev.preventDefault();
        onNav('home');
      },
      style: {
        display: 'flex',
        alignItems: 'center',
        gap: 10,
        textDecoration: 'none'
      }
    }, e('img', {
      src: '../../assets/logo-club.svg',
      alt: 'CTT',
      style: {
        height: 34,
        width: 'auto'
      }
    }), e('span', {
      style: {
        fontSize: 20,
        fontWeight: 700,
        color: 'var(--club-blue)',
        letterSpacing: '-0.01em'
      }
    }, 'CTT Ottignies-Blocry')), e('div', {
      style: {
        display: 'flex',
        alignItems: 'center',
        gap: 4
      }
    }, links.map(([id, label]) => e('a', {
      key: id,
      href: '#',
      onClick: ev => {
        ev.preventDefault();
        onNav(id);
      },
      style: {
        fontSize: 14,
        fontWeight: 500,
        padding: '8px 12px',
        borderRadius: 6,
        textDecoration: 'none',
        color: active === id ? 'var(--club-blue)' : 'var(--gray-900)'
      }
    }, label)), e('div', {
      style: {
        width: 8
      }
    }), e(Button, {
      variant: 'primary',
      size: 'sm',
      onClick: () => onNav('contact')
    }, 'Rejoindre'), e(Button, {
      variant: 'secondary',
      size: 'sm',
      onClick: () => onNav('login')
    }, 'Connexion'))));
  }

  // ----------------------------------------------------------------------- Hero
  function Hero({
    onNav
  }) {
    return e('section', {
      style: {
        position: 'relative',
        overflow: 'hidden',
        color: '#fff'
      }
    }, e('img', {
      src: '../../assets/images/background_home.webp',
      alt: 'Tennis de table',
      style: {
        position: 'absolute',
        inset: 0,
        width: '100%',
        height: '100%',
        objectFit: 'cover'
      }
    }), e('div', {
      style: {
        position: 'absolute',
        inset: 0,
        background: 'linear-gradient(135deg, rgba(30,64,175,.88), rgba(30,64,175,.82) 50%, rgba(59,130,246,.85))'
      }
    }), e('div', {
      style: {
        ...CONTAINER,
        position: 'relative',
        padding: '110px 24px',
        textAlign: 'center'
      }
    }, e('h1', {
      style: {
        fontSize: 'clamp(40px, 6vw, 72px)',
        fontWeight: 700,
        lineHeight: 1.05,
        margin: 0,
        letterSpacing: '-0.02em',
        textShadow: '0 2px 12px rgba(0,0,0,.25)'
      }
    }, 'CTT Ottignies-Blocry'), e('p', {
      style: {
        fontSize: 'clamp(18px,2.2vw,24px)',
        maxWidth: 720,
        margin: '24px auto 36px',
        opacity: 0.92,
        lineHeight: 1.5
      }
    }, 'Rejoignez notre communauté passionnée de joueurs de tennis de table. Des débutants aux champions, tout le monde est le bienvenu au sein de notre club.'), e('div', {
      style: {
        display: 'flex',
        gap: 16,
        justifyContent: 'center',
        flexWrap: 'wrap'
      }
    }, e(Button, {
      variant: 'secondary',
      size: 'lg',
      onClick: () => onNav('contact')
    }, 'Rejoindre le Club'), e(Button, {
      variant: 'outline',
      size: 'lg',
      onClick: () => onNav('about'),
      style: {
        color: '#fff',
        borderColor: 'rgba(255,255,255,.7)',
        background: 'rgba(255,255,255,.06)'
      }
    }, 'En Savoir Plus'))));
  }

  // ---------------------------------------------------------------------- About
  function SectionHead({
    eyebrow,
    title,
    sub,
    dark
  }) {
    return e('div', {
      style: {
        textAlign: 'center',
        maxWidth: 720,
        margin: '0 auto 56px'
      }
    }, eyebrow && e('div', {
      style: {
        fontSize: 14,
        fontWeight: 700,
        letterSpacing: '.15em',
        textTransform: 'uppercase',
        color: 'var(--club-blue)',
        marginBottom: 12
      }
    }, eyebrow), e('h2', {
      style: {
        fontSize: 'clamp(28px,3.5vw,38px)',
        fontWeight: 700,
        margin: 0,
        color: dark ? '#fff' : 'var(--text-strong)',
        letterSpacing: '-0.01em'
      }
    }, title), sub && e('p', {
      style: {
        fontSize: 20,
        color: dark ? 'rgba(255,255,255,.8)' : 'var(--text-body)',
        marginTop: 16,
        lineHeight: 1.5
      }
    }, sub));
  }
  function About() {
    const feats = [{
      emoji: '🏆',
      bg: 'var(--club-blue)',
      title: 'Excellence compétitive',
      body: 'Participez à des tournois locaux et régionaux avec nos équipes compétitives.'
    }, {
      emoji: '👥',
      bg: 'var(--club-yellow)',
      title: 'Communauté accueillante',
      body: 'Rejoignez des joueurs de tous niveaux dans un environnement amical et solidaire.'
    }, {
      emoji: '🎯',
      bg: 'var(--club-blue)',
      title: 'Coaching professionnel',
      body: 'Apprenez avec des entraîneurs expérimentés et progressez grâce à un encadrement structuré.'
    }];
    return e('section', {
      style: {
        padding: '88px 0',
        background: 'var(--gray-50)'
      }
    }, e('div', {
      style: CONTAINER
    }, e(SectionHead, {
      title: 'Pourquoi choisir le CTT Ottignies-Blocry\u00a0?',
      sub: 'Nous sommes plus qu\u2019un simple club \u2013 nous sommes une communauté dédiée au sport que nous aimons.'
    }), e('div', {
      style: {
        display: 'grid',
        gridTemplateColumns: 'repeat(3, 1fr)',
        gap: 32
      }
    }, feats.map((f, i) => e('div', {
      key: i,
      style: {
        textAlign: 'center',
        padding: 24
      }
    }, e('div', {
      style: {
        width: 64,
        height: 64,
        borderRadius: '50%',
        background: f.bg,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        margin: '0 auto 16px',
        fontSize: 28
      }
    }, f.emoji), e('h3', {
      style: {
        fontSize: 20,
        fontWeight: 600,
        margin: '0 0 8px'
      }
    }, f.title), e('p', {
      style: {
        color: 'var(--text-body)',
        margin: 0,
        lineHeight: 1.55
      }
    }, f.body))))));
  }

  // --------------------------------------------------------------- Featured events
  function Events() {
    const evs = [{
      type: 'TOURNOI',
      bar: 'var(--club-yellow)',
      tone: 'secondary',
      emoji: '🏓',
      title: 'Tournoi interne de printemps',
      date: 'samedi 21 juin 2026',
      time: '13h00',
      loc: 'Centre sportif de Blocry',
      price: '5 €'
    }, {
      type: 'INTERCLUB',
      bar: 'var(--club-blue)',
      tone: 'primary',
      emoji: '🏆',
      title: 'Finale provinciale — Équipe A',
      date: 'dimanche 29 juin 2026',
      time: '10h00',
      loc: 'Wavre',
      price: 'Entrée libre'
    }, {
      type: 'VIE DU CLUB',
      bar: '#10b981',
      tone: 'success',
      emoji: '🍻',
      title: 'Barbecue de fin de saison',
      date: 'vendredi 4 juillet 2026',
      time: '18h30',
      loc: 'Club house',
      price: '12 €'
    }];
    return e('section', {
      style: {
        padding: '88px 0',
        background: 'var(--gray-50)',
        borderTop: '1px solid var(--border-default)'
      }
    }, e('div', {
      style: CONTAINER
    }, e(SectionHead, {
      eyebrow: 'Prochains événements',
      title: 'À ne pas manquer',
      sub: 'Notez ces dates — ces rendez-vous du club valent le déplacement.'
    }), e('div', {
      style: {
        display: 'grid',
        gridTemplateColumns: 'repeat(3,1fr)',
        gap: 24
      }
    }, evs.map((ev, i) => e(Card, {
      key: i,
      accent: ev.bar,
      padding: '0',
      hoverable: true
    }, e('div', {
      style: {
        padding: '20px 24px'
      }
    }, e('div', {
      style: {
        display: 'flex',
        alignItems: 'center',
        gap: 10,
        marginBottom: 16
      }
    }, e('span', {
      style: {
        fontSize: 20
      }
    }, ev.emoji), e(Badge, {
      tone: ev.tone,
      size: 'sm'
    }, ev.type)), e('h3', {
      style: {
        fontSize: 20,
        fontWeight: 700,
        margin: '0 0 16px',
        lineHeight: 1.3
      }
    }, ev.title), e('div', {
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: 8,
        borderTop: '1px solid var(--border-default)',
        paddingTop: 14,
        fontSize: 14,
        color: 'var(--text-body)'
      }
    }, e(MetaRow, {
      icon: 'calendar',
      children: ev.date + ' · ' + ev.time
    }), e(MetaRow, {
      icon: 'mapPin',
      children: ev.loc
    }), e(MetaRow, {
      icon: 'cash',
      children: ev.price
    }))))))));
  }
  function MetaRow({
    icon,
    children
  }) {
    return e('div', {
      style: {
        display: 'flex',
        alignItems: 'center',
        gap: 8
      }
    }, e(Icon, {
      name: icon,
      size: 16,
      style: {
        color: 'var(--text-faint)',
        flexShrink: 0
      }
    }), e('span', null, children));
  }

  // ----------------------------------------------------------------------- News
  function News() {
    const items = [{
      image: '../../assets/images/background_news.webp',
      category: 'Compétition',
      date: '14 juin 2026',
      title: 'Victoire de l\u2019équipe A en D2',
      excerpt: 'Un week-end décisif pour nos joueurs, qui s\u2019imposent 10-6 face à Wavre et consolident leur place dans le haut du classement.'
    }, {
      image: '../../assets/images/background_events.webp',
      category: 'Formation',
      date: '2 juin 2026',
      title: 'Stage jeunes pendant les vacances',
      excerpt: 'Cinq jours d\u2019entraînement encadré pour les 8-14 ans, tous niveaux confondus. Les inscriptions sont désormais ouvertes.'
    }, {
      image: '../../assets/images/background_results.webp',
      category: 'Vie du club',
      date: '20 mai 2026',
      title: 'Assemblée générale 2026',
      excerpt: 'Retour sur une belle saison et présentation des projets pour l\u2019année à venir, suivis du verre de l\u2019amitié.'
    }];
    return e('section', {
      style: {
        padding: '88px 0',
        background: 'var(--white)'
      }
    }, e('div', {
      style: CONTAINER
    }, e(SectionHead, {
      title: 'Dernières nouvelles',
      sub: 'Résultats, événements et vie du club.'
    }), e('div', {
      style: {
        display: 'grid',
        gridTemplateColumns: 'repeat(3,1fr)',
        gap: 24
      }
    }, items.map((it, i) => e(NewsCard, {
      key: i,
      ...it,
      href: '#'
    })))));
  }

  // ------------------------------------------------------------------- Schedule
  function Schedule() {
    const rows = [{
      type: 'Dirigé',
      accent: '#3b82f6',
      tone: 'info',
      day: 'Lundi',
      time: '18h00 – 20h00',
      activity: 'Entraînement dirigé',
      coach: 'Coach Vasseur',
      level: 'Tous niveaux',
      levelTone: 'info'
    }, {
      type: 'Jeunes',
      accent: '#15803d',
      tone: 'success',
      day: 'Mercredi',
      time: '14h00 – 16h00',
      activity: 'École de jeunes',
      coach: 'Coach Marie',
      level: 'Jeunes',
      levelTone: 'success'
    }, {
      type: 'Compétition',
      accent: '#b91c1c',
      tone: 'error',
      day: 'Jeudi',
      time: '19h00 – 22h00',
      activity: 'Préparation interclubs',
      coach: 'Coach Paulus',
      level: 'Compétition',
      levelTone: 'error'
    }, {
      type: 'Libre',
      accent: 'var(--gray-300)',
      tone: 'neutral',
      day: 'Samedi',
      time: '10h00 – 13h00',
      activity: 'Jeu libre',
      coach: null,
      level: 'Tous niveaux',
      levelTone: 'info'
    }];
    return e('section', {
      style: {
        padding: '88px 0',
        background: 'var(--gray-50)'
      }
    }, e('div', {
      style: {
        ...CONTAINER,
        maxWidth: 860
      }
    }, e(SectionHead, {
      title: 'Horaires des entraînements',
      sub: 'Retrouvez-nous au Centre sportif de Blocry tout au long de la semaine.'
    }), e('div', {
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: 12
      }
    }, rows.map((r, i) => e(Card, {
      key: i,
      accent: r.accent,
      accentSide: 'left',
      padding: '16px 20px',
      radius: 'var(--radius-lg)'
    }, e('div', {
      style: {
        display: 'flex',
        alignItems: 'flex-start',
        justifyContent: 'space-between',
        gap: 16
      }
    }, e('div', null, e('div', {
      style: {
        display: 'flex',
        alignItems: 'center',
        gap: 8,
        marginBottom: 4,
        flexWrap: 'wrap'
      }
    }, e(Badge, {
      tone: r.tone,
      size: 'sm'
    }, r.type), e('span', {
      style: {
        fontWeight: 700,
        fontSize: 15
      }
    }, r.day + ' · ' + r.activity)), e('div', {
      style: {
        display: 'flex',
        gap: 14,
        flexWrap: 'wrap',
        fontSize: 14,
        color: 'var(--text-muted)'
      }
    }, e('span', {
      style: {
        fontWeight: 500,
        color: 'var(--text-body)'
      }
    }, r.time), r.coach && e('span', {
      style: {
        display: 'flex',
        alignItems: 'center',
        gap: 4
      }
    }, e(Icon, {
      name: 'users',
      size: 14
    }), r.coach))), e(Badge, {
      tone: r.levelTone,
      size: 'sm'
    }, r.level)))))));
  }

  // -------------------------------------------------------------------- Contact
  function Contact({
    onSubmit
  }) {
    const [sent, setSent] = React.useState(false);
    const info = [{
      icon: 'mapPin',
      bg: 'var(--club-blue)',
      title: 'Adresse',
      lines: ['Centre sportif de Blocry', 'Place des Sports 1', '1348 Ottignies-Louvain-la-Neuve']
    }, {
      icon: 'phone',
      bg: 'var(--club-blue)',
      title: 'Téléphone',
      lines: ['+32 10 12 34 56', 'Lun-Ven : 16h-20h']
    }, {
      icon: 'mail',
      bg: 'var(--club-yellow)',
      title: 'Email',
      lines: ['info@cttottigniesblocry.be', 'Réponse sous 48h']
    }];
    return e('section', {
      style: {
        padding: '88px 0',
        background: 'var(--white)'
      }
    }, e('div', {
      style: {
        ...CONTAINER,
        display: 'grid',
        gridTemplateColumns: '1fr 1fr',
        gap: 48,
        alignItems: 'start'
      }
    }, e('div', null, e('h2', {
      style: {
        fontSize: 38,
        fontWeight: 700,
        margin: '0 0 16px',
        letterSpacing: '-0.01em'
      }
    }, 'Contactez-nous'), e('p', {
      style: {
        fontSize: 20,
        color: 'var(--text-body)',
        margin: '0 0 32px',
        lineHeight: 1.5
      }
    }, 'Des questions ? Envie de nous rendre visite ? Nous serions ravis de vous entendre !'), e('div', {
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: 22
      }
    }, info.map((it, i) => e('div', {
      key: i,
      style: {
        display: 'flex',
        gap: 16,
        alignItems: 'flex-start'
      }
    }, e('div', {
      style: {
        flexShrink: 0,
        width: 48,
        height: 48,
        borderRadius: 'var(--radius-lg)',
        background: it.bg,
        color: '#fff',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center'
      }
    }, e(Icon, {
      name: it.icon,
      size: 22
    })), e('div', null, e('h3', {
      style: {
        margin: '0 0 2px',
        fontSize: 17,
        fontWeight: 600
      }
    }, it.title), it.lines.map((l, j) => e('p', {
      key: j,
      style: {
        margin: 0,
        color: j === 0 ? 'var(--text-body)' : 'var(--text-muted)',
        fontSize: j === 0 ? 15 : 13
      }
    }, l))))))), e(Card, {
      padding: '32px'
    }, e('h3', {
      style: {
        fontSize: 24,
        fontWeight: 700,
        margin: '0 0 24px'
      }
    }, 'Envoyez-nous un message'), sent ? e('div', {
      style: {
        padding: '16px',
        background: '#dcfce7',
        border: '1px solid #bbf7d0',
        borderRadius: 'var(--radius-lg)',
        display: 'flex',
        gap: 10,
        color: 'var(--success-fg)',
        alignItems: 'center'
      }
    }, e(Icon, {
      name: 'check',
      size: 20
    }), e('span', {
      style: {
        fontWeight: 500
      }
    }, 'Merci ! Votre message a bien été envoyé.')) : e('form', {
      onSubmit: ev => {
        ev.preventDefault();
        setSent(true);
        onSubmit && onSubmit();
      },
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: 16
      }
    }, e('div', {
      style: {
        display: 'grid',
        gridTemplateColumns: '1fr 1fr',
        gap: 16
      }
    }, e(DS.Input, {
      label: 'Prénom',
      placeholder: 'Marie',
      required: true
    }), e(DS.Input, {
      label: 'Nom',
      placeholder: 'Dubois',
      required: true
    })), e(DS.Input, {
      label: 'Email',
      type: 'email',
      placeholder: 'vous@exemple.be',
      required: true
    }), e('div', {
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: 6
      }
    }, e('label', {
      style: {
        fontSize: 14,
        fontWeight: 600
      }
    }, 'Message'), e('textarea', {
      rows: 4,
      placeholder: 'Votre message…',
      style: {
        font: 'var(--font-body)',
        padding: '10px 14px',
        border: '1px solid var(--border-strong)',
        borderRadius: 'var(--radius-field)',
        resize: 'vertical',
        outline: 'none'
      }
    })), e(Button, {
      variant: 'primary',
      type: 'submit'
    }, 'Envoyer le message')))));
  }

  // --------------------------------------------------------------------- Footer
  function Footer() {
    return e('footer', {
      style: {
        background: 'var(--gray-900)',
        color: '#fff',
        padding: '56px 0 32px'
      }
    }, e('div', {
      style: CONTAINER
    }, e('div', {
      style: {
        display: 'grid',
        gridTemplateColumns: '1.4fr 1fr 1fr',
        gap: 32,
        paddingBottom: 32
      }
    }, e('div', null, e('div', {
      style: {
        display: 'flex',
        alignItems: 'center',
        gap: 10,
        marginBottom: 14
      }
    }, e('img', {
      src: '../../assets/logo-club.svg',
      alt: 'CTT',
      style: {
        height: 30,
        filter: 'brightness(0) invert(1)'
      }
    }), e('span', {
      style: {
        fontSize: 20,
        fontWeight: 700
      }
    }, 'CTT Ottignies-Blocry')), e('p', {
      style: {
        color: 'var(--gray-400)',
        lineHeight: 1.6,
        margin: 0,
        maxWidth: 320
      }
    }, 'Votre destination de choix pour le tennis de table à Ottignies et environs. Rejoignez notre communauté dès aujourd\u2019hui !')), e(FootCol, {
      title: 'Liens rapides',
      links: ['Accueil', 'Résultats', 'Événements', 'Contact']
    }), e(FootCol, {
      title: 'Le club',
      links: ['Horaires', 'Nos équipes', 'Devenir membre', 'Sponsors']
    })), e('div', {
      style: {
        borderTop: '1px solid var(--gray-800)',
        paddingTop: 24,
        display: 'flex',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        gap: 12,
        color: 'var(--gray-400)',
        fontSize: 13
      }
    }, e('span', null, '© 2026 CTT Ottignies-Blocry. Tous droits réservés.'), e('span', null, 'Made with ♥ — Laravel · TailwindCSS · Alpine.js'))));
  }
  function FootCol({
    title,
    links
  }) {
    return e('div', null, e('h4', {
      style: {
        fontSize: 16,
        fontWeight: 600,
        margin: '0 0 14px'
      }
    }, title), e('ul', {
      style: {
        listStyle: 'none',
        padding: 0,
        margin: 0,
        display: 'flex',
        flexDirection: 'column',
        gap: 8
      }
    }, links.map((l, i) => e('li', {
      key: i
    }, e('a', {
      href: '#',
      style: {
        color: 'var(--gray-400)',
        textDecoration: 'none',
        fontSize: 14
      }
    }, l)))));
  }
  window.PublicSite = {
    Nav,
    Hero,
    About,
    Events,
    News,
    Schedule,
    Contact,
    Footer
  };
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/public-website/PublicSite.jsx", error: String((e && e.message) || e) }); }

__ds_ns.DashboardTile = __ds_scope.DashboardTile;

__ds_ns.Avatar = __ds_scope.Avatar;

__ds_ns.Badge = __ds_scope.Badge;

__ds_ns.Button = __ds_scope.Button;

__ds_ns.Card = __ds_scope.Card;

__ds_ns.Input = __ds_scope.Input;

__ds_ns.NewsCard = __ds_scope.NewsCard;

})();
