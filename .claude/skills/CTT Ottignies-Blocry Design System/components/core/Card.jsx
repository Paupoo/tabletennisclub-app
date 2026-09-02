import React from 'react';

/**
 * CTT Ottignies-Blocry — Card
 * The workhorse surface: white, 1px gray border, subtle hover. The border
 * brightens to club-blue on hover (public site) — toggle with `hoverable`.
 * `accent` adds a colored top bar (featured events) or left border (schedules).
 */
export function Card({
  children,
  hoverable = false,
  accent = null,        // color string for a top accent bar
  accentSide = 'top',   // 'top' | 'left'
  padding = '1.5rem',
  radius = 'var(--radius-2xl)',
  className = '',
  style = {},
  ...rest
}) {
  const [hover, setHover] = React.useState(false);
  const accentBar = accent && accentSide === 'left'
    ? { borderLeft: `4px solid ${accent}` }
    : {};

  return (
    <div
      className={className}
      onMouseEnter={() => setHover(true)}
      onMouseLeave={() => setHover(false)}
      style={{
        background: 'var(--surface-card)',
        border: '1px solid var(--border-default)',
        borderColor: hoverable && hover ? 'var(--border-hover)' : 'var(--border-default)',
        borderRadius: radius,
        boxShadow: hoverable && hover ? 'var(--shadow-md)' : 'var(--shadow-sm)',
        overflow: 'hidden',
        transition: 'border-color var(--dur-fast) var(--ease-out), box-shadow var(--dur-base) var(--ease-out)',
        ...accentBar,
        ...style,
      }}
      {...rest}
    >
      {accent && accentSide === 'top' && (
        <div style={{ height: 4, background: accent }} aria-hidden="true" />
      )}
      <div style={{ padding }}>{children}</div>
    </div>
  );
}
