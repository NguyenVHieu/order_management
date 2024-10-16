<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Webpatser\Countries\Countries;
use League\ISO3166\ISO3166;

class CategoryProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {   
        $data = [
            "Printed Baby Clothes",
            "Printed T-shirts",
            "Printed Sweatshirts",
            "Printed Hoodies",
            "Printed Long Sleeve",
            "Printed Tank Top",
            "Embroidered T-shirts",
            "Embroidered Sweatshirts",
            "Embroidered Hoodies",
            "Jersey",
            "Garden Flag",
            "House Flag",
            "Wall Flag",
            "Big Size Flag",
            "Minky Blanket",
            "Fleece Blanket",
            "Sherpa Blanket",
            "Woven Blanket",
            "Poster",
            "Canvas",
            "Wood Sign",
            "Pillow",
            "Tree Skirt",
            "Sack",
            "Ornament",
            "Cap"
        ];

        foreach ($data as $category) {
            DB::table('categories')->insert([
                'name' => $category
            ]);
        }
    }
}
