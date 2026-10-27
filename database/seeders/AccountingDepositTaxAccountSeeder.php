<?php

namespace Database\Seeders;

use App\Models\AccountingAccount;
use Illuminate\Database\Seeder;

class AccountingDepositTaxAccountSeeder extends Seeder
{
    public function run(): void
    {
        // Existing chart entries, including names and deliberate deactivation, stay intact.
        AccountingAccount::firstOrCreate(['account_number' => '1461'], [
            'name' => 'Vorsteuer Pfand', 'kind' => 'tax', 'active' => true,
        ]);
    }
}
