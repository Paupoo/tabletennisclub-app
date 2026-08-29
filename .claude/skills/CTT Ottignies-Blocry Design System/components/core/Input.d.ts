import * as React from 'react';

/**
 * Labeled text input — gray border, club-blue focus ring, hint/error states.
 */
export interface InputProps {
  label?: string;
  /** @default "text" */
  type?: string;
  placeholder?: string;
  value?: string;
  defaultValue?: string;
  onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
  /** Optional leading icon node. */
  icon?: React.ReactNode;
  /** Helper text shown below when there's no error. */
  hint?: string;
  /** Error message — turns the field red and overrides hint. */
  error?: string;
  required?: boolean;
  disabled?: boolean;
  id?: string;
  name?: string;
  className?: string;
  style?: React.CSSProperties;
}

export function Input(props: InputProps): JSX.Element;
