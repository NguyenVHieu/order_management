<?php

namespace App\Imports;

use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class OrderImport implements WithMultipleSheets 
{
    public function sheets(): array
    {
        return [
            new FirstSheetImport()
        ];
    }
}

class FirstSheetImport implements ToCollection, WithStartRow, WithCalculatedFormulas
{
    public function startRow(): int
    {
        return 4; 
    }

    public function collection(Collection $rows)
    {
        $data = [];
        foreach($rows as $row) {
            if (empty($row[0])) {
                continue;
            }

            $date_push = Date::excelToDateTimeObject($row[0])->format('Y-m-d');
            $order_number = $row[1];
            $quantity = $row[2];
            $size = $row[3];
            $img_1 = $row[4];
            $name = $row[5];
            $address = $row[6];
            $city = $row[7];
            $zip = $row[8];
            $state = $row[9];
            $country = $row[10];
            $img_6 = $row[11];
            $status_order = $row[12];
            $tracking_order = $row[13];
            $seller = $row[14];
            $shop = $row[15];
            $base_cost = $row[16];
            $note = $row[17];
            $support = $row[18];
            $link = $row[19];
            if (!empty($shop)) {
                $shop_data = Shop::where('name', $shop)->first();
                if (empty($shop_data)) {
                    $shop_data = Shop::create(['name' => $shop])->fresh();  
                }
            }

            if (!empty($seller)) {
                $seller_data = User::where('name', $seller)->first();
                if (empty($seller_data)) {
                    $seller_data = User::create(['name' => $seller, 'email' => $seller.'@gmail.com', 'password' => '123', 'user_type' => 1])->fresh();  
                }
            }

            $sp_data = User::where('name', $support)->first();

            $data[] = [
                'order_number' => $order_number,
                'place_order' => 'interest',
                'shop_id' => $shop_data->id,
                'size' => $size,
                'personalization' => $note,
                'img_1' => $img_1,
                'quantity' => $quantity,
                'first_name' => explode(" ", $name)[0] ?? '',
                'last_name' => explode(" ", $name)[1] ?? '',
                'zip' => $zip,
                'country' => $country,
                'state' => $state,
                'city' =>$city,
                'address' => $address,
                'img_6' => $img_6,
                'is_push' => 1,
                'is_approval' => 1,
                'status_order' => $status_order,
                'tracking_order' => $tracking_order,
                'cost' => $base_cost,
                'date_push' => $date_push,
                'approval_by' => !empty($seller_data->id) ? $seller_data->id : $seller,
                'push_by' => !empty($sp_data->id) ? $sp_data->id : null,
            ];
        }   

        collect($data)->chunk(100)->each(function ($data) {
            Order::insert($data->toArray());
        }); 
        
    }

    public function chunkSize(): int
    {
        return 500; // Số lượng bản ghi mỗi lần đọc
    }
}