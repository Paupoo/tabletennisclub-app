<?php

declare(strict_types=1);

namespace App\Console\Commands\Docs;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class GenerateErdCommand extends Command
{
    /** @var array<string, string> */
    private const RELATION_ARROWS = [
        'HasMany' => '||--o{',
        'HasOne' => '||--o|',
        'BelongsToMany' => '}o--o{',
        'MorphMany' => '||--o{',
        'MorphOne' => '||--o|',
        'HasManyThrough' => '||--o{',
        'HasOneThrough' => '||--||',
    ];

    protected $description = 'Generate Mermaid ERD documentation from Eloquent models';

    protected $signature = 'docs:erd {--path= : Directory to write into, defaulting to docs/}';

    public function handle(): int
    {
        $models = $this->discoverModels();

        $this->generateGlobalErd($models);
        $this->generateDomainErds($models);

        $this->info('ERD documentation generated.');

        return self::SUCCESS;
    }

    /** @return array<int, array{class: string, fqn: string, domain: string, columns: list<array{name: string, type: string, nullable: bool, pk: bool, fk: bool}>, relationships: list<array{method: string, type: string, related_fqn: string, related_class: string}>}> */
    private function discoverModels(): array
    {
        $finder = (new Finder)->files()->in(app_path('Domains'))->path('Models')->name('*.php');

        $models = [];
        foreach ($finder as $file) {
            $data = $this->parseModelFile($file->getPathname());
            if ($data !== null) {
                $models[] = $data;
            }
        }

        // Finder walks the tree in filesystem order, which varies between machines
        // and between runs. Sort so the generated files only change when a model does.
        usort($models, fn (array $a, array $b): int => [$a['domain'], $a['class']] <=> [$b['domain'], $b['class']]);

        return $models;
    }

    /**
     * @param  array<int, array{class: string, fqn: string, domain: string, columns: list<array{name: string, type: string, nullable: bool, pk: bool, fk: bool}>, relationships: list<array{method: string, type: string, related_fqn: string, related_class: string}>}>  $models
     */
    private function generateDomainErds(array $models): void
    {
        /** @var array<string, list<array{class: string, fqn: string, domain: string, columns: list<array{name: string, type: string, nullable: bool, pk: bool, fk: bool}>, relationships: list<array{method: string, type: string, related_fqn: string, related_class: string}>}>> $byDomain */
        $byDomain = [];
        /** @var array<string, string> $classByFqn */
        $classByFqn = [];

        foreach ($models as $model) {
            $byDomain[$model['domain']][] = $model;
            $classByFqn[$model['fqn']] = $model['class'];
        }

        foreach ($byDomain as $domain => $domainModels) {
            $slug = strtolower(str_replace('/', '-', $domain));
            $lines = ["# ERD — {$domain}", '', '```mermaid', 'erDiagram'];

            foreach ($domainModels as $model) {
                $lines[] = "    {$model['class']} {";
                foreach ($model['columns'] as $col) {
                    $suffix = $col['pk'] ? ' PK' : ($col['fk'] ? ' FK' : '');
                    $comment = $col['nullable'] ? ' "nullable"' : '';
                    $lines[] = "        {$col['type']} {$col['name']}{$suffix}{$comment}";
                }
                $lines[] = '    }';
            }

            $lines[] = '';

            foreach ($domainModels as $model) {
                foreach ($model['relationships'] as $rel) {
                    $relatedClass = $classByFqn[$rel['related_fqn']] ?? $rel['related_class'];
                    $arrow = self::RELATION_ARROWS[$rel['type']];
                    $lines[] = "    {$model['class']} {$arrow} {$relatedClass} : \"{$rel['method']}\"";
                }
            }

            $lines[] = '```';

            $this->writeFile($this->outputPath("erd/{$slug}.md"), implode("\n", $lines));
        }
    }

    /**
     * @param  array<int, array{class: string, fqn: string, domain: string, columns: list<array{name: string, type: string, nullable: bool, pk: bool, fk: bool}>, relationships: list<array{method: string, type: string, related_fqn: string, related_class: string}>}>  $models
     */
    private function generateGlobalErd(array $models): void
    {
        /** @var array<string, list<array{class: string, fqn: string}>> $byDomain */
        $byDomain = [];
        /** @var array<string, string> $classByFqn */
        $classByFqn = [];

        foreach ($models as $model) {
            $byDomain[$model['domain']][] = $model;
            $classByFqn[$model['fqn']] = $model['class'];
        }

        $lines = ['# Entity-Relationship Diagram — Vue globale', '', '```mermaid', 'erDiagram'];

        foreach ($byDomain as $domain => $domainModels) {
            $lines[] = "    %% {$domain}";
            foreach ($domainModels as $model) {
                $lines[] = "    {$model['class']}";
            }
            $lines[] = '';
        }

        foreach ($models as $model) {
            foreach ($model['relationships'] as $rel) {
                $relatedClass = $classByFqn[$rel['related_fqn']] ?? $rel['related_class'];
                $arrow = self::RELATION_ARROWS[$rel['type']];
                $lines[] = "    {$model['class']} {$arrow} {$relatedClass} : \"{$rel['method']}\"";
            }
        }

        $lines[] = '```';

        $this->writeFile($this->outputPath('erd.md'), implode("\n", $lines));
    }

