import React from 'react';

/**
 * CTT Ottignies-Blocry — Badge
 * Rounded-full pill used for categories, levels and statuses across the site.
 * `tone` picks a preset palette; `solid` toggles filled vs soft-tinted.
 */
export function Badge({ children, tone = 'neutral', solid = false, size = 'md', icon = null, className = '', style = {} }) {
  const palettes = {
    // solid fills (categories on the public site)
    primary: { solid: ['var(--club-blue)', '#fff'], soft: ['#dbeafe', 'var(--club-blue)'] },
    secondary: { solid: ['var(--club-yellow)', 'var(--club-blue)'], soft: ['#fef3c7', '#b45309'] },
    dark: { solid: ['var(--gray-800)', '#fff'], soft: ['var(--gray-100)', 'var(--gray-800)'] },
    neutral: { solid: ['var(--gray-200)', 'var(--gray-800)'], soft: ['var(--gray-100)', 'var(--gray-600)'] },
    success: { solid: ['var(--success)', '#fff'], soft: ['#dcfce7', 'var(--success-fg)'] },
    warning: { solid: ['var(--warning)', '#fff'], soft: ['#ffedd5', 'var(--warning-fg)'] },
    error: { solid: ['var(--error)', '#fff'], soft: ['#fee2e2', 'var(--error-fg)'] },
    info: { solid: ['var(--info)', '#fff'], soft: ['#dbeafe', '#1d4ed8'] },
  };
  const p = palettes[tone] || palettes.neutral;
  const [bg, fg] = solid ? p.solid : p.soft;

  const sizes = {
    sm: { fontSize: '0.6875rem', padding: '0.15rem 0.55rem' },
    md: { fontSize: '0.75rem', padding: '0.25rem 0.7rem' },
    lg: { fontSize: '0.8125rem', padding: '0.35rem 0.85rem' },
  };
  const s = sizes[size] || sizes.md;

  return (
    <span
      className={className}
      style={{
        display: 'inline-flex', alignItems: 'center', gap: '0.3rem',
        background: bg, color: fg, fontFamily: 'var(--font-sans)', fontWeight: 600,
        borderRadius: 'var(--radius-full)', whiteSpace: 'nowrap',
        ...s, ...style,
      }}
    >
      {icon && <span style={{ display: 'inline-flex', width: '0.85em', height: '0.85em' }} aria-hidden="true">{icon}</span>}
      {children}
    </span>
  );
}
