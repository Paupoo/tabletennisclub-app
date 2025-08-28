<?php
declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class ExtractTranslations extends Command
{
    /**
     * Le nom et la signature de la commande.
     *
     * @var string
     */
    protected $signature = 'translations:extract 
                            {--path= : Répertoire à scanner (par défaut app/ et resources/views/)} 
                            {--output=translations.json : Fichier de sortie JSON}';

    /**
     * La description de la commande.
     *
     * @var string
     */
    protected $description = 'Extrait toutes les chaînes traduisibles (__() et @lang) du projet et génère un JSON.';

    public function handle(): int
    {
        $path = $this->option('path') ?: base_path();
        $outputFile = base_path($this->option('output'));

        $finder = new Finder();
        $finder->files()->in([$path . '/app', $path . '/resources/views'])->name(['*.php', '*.blade.php']);

        $translations = [];

        foreach ($finder as $file) {
            $content = $file->getContents();

            // Matches __('...')
            preg_match_all("/__\(['\"]([^'\"]+)['\"]\)/", $content, $matches1);

            // Matches @lang('...')
            preg_match_all("/@lang\(['\"]([^'\"]+)['\"]\)/", $content, $matches2);

            $strings = array_merge($matches1[1], $matches2[1]);

            foreach ($strings as $str) {
                $translations[$str] = $translations[$str] ?? '';
            }
        }

        ksort($translations);

        file_put_contents($outputFile, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("✅ Extraction terminée. Résultats sauvegardés dans : {$outputFile}");

        return Command::SUCCESS;
    }
}
