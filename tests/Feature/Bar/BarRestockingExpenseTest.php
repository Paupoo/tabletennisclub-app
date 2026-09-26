<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarRestocking;
use App\Domains\Bar\Models\BarStockMovement;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\ExpenseReportStatus;
use App\Domains\Shared\Enums\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Bar — la tournée et la note de frais
|--------------------------------------------------------------------------
|
| Au retour, une dernière question : qui a payé ? Si c'est la personne, la note
| de frais part dans la foulée, pré-remplie — catégorie Bar, date, détail des
| achats — et il ne reste qu'à donner le total du ticket et sa photo. Une tournée
| porte au plus une note ; la note montre au valideur ce qui est entré en stock.
|
*/

beforeEach(function (): void {
    Storage::fake('local');
    $this->shopper = User::factory()->withRole(Role::STORE_KEEPER)->create(['iban' => 'BE68539007547034']);
    $beers = BarCategory::create(['name' => 'Bières']);
    $this->jupiler = BarProduct::create([
        'name' => 'Jupiler', 'sale_price' => 200, 'is_available' => 1, 'category_id' => $beers->id,
        'low_stock_threshold' => 12, 'max_stock' => 48, 'pack_size' => 24, 'pack_label' => 'casier',
    ]);
    BarStockMovement::create(['product_id' => $this->jupiler->id, 'quantity' => 5, 'remaining_quantity' => 5, 'movement_type' => BarStockMovement::TYPE_IN]);
});

function restockingExpenseClosing(User $shopper): Testable
{
    Livewire::actingAs($shopper)->test('pages::bar.restocking')->call('start');
    $line = BarRestocking::inProgress()->lines->first();

    return Livewire::actingAs($shopper)
        ->test('pages::bar.restocking')
        ->call('toggleInCart', $line->id, true)
        ->call('openClosing');
}

it('submits the expense report of whoever paid, filled from the trip', function (): void {
    restockingExpenseClosing($this->shopper)
        ->set('paidBy', 'me')
        ->set('ticketAmount', '31,68')
        ->set('ticketFiles', [UploadedFile::fake()->image('ticket.jpg')])
        ->call('close')
        ->assertHasNoErrors();

    $trip = BarRestocking::query()->sole();
    $report = ExpenseReport::query()->sole();

    expect($report)
        ->user_id->toBe($this->shopper->id)
        ->category->toBe(ExpenseCategory::Bar)
        ->status->toBe(ExpenseReportStatus::Submitted)
        ->amount->toBe(31.68)
        ->and($report->spent_on->isToday())->toBeTrue()
        ->and($report->description)->toContain('Jupiler')->toContain('2 × casier')
        ->and($report->files)->toHaveCount(1);

    expect($trip)
        ->status->toBe(BarRestocking::STATUS_CLOSED)
        ->paid_by->toBe('me')
        ->expense_report_id->toBe($report->id);
    expect($this->jupiler->fresh()->stock)->toBe(53);
});

it('closes without a report when the club or nobody paid', function (string $payer): void {
    restockingExpenseClosing($this->shopper)
        ->set('paidBy', $payer)
        ->call('close')
        ->assertHasNoErrors();

    expect(ExpenseReport::query()->count())->toBe(0)
        ->and(BarRestocking::query()->sole()->paid_by)->toBe($payer)
        ->and($this->jupiler->fresh()->stock)->toBe(53);
})->with(['club', 'nobody']);

it('asks who paid, and for the ticket when it was the shopper, before touching the stock', function (): void {
    restockingExpenseClosing($this->shopper)
        ->call('close')
        ->assertHasErrors('paidBy')
        ->set('paidBy', 'me')
        ->call('close')
        ->assertHasErrors(['ticketAmount', 'ticketFiles']);

    expect(BarRestocking::inProgress())->not->toBeNull()
        ->and($this->jupiler->fresh()->stock)->toBe(5)
        ->and(ExpenseReport::query()->count())->toBe(0);
});

it('does not let a minor claim the purchase back', function (): void {
    $minor = User::factory()->minor()->withRole(Role::STORE_KEEPER)->create();

    restockingExpenseClosing($minor)
        ->assertDontSee(__('I paid, I want to be refunded'))
        ->set('paidBy', 'me')
        ->set('ticketAmount', '31,68')
        ->set('ticketFiles', [UploadedFile::fake()->image('ticket.jpg')])
        ->call('close')
        ->assertHasErrors('paidBy');

    expect($this->jupiler->fresh()->stock)->toBe(5)
        ->and(ExpenseReport::query()->count())->toBe(0);
});

it('shows the treasurer what the trip brought into stock, next to the report', function (): void {
    restockingExpenseClosing($this->shopper)
        ->set('paidBy', 'me')
        ->set('ticketAmount', '31,68')
        ->set('ticketFiles', [UploadedFile::fake()->image('ticket.jpg')])
        ->call('close');

    $report = ExpenseReport::query()->sole();
    $treasurer = User::factory()->isCommitteeMember()->withRole(Role::TREASURY)->create();

    Livewire::actingAs($treasurer)
        ->test('pages::club-admin.treasury.expense-reports')
        ->call('show', $report->id)
        ->assertSee(__('Bar shopping trip'))
        ->assertSee('Jupiler')
        ->assertSee('2 × casier');
});
