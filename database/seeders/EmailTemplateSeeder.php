<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\ClubAdmin\Contact\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->templates() as $template) {
            EmailTemplate::updateOrCreate(
                ['key' => $template['key']],
                $template,
            );
        }
    }

    /**
     * The starter set of editable response templates.
     *
     * Migrated from the hard-coded Mailables (welcome, membership_info,
     * request_info, polite_decline) plus the questionnaire and trial invite.
     *
     * @return list<array{key: string, name: string, subject: string, body: string, apply_status: ?string, is_questionnaire: bool, is_active: bool}>
     */
    private function templates(): array
    {
        return [
            [
                'key' => 'welcome',
                'name' => __('Welcome'),
                'subject' => 'Bienvenue dans notre club !',
                'body' => <<<'TXT'
Bonjour {{first_name}} !

Nous avons bien reçu votre demande de contact concernant « {{interest}} » et nous vous remercions de l'intérêt que vous portez à {{club_name}}.

Notre équipe va examiner votre demande dans les plus brefs délais. En attendant, sachez que vous rejoignez un club convivial, avec des entraîneurs qualifiés, des programmes adaptés à tous les niveaux et des compétitions régulières pour progresser.

N'hésitez pas à répondre à cet email si vous avez la moindre question.

Sportivement,
L'équipe de {{club_name}}
TXT,
                'apply_status' => 'processed',
                'is_questionnaire' => false,
                'is_active' => true,
            ],
            [
                'key' => 'membership_info',
                'name' => __('Membership information'),
                'subject' => 'Informations sur l\'adhésion',
                'body' => <<<'TXT'
Bonjour {{first_name}} !

Suite à votre demande concernant « {{interest}} », voici tous les détails pratiques sur l'adhésion à {{club_name}}.

Nos tarifs :
- Licence récréative adulte : 120€ / an
- Licence récréative enfant (-16 ans) : 80€ / an
- Licence compétitive adulte : 180€ / an
- Réduction famille (3 personnes et plus) : -20%

Documents nécessaires : formulaire d'inscription, certificat médical de moins d'un an, photo d'identité et copie de la pièce d'identité.

Vous pouvez aussi venir découvrir notre ambiance lors d'un cours d'essai gratuit. Répondez simplement à cet email pour convenir d'un créneau.

Sportivement,
L'équipe de {{club_name}}
TXT,
                'apply_status' => null,
                'is_questionnaire' => false,
                'is_active' => true,
            ],
            [
                'key' => 'request_info',
                'name' => __('Request for information'),
                'subject' => 'Informations complémentaires requises',
                'body' => <<<'TXT'
Bonjour {{first_name}},

Merci pour votre demande concernant « {{interest}} » ! Pour finaliser l'étude de votre dossier chez {{club_name}}, nous aurions besoin de quelques précisions :

- Votre niveau de pratique (débutant / intermédiaire / confirmé)
- Vos objectifs (loisir, remise en forme, compétition)
- Vos disponibilités dans la semaine
- Votre expérience antérieure éventuelle

Il vous suffit de répondre à cet email avec ces informations. Nous restons à votre disposition pour toute question.

Sportivement,
L'équipe de {{club_name}}
TXT,
                'apply_status' => null,
                'is_questionnaire' => false,
                'is_active' => true,
            ],
            [
                'key' => 'polite_decline',
                'name' => __('Polite decline'),
                'subject' => 'Nous ne pouvons donner suite à votre demande',
                'body' => <<<'TXT'
Bonjour {{first_name}},

Nous vous remercions sincèrement pour l'intérêt que vous avez manifesté envers {{club_name}} et pour votre demande concernant « {{interest}} ».

Après étude attentive, nous regrettons de ne pas pouvoir y donner une suite favorable à ce stade, nos effectifs étant complets pour cette saison.

Nous vous proposons toutefois de vous inscrire sur notre liste d'attente et de participer à nos événements ouverts au public. N'hésitez pas à nous recontacter dans quelques mois.

Cordialement,
L'équipe de {{club_name}}
TXT,
                'apply_status' => 'rejected',
                'is_questionnaire' => false,
                'is_active' => true,
            ],
            [
                'key' => 'info_request_questionnaire',
                'name' => __('Information questionnaire'),
                'subject' => 'Quelques questions pour mieux vous orienter',
                'body' => <<<'TXT'
Bonjour {{first_name}},

Merci pour votre intérêt envers {{club_name}} concernant « {{interest}} » ! Afin de vous proposer le créneau le plus adapté, pourriez-vous nous préciser :

1. Quelle est votre catégorie d'âge (enfant, adolescent, adulte) ?
2. Quelle est votre expérience du tennis de table (jamais joué, quelques mois, quelques années, joueur classé) ?
3. Souhaitez-vous jouer en compétition / disputer des matchs ?
4. Quels jours de la semaine vous conviennent le mieux ?
5. Un membre de la famille peut-il assurer les trajets (covoiturage) ?

Répondez simplement à cet email, cela nous aidera beaucoup. Merci d'avance !

Sportivement,
L'équipe de {{club_name}}
TXT,
                'apply_status' => null,
                'is_questionnaire' => true,
                'is_active' => true,
            ],
            [
                'key' => 'trial_invite',
                'name' => __('Trial invitation'),
                'subject' => 'Venez essayer le tennis de table avec nous !',
                'body' => <<<'TXT'
Bonjour {{first_name}},

Suite à votre demande concernant « {{interest}} », nous serions ravis de vous accueillir pour un cours d'essai gratuit chez {{club_name}}.

Venez découvrir notre ambiance, rencontrer les entraîneurs et tester votre niveau, sans engagement. Apportez simplement une tenue de sport et des chaussures de salle.

Répondez à cet email pour convenir ensemble d'un créneau qui vous arrange.

À très bientôt,
L'équipe de {{club_name}}
TXT,
                'apply_status' => null,
                'is_questionnaire' => false,
                'is_active' => true,
            ],
        ];
    }
}
