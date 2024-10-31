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
use App\Http\Resources\TaskResource;
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
            ];

            $results = $this->taskRepository->getAllTasks($params);

            $tasks = TaskResource::collection($results);
            $paginator = $tasks->resource->toArray();

            $data = [
                'tasks' => $tasks,
                'paginator' => count($paginator['data']) > 0 ? $this->paginate($paginator) : null,
            ];
            
            return $this->sendSuccess($data);
        } catch (\Throwable $th) {
            Helper::trackingError($th->getMessage());   
            return $this->sendError('Lỗi Server');
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $this->getData($request);
            $data['created_at'] = Carbon::now();
            $data['deadline'] = Carbon::now()->addDay();
            $data['created_by'] = Auth::user()->id;

            $task = $this->taskRepository->createTask($data);
            return $this->sendSuccess($task);
            
         } catch (\Exception $e) {
            Helper::trackingError($e->getMessage());
            return $this->sendError($e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try{
            $data = [
                'status_id' => $request->status_id,
                'updated_by' => Auth::user()->id,
                'updated_at' => now()
            ];

            if ($request->status_id == 1){
                $data['designer_process'] = Auth::user()->id;
                $data['deadline'] = Carbon::now()->addDay();
            } else if ($request->status == 6) {
                $data['is_done'] = true;
                $data['url_done'] = $request->url_done;
                $data['done_at'] = Carbon::now();
            }

            $data = $this->taskRepository->updateTask($id, $data);
            return $this->sendSuccess($data);


        } catch (\Exception $e) {
            Helper::trackingError($e->getMessage());
            return $this->sendError($e->getMessage());
        }
    }

    public function changeStatus(Request $request)
    {
        try {
            $id = $request->id;
            $status = DB::table('status_tasks')->where('name', $request->status)->first();
            $task = $this->taskRepository->getTaskById($id);

            if (!$task || !$status) {
                return $this->sendError('Không tìm thấy task hoặc status'); 
            }

            $status_id = $status->id;

            $data = [
                'status_id' => $status_id,
                'updated_at' => now(),
                'updated_by' => Auth::user()->id,
            ];

            if ($status_id == 7) {
                $data['is_done'] = 1;
                $data['done_at'] = now();
            }

            $res = $this->taskRepository->updateTask($id, $data);
            return $this->sendSuccess($res);

        } catch (\Exception $e) {
            Helper::trackingError($e->getMessage());
            return $this->sendError($e->getMessage());
        }
    }

    
    // public function edit($id)
    // {
    //     try {
    //         $data = DB::table('shops')->where('id', $id)->first();
    //         return $this->sendSuccess($data);
    //     } catch (\Throwable $th) {
    //         return $this->sendError('Lỗi Server');
    //     }
    // }

    // public function destroy($id)
    // {
    //     try {
    //         DB::table('shops')->where('id', $id)->delete();
    //         return $this->sendSuccess('Success!');
    //     } catch (\Throwable $th) {
    //         return $this->sendError('Lỗi Server');
    //     }
    // }


    public function getData($request) 
    {
        $data = [
            'title' => $request['title'],
            'description' => $request['description'] ?? null,
            'status_id' => $request['status_id'],
            'category_design_id' => $request['category_design_id'] ?? null,
            'designer_tag' => $request['designer_tag'] ?? null,
            'level_task' => $request['level_task'],
            'comment' => $request['comment']
        ];

        return $data;
    }
}