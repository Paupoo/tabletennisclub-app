import * as React from 'react';

/**
 * Pill badge for categories, player levels and statuses.
 *
 * @startingPoint section="Core" subtitle="Category / status / level pills" viewport="700x120"
 */
export interface BadgeProps {
  children?: React.ReactNode;
  /** Color preset. @default "neutral" */
  tone?: 'primary' | 'secondary' | 'dark' | 'neutral' | 'success' | 'warning' | 'error' | 'info';
  /** Filled vs soft-tinted. @default false */
  solid?: boolean;
  /** @default "md" */
  size?: 'sm' | 'md' | 'lg';
  /** Optional leading icon node. */
  icon?: React.ReactNode;
  className?: string;
  style?: React.CSSProperties;
}

export function Badge(props: BadgeProps): JSX.Element;
