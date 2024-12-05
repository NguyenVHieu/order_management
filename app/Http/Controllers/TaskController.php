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
use App\Models\KpiUser;
use App\Models\Task;
use App\Models\TaskDoneImage;
use App\Models\TaskHistory;
use App\Repositories\TaskRepository;
use Carbon\Carbon;

class TaskController extends BaseController
{
    protected $taskRepository;
    private $baseUrl = 'https://open.feishu.cn/open-apis/';
    private $client;

    public function __construct(TaskRepository $taskRepository)
    {
        $this->client = new Client();
        $this->taskRepository = $taskRepository;
    }

    public function initIndex()
    {
        try {
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
                'designers' => $designers,
                'sellers' => $sellers,
            ];
            return $this->sendSuccess($data);
        } catch (\Exception $ex) {
            Helper::trackingError($ex->getMessage());
            return $this->sendError('Lỗi Server');
        }
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
    
            $data = [
                'tasks' => $tasks,
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

            $paramScore = [
                'seller_id' => $data['created_by'],
                'score_old' => 0,
                'score_new' => $data['count_product'],
                'year_month' => now()->format('Y-m')    
            ];

            if (!$this->checkScoreSeller($paramScore)) {
                return $this->sendError('Quá số hạng ngạch', 422);
            }

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

            if (in_array(Auth::user()->user_type_id, [1, 3])) {
                $params = [
                    'seller_id' => $task->created_by,
                    'score_old' => $task->count_product,
                    'score_new' => $data['count_product'],
                    'year_month' => now()->format('Y-m')
                ];
    
                if (!$this->checkScoreSeller($params)) {
                    return $this->sendError('Quá số hạng ngạch', 422);
                }
            }

            $result = $this->taskRepository->updateTask($id, $data);

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
            } else {
                $file_reviews = $request->file_review ?? [];
                $image_src_reviews = $request->image_src_review ?? [];

                DB::table('task_done_images')->where('task_id', $task->id)->whereNotIn('image_url', $image_src_reviews)->delete();
                foreach($file_reviews as $file)
                {
                    $url = $this->saveImageTask($file);
                    $data = [
                        'task_id' => $task->id,
                        'image_url' => $url,
                    ];

                    DB::table('task_done_images')->insert($data);
                }
            }

            DB::commit();
            
