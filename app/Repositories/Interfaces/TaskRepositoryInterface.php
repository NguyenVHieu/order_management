<?php
namespace App\Repositories\Interfaces;

use App\Models\Task;

interface TaskRepositoryInterface
{
    public function getAllTasks($params);
    public function getTaskById($id);
    public function createTask(array $data);
    public function updateTask($id, array $data);
    public function deleteTask($id);
}
