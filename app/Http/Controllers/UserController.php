<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Helpers\Helper;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\Shop;
use App\Models\UserType;

class UserController extends BaseController
{
    public function index()
    {
        try {
            $query = User::select('users.*', 'shops.name as shop_name', 'user_types.name as user_type_name')
                    ->leftJoin('user_types', function($join) {
                        $join->on('user_types.id', '=', 'users.user_type_id');
                        $join->whereNull('user_types.deleted_at');
                    })
                    ->leftJoin('shops', function($join) {
                        $join->on('shops.id', '=', 'users.shop_id');
                        $join->whereNull('shops.deleted_at');
                    })
                    ->where('users.is_admin', 0)
                    ->orderBy('users.id', 'desc')
                    ->get();
            
            $collection = UserResource::collection($query);
            $data = $collection->resource->toArray();

            return $this->sendSuccess($data);
        } catch (\Throwable $th) {
            return $this->sendError($th->getMessage());
        }
    }

    public function create() 
    {
        try {
            $shops = Shop::select(['id as value', 'name as label'])->get();
            $userTypes = UserType::select(['id as value', 'name as label'])->get();
            $data = [
                'shops' => $shops,
                'userTypes' => $userTypes
            ];
            return $this->sendSuccess($data);
        } catch (\Throwable $th) {
            return $this->sendError($th->getMessage());
        }
    }

    public function store(UserRequest $request)
    {
        try {
            $id = $request->id ?? 0;
            $columns = [
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'user_type_id' => $request->user_type_id,
                'shop_id' => $request->shop_id,
                'created_by' => Auth::user()->id,
                'created_at' => date('Y-m-d H:i:s')
            ];

            if ($id > 0) {
                $data = User::find($id);
                
                if ($request->password) {
                    $columns['password'] = bcrypt($request->password);
                }else{
                    unset($columns['password']);
                }

                if (!$data) {
                    return $this->sendError('User Not Found');
                }
                $data->update($columns);
            } else {
                $data = User::create($columns);
            }
            
            return $this->sendSuccess($data);
        } catch (\Throwable $th) {
            return $this->sendError($th->getMessage());
        }
    }

    public function edit($id)
    {
        try {
            $query = User::find($id);
            if (!$query) {
                return $this->sendError('User Not Found');
            }
            $user = new UserResource($query);  
            $shops = Shop::select(['id as value', 'name as label'])->get();;
            $userTypes = UserType::select(['id as value', 'name as label'])->get();;
            $data = [
                'shops' => $shops,
                'userTypes' => $userTypes,
                'user' => $user
            ];

            return $this->sendSuccess($data);
        } catch (\Throwable $th) {
            return $this->sendError($th->getMessage());
        }
    }

    // public function inserOrUpdate(Request $request, $id)
    // {
    //     try {
    //         $columns = [
    //             'name' => $request->name,
    //             'email' => $request->email,
    //             'password' => bcrypt($request->password),
    //             'user_type_id' => $request->user_type_id,
    //             'shop_id' => $request->shop_id,
    //             'updated_by' => Auth::user()->id,
    //             'updated_at' => date('Y-m-d H:i:s')
    //         ];

    //         $data = User::find($id);
    //         if (!$data) {
    //             return $this->sendError('User Not Found');
    //         }

    //         $data->update($columns);
    //         return $this->sendSuccess($data);
    //     } catch (\Throwable $th) {
    //         return $this->sendError($th->getMessage());
    //     }
    // }

    public function destroy($id)
    {
        try {
            $data = User::find($id);
            if (!$data) {
                return $this->sendError('User Not Found');
            }
            $data->delete();
            return $this->sendSuccess($data);
        } catch (\Throwable $th) {
            dd($th);
            return $this->sendError($th->getMessage());
        }
    }
}