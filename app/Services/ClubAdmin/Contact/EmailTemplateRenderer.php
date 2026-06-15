<?php

declare(strict_types=1);

namespace App\Services\ClubAdmin\Contact;

use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Contact\Models\EmailTemplate;
use App\Domains\Competitions\Interclub\Models\Club;
use InvalidArgumentException;

/**
 * Resolves a database-stored {@see EmailTemplate} against a {@see Contact},
 * substituting the supported `{{variable}}` placeholders in its subject and body.
 *
 * Supported variables: first_name, last_name, full_name, interest, club_name.
 * Unknown variables are left untouched so the secretary can spot and fix them
 * before sending (the rendered output pre-fills the editor — decision #13).
 */
class EmailTemplateRenderer
{
    /**
     * Render the active template identified by $key against $contact.
     *
     * @return array{subject: string, body: string, apply_status: ?string}
     *
     * @throws InvalidArgumentException when no active template matches $key
     */
    public function render(string $key, Contact $contact): array
    {
        $template = EmailTemplate::query()->active()->where('key', $key)->first();

        if ($template === null) {
            throw new InvalidArgumentException(__('No active email template found for key :key', ['key' => $key]));
        }

        $variables = $this->variablesFor($contact);

        return [
            'subject' => $this->substitute($template->subject, $variables),
            'body' => $this->substitute($template->body, $variables),
            'apply_status' => $template->apply_status,
        ];
    }

    /**
     * Replace each `{{name}}` occurrence with its resolved value.
     * Placeholders without a matching variable are left as-is.
     *
     * @param  array<string, string>  $variables
     */
    private function substitute(string $text, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            static fn (array $matches): string => $variables[$matches[1]] ?? $matches[0],
            $text,
        );
    }

    /**
     * Build the variable map resolved from the contact.
     *
     * @return array<string, string>
     */
    private function variablesFor(Contact $contact): array
    {
        return [
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'full_name' => trim($contact->first_name . ' ' . $contact->last_name),
            'interest' => $contact->interest?->getLabel() ?? '',
            'club_name' => Club::own()?->name ?? (string) config('app.name'),
        ];
    }
}
