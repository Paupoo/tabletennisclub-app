<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Spam;
use Illuminate\Console\Command;

final class ParseSpamLog extends Command
{
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyse le log Laravel et enregistre les tentatives de spam dans la table spam';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:save-logged-spam';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $file = storage_path('logs/laravel.log');

        if (! file_exists($file)) {
            $this->error('Fichier de log introuvable.');

            return self::FAILURE;
        }

        $handle = fopen($file, 'r');

        if ($handle === false) {
            $this->error('Impossible de lire le fichier log.');

            return self::FAILURE;
        }

        $count = 0;
        $logDate = now()->toDateTimeString();

        while (($line = fgets($handle)) !== false) {
            if (str_contains($line, 'Spam attempt detected')) {
                $this->line('DEBUG: Ligne détectée -> ' . substr($line, 0, 200));

                $parts = explode('Spam attempt detected', $line, 2);

                if (count($parts) === 2) {
                    $json = trim($parts[1]);
                    $this->line('DEBUG: JSON extrait -> ' . substr($json, 0, 200));

                    $data = json_decode($json, true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        preg_match('/\[(.*?)\]/', $line, $dateMatches)
                            ? $logDate = $dateMatches[1] // "2025-08-17 20:59:51"
                            : $logDate = now()->toDateTimeString();

                        $spam = Spam::create([
                            'ip' => $data['ip'] ?? null,
                            'user_agent' => $data['user_agent'] ?? null,
                            'inputs' => $data['inputs'] ?? [],
                        ]);

                        $spam->created_at = $logDate;
                        $spam->updated_at = $logDate;
                        $spam->save();

                        $count++;
                    } else {
                        $this->error('JSON invalide : ' . json_last_error_msg());
                    }
                }
            }
        }

        fclose($handle);

        $this->info("Analyse terminée. {$count} entrées insérées.");

        return self::SUCCESS;
    }
}
