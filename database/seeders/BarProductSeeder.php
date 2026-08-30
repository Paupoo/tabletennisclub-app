<?php

namespace Database\Seeders;

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarProduct;
use Illuminate\Database\Seeder;

class BarProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Beers' => BarCategory::firstOrCreate(['name' => 'Bières']),
            'Softs' => BarCategory::firstOrCreate(['name' => 'Softs']),
            'Snacks' => BarCategory::firstOrCreate(['name' => 'Snacks']),
        ];

        $products = [
            [
                'name' => 'Eau plate',
                'sale_price' => cents('1.50'),
                'is_available' => true,
                'category_id' => $categories['Softs']->id,
            ],
            [
                'name' => 'Coca-Cola',
                'sale_price' => cents('2.50'),
                'is_available' => true,
                'category_id' => $categories['Softs']->id,
            ],
            [
                'name' => 'Jupiler',
                'sale_price' => cents('3.00'),
                'is_available' => true,
                'category_id' => $categories['Beers']->id,
            ],
            [
                'name' => 'Chips sel',
                'sale_price' => cents('1.50'),
                'is_available' => true,
                'category_id' => $categories['Snacks']->id,
            ],
        ];

        foreach ($products as $product) {
            BarProduct::firstOrCreate(
                ['name' => $product['name']],
                $product
            );
        }
    }
}