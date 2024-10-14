<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Helpers\Helper;
use App\Http\Requests\ShopRequest;
use App\Http\Requests\TeamRequest;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\Shop;
use App\Models\Team;
use App\Models\UserType;

class ShopController extends BaseController
{
    public function index()
    {
        try {
            $data = DB::table('shops')->get();
            return $this->sendSuccess($data);
        } catch (\Throwable $th) {
            return $this->sendError('Lỗi Server');
        }
    }

    public function updateOrCreate(ShopRequest $request)
    {
        try {
            $id = $request->id ?? 0;
            $data = [
                'name' => $request->name,
            ];
            if ($id > 0) {
                DB::table('shops')->where('id', $id)->update($data);
            } else {
                DB::table('shops')->insert($data);
            }
            return $this->sendSuccess('Success!');
        } catch (\Throwable $th) {
            return $this->sendError('Lỗi Server');
        }
    }

    public function edit($id)
    {
        try {
            $data = DB::table('shops')->where('id', $id)->first();
            return $this->sendSuccess($data);
        } catch (\Throwable $th) {
            return $this->sendError('Lỗi Server');
        }
    }

    public function destroy($id)
    {
        try {
            $DB::table('shops')->where('id', $id)->delete();
            return $this->sendSuccess('Success!');
        } catch (\Throwable $th) {
            return $this->sendError('Lỗi Server');
        }
    }
}