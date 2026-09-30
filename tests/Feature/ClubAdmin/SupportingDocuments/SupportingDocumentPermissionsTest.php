<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\LinkSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

function sdPermLinkedToBank(SupportingDocument $document): void
{
    (new LinkSupportingDocument)($document, Transaction::create(['date' => '2026-09-01', 'description' => 'X', 'amount' => -10]));
}

it('serves a document\'s file inline, locked down, to the treasury readers', function (Role $role): void {
    $file = SupportingDocument::factory()->create()->files()->sole();

    $this->actingAs(User::factory()->withRole($role)->create())
        ->get(route('admin.treasury.supporting-documents.file', $file))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeaderContains('Content-Security-Policy', 'sandbox');
})->with([Role::TREASURY, Role::COMMITTEE, Role::ACCOUNTS_AUDIT]);

it('keeps the files away from a plain member', function (): void {
    $file = SupportingDocument::factory()->create()->files()->sole();

    $this->actingAs(User::factory()->create())
        ->get(route('admin.treasury.supporting-documents.file', $file))
        ->assertForbidden();
});

it('lets the treasury file, correct and link any document', function (): void {
    $treasurer = User::factory()->withRole(Role::TREASURY)->create();
    $document = SupportingDocument::factory()->create();
    sdPermLinkedToBank($document);

    expect($treasurer->can('create', SupportingDocument::class))->toBeTrue()
        ->and($treasurer->can('update', $document))->toBeTrue()
        ->and($treasurer->can('linkTransaction', SupportingDocument::class))->toBeTrue()
        ->and($treasurer->can('linkCashRegisterEntry', $document))->toBeTrue();
});

it('lets the cash register délégation file a ticket for its till, never touch the bank', function (): void {
    $cashier = User::factory()->withRole(Role::CASH_REGISTER)->create();
    $tillTicket = SupportingDocument::factory()->create();
    $bankInvoice = SupportingDocument::factory()->create();
    sdPermLinkedToBank($bankInvoice);

    expect($cashier->can('create', SupportingDocument::class))->toBeTrue()
        ->and($cashier->can('linkCashRegisterEntry', $tillTicket))->toBeTrue()
        ->and($cashier->can('view', $tillTicket))->toBeTrue()
        ->and($cashier->can('linkTransaction', SupportingDocument::class))->toBeFalse()
        ->and($cashier->can('update', $bankInvoice))->toBeFalse()
        ->and($cashier->can('view', $bankInvoice))->toBeFalse()
        ->and($cashier->can('linkCashDeposit', SupportingDocument::class))->toBeFalse();
});

it('lets the committee read documents, never write them', function (): void {
    $member = User::factory()->withRole(Role::COMMITTEE)->create();
    $document = SupportingDocument::factory()->create();

    expect($member->can('view', $document))->toBeTrue()
        ->and($member->can('create', SupportingDocument::class))->toBeFalse()
        ->and($member->can('update', $document))->toBeFalse();
});
