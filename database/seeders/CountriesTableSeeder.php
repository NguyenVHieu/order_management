<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Webpatser\Countries\Countries;
use League\ISO3166\ISO3166;

class CountriesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {   
        // Xóa dữ liệu cũ để tránh trùng lặp
        Countries::truncate();

        // Sử dụng thư viện league/iso3166 để lấy danh sách quốc gia
        $iso3166 = new ISO3166();
        $countries = $iso3166->all();

        foreach ($countries as $country) {
            Countries::create([
                'name' => $country['name'],
                'iso_alpha_2' => $country['alpha2'],
                'iso_alpha_3' => $country['alpha3'],
                'iso_numeric' => $country['numeric'],
            ]);
        }
    }
}
