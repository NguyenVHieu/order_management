<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 0; $i < 100; $i++) {
            DB::table('tasks')->insert([
                'title' => 'Task ' . ($i + 1),
                'description' => 'This is a description for task ' . ($i + 1),
                'status_id' => rand(1, 7),
                'created_by' => rand(1, 10), // Ví dụ ID người tạo từ 1 đến 10
                'category_design_id' => rand(1, 5), // Ví dụ ID danh mục từ 1 đến 5
                'designer_tag' => rand(1, 10), // Gán tag cho designer
                'deadline' => now()->addDays(rand(1, 30)), // Deadline ngẫu nhiên trong vòng 30 ngày
                'updated_by' => rand(1, 10), // ID người cập nhật ngẫu nhiên
                'url_done' => 'https://example.com/task-' . ($i + 1), // URL mẫu
                'level_task' => rand(1, 3) // Gán cấp độ ngẫu nhiên từ 1 đến 3
            ]);
        }
    }
}
