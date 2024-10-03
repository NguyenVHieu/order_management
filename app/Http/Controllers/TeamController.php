<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Helpers\Helper;
use App\Http\Requests\TeamRequest;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\Shop;
use App\Models\Team;
use App\Models\UserType;

class TeamController extends BaseController
{
    public function index()
    {
        try {
            $data = DB::table('teams')->get();
            return $this->sendSuccess($data);
        } catch (\Throwable $th) {
            return $this->sendError('Lỗi Server');
        }
    }

    public function updateOrCreate(TeamRequest $request)
    {
        try {
            $id = $request->id ?? 0;
            $data = [
                'name' => $request->name,
            ];
            if ($id > 0) {
                DB::table('teams')->where('id', $id)->update($data);
            } else {
                DB::table('teams')->insert($data);
            }
            return $this->sendSuccess('Success!');
        } catch (\Throwable $th) {
            return $this->sendError('Lỗi Server');
        }
    }

    public function edit($id)
    {
        try {
            DB::table('teams')->where('id', $id)->first();
            return $this->sendSuccess('Success!');
        } catch (\Throwable $th) {
            return $this->sendError('Lỗi Server');
        }
    }

    public function destroy($id)
    {
        try {
            $team = DB::table('teams')->where('id', $id)->delete();
            return $this->sendSuccess($team);
        } catch (\Throwable $th) {
            return $this->sendError('Lỗi Server');
        }
    }
}