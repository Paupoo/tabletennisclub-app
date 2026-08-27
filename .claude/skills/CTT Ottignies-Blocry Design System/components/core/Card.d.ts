import * as React from 'react';

/**
 * White surface card — 1px gray border, soft shadow, optional brand accent + hover.
 *
 * @startingPoint section="Core" subtitle="Workhorse content surface" viewport="700x220"
 */
export interface CardProps {
  children?: React.ReactNode;
  /** Border brightens to club-blue + lifts shadow on hover. @default false */
  hoverable?: boolean;
  /** Color of an accent bar (e.g. event-type color). Omit for none. */
  accent?: string;
  /** Where the accent sits. @default "top" */
  accentSide?: 'top' | 'left';
  /** Inner padding. @default "1.5rem" */
  padding?: string;
  /** @default "var(--radius-2xl)" */
  radius?: string;
  className?: string;
  style?: React.CSSProperties;
}

export function Card(props: CardProps): JSX.Element;
