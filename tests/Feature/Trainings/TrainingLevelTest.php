<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Trainings\Models\TrainingLevel;
use App\Domains\Trainings\Models\TrainingPack;
use Livewire\Livewire;

describe('training levels', function (): void {
    it('ships the club six, in reading order', function (): void {
        // Du plus jeune au plus fort, puis « Tous niveaux » : c'est l'ordre dans
        // lequel le comité les lit, pas l'ordre alphabétique.
        expect(TrainingLevel::ordered()->pluck('label')->all())->toBe([
            'Jeunes',
            'Débutant',
            'Jeunes espoirs',
            'Confirmé',
            'Compétition',
            'Tous niveaux',
        ]);
    })->group('training', 'levels');

    it('keeps a retired level out of the lists but on its packs', function (): void {
        $level = TrainingLevel::where('label', 'Compétition')->firstOrFail();
        $pack = TrainingPack::factory()->create(['training_level_id' => $level->id, 'price' => 90]);

        $level->update(['is_active' => false]);

        expect(TrainingLevel::active()->pluck('label'))->not->toContain('Compétition')
            ->and($pack->fresh()->level->label)->toBe('Compétition');
    })->group('training', 'levels');

    it('knows when a level is still in use', function (): void {
        $used = TrainingLevel::where('label', 'Débutant')->firstOrFail();
        $unused = TrainingLevel::where('label', 'Tous niveaux')->firstOrFail();

        TrainingPack::factory()->create(['training_level_id' => $used->id, 'price' => 90]);

        expect($used->isInUse())->toBeTrue()
            ->and($unused->isInUse())->toBeFalse();
    })->group('training', 'levels');
});

describe('the delegation manages the levels', function (): void {

    beforeEach(function (): void {
        $this->admin = User::factory()->isAdmin()->create();
    });

    it('adds a level of its own', function (): void {
        Livewire::actingAs($this->admin)
            ->test('pages::club-events.trainings.index')
            ->set('levelForm', ['label' => 'Vétérans', 'color' => 'info'])
            ->call('saveLevel');

        $created = TrainingLevel::where('label', 'Vétérans')->first();

        // Il arrive en fin de liste : l'ordre est une décision, pas un effet
        // de bord de la date de création.
        expect($created)->not->toBeNull()
            ->and($created->color)->toBe('info')
            ->and($created->position)->toBe(7)
            ->and($created->is_active)->toBeTrue();
    })->group('training', 'levels');

    it('refuses to delete a level a pack still uses', function (): void {
        $level = TrainingLevel::where('label', 'Débutant')->firstOrFail();
        TrainingPack::factory()->create(['training_level_id' => $level->id, 'price' => 90]);

        Livewire::actingAs($this->admin)
            ->test('pages::club-events.trainings.index')
            ->call('deleteLevel', $level->id);

        expect(TrainingLevel::find($level->id))->not->toBeNull();
    })->group('training', 'levels');

    it('deletes one nothing points at', function (): void {
        $spare = TrainingLevel::factory()->create(['label' => 'Essai']);

        Livewire::actingAs($this->admin)
            ->test('pages::club-events.trainings.index')
            ->call('deleteLevel', $spare->id);

        expect(TrainingLevel::find($spare->id))->toBeNull();
    })->group('training', 'levels');

    it('offers only the live levels in the pack wizard', function (): void {
        TrainingLevel::where('label', 'Compétition')->update(['is_active' => false]);

        $options = Livewire::actingAs($this->admin)
            ->test('pages::club-events.trainings.index')
            ->get('levelOptions');

        // Et le libellé, pas la valeur brute anglaise que l'écran affichait.
        expect(collect($options)->pluck('name'))->toContain('Débutant')
            ->and(collect($options)->pluck('name'))->not->toContain('Compétition')
            ->and(collect($options)->pluck('name'))->not->toContain('Beginners');
    })->group('training', 'levels');
});
