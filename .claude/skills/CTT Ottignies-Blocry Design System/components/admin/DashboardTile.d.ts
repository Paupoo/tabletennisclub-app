import * as React from 'react';

/**
 * Admin dashboard quick-action tile — icon chip + label + sublabel.
 *
 * The chip colour carries urgency, never domain: neutral by default, club blue
 * when something is waiting on the reader, club yellow for the one entry a
 * persona must find first.
 *
 * @startingPoint section="Admin" subtitle="Quick-action dashboard tile" viewport="700x200"
 */
export interface DashboardTileProps {
  /** Icon node (inline Heroicon SVG). */
  icon: React.ReactNode;
  label: string;
  sub?: string;
  /** Urgency of the tile, not its domain. @default "neutral" */
  color?: 'neutral' | 'primary' | 'secondary';
  /** Red count badge (top-right); hidden when 0/null. A badge forces "primary". */
  badge?: number | null;
  href?: string;
  onClick?: (e: React.MouseEvent) => void;
  className?: string;
  style?: React.CSSProperties;
}

export function DashboardTile(props: DashboardTileProps): JSX.Element;
