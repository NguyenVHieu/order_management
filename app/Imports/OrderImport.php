<?php
namespace App\Imports;

use App\Models\Order;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

class OrderImport implements ToCollection, WithStartRow
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) 
        {
            $orderNumber = $row[28];
            if (empty($orderNumber)) 
            {
                continue;
            }
            $orderNumber = str_replace('#', '', $orderNumber);
            $order = Order::where('order_number_group', $orderNumber)->first();
            if (!$order) {
                continue;
            }
            $order->update(['cost' => $row[8]]);

            $data = [
                'status_order' => $row[3],
                'tracking_order' => $row[23] === 'DELAY' ? null : $row[23],
            ];
            Order::where('order_number_group', $orderNumber)->update($data);
        }
    }


    public function startRow(): int
    {
        return 2;
    }
    
}