            return $this->sendSuccess(new TaskResource($task));
                
        } catch (\Exception $e) {
            DB::rollBack();
            Helper::trackingError($e->getMessage());
            return $this->sendError($e->getMessage());
        }
    }

    protected function getUpdateData(Request $request)
    {
        $data = $this->getData($request);
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
            $status_old = DB::table('status_tasks')->where('name', $request->status_old)->first();
            
            $status_id = $status->id;
            $task = $this->taskRepository->getTaskByCondition($id, $status_old->id);

            if (empty($task)) {
                return $this->sendError('Không tìm thấy task hoặc đã cập nhật trạng thái', 404);
            }

            if (!$this->hasChangeStatusPermission($task->status_id, $request->status, $task->design_recipient_id, $userId, $userTypeId)) {
                return $this->sendError('Không có quyền cập nhật', 403);
            }

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
                if ($task->created_by != $userId) {
                    return $this->sendError('Không có quyền cập nhật', 403);
                }
                
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
            'template_id' => $request->template_id ?? null,
            'count_product' => $request->count_product,
        ];

        return $data;
    }

    public function initForm() 
    {
        try {
            $product = DB::table('product_tasks')->select([DB::raw('CAST(id AS CHAR) as value'), 'name as label'])->get();
            $platform_size = DB::table('platform_size_tasks')->select([DB::raw('CAST(id AS CHAR) as value'), 'name as label'])->get();
            $templates = DB::table('templates')->select([DB::raw('CAST(id AS CHAR) as value'), 'name as label'])->get();
            $category_designs = DB::table('category_designs')->select([DB::raw('CAST(id AS CHAR) as value'), 'name as label'])->get();    
            $designers = DB::table('users')
                ->where('user_type_id', 4)
                ->leftJoin('tasks', function($join) {
                    $join->on('users.id', '=', 'tasks.design_recipient_id')
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
                'designers' => $designers,
                'product' => $product,
                'platform_size' => $platform_size
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
            DB::beginTransaction();
            $data = [
                'task_id' => $request->task_id,
                'message' => $request->message,
                'created_at' => now(),
                'action_by' => Auth::user()->id
            ];

            $comment = TaskHistory::create($data)->fresh();

            if (!empty($request->files_comment)) {
                $images = $request->files_comment ?? [];
                if (!empty($images)) {
                    foreach($images as $image) {
                        $url = $this->saveImageTask($image);
                        $data_image = [
                            'task_history_id' => $comment->id,
                            'image_url' => $url
                        ];
                        DB::table('task_history_images')->insert($data_image);  
                    }
                }
            }
            Helper::trackingInfo(json_encode($request->all()));
            DB::commit();   
            return $this->sendSuccess($comment);

        } catch (\Exception $ex) {
            DB::rollBack();
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
        if (!in_array($status_old, [1, 2]) && in_array($status, ['new_design', 'new_order'])) {
            return false;
        } else if (in_array($status_old, [1, 2]) && $status === 'done') {
            return false;
        }

        if (in_array($userTypeId, [1, 3])) {
            return in_array($status, ['new_design', 'new_order']) || ($status_old == 4 && in_array($status, ['fix', 'done']));
        }

        if (in_array($userTypeId, [4, 5])) {
            return !in_array($status, ['new_design', 'new_order', 'done', 'fix']) &&
                ($design_recipient_id == $userId || $design_recipient_id === null);
        }

        return false;
    }

    public function getTaskDone(Request $request)
    {
        try {
            $params = [
                'userId' => Auth::user()->id,
                'userTypeId' => Auth::user()->user_type_id ?? -1,
                'teamId' => Auth::user()->team_id ?? -1,
                'keyword' => $request->keyword ?? '',
                'sort' => $request->sort ?? 1,
            ];
            $results = $this->taskRepository->getTaskDone($params);

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

    public function reportTask(Request $request)
    {
        try {
            $userTypeId = Auth::user()->user_type_id ?? -1;
            if ($userTypeId == -1 || $userTypeId == 3 || $userTypeId == 5) {
                $params = [
                    'startDate' => $request->start_date,
                    'endDate' => $request->end_date,
                    'userTypeId' => $userTypeId,    
                    'teamId' => Auth::user()->team_id ?? -1
                ];
                $seller = $this->taskRepository->reportTaskBySeller($params);
                $designer = $this->taskRepository->reportTaskByDesigner($params);
                $leader = $this->taskRepository->reportTaskByLeader($params);
                $total = $this->taskRepository->totalCountTask($params);
                $data = [
                    'seller' => $seller,
                    'designer' => $designer,
                    'leader' => $leader,
                    'total' => $total
                ];

                if ($userTypeId == -1 || $userTypeId == 5) {
                    $data['team'] = $this->taskRepository->reportTaskByTeam($params);
                }

                return $this->sendSuccess($data);
                
            } else {
                return $this->sendError('Không có quyền truy cập', 403);
            }
        } catch (\Exception $ex) {
            Helper::trackingError($ex->getMessage());
            return $this->sendError($ex->getMessage());
        }
    }

    public function getTemplate(Request $request)
    {
        try{
            $product_id = $request->product_id ?? null;
            $platform_size_id = $request->platform_size_id ?? null;

            $template = DB::table('templates')->select([DB::raw('CAST(id AS CHAR) as value'), 'name as label']);

            if (!empty($product_id)) {
                $template->where('product_task_id', $product_id);
            }
            
            if (!empty($platform_size_id)) {
                $template->where('platform_size_task_id', $platform_size_id);
            }
                
            $data = $template->get();
            return $this->sendSuccess($data);

        } catch (\Exception $ex) {
            Helper::trackingError($ex->getMessage());
            return $this->sendError($ex->getMessage());
        }
    }

    public function getSizeByProductId($id)
    {
        try {
            $sizes = DB::table('platform_size_tasks')->where('product_task_id', $id)->select([DB::raw('CAST(id AS CHAR) as value'), 'name as label'])->get();
            return $this->sendSuccess($sizes);
        } catch (\Exception $ex) {
            Helper::trackingError($ex->getMessage());
            return $this->sendError($ex->getMessage());
        }   
    }

    public function notificationLark(Request $request)
    {
        try {
            // 1. Đăng nhập (Lấy tenant_access_token)
            $tenantAccessToken = $this->getTenantAccessToken();

            $user = User::find($request->userId);
            $text = $request->text;
            //2. Tìm OpenID
            $openId = $this->findOpenId($tenantAccessToken, $user->email); // Thay email cần tìm

            // // 3. Gửi tin nhắn
            $this->sendMessage($tenantAccessToken, $openId, $text);

            return $this->sendSuccess('ok');
        } catch (\Exception $e) {
            Helper::trackingError($e->getMessage());
            return $this->sendError($e->getMessage());
        }

    }


    public function addKpiUser(Request $request)    
    {
        try {
            $userTypeId = Auth::user()->user_type_id ?? -1;

            if (!in_array($userTypeId, [-1, 3])) {
                return $this->sendError('Không có quyền cập nhật', 403);
            }

            $users = $request->users ?? [];

            if (count($users) > 0) {
                foreach($users as $user) {
                    $data = [
                        'year_month' => date('Y-m', strtotime($user['year_month'])),   
                        'user_id' => $user['user_id'], 
                        'kpi' => $user['kpi'],
                    ];
    
                    KpiUser::updateOrCreate(
                        ['year_month' => $data['year_month'], 'user_id' => $data['user_id']],
                        $data
                    );  
                }
            }

            return $this->sendSuccess('ok');
        } catch (\Exception $e) {
            Helper::trackingError($e->getMessage());
            return $this->sendError($e->getMessage());
        }
    }

    private function getTenantAccessToken()
    {
        $url = $this->baseUrl . 'auth/v3/tenant_access_token/internal';

        $response = $this->client->post($url, [
            'json' => [
                'app_id' => env('APP_ID_LARK'),
                'app_secret' => env('APP_SECRET_LARK'), // Thay bằng app_secret của bạn
            ],
        ]);

        $body = json_decode($response->getBody(), true);

        if (!isset($body['tenant_access_token'])) {
            throw new \Exception('Failed to retrieve tenant_access_token');
        }

        return $body['tenant_access_token'];
    }

    private function findOpenId($tenantAccessToken, $email)
    {
        $url = 'https://open.larksuite.com/open-apis/contact/v3/users/batch_get_id';

        $response = $this->client->post($url, [
            'query' => [
                'user_id_type' => 'open_id'
            ],
            'json' => [
                'emails' => [$email],
            ],
            'headers' => [
                'Authorization' => 'Bearer ' . $tenantAccessToken,
            ],
        ]);

        $body = json_decode($response->getBody(), true);

        if (!isset($body['data']['user_list'][0]['user_id'])) {
            throw new \Exception('Failed to find OpenID for email: ' . $email);
        }

        return $body['data']['user_list'][0]['user_id'];
    }

    private function sendMessage($tenantAccessToken, $openId, $message)
    {
        $url = $this->baseUrl . 'message/v4/send/';

        $response = $this->client->post($url, [
            'json' => [
                'open_id' => $openId,
                'msg_type' => 'text',
                'content' => [
                    'text' => $message,
                ],
            ],
            'headers' => [
                'Authorization' => 'Bearer ' . $tenantAccessToken,
            ],
        ]);

        $body = json_decode($response->getBody(), true);

        if ($body['code'] !== 0) {
            throw new \Exception('Failed to send message: ' . $body['msg']);
        }

        return $body;
    }

    private function checkScoreSeller($params)
    {
        
        $score_current = $this->taskRepository->getScoreBySeller($params) ?? 0;
        $kpi_seller = $this->taskRepository->getKpiSeller($params) ?? 0;
        if ($score_current - $params['score_old'] + $params['score_new'] > $kpi_seller) {
            return false;
        }

        return true;
        
    }

    public function deleteTask($id)
    {
        try {
            $task = $this->taskRepository->getTaskById($id);
            $userId = Auth::user()->id;

            if ($userId != $task->created_by || !in_array($task->status_id, [1, 2])) {
                return $this->sendError('Không có quyền xóa', 403);
            }
            DB::table('task_images')->where('task_id', $id)->delete();
            DB::table('task_histories')->where('task_id', $id)->delete();
            DB::table('task_done_images')->where('task_id', $id)->delete();

            DB::beginTransaction();
            $this->taskRepository->deleteTask($id);
            DB::commit();
            Helper::trackingInfo('Xóa task: ' . json_encode($task));
            return $this->sendSuccess('ok');
        } catch (\Exception $e) {
            DB::rollBack();
            Helper::trackingError($e->getMessage());
            return $this->sendError($e->getMessage());
        }
    }

    public function updateCover(Request $request)
    {
        try {
            DB::beginTransaction();
            TaskDoneImage::where('task_id', $request->task_id)->update(['is_cover' => false]);
            $cover = TaskDoneImage::where('task_id', $request->task_id)->where('image_url', $request->image_url)->first();
            if (!$cover) {
                return $this->sendError('Không tìm thấy ảnh', 404);
            }
            $cover->is_cover = true;
            $cover->save();
            DB::commit();
        
            return $this->sendSuccess('ok');
        } catch (\Exception $e) {
            DB::rollBack();
            Helper::trackingError($e->getMessage());
            return $this->sendError($e->getMessage());
        }
    }

}