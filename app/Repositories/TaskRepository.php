<?php
namespace App\Repositories;

use App\Models\KpiUser;
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

        if ($params['status_id'] == 6) {
            $daysAgo = Carbon::now()->subDays(13)->startOfDay();
            $today = Carbon::now()->endOfDay();
            $query->whereBetween('tasks.created_at', [$daysAgo, $today]);
            return $query->get();
        }

        return $query->paginate(12);
    }

    public function getTaskById($id)
    {
        return Task::with(['images', 'status', 'template.product', 'template.platformSize'])->findOrFail($id);
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

        public function getTaskDone($params)
        {
            $query = Task::with(['status', 'images', 'designer', 'createdBy']);

            if (!empty($params['userTypeId']) && $params['userTypeId'] != -1) {
                
                if (!empty($params['userTypeId']) && $params['userTypeId'] != -1) {
                    if ($params['userTypeId'] == 3) {
                        $query->whereHas('createdBy.team', function ($q) use ($params) {
                            $q->where('id', $params['teamId']);
                        });
                    } elseif ($params['userTypeId'] == 1) {
                        $query->where('created_by', $params['userId']);
                    } elseif ($params['userTypeId'] == 4) {
                        $query->where('design_recipient_id', $params['userId']);
                    }
                }
            }

            if (!empty($params['keyword'])) {
                $query->where(function($q) use ($params) {
                    $q->where('tasks.title', 'like', '%' . $params['keyword'] . '%')
                    ->orWhere('tasks.description', 'like', '%' . $params['keyword'] . '%');
                });
            }
            
            $query->where('status_id', 6);

               // Xử lý tham số sort
        if (!empty($params['sort'])) {
            switch ($params['sort']) {
                case 1:
                    $query->orderBy('done_at', 'ASC'); // Sắp xếp theo time_done tăng dần
                    break;
                case 2:
                    $query->orderBy('done_at', 'DESC'); // Sắp xếp theo time_done giảm dần
                    break;
                case 3:
                    $query->orderBy('created_at', 'ASC'); // Sắp xếp theo created_at tăng dần
                    break;
                case 4:
                    $query->orderBy('created_at', 'DESC'); // Sắp xếp theo created_at giảm dần
                    break;
                default:
                    $query->orderBy('created_at', 'DESC'); // Mặc định
            }
        } else {
            // Sắp xếp mặc định nếu không có sort
            $query->orderBy('created_at', 'DESC');
        }

            return $query->paginate(32);
        }

    public function reportTaskByDesigner($params)
    {
        $year_month = Carbon::parse($params['startDate'])->format('Y-m');

        $query = Task::select(
            DB::raw('CAST(SUM(tasks.count_product) AS FLOAT) AS count'),
            'users2.name AS recipient_name',
            'kpi_users.kpi AS kpi'
        )
        ->leftJoin('users', 'users.id', '=', 'tasks.created_by') // Join với bảng users
        ->leftJoin('users as users2', 'users2.id', '=', 'tasks.design_recipient_id') // Join với bảng users2
        ->leftJoin('kpi_users', function($join) use ($year_month) {
            $join->on('kpi_users.user_id', '=', 'users2.id')
            ->where('kpi_users.year_month', $year_month);
        })
        ->where('tasks.status_id', 6)
        ->whereBetween('tasks.created_at', [$params['startDate'], $params['endDate']])
        ->groupBy('users2.name', 'kpi_users.kpi'); // Nhóm thêm theo cột name để tránh lỗi SQL
        if ($params['userTypeId'] != -1) {
            $query->where('users.team_id', $params['teamId']);
        }

        return $query->get();
    }

    public function reportTaskBySeller($params)
    {
        $year_month = Carbon::parse($params['startDate'])->format('Y-m');

        $query = Task::select(
            DB::raw('CAST(SUM(tasks.count_product) AS FLOAT) AS count'),
            'users.name AS seller_name',
            'kpi_users.kpi AS kpi'
        )
        ->leftJoin('users', 'users.id', '=', 'tasks.created_by') // Join với bảng users
        ->leftJoin('kpi_users', function($join) use ($year_month) {
            $join->on('kpi_users.user_id', '=', 'users.id')
            ->where('kpi_users.year_month', $year_month);
        })
        ->where('tasks.status_id', 6)
        ->whereBetween('tasks.created_at', [$params['startDate'], $params['endDate']])
        ->groupBy('users.name', 'kpi_users.kpi'); // Nhóm thêm theo cột name để tránh lỗi SQL

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


    public function reportTaskByTeam($params)
    {
        $query = Task::select(
            DB::raw('CAST(SUM(tasks.count_product) AS FLOAT) AS count'),
            'teams.name AS team_name'
        )
        ->leftJoin('users', 'users.id', '=', 'tasks.created_by') // Join với bảng users
        ->leftJoin('teams', 'teams.id', '=', 'users.team_id') // Join với bảng teams
        ->where('tasks.status_id', 6)
        ->whereBetween('tasks.created_at', [$params['startDate'], $params['endDate']])
        ->groupBy('teams.name'); // Nhóm thêm theo cột name để tránh lỗi SQL

        return $query->get();
    }

    public function getScoreBySeller($params)
    {
        $start_date = Carbon::parse($params['year_month'])->startOfMonth();
        $end_date = Carbon::parse($params['year_month'])->endOfMonth();

        $query = Task::select(
            DB::raw('SUM(tasks.count_product) AS total_count'))
        ->whereBetween('created_at', [$start_date, $end_date])  
        ->where('tasks.created_by', $params['seller_id'])
        ->pluck('total_count')
        ->first();

        return $query;
    }

    public function getKpiSeller($params)
    {
        $query = KpiUser::where('user_id', $params['seller_id'])
            ->where('year_month', $params['year_month'])
            ->pluck('kpi')  
            ->first();

        return $query;  

            
    }

    
}
