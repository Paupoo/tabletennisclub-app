import React from 'react';

/**
 * CTT Ottignies-Blocry — Input
 * Text field matching the app forms: white, gray border, club-blue focus ring.
 * Supports label, optional icon, hint and error states.
 */
export function Input({
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

  return (
    <div className={className} style={{ display: 'flex', flexDirection: 'column', gap: '0.375rem', fontFamily: 'var(--font-sans)', ...style }}>
      {label && (
        <label htmlFor={inputId} style={{ fontSize: '0.875rem', fontWeight: 600, color: 'var(--text-strong)' }}>
          {label}{required && <span style={{ color: 'var(--error)', marginLeft: 2 }}>*</span>}
        </label>
      )}
      <div style={{ position: 'relative', display: 'flex', alignItems: 'center' }}>
        {icon && (
          <span style={{ position: 'absolute', left: 12, width: 18, height: 18, color: 'var(--text-faint)', display: 'inline-flex', pointerEvents: 'none' }} aria-hidden="true">{icon}</span>
        )}
        <input
          id={inputId} name={name} type={type} placeholder={placeholder}
          value={value} defaultValue={defaultValue} onChange={onChange}
          required={required} disabled={disabled}
          onFocus={() => setFocus(true)} onBlur={() => setFocus(false)}
          style={{
            width: '100%', boxSizing: 'border-box',
            font: 'var(--font-body)', color: 'var(--text-strong)',
            padding: icon ? '0.6rem 0.85rem 0.6rem 2.35rem' : '0.6rem 0.85rem',
            background: disabled ? 'var(--gray-50)' : 'var(--white)',
            border: `1px solid ${hasError ? 'var(--error)' : focus ? 'var(--club-blue)' : 'var(--border-strong)'}`,
            borderRadius: 'var(--radius-field)', outline: 'none',
            boxShadow: focus ? (hasError ? '0 0 0 3px rgba(241,87,108,.12)' : 'var(--shadow-focus)') : 'none',
            transition: 'border-color var(--dur-fast) var(--ease-out), box-shadow var(--dur-fast) var(--ease-out)',
          }}
          {...rest}
        />
      </div>
      {hasError ? (
        <span style={{ fontSize: '0.8125rem', color: 'var(--error-fg)' }}>{error}</span>
      ) : hint ? (
        <span style={{ fontSize: '0.8125rem', color: 'var(--text-muted)' }}>{hint}</span>
      ) : null}
    </div>
  );
}
