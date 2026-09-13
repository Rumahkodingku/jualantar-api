<?php

namespace App\Modules\Service\Database\Seeders;

use App\Modules\Service\Domain\Models\Service;
use App\Modules\Service\Domain\Models\ServiceCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $services = [
            'jafood' => [
                'name' => 'JAfood',
                'description' => 'Layanan makanan dan minuman',
                'icon' => 'utensils',
            ],
            'jamart' => [
                'name' => 'JAmart',
                'description' => 'Layanan kebutuhan harian dan sembako',
                'icon' => 'shopping-cart',
            ],
            'jaride' => [
                'name' => 'JAride',
                'description' => 'Layanan transportasi dan pengiriman',
                'icon' => 'car',
            ],
            'jastip' => [
                'name' => 'Jastip',
                'description' => 'Layanan titip beli',
                'icon' => 'shopping-bag',
            ],
        ];

        $categories = [
            'jafood' => [
                'makanan' => ['name' => 'Makanan', 'description' => 'Kategori makanan', 'icon' => 'utensils'],
                'minuman' => ['name' => 'Minuman', 'description' => 'Kategori minuman', 'icon' => 'cup-soda'],
                'snack' => ['name' => 'Snack', 'description' => 'Kategori camilan', 'icon' => 'cookie'],
                'dessert' => ['name' => 'Dessert', 'description' => 'Kategori hidangan penutup', 'icon' => 'ice-cream-cone'],
            ],
            'jamart' => [
                'sembako' => ['name' => 'Sembako', 'description' => 'Kebutuhan pokok sehari-hari', 'icon' => 'shopping-basket'],
                'minuman' => ['name' => 'Minuman', 'description' => 'Kategori minuman', 'icon' => 'cup-soda'],
                'kebutuhan-rumah' => ['name' => 'Kebutuhan Rumah', 'description' => 'Peralatan dan kebutuhan rumah', 'icon' => 'house'],
                'personal-care' => ['name' => 'Personal Care', 'description' => 'Perawatan diri', 'icon' => 'sparkles'],
            ],
        ];

        DB::transaction(function () use ($services, $categories): void {
            foreach ($services as $slug => $attributes) {
                $service = Service::updateOrCreate(
                    ['slug' => $slug],
                    $attributes + ['is_active' => true],
                );

                foreach ($categories[$slug] ?? [] as $categorySlug => $categoryAttributes) {
                    ServiceCategory::updateOrCreate(
                        ['service_id' => $service->getKey(), 'slug' => $categorySlug],
                        $categoryAttributes + ['is_active' => true],
                    );
                }
            }
        });
    }
}
