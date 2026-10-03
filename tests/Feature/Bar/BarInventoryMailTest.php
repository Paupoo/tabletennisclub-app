<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarInventory;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarStockMovement;
use App\Domains\Bar\Notifications\BarInventoryValidatedNotification;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\BarInventoryCause;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Role;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Bar — le récapitulatif d'un inventaire
|--------------------------------------------------------------------------
|
| Une correction de stock part toujours chez le trésorier et les magasiniers :
| un magasinier seul ne corrige pas sans que le trésorier le voie. Un seul mail
| par inventaire validé, un seul par personne, et celui qui valide reçoit sa
| copie. Un inventaire annulé n'a rien écrit et n'envoie rien.
|
*/

beforeEach(function (): void {
    Notification::fake();
    Cache::forget('own_club');

    $this->storeKeeper = User::factory()->withRole(Role::STORE_KEEPER)->create();
    $this->jupiler = BarProduct::create(['name' => 'Jupiler', 'sale_price' => 150, 'is_available' => 1, 'category_id' => BarCategory::create(['name' => 'Bières'])->id]);
    BarStockMovement::create(['product_id' => $this->jupiler->id, 'quantity' => 12, 'remaining_quantity' => 12, 'movement_type' => BarStockMovement::TYPE_IN]);
});

function inventoryMailValidate(User $by, BarProduct $product, int $counted, ?BarInventoryCause $cause = null, string $comment = ''): void
{
    $component = Livewire::actingAs($by)
        ->test('pages::bar.inventory')
        ->call('open')
        ->call('saveCount', $product->id, (string) $counted);

    if ($cause !== null) {
        $component->call('saveCause', $product->id, $cause->value);
    }

    $component->set('comment', $comment)->call('validateInventory')->assertHasNoErrors();
}

function inventoryMailTreasurer(): User
{
    return User::factory()->isCommitteeMember()->create(['committee_role' => CommitteeRolesEnum::TREASURER->value]);
}

it('sends one summary to the treasurer, the store keepers and whoever validates', function (): void {
    $treasurer = inventoryMailTreasurer();
    $otherKeeper = User::factory()->withRole(Role::STORE_KEEPER)->create();
    $admin = User::factory()->isAdmin()->create();
    $president = User::factory()->isCommitteeMember()->create(['committee_role' => CommitteeRolesEnum::PRESIDENT->value]);

    inventoryMailValidate($admin, $this->jupiler, 8, BarInventoryCause::Broken);

    Notification::assertSentToTimes($treasurer, BarInventoryValidatedNotification::class, 1);
    Notification::assertSentToTimes($this->storeKeeper, BarInventoryValidatedNotification::class, 1);
    Notification::assertSentToTimes($otherKeeper, BarInventoryValidatedNotification::class, 1);
    Notification::assertSentToTimes($admin, BarInventoryValidatedNotification::class, 1);
    Notification::assertNotSentTo($president, BarInventoryValidatedNotification::class);
});

it('sends a store keeper who is also the treasurer a single copy', function (): void {
    $both = inventoryMailTreasurer();
    $both->assignRole(Role::STORE_KEEPER->value);

    inventoryMailValidate($both, $this->jupiler, 8, BarInventoryCause::Broken);

    Notification::assertSentToTimes($both, BarInventoryValidatedNotification::class, 1);
});

it('falls back on the club address when nobody is the treasurer', function (): void {
    Club::factory()->ownClub()->create(['email_contact' => 'info@cttob.be']);

    inventoryMailValidate($this->storeKeeper, $this->jupiler, 8, BarInventoryCause::Broken);

    Notification::assertSentOnDemand(
        BarInventoryValidatedNotification::class,
        fn (BarInventoryValidatedNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'info@cttob.be',
    );
});

it('still tells the treasurer when the count was right everywhere', function (): void {
    $treasurer = inventoryMailTreasurer();

    inventoryMailValidate($this->storeKeeper, $this->jupiler, 12);

    Notification::assertSentTo($treasurer, BarInventoryValidatedNotification::class,
        fn (BarInventoryValidatedNotification $notification): bool => str_contains((string) $notification->toMail($treasurer)->render(), __('No gap: every product counted was right.')));
});

it('sends nothing for a cancelled inventory', function (): void {
    inventoryMailTreasurer();

    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.inventory')
        ->call('open')
        ->call('saveCount', $this->jupiler->id, '8')
        ->call('cancel');

    Notification::assertNothingSent();
});

it('tells who validated, what is missing, what happened, the value and the word left', function (): void {
    $treasurer = inventoryMailTreasurer();
    $lemonCoke = BarProduct::create(['name' => 'Coca-Cola citron', 'sale_price' => 200, 'is_available' => 1, 'category_id' => $this->jupiler->category_id]);

    $component = Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.inventory')
        ->call('open')
        ->call('saveCount', $this->jupiler->id, '8')
        ->call('saveCause', $this->jupiler->id, BarInventoryCause::UnrecordedSale->value)
        ->call('saveCount', $lemonCoke->id, '24')
        ->set('comment', 'Comptage fait avec Julie')
        ->call('validateInventory');

    Notification::assertSentTo($treasurer, BarInventoryValidatedNotification::class, function (BarInventoryValidatedNotification $notification) use ($treasurer): bool {
        $mail = $notification->toMail($treasurer);
        $html = (string) $mail->render();

        return str_contains($mail->subject, '4')
            && str_contains($html, $this->storeKeeper->full_name)
            && str_contains($html, 'Jupiler')
            && str_contains($html, BarInventoryCause::UnrecordedSale->label())
            && str_contains($html, euros(-600))
            && str_contains($html, 'Comptage fait avec Julie')
            && str_contains($html, 'Coca-Cola citron');
    });
});

it('links every recipient to an inventory they can open', function (): void {
    $treasurer = inventoryMailTreasurer();
    $admin = User::factory()->isAdmin()->create();

    inventoryMailValidate($admin, $this->jupiler, 8, BarInventoryCause::Broken);

    $url = route('bar.inventories.show', BarInventory::query()->sole());

    foreach ([$treasurer, $this->storeKeeper, $admin] as $recipient) {
        $this->actingAs($recipient)->get($url)->assertOk();
    }
});
