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
                'user_id' => Auth::user()->id,
                'user_type_id' => Auth::user()->user_type_id,
            ];

            $results = $this->taskRepository->getAllTasks($params);

            $tasks = TaskResource::collection($results);
            $paginator = $tasks->resource->toArray();
            $paginator['data'] = $paginator['data'] ?? [];  

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
            DB::beginTransaction();
            $data = $this->getData($request);
            $data['status_id'] = 1;

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
            return $this->sendSuccess($task);
            
         } catch (\Exception $e) {
            DB::rollBack();
            Helper::trackingError($e->getMessage());
            return $this->sendError($e->getMessage());
        }
    }

    // public function update(Request $request, $id)
    // {
    //     try{
    //         DB::beginTransaction();
    //         $data = [
    //             'status_id' => $request->status_id,
    //             'updated_by' => Auth::user()->id,
    //             'updated_at' => now()
    //         ];

    //         if ($request->status_id == 1){
    //             $data['designer_process'] = Auth::user()->id;
    //             $data['deadline'] = Carbon::now()->addDay();
    //         } else if ($request->status == 6) {
    //             $data['is_done'] = true;
    //             $data['url_done'] = $request->url_done;
    //             $data['done_at'] = Carbon::now();
    //         }

    //         $data = $this->taskRepository->updateTask($id, $data);


    //         DB::commit();   
    //         return $this->sendSuccess($data);


    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Helper::trackingError($e->getMessage());
    //         return $this->sendError($e->getMessage());
    //     }
    // }

    public function changeStatus(Request $request)
    {
        try {
            DB::beginTransaction();
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

            $task_new = $this->taskRepository->updateTask($id, $data);

            $this->updateHistory($id, $task->status_id, $task_new->status_id);
            
            $res = new TaskResource($task_new);
            DB::commit();   
            return $this->sendSuccess($res);

        } catch (\Exception $e) {
            DB::rollBack();
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
            'title' => $request->title,
            'description' => $request->description ?? null,
            'status_id' => $request->status_id,
            'category_design_id' => $request->category_design_id ?? null,
            'design_recipient_id' => $request->design_recipient_id ?? null,
            'count_product' => $request->count_product,
            'created_by' => $request->userId,
            'created_at' => now()   
        ];

        return $data;
    }

    public function initForm() 
    {
        try {
            $templates = DB::table('templates')->select(['id as value', 'name as label'])->get();
            $category_designs = DB::table('category_designs')->select(['id as value', 'name as label'])->get();    
            $designers = DB::table('users')->where('user_type_id', 4)->select(['id as value', 'name as label'])->get();

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
}