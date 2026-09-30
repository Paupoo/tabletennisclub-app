<?php

declare(strict_types=1);

namespace App\Support\Treasury;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

/**
 * Lit un relevé bancaire, quel que soit l'export qui l'a produit.
 *
 * CBC en produit au moins deux, qui ne partagent presque rien : l'export
 * rapide (`export_<IBAN>_…`) est en Latin-1, séparé par des points-virgules,
 * et finit ses lignes par un **CR seul** depuis juin 2026 ; l'export par
 * période est en UTF-8, séparé par des virgules, en CRLF, et nomme ses
 * colonnes autrement. Le CR seul a suffi à faire lire le fichier entier comme
 * une seule ligne d'en-tête — et l'import annonçait « 0 transaction » en vert.
 */
final class BankStatementReader
{
    /**
     * Les libellés acceptés pour chaque champ, sous leur forme normalisée.
     *
     * Une variante d'export qui renomme une colonne s'ajoute ici, et nulle
     * part ailleurs.
     *
     * @var array<string, list<string>>
     */
    public const array ALIASES = [
        'account' => ['numero de compte'],
        'date' => ['date'],
        'amount' => ['montant', 'amount'],
        'description' => ['description'],
        'counterparty_account' => ['numero de compte contrepartie'],
        'counterparty_name' => ['nom contrepartie'],
        'structured_reference' => ['communication structuree'],
        'free_reference' => ['communication libre'],
        'balance' => ['solde'],
        'statement_number' => ['numero extrait', "numero de l'extrait"],
    ];

    /**
     * Ce sans quoi une ligne ne peut pas entrer, sous le libellé que la banque
     * lui donne — c'est ce libellé que le trésorier cherchera dans son fichier.
     *
     * Un groupe de plusieurs champs se satisfait d'un seul d'entre eux. Le
     * compte de la contrepartie doit exister comme colonne, mais peut rester
     * vide : un frais bancaire n'a pas de contrepartie.
     *
     * @var array<string, list<string>>
     */
    private const array REQUIRED = [
        'Date' => ['date'],
        'Montant' => ['amount'],
        'Numéro de compte contrepartie' => ['counterparty_account'],
        'Communication structurée / Communication libre' => ['structured_reference', 'free_reference'],
    ];

    /**
     * @throws \DomainException Quand l'en-tête ne porte pas une colonne indispensable.
     */
    public function read(string $path): BankStatement
    {
        $rows = $this->load($path);
        $headerRow = array_shift($rows) ?? [];

        $columns = array_values(array_filter(
            array_map(fn (mixed $h): string => trim((string) $h), $headerRow),
            fn (string $h): bool => $h !== '',
        ));
        $positions = $this->positions(array_map($this->normalizeHeader(...), array_values($headerRow)));

        $this->ensureRequiredColumns($positions, $columns);

        $statement = [];
        $line = 1;

        foreach ($rows as $row) {
            $line++;

            $values = array_map(
                fn (mixed $v): ?string => ($v === null || trim((string) $v) === '') ? null : trim((string) $v),
                array_values($row),
            );

            if (array_filter($values, fn (?string $v): bool => $v !== null) === []) {
                continue;
            }

            $fields = ['line' => $line];

            foreach (array_keys(self::ALIASES) as $field) {
                $fields[$field] = isset($positions[$field]) ? ($values[$positions[$field]] ?? null) : null;
            }

            $statement[] = $fields;
        }

        return new BankStatement($statement, $columns);
    }

    /**
     * Le séparateur, lu sur la seule ligne d'en-tête.
     *
     * Deviner sur tout le fichier se laisse piéger par les montants : `195,00`
     * porte une virgule même dans un export séparé par des points-virgules.
     * L'en-tête, lui, ne contient jamais de montant.
     */
    private function delimiter(string $content): string
    {
        $header = strstr($content, "\n", true);
        $header = $header === false ? $content : $header;

        return substr_count($header, ';') > substr_count($header, ',') ? ';' : ',';
    }

    /**
     * Refuse le fichier entier plutôt que d'en importer des lignes à moitié
     * vides : c'est un « 0 transaction » silencieux qui a caché la panne de
     * juin 2026 pendant trois mois.
     *
     * @param  array<string, int>  $positions
     * @param  list<string>  $columns
     *
     * @throws \DomainException
     */
    private function ensureRequiredColumns(array $positions, array $columns): void
    {
        $missing = array_keys(array_filter(
            self::REQUIRED,
            fn (array $fields): bool => array_intersect($fields, array_keys($positions)) === [],
        ));

        if ($missing === []) {
            return;
        }

        throw new \DomainException(__('This statement lacks the column(s) :missing. Columns read: :columns.', [
            'missing' => implode(', ', $missing),
            'columns' => $columns === [] ? '—' : implode(', ', $columns),
        ]));
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function load(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);

        if (! $reader instanceof Csv) {
            return array_values($reader->load($path)->getActiveSheet()->toArray(null, true, true, true));
        }

        // L'encodage est deviné par le lecteur lui-même, mais converti ici : le
        // contenu lui est ensuite remis en mémoire, et il ne convertit que ce
        // qu'il lit sur disque.
        $content = (string) file_get_contents($path);
        $encoding = Csv::guessEncoding($path);

        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        // Les fins de ligne sont ramenées à LF avant que le lecteur ne les
        // voie : il ne reconnaît le CR seul qu'au travers d'un réglage PHP
        // déprécié depuis 8.1, et appelé à disparaître.
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        $reader->setDelimiter($this->delimiter($content));

        return array_values($reader->loadSpreadsheetFromString($content)->getActiveSheet()->toArray(null, true, true, true));
    }

    private function normalizeHeader(mixed $header): string
    {
        $header = mb_strtolower(trim((string) $header));
        $header = strtr($header, [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ô' => 'o', 'ö' => 'o', 'î' => 'i', 'ï' => 'i', 'ç' => 'c',
            '’' => "'",
        ]);

        return (string) preg_replace('/\s+/', ' ', $header);
    }

    /**
     * La colonne de chaque champ reconnu.
     *
     * @param  list<string>  $headers
     * @return array<string, int>
     */
    private function positions(array $headers): array
    {
        $positions = [];

        foreach (self::ALIASES as $field => $aliases) {
            foreach ($headers as $index => $header) {
                if (in_array($header, $aliases, true)) {
                    $positions[$field] = $index;

                    break;
                }
            }
        }

        return $positions;
    }
}
