<?php

namespace Database\Seeders;

use App\Models\AccountingAccount;
use Illuminate\Database\Seeder;

class AccountingInvoiceClearingAccountSeeder extends Seeder
{
    public function run(): void
    {
        // Explicitly chosen for invoice imports. Preserve existing names, kind and activation.
        AccountingAccount::firstOrCreate(['account_number' => '1370'], [
            'name' => 'Verrechnung Rechnungsimport', 'kind' => 'interim', 'active' => true,
        ]);
    }
}
