<?php
namespace App\Repositories;

use App\Repositories\Interfaces\TaskRepositoryInterface;
use App\Models\Task;
use App\Models\TaskHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TaskRepository implements TaskRepositoryInterface
{
    public function getAllTasks($params)
    {
        $query = Task::with(['status', 'images', 'designer', 'createdBy'])
            ->where('tasks.status_id', $params['status_id'])
            ->orderBy('tasks.created_at', 'DESC')
            ->select('tasks.*');    

        if (!empty($params['user_id']) && $params['user_type_id'] != -1) {
            if ($params['user_type_id'] == 4) {
                $query->where(function($query) use ($params) {
                    $query->where('tasks.design_recipient_id', $params['user_id']);
                    
                    if (in_array($params['status_id'], [1, 2])) {
                        $query->orWhereNull('tasks.design_recipient_id');
                    }
                });
            } else {
                $query->join('users', 'users.id', '=', 'tasks.created_by');
                $query->join('teams', 'teams.id', '=', 'users.team_id');

                $query->where('teams.id', $params['team_id']);

                if ($params['user_type_id'] == 2){
                    $query->where('tasks.created_by', $params['user_id']);
                }
            }
        }

        if (!empty($params['keyword'])) {
            $query->where(function($q) use ($params) {
                $q->where('tasks.title', 'like', '%' . $params['keyword'] . '%')
                  ->orWhere('tasks.description', 'like', '%' . $params['keyword'] . '%');
            });
        }

        if (!empty($params['created_by'])) {
            $query->where('tasks.created_by', $params['created_by']);
        }

        if (!empty($params['design_recipient_id'])) {
            $query->where('tasks.design_recipient_id', $params['design_recipient_id']);
        }

        if ($params['status_id'] == 7) {
            $daysAgo = Carbon::now()->subDays(6)->startOfDay();
            $today = Carbon::now()->endOfDay();
            $query->whereBetween('tasks.created_at', [$daysAgo, $today]);
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

    public function getHistory($id)
    {
        return TaskHistory::where('task_id', $id)->orderBy('created_at', 'DESC')->paginate(5);
    
    }

    public function getTaskDone()
    {
        return Task::with(['status', 'images', 'designer', 'createdBy'])->where('status_id', 6)->paginate(12);
    }

    public function reportTaskByDesigner($params)
    {
        $query = Task::select(
            DB::raw('CAST(SUM(tasks.count_product) AS FLOAT) AS count'),
            'users2.name AS recipient_name'
        )
        ->leftJoin('users', 'users.id', '=', 'tasks.created_by') // Join với bảng users
        ->leftJoin('users as users2', 'users2.id', '=', 'tasks.design_recipient_id') // Join với bảng users2
        ->where('tasks.status_id', 6)
        ->whereBetween('tasks.created_at', [$params['startDate'], $params['endDate']])
        ->groupBy('users2.name'); // Nhóm thêm theo cột name để tránh lỗi SQL
        if ($params['userTypeId'] != -1) {
            $query->where('users.team_id', $params['teamId']);
        }

        return $query->get();
    }

    public function reportTaskBySeller($params)
    {
        $query = Task::select(
            DB::raw('CAST(SUM(tasks.count_product) AS FLOAT) AS count'),
            'users.name AS seller_name'
        )
        ->leftJoin('users', 'users.id', '=', 'tasks.created_by') // Join với bảng users
        ->where('tasks.status_id', 6)
        ->whereBetween('tasks.created_at', [$params['startDate'], $params['endDate']])
        ->groupBy('users.name'); // Nhóm thêm theo cột name để tránh lỗi SQL

        if (!empty($params['userTypeId'] != -1)) {
            $query->where('users.team_id', $params['teamId']);
        }

        return $query->get();
    }

    public function totalCountTask($params)
    {
        $query = Task::select(
            DB::raw('SUM(tasks.count_product) AS total_count')
        )
        ->leftJoin('users', 'users.id', '=', 'tasks.created_by') // Join với bảng users
        ->where('tasks.status_id', 6)
        ->whereBetween('tasks.created_at', [$params['startDate'], $params['endDate']]);

        if ($params['userTypeId'] != -1) {
            $query->where('users.team_id', $params['teamId']);
        }

        $result = $query->first();

        return $result ? (float)$result->total_count : 0;
    }   
}
