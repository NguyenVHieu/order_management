<?php
namespace App\Repositories;

use App\Repositories\Interfaces\TaskRepositoryInterface;
use App\Models\Task;
use Carbon\Carbon;

class TaskRepository implements TaskRepositoryInterface
{
    public function getAllTasks($params)
    {
        $query = Task::with(['status', 'images', 'designer', 'createdBy'])
            ->where('status_id', $params['status_id'])
            ->orderBy('created_at', 'DESC');

        if ($params['status_id'] == 7) {
            $daysAgo = Carbon::now()->subDays(6)->startOfDay();
            $today = Carbon::now()->endOfDay();
            $query->whereBetween('created_at', [$daysAgo, $today]);
            return $query->get();
        }

        return $query->paginate(12);
    }

    public function getTaskById($id)
    {
        return Task::with(['images', 'status'])->findOrFail($id);
    }

    public function createTask(array $data)
    {
        return Task::create($data);
        // if (!empty($data['images'])) {
        //     foreach ($data['images'] as $imageUrl) {
        //         $task->images()->create(['image_url' => $imageUrl]);
        //     }
        // }
    }

    public function updateTask($id, array $data)
    {
        $task = Task::findOrFail($id);
        $task->update($data);

        // Nếu có ảnh mới, xóa ảnh cũ và thêm ảnh mới
        if (!empty($data['images'])) {
            $task->images()->delete();
            foreach ($data['images'] as $imageUrl) {
                $task->images()->create(['image_url' => $imageUrl]);
            }
        }
        return $task;
    }

    public function deleteTask($id)
    {
        $task = Task::findOrFail($id);
        $task->delete();
    }
}
