import * as React from 'react';

/**
 * Brand button — club-blue primary, club-yellow secondary, plus outline/ghost/danger.
 *
 * @startingPoint section="Core" subtitle="Brand button — blue / yellow / outline / ghost" viewport="700x150"
 */
export interface ButtonProps {
  children?: React.ReactNode;
  /** Visual style. @default "primary" */
  variant?: 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger';
  /** @default "md" */
  size?: 'sm' | 'md' | 'lg';
  /** Optional leading icon node (e.g. an inline Heroicon SVG). */
  icon?: React.ReactNode;
  /** Render the icon after the label. @default false */
  iconRight?: boolean;
  disabled?: boolean;
  /** Render as `<a>` instead of `<button>`. @default "button" */
  as?: 'button' | 'a';
  href?: string;
  type?: 'button' | 'submit' | 'reset';
  onClick?: (e: React.MouseEvent) => void;
  className?: string;
  style?: React.CSSProperties;
}

export function Button(props: ButtonProps): JSX.Element;
