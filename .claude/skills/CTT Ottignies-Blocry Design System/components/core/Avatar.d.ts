import * as React from 'react';

/** Round member avatar — image or initials on club blue. */
export interface AvatarProps {
  src?: string;
  /** Used for initials fallback + tooltip. */
  name?: string;
  /** Pixel diameter. @default 40 */
  size?: number;
  /** Adds a white + club-yellow ring. @default false */
  ring?: boolean;
  className?: string;
  style?: React.CSSProperties;
}

export function Avatar(props: AvatarProps): JSX.Element;
