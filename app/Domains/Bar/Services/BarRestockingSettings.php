<?php

declare(strict_types=1);

namespace App\Domains\Bar\Services;

use Illuminate\Support\Facades\DB;

/**
 * Les réglages du réassort pour tout le bar, rangés dans `bar_settings`.
 *
 * - l'automatique : désactivé tant que le comité ne l'a pas allumé ;
 * - la couverture : combien de semaines de ventes le min et le max représentent.
 *
 * Un produit peut s'écarter de chacun : voir `restocking_mode` et
 * `restocking_weeks` sur BarProduct.
 */
class BarRestockingSettings
{
    public const int DEFAULT_MAX_WEEKS = 3;

    public const int DEFAULT_MIN_WEEKS = 1;

    private const string KEY_AUTO = 'restocking.auto';

    private const string KEY_MAX_WEEKS = 'restocking.max_weeks';

    private const string KEY_MIN_WEEKS = 'restocking.min_weeks';

    public function isAutomatic(): bool
    {
        return $this->read(self::KEY_AUTO) === '1';
    }

    public function maxWeeks(): int
    {
        return (int) ($this->read(self::KEY_MAX_WEEKS) ?? self::DEFAULT_MAX_WEEKS);
    }

    public function minWeeks(): int
    {
        return (int) ($this->read(self::KEY_MIN_WEEKS) ?? self::DEFAULT_MIN_WEEKS);
    }

    public function setAutomatic(bool $automatic): void
    {
        $this->write(self::KEY_AUTO, $automatic ? '1' : '0');
    }

    /**
     * Le min couvre au moins une semaine, et jamais plus que le max.
     */
    public function setCoverage(int $minWeeks, int $maxWeeks): void
    {
        $maxWeeks = max(1, $maxWeeks);

        $this->write(self::KEY_MIN_WEEKS, (string) min(max(1, $minWeeks), $maxWeeks));
        $this->write(self::KEY_MAX_WEEKS, (string) $maxWeeks);
    }

    private function read(string $key): ?string
    {
        $value = DB::table('bar_settings')->where('k', $key)->value('v');

        return $value === null ? null : (string) $value;
    }

    private function write(string $key, string $value): void
    {
        DB::table('bar_settings')->updateOrInsert(
            ['k' => $key],
            ['v' => $value, 'modified_by' => auth()->id(), 'updated_at' => now(), 'created_at' => now()],
        );
    }
}
