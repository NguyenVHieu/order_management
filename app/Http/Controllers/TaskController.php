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
use App\Repositories\TaskRepositoryInterface;
use Carbon\Carbon;

class ShopController extends BaseController
{
    protected $taskRepository;

    public function __construct(TaskRepositoryInterface $taskRepository)
    {
        $this->taskRepository = $taskRepository;
    }

    public function index()
    {
        try {
            $tasks = $this->taskRepository->getAllTasks();
            $task_done = $this->taskRepository->getAllTasks('done');
            $groupedTasks = [
                'resource' => [],
                'to_do' => [],
                'support_review' => [],
                'review' => [],
                'fix' => [],
            ];
        
            // Sử dụng Query để phân loại
            foreach ($tasks as $task) {
                switch ($task->status_id) {
                    case 1:
                        $groupedTasks['resource'][] = $task;
                        break;
                    case 2:
                        $groupedTasks['to_do'][] = $task;
                        break;
                    case 3:
                        $groupedTasks['support_review'][] = $task;
                        break;
                    case 4:
                        $groupedTasks['review'][] = $task;
                        break;
                    case 5:
                        $groupedTasks['fix'][] = $task;
                        break;
                }
            }

            return $this->sendSuccess($groupedTasks);
        } catch (\Throwable $th) {
            return $this->sendError('Lỗi Server');
        }
    }

    public function store(TaskRequest $request)
    {
        try {
            $data = $this->getData($request);
            $data['created_at'] = Carbon::now();
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
            $data = [];
            $data['status_id'] = $request->status_id;

            if ($request->status_id == 1){
                $data['designer_process'] = Auth::user()->id;
                $data['deadline'] = Carbon::now()->addDay();
            } else if ($request->status == 6) {
                $data['is_done'] = true;
                $data['url_done'] = $request->url_done;
                $data['updated_by'] = Auth::user()->id;
                $data['done_at'] = Carbon::now();
            }

            $data = $this->taskRepository->updateTask($id, $data);
            return $this->sendSuccess($data);


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
            'designer_id' => $request['designer_id'] ?? null,
            'deadline' => $request['deadline'] ?? null,
            'level_task' => $request['level_task'],
            'comment' => $request['comment']
        ];

        return $data;
    }
}