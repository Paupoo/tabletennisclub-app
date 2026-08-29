import React from 'react';

/**
 * CTT Ottignies-Blocry — Button
 * Brand button. Primary = club blue, Secondary = club yellow (dark text),
 * matching the marketing CTAs and daisyUI btn-primary in the app.
 */
export function Button({
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
    sm: { fontSize: '0.8125rem', padding: '0.4rem 0.85rem', gap: '0.35rem', icon: 15 },
    md: { fontSize: '0.9375rem', padding: '0.6rem 1.15rem', gap: '0.45rem', icon: 17 },
    lg: { fontSize: '1.0625rem', padding: '0.85rem 1.75rem', gap: '0.55rem', icon: 20 },
  };
  const s = sizes[size] || sizes.md;

  const variants = {
    primary: {
      background: 'var(--club-blue)', color: '#fff',
      border: '1px solid var(--club-blue)',
      '--hover-bg': 'var(--club-blue-light)', '--hover-bd': 'var(--club-blue-light)',
    },
    secondary: {
      background: 'var(--club-yellow)', color: 'var(--club-blue)',
      border: '1px solid var(--club-yellow)',
      '--hover-bg': 'var(--club-yellow-light)', '--hover-bd': 'var(--club-yellow-light)',
    },
    outline: {
      background: 'transparent', color: 'var(--club-blue)',
      border: '1px solid var(--border-strong)',
      '--hover-bg': 'var(--gray-50)', '--hover-bd': 'var(--club-blue)',
    },
    ghost: {
      background: 'transparent', color: 'var(--text-body)',
      border: '1px solid transparent',
      '--hover-bg': 'var(--gray-100)', '--hover-bd': 'transparent',
    },
    danger: {
      background: 'var(--error)', color: '#fff',
      border: '1px solid var(--error)',
      '--hover-bg': 'var(--error-fg)', '--hover-bd': 'var(--error-fg)',
    },
  };
  const v = variants[variant] || variants.primary;

  const baseStyle = {
    display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
    flexDirection: iconRight ? 'row-reverse' : 'row',
    gap: s.gap, fontFamily: 'var(--font-sans)', fontWeight: 600,
    fontSize: s.fontSize, lineHeight: 1.1, padding: s.padding,
    borderRadius: 'var(--radius-field)', cursor: disabled ? 'not-allowed' : 'pointer',
    textDecoration: 'none', whiteSpace: 'nowrap', userSelect: 'none',
    transition: 'background var(--dur-fast) var(--ease-out), border-color var(--dur-fast) var(--ease-out), transform var(--dur-base) var(--ease-out)',
    opacity: disabled ? 0.5 : 1,
    ...v, ...style,
  };

  const onEnter = (e) => { if (disabled) return; e.currentTarget.style.background = v['--hover-bg']; e.currentTarget.style.borderColor = v['--hover-bd']; };
  const onLeave = (e) => { if (disabled) return; e.currentTarget.style.background = v.background; e.currentTarget.style.borderColor = v.border.split(' ').pop(); };

  const iconEl = icon ? <span style={{ display: 'inline-flex', width: s.icon, height: s.icon }} aria-hidden="true">{icon}</span> : null;
  const content = <>{iconEl}{children && <span>{children}</span>}</>;

  if (as === 'a') {
    return (
      <a href={href} className={className} style={baseStyle} onMouseEnter={onEnter} onMouseLeave={onLeave} onClick={onClick} {...rest}>
        {content}
      </a>
    );
  }
  return (
    <button type={type} className={className} style={baseStyle} disabled={disabled} onMouseEnter={onEnter} onMouseLeave={onLeave} onClick={onClick} {...rest}>
      {content}
    </button>
  );
}
