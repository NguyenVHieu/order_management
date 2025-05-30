<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OrdersExport implements FromCollection, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $columns = [
            'order_number',
            'place_order',
            'cost',
            'date_push',
            'style',
            'quantity'
        ];
        return Order::query()
            ->whereBetween('date_push', ['2025-05-01', '2025-05-31'])
            ->where('shop_id', 10)
            ->where('is_push', true)
            ->select($columns)
            ->get();
        
    }

     public function headings(): array
    {
        // Tiêu đề cho các cột
        return ['Order Number', 'Place Order', 'Cost', 'Date Push', 'Style', 'Quantity'];
    }
}
