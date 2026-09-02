import React from 'react';

/**
 * CTT Ottignies-Blocry — DashboardTile
 * The admin dashboard quick-action tile: an icon chip over a label + sublabel,
 * in a white rounded-xl card that lifts on hover. Mirrors
 * clubAdmin/_dashboard_tile.blade.php.
 *
 * The chip colour carries URGENCY, never domain. The club owns two colours over
 * a neutral canvas, so a grid painted one hue per domain encodes nothing a
 * reader can decode — there is no legend to read it against.
 *
 *   neutral   — nothing is waiting on you (the default, and most tiles)
 *   primary   — something is waiting on you (club blue)
 *   secondary — the one entry a persona must find first (club yellow)
 *
 * A tile carrying a badge is waiting on someone by definition, so it takes the
 * club blue whatever it was given.
 */
export function DashboardTile({ icon, label, sub, color = 'neutral', badge = null, href = '#', onClick, className = '', style = {} }) {
  const colorMap = {
    neutral:   ['var(--gray-100)', 'var(--gray-600)'],
    primary:   ['rgba(30, 64, 175, 0.10)', 'var(--club-blue)'],
    secondary: ['rgba(251, 191, 36, 0.20)', 'var(--club-blue)'],
  };
  const pending = badge != null && badge !== 0;
  const [bg, fg] = colorMap[pending ? 'primary' : color] || colorMap.neutral;
  const [hover, setHover] = React.useState(false);

  return (
    <a
      href={href} onClick={onClick} className={className}
      onMouseEnter={() => setHover(true)} onMouseLeave={() => setHover(false)}
      style={{
        position: 'relative', display: 'flex', flexDirection: 'column',
        alignItems: 'center', textAlign: 'center', gap: '0.5rem',
        background: 'var(--surface-card)', border: '1px solid var(--border-default)',
        borderRadius: 'var(--radius-xl)', padding: '1rem', textDecoration: 'none',
        boxShadow: hover ? 'var(--shadow-md)' : 'none',
        transition: 'box-shadow var(--dur-base) var(--ease-out), border-color var(--dur-fast) var(--ease-out)',
        ...style,
      }}
    >
      {pending && (
        <span style={{
          position: 'absolute', top: 8, right: 8, minWidth: 18, height: 18, padding: '0 5px',
          background: 'var(--error)', color: '#fff', fontSize: 11, fontWeight: 700,
          borderRadius: 'var(--radius-full)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
          fontFamily: 'var(--font-sans)',
        }}>{badge}</span>
      )}
      <span style={{
        background: bg, color: fg, borderRadius: 'var(--radius-xl)', padding: '0.625rem',
        display: 'inline-flex', width: 20, height: 20, alignItems: 'center', justifyContent: 'center',
        transform: hover ? 'scale(1.1)' : 'scale(1)', transition: 'transform var(--dur-base) var(--ease-out)',
      }} aria-hidden="true">{icon}</span>
      <span style={{ fontSize: '0.75rem', fontWeight: 600, color: 'var(--text-strong)', lineHeight: 1.2, fontFamily: 'var(--font-sans)' }}>{label}</span>
      {sub && <span style={{ fontSize: '0.7rem', color: 'var(--text-faint)', lineHeight: 1.2, fontFamily: 'var(--font-sans)' }}>{sub}</span>}
    </a>
  );
}
