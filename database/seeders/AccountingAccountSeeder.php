<?php

namespace Database\Seeders;

use App\Models\AccountingAccount;
use Illuminate\Database\Seeder;

class AccountingAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['account_number' => '1141', 'name' => 'Pfand', 'kind' => 'interim'],
            ['account_number' => '1406', 'name' => 'Vorsteuer Pfand', 'kind' => 'tax'],
            ['account_number' => '1462', 'name' => 'Geldtransit Shift4', 'kind' => 'interim'],
            ['account_number' => '1600', 'name' => 'Kasse', 'kind' => 'asset'],
            ['account_number' => '1800', 'name' => 'Bank', 'kind' => 'asset'],
            ['account_number' => '4300', 'name' => 'Speisen', 'kind' => 'revenue'],
            ['account_number' => '4400', 'name' => 'Getränke', 'kind' => 'revenue'],
            ['account_number' => '5110', 'name' => 'Allgemeiner Wareneinkauf / Speisen 7 %', 'kind' => 'expense'],
            ['account_number' => '5111', 'name' => 'Fleischeinkauf', 'kind' => 'expense'],
            ['account_number' => '5112', 'name' => 'Fischeinkauf', 'kind' => 'expense'],
            ['account_number' => '5113', 'name' => 'Obst / Gemüse', 'kind' => 'expense'],
            ['account_number' => '5130', 'name' => 'Wareneinkauf Küche 19 %', 'kind' => 'expense'],
            ['account_number' => '5131', 'name' => 'AFG / alkoholfreie Getränke', 'kind' => 'expense'],
            ['account_number' => '5132', 'name' => 'Bier', 'kind' => 'expense'],
            ['account_number' => '5133', 'name' => 'Wein / Sekt / Prosecco', 'kind' => 'expense'],
            ['account_number' => '5134', 'name' => 'Spirituosen', 'kind' => 'expense'],
            ['account_number' => '6301', 'name' => 'Küchen- / Service- / Betriebsbedarf', 'kind' => 'expense'],
            ['account_number' => '6420', 'name' => 'Beiträge', 'kind' => 'expense'],
            ['account_number' => '6495', 'name' => 'Wartung Hard- / Software', 'kind' => 'expense'],
            ['account_number' => '6600', 'name' => 'Werbung / Marketing', 'kind' => 'expense'],
            ['account_number' => '6810', 'name' => 'Internetkosten', 'kind' => 'expense'],
            ['account_number' => '6855', 'name' => 'Nebenkosten des Geldverkehrs', 'kind' => 'expense'],
        ];

        foreach ($accounts as $account) {
            AccountingAccount::updateOrCreate(
                ['account_number' => $account['account_number']],
                [...$account, 'active' => true]
            );
        }
    }
}
