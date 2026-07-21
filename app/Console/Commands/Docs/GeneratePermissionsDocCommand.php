<?php

declare(strict_types=1);

namespace App\Console\Commands\Docs;

use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Feature;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Role;
use Illuminate\Console\Command;

/**
 * Renders the role → permission matrix as markdown.
 *
 * Generated rather than written, because a hand-maintained table of 60 rights
 * across 18 roles goes stale within a month and then quietly misleads. The enum
 * is the source; this only formats it. PermissionsDocTest fails when the file
 * drifts, so the doc cannot lag behind a matrix change. `--check` gives CI the
 * same guarantee without booting the test suite.
 */
class GeneratePermissionsDocCommand extends Command
{
    protected $description = 'Generate the delegations and permissions documentation from the Role matrix';

    protected $signature = 'docs:permissions {--check : Fail if the file on disk is out of date}';

    public function handle(): int
    {
        $path = base_path('docs/permissions.md');
        $rendered = $this->render();

        if ($this->option('check')) {
            $current = is_file($path) ? file_get_contents($path) : '';

            if ($current !== $rendered) {
                $this->error('docs/permissions.md is out of date — run `php artisan docs:permissions`.');

                return self::FAILURE;
            }

            $this->info('docs/permissions.md is up to date.');

            return self::SUCCESS;
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $rendered);
        $this->info('docs/permissions.md generated.');

        return self::SUCCESS;
    }

    public function render(): string
    {
        $lines = [
            '# Délégations et permissions',
            '',
            '> Fichier généré par `php artisan docs:permissions`. Ne pas modifier à la main :',
            '> la source est `App\Domains\Shared\Enums\Role`, et `PermissionsDocTest` échoue',
            '> si les deux divergent.',
            '',
            'Trois familles cohabitent, et une seule décide :',
            '',
            '| Famille | Nature | Stockage | Rôle |',
            '|---|---|---|---|',
            '| Titre statutaire | mandat AG, un par personne | `users.committee_role` | **s\'affiche** |',
            '| Délégation | charge opérationnelle, cumulable, attribuable à n\'importe qui | rôles Spatie | **décide** |',
            '| Équipement confié | objet remis, se rend | `users.has_key`, caisses détenues | **se trace** |',
            '',
            '---',
            '',
            '## Socle',
            '',
        ];

        foreach ([Role::ADMINISTRATOR, Role::COMMITTEE] as $role) {
            $lines = [...$lines, ...$this->renderRole($role)];
        }

        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## Délégations';
        $lines[] = '';
        $lines[] = 'Chacune peut être confiée à n\'importe quel membre, qu\'il siège au comité ou non.';
        $lines[] = '';

        foreach (Role::delegations() as $role) {
            $lines = [...$lines, ...$this->renderRole($role)];
        }

        $lines = [...$lines, ...$this->renderSuggestions(), ...$this->renderOrphans(), ...$this->renderFeatures()];

        return implode("\n", $lines) . "\n";
    }

    /**
     * @return array<int, string>
     */
    private function renderFeatures(): array
    {
        $lines = [
            '---',
            '',
            '## Domaines extinguibles',
            '',
            'Un drapeau par domaine (`config/features.php`, piloté par `.env`). Éteindre un domaine',
            'le retire des quatre surfaces à la fois : routes (404), navigation, tâches planifiées et',
            'calendrier public.',
            '',
            '| Domaine | Clé `.env` |',
            '|---|---|',
        ];

        foreach (Feature::cases() as $feature) {
            $lines[] = '| ' . $feature->label() . ' | `FEATURE_' . mb_strtoupper($feature->value) . '` |';
        }

        return [...$lines, ''];
    }

    /**
     * Permissions no role carries. Not necessarily a bug — an administrator still
     * holds them — but worth seeing.
     *
     * @return array<int, string>
     */
    private function renderOrphans(): array
    {
        $held = [];

        foreach (Role::delegations() as $role) {
            foreach ($role->permissions() as $permission) {
                $held[$permission->value] = true;
            }
        }

        foreach (Role::COMMITTEE->permissions() as $permission) {
            $held[$permission->value] = true;
        }

        $orphans = array_values(array_filter(
            Permission::cases(),
            static fn (Permission $p): bool => ! isset($held[$p->value]),
        ));

        if ($orphans === []) {
            return [];
        }

        $lines = [
            '---',
            '',
            '## Permissions détenues par le seul administrateur',
            '',
            'Aucune délégation ne les porte : elles ne peuvent pas être confiées.',
            '',
        ];

        foreach ($orphans as $permission) {
            $lines[] = '- `' . $permission->value . '`';
        }

        return [...$lines, ''];
    }

    /**
     * @return array<int, string>
     */
    private function renderRole(Role $role): array
    {
        $permissions = $role->permissions();

        $lines = [
            '### ' . $role->label() . ' — `' . $role->value . '`',
            '',
            $role->description(),
            '',
        ];

        if ($role === Role::ADMINISTRATOR) {
            $lines[] = 'Détient les ' . count($permissions) . ' permissions. Accordées explicitement plutôt que';
            $lines[] = 'par un court-circuit `Gate::before`, car certaines policies encodent des règles qui';
            $lines[] = 'doivent survivre à un administrateur — il ne peut toujours pas supprimer son propre';
            $lines[] = 'compte.';
            $lines[] = '';

            return $lines;
        }

        if ($permissions === []) {
            $lines[] = '_Aucune permission._';
            $lines[] = '';

            return $lines;
        }

        foreach ($permissions as $permission) {
            $lines[] = '- `' . $permission->value . '`';
        }
        $lines[] = '';

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    private function renderSuggestions(): array
    {
        $lines = [
            '---',
            '',
            '## Délégations suggérées par titre',
            '',
            'Pré-cochées à la nomination, et modifiables : un trésorier qui ne tient pas la caisse',
            'est une situation légitime.',
            '',
            '| Titre | Délégations suggérées |',
            '|---|---|',
        ];

        foreach (CommitteeRolesEnum::cases() as $title) {
            $suggested = array_map(
                static fn (Role $role): string => '`' . $role->value . '`',
                Role::suggestedFor($title),
            );

            $lines[] = '| ' . $title->label() . ' | ' . ($suggested === [] ? '—' : implode(', ', $suggested)) . ' |';
        }

        return [...$lines, ''];
    }
}
