<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankSeeder extends Seeder
{
    use WithoutModelEvents;

    private const CHUNK_SIZE = 100;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $banks = require database_path('data/banks.php');

        $now = now();

        $rows = array_map(fn(array $bank): array => $bank + [
            'created_at' => $now,
            'updated_at' => $now,
        ], $banks);

        DB::transaction(function () use ($rows): void {
            foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
                Bank::insert($chunk);
            }
        });
    }
}
