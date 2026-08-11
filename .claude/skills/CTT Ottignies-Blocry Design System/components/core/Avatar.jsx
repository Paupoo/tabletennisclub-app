import React from 'react';

/**
 * CTT Ottignies-Blocry — Avatar
 * Round member avatar with image or initials fallback on a club-blue tint.
 */
export function Avatar({ src, name = '', size = 40, ring = false, className = '', style = {} }) {
  const initials = name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase())
    .join('');

  return (
    <div
      className={className}
      style={{
        width: size, height: size, borderRadius: 'var(--radius-full)',
        background: 'var(--club-blue)', color: '#fff',
        display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
        fontFamily: 'var(--font-sans)', fontWeight: 600, fontSize: size * 0.4,
        overflow: 'hidden', flexShrink: 0, userSelect: 'none',
        boxShadow: ring ? '0 0 0 2px var(--white), 0 0 0 4px var(--club-yellow)' : 'none',
        ...style,
      }}
      title={name || undefined}
    >
      {src
        ? <img src={src} alt={name} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
        : (initials || '?')}
    </div>
  );
}
