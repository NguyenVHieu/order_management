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
use App\Models\Team;
use App\Models\UserType;

class UserController extends BaseController
{
    public function index()
    {
        try {
            $query = User::select('users.*', 'user_types.name as user_type_name', 'teams.name as team_name')
                    ->leftJoin('user_types', function($join) {
                        $join->on('user_types.id', '=', 'users.user_type_id');
                        $join->whereNull('user_types.deleted_at');
                    })
                    ->leftJoin('teams', function($join) {
                        $join->on('teams.id', '=', 'users.team_id');
                    })

                    ->where('users.is_admin', 0)
                    ->orderBy('users.id', 'desc')
                    ->get();
            foreach($query as $user) {
                $shop_name = DB::table('user_shops')->leftJoin('shops', 'shops.id', '=', 'user_shops.shop_id')
                                                    ->where('user_shops.user_id', $user->id)->pluck('shops.name')->toArray();
                $user->shop_name = $shop_name;
            }
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
            $teams = Team::select(['id as value', 'name as label'])->get();
            $data = [
                'shops' => $shops,
                'userTypes' => $userTypes,
                'teams' => $teams
            ];
            return $this->sendSuccess($data);
        } catch (\Throwable $th) {
            return $this->sendError($th->getMessage());
        }
    }

    public function store(UserRequest $request)
    {
        try {
            DB::beginTransaction();
            $id = $request->id ?? 0;
            $shop_ids = $request->shop_ids ?? [];
            $columns = [
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'user_type_id' => $request->user_type_id,
                'team_id' => $request->team_id,
                'created_by' => Auth::user()->id,
                'created_at' => date('Y-m-d H:i:s'),
                'folder' => !empty($request->folder) ? implode(', ', $request->folder) : null,
            ];

            if (!empty($request->avatar)) {
                $columns['avatar'] = (new Helper())->saveImage($request->avatar);
            }

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
                DB::table('user_shops')->where('user_id', $id)->delete();
                if (count($shop_ids) > 0) {
                    foreach ($shop_ids as $shop_id) {
                        DB::table('user_shops')->insert([
                            'user_id' => $id,
                            'shop_id' => $shop_id
                        ]);
                    }
                }
            } else {
                $data = User::create($columns);
                $user_id = $data->id;
                if (count($shop_ids) > 0) {
                    foreach ($shop_ids as $shop_id) {
                        DB::table('user_shops')->insert([
                            'user_id' => $user_id,
                            'shop_id' => $shop_id
                        ]);
                    }
                }
            }
            DB::commit();
            return $this->sendSuccess($data);
        } catch (\Throwable $th) {
            DB::rollBack();
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
            $arr_shop = DB::table('user_shops')->where('user_id', $id)->pluck('shop_id')->toArray();
            $query->shop_ids = $arr_shop;
            $user = new UserResource($query);  
            $shops = Shop::select(['id as value', 'name as label'])->get();
            $userTypes = UserType::select(['id as value', 'name as label'])->get();
            $teams = Team::select(['id as value', 'name as label'])->get();
            $data = [
                'shops' => $shops,
                'userTypes' => $userTypes,
                'user' => $user,
                'teams' => $teams
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
            DB::beginTransaction();
            $data = User::find($id);
            if (!$data) {
                return $this->sendError('User Not Found');
            }
            $data->delete();
            DB::table('user_shops')->where('user_id', $id)->delete();
            DB::commit();
            return $this->sendSuccess($data);
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError($th->getMessage());
        }
    }

    public function getUserByType($userTypeId)
    {
        try {
            $columns = [
                DB::raw('CAST(users.id AS CHAR) as value'),
                'users.name as label',
                'users.avatar',
            ];
            $userLogin = Auth::user();

            if ($userLogin->user_type_id != null && $userTypeId == 1) {
                $users = DB::table('users')->where('user_type_id', $userTypeId)->where('team_id', $userLogin->team_id)->select($columns)->get();
            } else {
                $users = DB::table('users')->where('user_type_id', $userTypeId)->select($columns)->get();
            }

            return $this->sendSuccess($users);
        } catch (\Exception $ex) {
            Helper::trackingError($ex->getMessage());
            return $this->sendError($ex->getMessage());
        }
    }

    public function getTypeSellerDesigner()
    {
        try {
            $columns = [
                DB::raw('CAST(user_types.id AS CHAR) as value'),
                'user_types.name as label',
            ];

            $data = DB::table('user_types')->whereIn('name', ['seller', 'designer'])->select($columns)->get();

            return $this->sendSuccess($data);
        } catch (\Exception $ex) {
            Helper::trackingError($ex->getMessage());
            return $this->sendError($ex->getMessage());
        }
    }
}