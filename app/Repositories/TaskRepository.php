<?php
namespace App\Repositories;

use App\Repositories\Interfaces\TaskRepositoryInterface;
use App\Models\Task;

class TaskRepository implements TaskRepositoryInterface
{
    public function getAllTasks()
    {
        return Task::with(['status', 'images', 'designer', 'createdBy'])->orderBy('created_at', 'DESC')->get();
    }

    public function getTaskById($id)
    {
        return Task::with('images')->findOrFail($id);
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
