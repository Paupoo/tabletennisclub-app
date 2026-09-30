<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The club's bank accounts, and the account each statement line belongs to.
 *
 * Until now the club had one account, written on the club record, and every
 * transaction implicitly belonged to it. That account becomes the first row
 * here, and the transactions already imported are attached to it. Without an
 * IBAN on the club record there is nothing safe to derive: the lines stay
 * unattached and the next import registers the account.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropColumn(['balance_after', 'statement_number', 'is_internal']);
        });

        Schema::dropIfExists('bank_accounts');
    }

    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('iban', 34)->unique();
            $table->string('name');
            $table->string('type', 16)->default('current');
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignId('bank_account_id')->nullable()->after('id')->constrained('bank_accounts')->nullOnDelete();
            $table->bigInteger('balance_after')->nullable()->after('amount');
            $table->string('statement_number', 32)->nullable()->after('balance_after');
            $table->boolean('is_internal')->default(false)->after('settled_by_id');
        });

        $this->attachExistingTransactionsToTheClubAccount();
    }

    private function attachExistingTransactionsToTheClubAccount(): void
    {
        $clubIban = DB::table('clubs')->where('is_own_club', true)->value('bank_account');
        $iban = $clubIban === null ? '' : strtoupper(str_replace([' ', '-'], '', (string) $clubIban));

        if ($iban === '') {
            return;
        }

        $now = now();
        $accountId = DB::table('bank_accounts')->insertGetId([
            'iban' => $iban,
            'name' => __('Current account'),
            'type' => 'current',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('transactions')->whereNull('bank_account_id')->update(['bank_account_id' => $accountId]);
    }
};
