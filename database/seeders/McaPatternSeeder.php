<?php

namespace Database\Seeders;

use App\Models\McaPattern;
use Illuminate\Database\Seeder;

class McaPatternSeeder extends Seeder
{
    public function run(): void
    {
        $lenders = McaPattern::getKnownLenders();
        $debtCollectors = McaPattern::getKnownDebtCollectors();

        foreach ($lenders as $id => $name) {
            McaPattern::firstOrCreate(
                ['lender_id' => $id, 'description_pattern' => strtolower($name)],
                [
                    'lender_name' => $name,
                    'is_mca'      => true,
                    'usage_count' => 0,
                ]
            );
        }

        foreach ($debtCollectors as $id => $name) {
            McaPattern::firstOrCreate(
                ['lender_id' => $id, 'description_pattern' => strtolower($name)],
                [
                    'lender_name' => $name,
                    'is_mca'      => true,
                    'usage_count' => 0,
                ]
            );
        }

        $this->command->info('Seeded ' . count($lenders) . ' lenders and ' . count($debtCollectors) . ' debt collectors.');
    }
}
