<?php

declare(strict_types=1);

namespace App\Support\Charts;

/**
 * The colours of the back office's charts, by role.
 *
 * On screen a chart paints with CSS custom properties (`--chart-*`, defined
 * in resources/css/app.css for the light and the dark theme), so it follows
 * the theme without being drawn twice. A PDF renderer such as mPDF resolves
 * no custom property inside an SVG: it is handed {@see self::print()}, the
 * same roles as plain hex, light theme.
 *
 * The hues are the validated reference palette of the dataviz method (blue
 * and orange, then aqua and violet), checked for colour-blind separation on
 * both surfaces; the previous year and the internal movements recede in grey;
 * « to process » wears the warning colour, always next to its label. The
 * money held stacks the bank accounts in the categorical order (blue,
 * orange, aqua) and the tills in violet, whatever the number of accounts.
 */
final class ChartPalette
{
    /** @var list<string> */
    public const array ROLES = [
        'income', 'expense', 'current', 'previous', 'result',
        'reconciled', 'justified', 'written_off', 'internal', 'to_process',
        'holding_1', 'holding_2', 'holding_3', 'holding_cash',
        'ink', 'muted', 'grid', 'axis', 'surface',
    ];

    /**
     * Light-theme hex values, for a renderer that cannot read CSS variables.
     *
     * @var array<string, string>
     */
    private const array PRINT = [
        'income' => '#2a78d6',
        'expense' => '#eb6834',
        'current' => '#2a78d6',
        'previous' => '#b8b6ae',
        'result' => '#1f1f1f',
        'reconciled' => '#2a78d6',
        'justified' => '#1baf7a',
        'written_off' => '#4a3aa7',
        'internal' => '#b8b6ae',
        'to_process' => '#fab219',
        'holding_1' => '#2a78d6',
        'holding_2' => '#eb6834',
        'holding_3' => '#1baf7a',
        'holding_cash' => '#4a3aa7',
        'ink' => '#1f1f1f',
        'muted' => '#5f5e5a',
        'grid' => '#e1e0d9',
        'axis' => '#c3c2b7',
        'surface' => '#ffffff',
    ];

    /**
     * Every role as a CSS variable: the screen, both themes.
     *
     * @return array<string, string>
     */
    public static function css(): array
    {
        $palette = [];

        foreach (self::ROLES as $role) {
            $palette[$role] = 'var(--chart-' . str_replace('_', '-', $role) . ')';
        }

        return $palette;
    }

    /**
     * Every role as plain hex: a PDF, or any renderer without CSS variables.
     *
     * @return array<string, string>
     */
    public static function print(): array
    {
        return self::PRINT;
    }
}
