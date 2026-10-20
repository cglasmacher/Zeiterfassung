<?php

namespace Database\Seeders;

use App\Models\AccountingSupplier;
use Illuminate\Database\Seeder;

class AccountingSupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['name' => 'Haufe Service Center GmbH', 'creditor_account' => '70003', 'aliases' => ['Lexware', 'Haufe-Lexware GmbH & Co. KG']],
            ['name' => 'IONOS SE', 'creditor_account' => '70004', 'aliases' => ['IONOS']],
            ['name' => 'Getränke Gato GmbH', 'creditor_account' => '70007', 'aliases' => ['Getraenke Gato GmbH', 'Getränke GATO', 'Gato']],
            ['name' => 'Handelshof Köln Stiftung & Co. KG', 'creditor_account' => '70012', 'aliases' => ['Handelshof', 'Handelshof Haan']],
            ['name' => 'Selgros Hilden', 'creditor_account' => '70013', 'aliases' => ['Selgros', 'Transgourmet Deutschland GmbH & Co. OHG']],
            ['name' => 'Dirk Middendorf GmbH', 'creditor_account' => '70016', 'aliases' => ['Middendorf', 'Middendorf Internationale Weine']],
            ['name' => 'ISB Seafood GmbH', 'creditor_account' => '70018', 'aliases' => ['ISB Seafood']],
            ['name' => 'Vectron Systems AG', 'creditor_account' => '70019', 'aliases' => ['Vectron']],
            ['name' => 'GEMA', 'creditor_account' => '70020', 'aliases' => ['GEMA Gesellschaft für musikalische Aufführungs- und mechanische Vervielfältigungsrechte']],
            ['name' => 'Weingut Karl Wegner', 'creditor_account' => '70023', 'aliases' => ['Weingut Wegner', 'Karl Wegner']],
            ['name' => 'Domaine des Feraud', 'creditor_account' => '70028', 'aliases' => ['Domaines Feraud', 'Domaine des Féraud']],
            ['name' => 'BOS FOOD', 'creditor_account' => '70030', 'aliases' => ['BOSFOOD', 'BOS FOOD Düsseldorf']],
        ];

        foreach ($suppliers as $supplier) {
            AccountingSupplier::updateOrCreate(
                ['creditor_account' => $supplier['creditor_account']],
                [...$supplier, 'active' => true]
            );
        }
    }
}
