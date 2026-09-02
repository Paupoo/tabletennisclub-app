import * as React from 'react';

/**
 * Public news/article card — image, category pill, date, title, excerpt, "Lire la suite".
 *
 * @startingPoint section="Public" subtitle="Article / news card with category pill" viewport="380x420"
 */
export interface NewsCardProps {
  image?: string;
  /** Category label — "Compétition"/"Tournoi" → blue, "Formation" → yellow, else neutral. @default "Actualité" */
  category?: string;
  date?: string;
  title: string;
  excerpt?: string;
  href?: string;
  className?: string;
  style?: React.CSSProperties;
}

export function NewsCard(props: NewsCardProps): JSX.Element;