    /**
     * Resolve a file below the output directory, so tests can generate into a
     * temporary one instead of dirtying the repository's docs/.
     */
    private function outputPath(string $relative): string
    {
        $base = (string) ($this->option('path') ?? '');

        return $base === ''
            ? base_path('docs/' . $relative)
            : rtrim($base, '/') . '/' . $relative;
    }

    /** @return array{class: string, fqn: string, domain: string, columns: list<array{name: string, type: string, nullable: bool, pk: bool, fk: bool}>, relationships: list<array{method: string, type: string, related_fqn: string, related_class: string}>}|null */
    private function parseModelFile(string $path): ?array
    {
        $content = (string) file_get_contents($path);

        if (! preg_match('/^namespace\s+(App\\\\Domains\\\\[^;]+);/m', $content, $nsMatch)) {
            return null;
        }

        if (! preg_match('/^(?:abstract\s+)?class\s+(\w+)/m', $content, $classMatch)) {
            return null;
        }

        $namespace = $nsMatch[1];
        $className = $classMatch[1];
        $fqn = $namespace . '\\' . $className;
        $domain = str_replace('\\', '/', (string) preg_replace('/^App\\\\Domains\\\\(.+)\\\\Models$/', '$1', $namespace));

        $useStatements = $this->parseUseStatements($content);

        return [
            'class' => $className,
            'fqn' => $fqn,
            'domain' => $domain,
            'columns' => $this->parseProperties($content),
            'relationships' => $this->parseRelationships($content, $useStatements),
        ];
    }

    /** @return list<array{name: string, type: string, nullable: bool, pk: bool, fk: bool}> */
    private function parseProperties(string $content): array
    {
        $columns = [];
        preg_match_all('/@property\s+([^\s]+)\s+\$(\w+)/', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $rawType = $match[1];
            $name = $match[2];

            if (in_array($name, ['created_at', 'updated_at', 'deleted_at'], true)) {
                continue;
            }

            $nullable = str_contains($rawType, '|null') || str_starts_with($rawType, '?');
            $baseType = str_replace(['|null', '?'], '', $rawType);

            $columns[] = [
                'name' => $name,
                'type' => $this->simplifyType($baseType),
                'nullable' => $nullable,
                'pk' => $name === 'id',
                'fk' => str_ends_with($name, '_id') && $name !== 'id',
            ];
        }

        return $columns;
    }

    /**
     * @param  array<string, string>  $useStatements
     * @return list<array{method: string, type: string, related_fqn: string, related_class: string}>
     */
    private function parseRelationships(string $content, array $useStatements): array
    {
        $relationTypes = implode('|', array_keys(self::RELATION_ARROWS));

        preg_match_all(
            '/public\s+function\s+(\w+)\s*\(\s*\)\s*:\s*(?:[\w\\\\]+\\\\)?(' . $relationTypes . ')/m',
            $content,
            $methodMatches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        $relationships = [];

        foreach ($methodMatches as $methodMatch) {
            $methodName = $methodMatch[1][0];
            $relationType = $methodMatch[2][0];
            $methodOffset = $methodMatch[0][1];

            $bodyStart = strpos($content, '{', $methodOffset);
            if ($bodyStart === false) {
                continue;
            }

            $bodyEnd = strpos($content, '}', $bodyStart);
            if ($bodyEnd === false) {
                continue;
            }

            $body = substr($content, $bodyStart, $bodyEnd - $bodyStart);

            if (! preg_match('/(\w+)::class/', $body, $classMatch)) {
                continue;
            }

            $relatedClass = $classMatch[1];
            $relatedFqn = $useStatements[$relatedClass] ?? $relatedClass;

            $relationships[] = [
                'method' => $methodName,
                'type' => $relationType,
                'related_fqn' => $relatedFqn,
                'related_class' => $relatedClass,
            ];
        }

        return $relationships;
    }

    /** @return array<string, string> */
    private function parseUseStatements(string $content): array
    {
        $uses = [];
        preg_match_all('/^use\s+([^;]+);/m', $content, $matches);
        foreach ($matches[1] as $use) {
            $use = trim($use);
            if (preg_match('/(.+)\s+as\s+(\w+)$/', $use, $aliasMatch)) {
                $uses[$aliasMatch[2]] = $aliasMatch[1];
            } else {
                $parts = explode('\\', $use);
                $uses[end($parts)] = $use;
            }
        }

        return $uses;
    }

    private function simplifyType(string $type): string
    {
        return match (true) {
            str_contains($type, 'Carbon') => 'datetime',
            $type === 'int' || $type === 'integer' => 'int',
            $type === 'float' || $type === 'double' => 'float',
            $type === 'bool' || $type === 'boolean' => 'bool',
            $type === 'string' => 'string',
            $type === 'array' => 'array',
            default => basename(str_replace('\\', '/', $type)),
        };
    }

    private function writeFile(string $path, string $content): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $content . "\n");
        $this->line('  → ' . str_replace(base_path() . '/', '', $path));
    }
}
