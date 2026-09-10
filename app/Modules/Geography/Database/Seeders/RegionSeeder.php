<?php

namespace App\Modules\Geography\Database\Seeders;

use App\Modules\Geography\Domain\Models\District;
use App\Modules\Geography\Domain\Models\Province;
use App\Modules\Geography\Domain\Models\Regency;
use App\Modules\Geography\Domain\Models\Village;
use Carbon\CarbonInterface;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegionSeeder extends Seeder
{
    use WithoutModelEvents;

    private const CHUNK_SIZE = 1000;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sql = file_get_contents(__DIR__.'/../Data/wilayah.sql');

        preg_match_all("/\(\s*'([^']*)'\s*,\s*'((?:[^']|'')*)'\s*\)/", $sql, $matches, PREG_SET_ORDER);

        $provinces = [];
        $regencies = [];
        $districts = [];
        $villages = [];

        $now = now();

        foreach ($matches as [, $code, $name]) {
            $name = str_replace("''", "'", $name);

            $row = [
                'code' => $code,
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            match (substr_count($code, '.') + 1) {
                1 => $provinces[] = $row,
                2 => $regencies[] = $row + ['type' => str_starts_with($name, 'Kota') ? 'city' : 'regency'],
                3 => $districts[] = $row,
                4 => $villages[] = $row,
            };
        }

        DB::transaction(function () use ($provinces, $regencies, $districts, $villages): void {
            $provinceIds = $this->seedProvinces($provinces);
            $regencyIds = $this->seedRegencies($regencies, $provinceIds);
            $districtIds = $this->seedDistricts($districts, $regencyIds);
            $this->seedVillages($villages, $districtIds, $regencyIds);
        });
    }

    /**
     * @param  list<array{code: string, name: string, created_at: CarbonInterface, updated_at: CarbonInterface}>  $rows
     * @return array<string, int>
     */
    private function seedProvinces(array $rows): array
    {
        $chunks = array_chunk(array_map(fn(array $row) => [
            'code' => $row['code'],
            'name' => $row['name'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ], $rows), self::CHUNK_SIZE);

        foreach ($chunks as $chunk) {
            Province::insert($chunk);
        }

        return Province::whereIn('code', array_column($rows, 'code'))->pluck('id', 'code')->all();
    }

    /**
     * @param  list<array{code: string, name: string, type: string, created_at: CarbonInterface, updated_at: CarbonInterface}>  $rows
     * @param  array<string, int>  $provinceIds
     * @return array<string, int>
     */
    private function seedRegencies(array $rows, array $provinceIds): array
    {
        $chunks = array_chunk(
            array_map(fn(array $row) => [
                'province_id' => $provinceIds[explode('.', $row['code'])[0]],
                'code' => $row['code'],
                'name' => $row['name'],
                'type' => $row['type'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ], $rows),
            self::CHUNK_SIZE
        );

        foreach ($chunks as $chunk) {
            Regency::insert($chunk);
        }

        return Regency::whereIn('code', array_column($rows, 'code'))->pluck('id', 'code')->all();
    }

    /**
     * @param  list<array{code: string, name: string, created_at: CarbonInterface, updated_at: CarbonInterface}>  $rows
     * @param  array<string, int>  $regencyIds
     * @return array<string, int>
     */
    private function seedDistricts(array $rows, array $regencyIds): array
    {
        $chunks = array_chunk(
            array_map(fn(array $row) => [
                'regency_id' => $regencyIds[implode('.', array_slice(explode('.', $row['code']), 0, 2))],
                'code' => $row['code'],
                'name' => $row['name'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ], $rows),
            self::CHUNK_SIZE
        );

        foreach ($chunks as $chunk) {
            District::insert($chunk);
        }

        return District::whereIn('code', array_column($rows, 'code'))->pluck('id', 'code')->all();
    }

    /**
     * @param  list<array{code: string, name: string, created_at: CarbonInterface, updated_at: CarbonInterface}>  $rows
     * @param  array<string, int>  $districtIds
     * @param  array<string, int>  $regencyIds
     */
    private function seedVillages(array $rows, array $districtIds, array $regencyIds): void
    {
        $regencyTypes = Regency::whereIn('id', array_values($regencyIds))->pluck('type', 'code')->all();

        $chunks = array_chunk(
            array_map(fn(array $row) => [
                'district_id' => $districtIds[implode('.', array_slice(explode('.', $row['code']), 0, 3))],
                'code' => $row['code'],
                'name' => $row['name'],
                'type' => $regencyTypes[implode('.', array_slice(explode('.', $row['code']), 0, 2))] === 'city' ? 'urban_village' : 'village',
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ], $rows),
            self::CHUNK_SIZE
        );

        foreach ($chunks as $chunk) {
            Village::insert($chunk);
        }
    }
}
