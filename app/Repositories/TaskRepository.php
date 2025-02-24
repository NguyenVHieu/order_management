<?php
namespace App\Repositories;

use App\Models\KpiUser;
use App\Repositories\Interfaces\TaskRepositoryInterface;
use App\Models\Task;
use App\Models\TaskHistory;
use App\Models\TaskRequest;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TaskRepository implements TaskRepositoryInterface
{
    public function getAllTasks($params)
    {
        $query = Task::with(['status', 'images', 'designer', 'createdBy', 'taskDoneImages'])
            ->where('tasks.status_id', $params['status_id'])
            ->orderBy('tasks.created_at', 'DESC')
            ->select('tasks.*');    

        if (!empty($params['user_id']) && $params['user_type_id'] != -1 && $params['user_type_id'] != 5) {
            if ($params['user_type_id'] == 4) {
                $query->where(function($query) use ($params) {
                    $query->where('tasks.design_recipient_id', $params['user_id']);
                });
            } else {
                $query->join('users', 'users.id', '=', 'tasks.created_by');
                $query->join('teams', 'teams.id', '=', 'users.team_id');

                $query->where('teams.id', $params['team_id']);

                if ($params['user_type_id'] == 1){
                    $query->where('tasks.created_by', $params['user_id']);
                }
            }
        }

        if ($params['my_task'] == 1) {
            $query->where('tasks.design_recipient_id', $params['user_id'])
                ->orWhere('tasks.created_by', $params['user_id']);
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

            if (!empty($params['userTypeId']) && $params['userTypeId'] != -1 && $params['userTypeId'] != 5) {
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

            if (!empty($params['created_by'])) {
                $query->where('tasks.created_by', $params['created_by']);
            }
    
            if (!empty($params['design_recipient_id'])) {
                $query->where('tasks.design_recipient_id', $params['design_recipient_id']);
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

        if (!empty($params['date_from'])) {
            $date_from = Carbon::parse($params['date_from'])->startOfDay();
            $query->where('done_at', '>=', $date_from);
        }
        
        if (!empty($params['date_to'])) {
            $date_to = Carbon::parse($params['date_to'])->endOfDay();
            $query->where('done_at', '<=', $date_to);
        }

        return $query->paginate(32);
    }

    public function reportTaskByDesigners($params)
    {
        $year_month = Carbon::parse($params['startDate'])->format('Y-m');
        $start_date = Carbon::parse($params['startDate'])->startOfDay();
        $end_date = Carbon::parse($params['endDate'])->endOfDay();

        $query = User::select(
            DB::raw('CAST(SUM(CASE WHEN (' . ($params['userTypeId'] != -1 && $params['userTypeId'] != 5 ? 'user_2.team_id = ' . $params['teamId'] : '1=1') . ') THEN tasks.count_product ELSE 0 END) AS FLOAT) AS count'),
            'users.name AS recipient_name',
            'kpi_users.kpi AS kpi'
        )
        ->leftJoin('tasks', function ($join) use($start_date, $end_date){
            $join->on('tasks.design_recipient_id', '=', 'users.id')
            ->where('tasks.status_id', 6)
            ->whereBetween('tasks.done_at', [$start_date, $end_date]);
        }) // Join với bảng tasks
        ->leftJoin('kpi_users', function($join) use ($year_month) {
            $join->on('kpi_users.user_id', '=', 'users.id')
            ->where('kpi_users.year_month', $year_month);
        });
        
        if ($params['userTypeId'] != -1) {
            $query->leftjoin('users as user_2', function ($join) use ($params) {
                $join->on('user_2.id', '=', 'tasks.created_by');
            });
        }

        $query->whereIn('users.user_type_id', [4, 5])
        ->groupBy('users.name', 'kpi_users.kpi'); // Nhóm thêm theo cột name để tránh lỗi SQL
        return $query->get();
    }

    public function reportTaskByDesigner($params)
    {
        $year_month = Carbon::parse($params['startDate'])->format('Y-m');
        $start_date = Carbon::parse($params['startDate'])->startOfDay();
        $end_date = Carbon::parse($params['endDate'])->endOfDay();

        $query = User::select(
            DB::raw('CAST(SUM(CASE WHEN (1=1) THEN tasks.count_product ELSE 0 END) AS FLOAT) AS count'),
            'users.name AS recipient_name',
            'kpi_users.kpi AS kpi'
        )
        ->leftJoin('tasks', function ($join) use($start_date, $end_date, $params){
            $join->on('tasks.design_recipient_id', '=', 'users.id')
            ->where('tasks.status_id', 6)
            ->where('tasks.design_recipient_id', $params['designerId'])
            ->whereBetween('tasks.done_at', [$start_date, $end_date]);
        }) // Join với bảng tasks
        ->leftJoin('kpi_users', function($join) use ($year_month) {
            $join->on('kpi_users.user_id', '=', 'users.id')
            ->where('kpi_users.year_month', $year_month);
        });
        
        $query->where('users.id', $params['designerId'])
        ->groupBy('users.name', 'kpi_users.kpi'); // Nhóm thêm theo cột name để tránh lỗi SQL
        return $query->get();
    }

    public function reportTaskBySeller($params)
    {
        $year_month = Carbon::parse($params['startDate'])->format('Y-m');
        $start_date = Carbon::parse($params['startDate'])->startOfDay();
        $end_date = Carbon::parse($params['endDate'])->endOfDay();

        $query = User::select(
            DB::raw('CAST(SUM(CASE WHEN (' . ($params['userTypeId'] != -1 && $params['userTypeId'] != 5 ? 'user_2.team_id = ' . $params['teamId'] : '1=1') . ') THEN tasks.count_product ELSE 0 END) AS FLOAT) AS count'),
            'users.name AS seller_name',
            'kpi_users.kpi AS kpi'
        )

        ->leftJoin('tasks', function ($join) use($start_date, $end_date){
            $join->on('tasks.created_by', '=', 'users.id')
            ->where('tasks.status_id', 6)
            ->where('tasks.type', 'new_design')
            ->whereBetween('tasks.done_at', [$start_date, $end_date]);
        }) // Join với bảng tasks
        ->leftJoin('kpi_users', function($join) use ($year_month) {
            $join->on('kpi_users.user_id', '=', 'users.id')
            ->where('kpi_users.year_month', $year_month);
        });
        
        if ($params['userTypeId'] != -1) {
            $query->leftjoin('users as user_2', function ($join) use ($params) {
                $join->on('user_2.id', '=', 'tasks.created_by');
            });

            $query->where('users.team_id', $params['teamId']);
        }

        $query->where('users.user_type_id', 1);
        $query->groupBy('users.name', 'kpi_users.kpi'); // Nhóm thêm theo cột name để tránh lỗi SQL

        return $query->get();
    }

    public function reportTaskByLeader($params)
    {
        $year_month = Carbon::parse($params['startDate'])->format('Y-m');
        $start_date = Carbon::parse($params['startDate'])->startOfDay();
        $end_date = Carbon::parse($params['endDate'])->endOfDay();

        $query = User::select(
            DB::raw('CAST(SUM(tasks.count_product) AS FLOAT) AS count'),
            'users.name AS leader_name',
            'kpi_users.kpi AS kpi'
        )
        ->leftJoin('tasks', function ($join) use($start_date, $end_date){
            $join->on('tasks.created_by', '=', 'users.id')
            ->where('tasks.status_id', 6)
            ->where('tasks.type', 'new_design')
            ->whereBetween('tasks.done_at', [$start_date, $end_date]);
        }) // Join với bảng tasks
        ->leftJoin('kpi_users', function($join) use ($year_month) {
            $join->on('kpi_users.user_id', '=', 'users.id')
            ->where('kpi_users.year_month', $year_month);
        });

        if ($params['userTypeId'] != -1 && $params['userTypeId'] != 5) {
            $query->leftjoin('users as user_2', function ($join) use ($params) {
                $join->on('user_2.id', '=', 'tasks.created_by');
            });

            $query->where('users.team_id', $params['teamId']);
        }

        $query->where('users.user_type_id', 3)->orWhere('users.user_type_id', 5);
        $query->groupBy('users.name', 'kpi_users.kpi'); // Nhóm thêm theo cột name để tránh lỗi SQL

        return $query->get();

    }

    public function totalCountTask($params)
    {
        $start_date = Carbon::parse($params['startDate'])->startOfDay();
        $end_date = Carbon::parse($params['endDate'])->endOfDay();

        $query = Task::select(
            DB::raw('SUM(tasks.count_product) AS total_count')
        )
        ->leftJoin('users', 'users.id', '=', 'tasks.created_by') // Join với bảng users
        ->where('tasks.status_id', 6)
        ->whereBetween('tasks.done_at', [$start_date, $end_date]);

        if ($params['userTypeId'] != -1 && $params['userTypeId'] != 5) {
            $query->where('users.team_id', $params['teamId']);
        }

        $result = $query->first();

        return $result ? (float)$result->total_count : 0;
    }   


    public function reportTaskByTeam($params)
    {
        $start_date = Carbon::parse($params['startDate'])->startOfDay();
        $end_date = Carbon::parse($params['endDate'])->endOfDay();

        $query = Team::select(
            DB::raw('CAST(SUM(tasks.count_product) AS FLOAT) AS count'),
            'teams.name AS team_name'
        )
        ->leftJoin('users', function ($join) use ($params){
            $join->on('users.team_id', '=', 'teams.id');
        })

        ->leftJoin('tasks', function ($join) use($start_date, $end_date){
            $join->on('tasks.created_by', '=', 'users.id')
            ->where('tasks.status_id', 6)
            ->where('tasks.type', 'new_design')
            ->whereBetween('tasks.done_at', [$start_date, $end_date]);
        })
        
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
        ->where('type', 'new_design')
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

    public function getTaskByCondition($id, $status)
    {
        $query = Task::where('id', $id)
            ->where('status_id', $status)
            ->first();

        return $query;
    }

    public function getRequestTask($params)
    {
        $query = TaskRequest::with(['task', 'requestFrom', 'requestTo', 'approvedBy']);
        if ($params['type'] == 0) {
            $query->where('request_from', $params['userId']);
        } else {
            $query->where('request_to', $params['userId']);
        }

        if (!empty($params['keyword'])) {
            $query->where(function($q) use ($params) {
                $q->where('description', 'like', '%' . $params['keyword'] . '%');
                $q->orWhereHas('task', function($q) use ($params) {
                    $q->where('title', 'like', '%' . $params['keyword'] . '%');
                });
            });
        }

        $query->orderBy('approval', 'ASC');
        return $query->paginate(12);
    }
}
