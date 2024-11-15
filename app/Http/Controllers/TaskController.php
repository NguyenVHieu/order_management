<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Helpers\Helper;
use App\Http\Requests\TaskRequest;
use App\Http\Resources\TaskHistoryResource;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Repositories\TaskRepository;
use Carbon\Carbon;

class TaskController extends BaseController
{
    protected $taskRepository;

    public function __construct(TaskRepository $taskRepository)
    {
        $this->taskRepository = $taskRepository;
    }

    public function index(Request $request)
    {
        try {
            $status = DB::table('status_tasks')->where('name', $request->status)->first();
            if (!$status) {
                return $this->sendError('Không tìm thấy status'); 
            }

            $params = [
                'status_id' => $status->id,
                'user_id' => Auth::user()->id,
                'user_type_id' => Auth::user()->user_type_id ?? -1,
                'keyword' => $request->keyword ?? '',
                'team_id' => Auth::user()->team_id,
                'created_by' => $request->created_by ?? null,
                'design_recipient_id' => $request->design_recipient_id ?? null
            ];

            $results = $this->taskRepository->getAllTasks($params);

            $tasks = TaskResource::collection($results);
            $paginator = $tasks->resource->toArray();
            $paginator['data'] = $paginator['data'] ?? [];  

            

            $columns = [
                DB::raw('CAST(users.id AS CHAR) as value'),
                'users.name as label',
                'users.avatar',
            ];
            

            if (Auth::user()->user_type_id == 4) {
                $designers = [];
                $sellers = [];
            } else {
                $designers = DB::table('users')->where('user_type_id', 4)->select($columns)->get();
                $sellers = $this->getDataUser($columns);
            }
    
            $data = [
                'tasks' => $tasks,
                'designers' => $designers,   
                'sellers' => $sellers,  
                'paginator' => count($paginator['data']) > 0 ? $this->paginate($paginator) : null,
            ];
            
            return $this->sendSuccess($data);
        } catch (\Exception $ex) {
            Helper::trackingError($ex->getMessage());   
            return $this->sendError('Lỗi Server');
        }
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $data = $this->getData($request);
            $data['status_id'] = $request->status_id ?? 1;
            $data['created_by'] = $request->userId; 
            $data['created_at'] = now();

            $task = $this->taskRepository->createTask($data);
            $task_images = $request->file ?? [];  

            if (!empty($task_images)) {
                foreach($task_images as $image) {
                    $url = $this->saveImageTask($image);
                    $data_image = [
                        'task_id' => $task->id,
                        'image_url' => $url
                    ];
                    DB::table('task_images')->insert($data_image);  
                }
            }

            DB::commit();
            Helper::trackingInfo(json_encode($request->all()));
            return $this->sendSuccess($task);
            
         } catch (\Exception $e) {
            DB::rollBack();
            Helper::trackingError($e->getMessage());
            return $this->sendError($e->getMessage());
        }
    }
    
    public function edit($id) 
    {
        try {
            $result = $this->taskRepository->getTaskById($id);
            $data = new TaskResource($result);
            return $this->sendSuccess($data);
        } catch (\Exception $e) {
            DB::rollBack();
            Helper::trackingError($e->getMessage());
            return $this->sendError($e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try {
            Helper::trackingInfo('Request update task: ' .json_encode($request->all()));
            $task = $this->taskRepository->getTaskById($id);
            $userId = Auth::user()->id;
            
            if (!$this->hasUpdatePermission($task, $userId)) {
                return $this->sendError('Không có quyền cập nhật', 403);
            }

            DB::beginTransaction();

            $data = $this->getUpdateData($request);

            $data['updated_by'] = $userId;
            $data['updated_at'] = now();

            $this->taskRepository->updateTask($id, $data);

            if (in_array($task->status_id, [1, 2])) {
                $taskImages = $request->file ?? [];  
                $imgUrls = $request->imageUrl ?? [];
                
                if (!empty($taskImages)) {
                    DB::table('task_images')->where('task_id', $task->id)->whereNotIn('image_url', $imgUrls)->delete();
                    foreach ($taskImages as $image) {
                        $url = $this->saveImageTask($image);
                        DB::table('task_images')->insert([
                            'task_id' => $task->id,
                            'image_url' => $url
                        ]);
                    }
                }
            }
    
            DB::commit();
            
            return $this->sendSuccess($data);
                
        } catch (\Exception $e) {
            DB::rollBack();
            Helper::trackingError($e->getMessage());
            return $this->sendError($e->getMessage());
        }
    }

    protected function getUpdateData(Request $request)
    {
        $data = $this->getData($request);
        $data['url_done'] = $request->url_done ?? null; 
        unset($data['status_id']);
        return $data;
    }

    public function changeStatus(Request $request)
    {
        try {
            DB::beginTransaction();
            $id = $request->id;
            $userId = Auth::user()->id; 
            $userTypeId = Auth::user()->user_type_id;

            $status = DB::table('status_tasks')->where('name', $request->status)->first();
            $task = $this->taskRepository->getTaskById($id);
            $status_id = $status->id;
            // if (!$this->hasChangeStatusPermission($task->status_id, $request->status, $task->design_recipient_id, $userId, $userTypeId)) {
            //     return $this->sendError('Không có quyền cập nhật', 403);
            // }

            if (!$task || !$status) {
                return $this->sendError('Không tìm thấy task hoặc status'); 
            }

            $data = [
                'status_id' => $status_id,
                'updated_at' => now(),
                'updated_by' => Auth::user()->id,
            ];

            if($request->status === 'in_progress' && empty($data['deadline'])) {
                $data['design_recipient_id'] = Auth::user()->id;
                $data['deadline'] = now()->addDays(1);
            }

            if ($request->status === 'done') {
                $data['is_done'] = 1;
                $data['done_at'] = now();
            }

            $task_new = $this->taskRepository->updateTask($id, $data);

            $this->updateHistory($id, $task->status_id, $task_new->status_id);
            
            $res = new TaskResource($task_new);
            DB::commit();   
            Helper::trackingInfo(json_encode($request->all()));
            return $this->sendSuccess($res);

        } catch (\Exception $e) {
            DB::rollBack();
            Helper::trackingError($e->getMessage());
            return $this->sendError($e->getMessage());
        }
    }


    public function getData($request) 
    {
        $data = [
            'title' => $request->title,
            'description' => $request->description ?? null,
            'status_id' => $request->status_id,
            'category_design_id' => $request->category_design_id ?? null,
            'design_recipient_id' => $request->design_recipient_id ?? null,
            'count_product' => $request->count_product,
        ];

        return $data;
    }

    public function initForm() 
    {
        try {
            $startTime = Carbon::today()->setTime(0, 0, 0);      // 0h (nửa đêm)
            $endTime = Carbon::today()->setTime(23, 59, 59);      // 23h59 (cuối ngày)

            $templates = DB::table('templates')->select([DB::raw('CAST(id AS CHAR) as value'), 'name as label'])->get();
            $category_designs = DB::table('category_designs')->select([DB::raw('CAST(id AS CHAR) as value'), 'name as label'])->get();    
            $designers = DB::table('users')
                ->where('user_type_id', 4)
                ->leftJoin('tasks', function($join) use ($startTime, $endTime) {
                    $join->on('users.id', '=', 'tasks.design_recipient_id')
                         ->whereBetween('tasks.created_at', [$startTime, $endTime])
                         ->whereIn('tasks.status_id', [3, 4, 5]);
                })
                ->select([
                    DB::raw('CAST(users.id AS CHAR) as value'),
                    'users.name as label',
                    'users.avatar',
                    DB::raw('SUM(CASE WHEN tasks.id IS NOT NULL THEN 1 ELSE 0 END) as task_count')
                ])
                ->groupBy('users.id', 'users.name', 'users.avatar')
                ->get();

            $data = [
                'templates' => $templates,
                'category_designs' => $category_designs,
                'designers' => $designers
            ];

            return $this->sendSuccess($data);
        } catch (\Throwable $th) {
            Helper::trackingError($th->getMessage());
            return $this->sendError('Lỗi Server');
        } 
    }

    public function saveImageTask($image)
    {
        $dateFolder = now()->format('Ymd');
        $time = now()->format('his');

        $directory = public_path('tasks/' . $dateFolder);

        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        // $name = rawurlencode($image->getClientOriginalName());

        $path = $image->move($directory, $time. '_'. $image->getClientOriginalName());

        $url = asset('tasks/' .$dateFolder. '/'. $time. '_'. $image->getClientOriginalName());

        return $url;
    }

    public function updateHistory($task_id, $status_old, $status_new)
    {
        $status_old = DB::table('status_tasks')->where('id', $status_old)->first();  
        $status_new = DB::table('status_tasks')->where('id', $status_new)->first();

        DB::table('task_histories')->insert([
            'task_id' => $task_id,
            'message' => Auth::user()->name.' Cập nhật task '.$status_old->name.' thành '.$status_new->name,
            'action_by' => Auth::user()->id,
            'created_at' => now(),
        ]);

        Helper::trackingInfo(Auth::user()->name.' Cập nhật task '.$status_old->name.' thành '.$status_new->name);
    }

    public function commentTask(Request $request)
    {
        try {
            $data = [
                'task_id' => $request->task_id,
                'message' => $request->message,
                'created_at' => now(),
                'action_by' => Auth::user()->id
            ];
            $comment = DB::table('task_histories')->insert($data);
            Helper::trackingInfo(json_encode($request->all()));
            return $this->sendSuccess($comment);

        } catch (\Exception $ex) {
            Helper::trackingError($ex->getMessage());
            return $this->sendError('Lỗi Server');
        }
    }

    public function getHistory($id)
    {
        try {
            $results = $this->taskRepository->getHistory($id);
            $data = TaskHistoryResource::collection($results);
            $paginator = $data->resource->toArray();
            $paginator['data'] = $paginator['data'] ?? [];  

            $data = [
                'comments' => $data,
                'paginator' => count($paginator['data']) > 0 ? $this->paginate($paginator) : null,
            ];
            return $this->sendSuccess($data);
        } catch (\Exception $ex) {
            Helper::trackingError($ex->getMessage());
            return $this->sendError('Lỗi Server');
        }
    }

    protected function hasUpdatePermission($task, $userId)
    {
        return (in_array($task->status_id, [3, 4, 5]) && $userId == $task->design_recipient_id) || (in_array($task->status_id, [1, 2]) && $userId == $task->created_by);
    }



    protected function hasChangeStatusPermission($status_old, $status, $design_recipient_id, $userId, $userTypeId)
    {
        if (!in_array($status_old, [1, 2]) && in_array($status, ['resource', 'by_order'])) {
            return false;
        }

        if (in_array($userTypeId, [1, 3])) {
            return in_array($status, ['resource', 'by_order']);
        }

        if ($userTypeId == 4) {
            return !in_array($status, ['resource', 'by_order']) &&
                ($design_recipient_id == $userId || $design_recipient_id === null);
        }

        return false;
    }

    public function getTaskDone()
    {
        try {
            $results = $this->taskRepository->getTaskDone();
            $data = TaskResource::collection($results);
            $paginator = $data->resource->toArray();
            $paginator['data'] = $paginator['data'] ?? [];  
            $data = [
                'tasks' => $data,
                'paginator' => count($paginator['data']) > 0 ? $this->paginate($paginator) : null,
            ];
            return $this->sendSuccess($data);
        } catch (\Exception $ex) {
            Helper::trackingError($ex->getMessage());
            return $this->sendError($ex->getMessage());
        }   
    }

    public function getDataUser($columns)
    {
        $userTypeId = Auth::user()->user_type_id ?? -1; 
        $teamId = Auth::user()->team_id;    

        $seller = [];   

        if ($userTypeId == -1) {
            $seller = DB::table('users')->where('user_type_id', 1)->select($columns)->get(); 
        } else if ($userTypeId == 3) {
            $seller = DB::table('users')->where('user_type_id', 1)->where('team_id', $teamId)->select($columns)->get();
        } 

        return $seller;
    }

}