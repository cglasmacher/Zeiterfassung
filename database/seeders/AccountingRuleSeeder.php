<?php

namespace Database\Seeders;

use App\Models\AccountingAccount;
use App\Models\AccountingRule;
use App\Models\AccountingSupplier;
use Illuminate\Database\Seeder;

class AccountingRuleSeeder extends Seeder
{
    public function run(): void
    {
        $supplierRules = [
            ['creditor' => '70003', 'match_type' => 'supplier_default', 'match_value' => '*', 'account' => '6495', 'tax_rate' => 19, 'priority' => 100],
            ['creditor' => '70019', 'match_type' => 'supplier_default', 'match_value' => '*', 'account' => '6495', 'tax_rate' => 19, 'priority' => 100],
            ['creditor' => '70020', 'match_type' => 'supplier_default', 'match_value' => '*', 'account' => '6420', 'tax_rate' => null, 'priority' => 100],
            ['creditor' => '70016', 'match_type' => 'supplier_default', 'match_value' => '*', 'account' => '5133', 'tax_rate' => 19, 'priority' => 100],
            ['creditor' => '70023', 'match_type' => 'supplier_default', 'match_value' => '*', 'account' => '5133', 'tax_rate' => 19, 'priority' => 100],
            ['creditor' => '70028', 'match_type' => 'supplier_default', 'match_value' => '*', 'account' => '5133', 'tax_rate' => 19, 'priority' => 100],
            ['creditor' => '70004', 'match_type' => 'description_contains', 'match_value' => 'rankingcoach', 'account' => '6600', 'tax_rate' => 19, 'priority' => 10],
        ];

        foreach ($supplierRules as $rule) {
            $supplier = AccountingSupplier::where('creditor_account', $rule['creditor'])->first();
            $account = AccountingAccount::where('account_number', $rule['account'])->first();

            if (! $supplier || ! $account) {
                continue;
            }

            AccountingRule::updateOrCreate(
                [
                    'supplier_id' => $supplier->id,
                    'match_type' => $rule['match_type'],
                    'match_value' => $rule['match_value'],
                ],
                [
                    'account_id' => $account->id,
                    'tax_rate' => $rule['tax_rate'],
                    'priority' => $rule['priority'],
                    'learned_from_manual' => false,
                    'active' => true,
                ]
            );
        }
    }
}
