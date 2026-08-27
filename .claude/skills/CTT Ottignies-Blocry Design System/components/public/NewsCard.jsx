import React from 'react';
import { Badge } from '../core/Badge.jsx';

/**
 * CTT Ottignies-Blocry — NewsCard
 * Public article/news card: 16:9 image, category pill, date, title, excerpt
 * and a "Lire la suite" link. Border brightens to club-blue on hover; the
 * image zooms slightly. Mirrors components/public/news-card.blade.php.
 */
const CATEGORY_TONE = {
  'Compétition': { tone: 'primary', solid: true },
  'Formation':   { tone: 'secondary', solid: true },
  'Tournoi':     { tone: 'primary', solid: true },
};

export function NewsCard({ image, category = 'Actualité', date, title, excerpt, href = '#', className = '', style = {} }) {
  const [hover, setHover] = React.useState(false);
  const cat = CATEGORY_TONE[category] || { tone: 'neutral', solid: false };

  return (
    <article
      className={className}
      onMouseEnter={() => setHover(true)} onMouseLeave={() => setHover(false)}
      style={{
        background: 'var(--surface-card)', border: '1px solid var(--border-default)',
        borderColor: hover ? 'var(--border-hover)' : 'var(--border-default)',
        borderRadius: 'var(--radius-lg)', overflow: 'hidden',
        transition: 'border-color var(--dur-fast) var(--ease-out)',
        fontFamily: 'var(--font-sans)', display: 'flex', flexDirection: 'column',
        ...style,
      }}
    >
      <div style={{ aspectRatio: '16 / 9', background: 'var(--gray-100)', overflow: 'hidden' }}>
        {image && (
          <img src={image} alt={title} style={{
            width: '100%', height: '100%', objectFit: 'cover',
            transform: hover ? 'scale(1.05)' : 'scale(1)',
            transition: 'transform var(--dur-slow) var(--ease-out)',
          }} />
        )}
      </div>
      <div style={{ padding: '1.5rem', display: 'flex', flexDirection: 'column', flex: 1 }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '0.75rem' }}>
          <Badge tone={cat.tone} solid={cat.solid} size="sm">{category}</Badge>
          {date && <time style={{ fontSize: '0.875rem', color: 'var(--text-muted)' }}>{date}</time>}
        </div>
        <h3 style={{ margin: 0, fontSize: '1.25rem', fontWeight: 700, lineHeight: 1.25, color: hover ? 'var(--club-blue)' : 'var(--text-strong)', transition: 'color var(--dur-fast) var(--ease-out)' }}>
          <a href={href} style={{ color: 'inherit', textDecoration: 'none' }}>{title}</a>
        </h3>
        {excerpt && (
          <p style={{
            margin: '0.75rem 0 1rem', color: 'var(--text-body)', fontSize: '0.95rem', lineHeight: 1.55,
            display: '-webkit-box', WebkitLineClamp: 3, WebkitBoxOrient: 'vertical', overflow: 'hidden',
          }}>{excerpt}</p>
        )}
        <a href={href} style={{
          marginTop: 'auto', color: 'var(--club-blue)', fontWeight: 600, fontSize: '0.875rem',
          display: 'inline-flex', alignItems: 'center', gap: '0.3rem', textDecoration: 'none',
        }}>
          Lire la suite
          <svg width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
      </div>
    </article>
  );
}
