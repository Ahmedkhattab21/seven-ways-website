<?php

namespace App\Http\Controllers;

class UserController extends Controller
{
    //

    public function index()
    {
        $users = [
            ['id' => 1, 'name' => 'ahmed'],
            ['id' => 2, 'name' => 'omar'],
            ['id' => 3, 'name' => 'ali'],
        ];

        return response()->json([
            'data' => [
                'code' => 200,
                'message' => 'done',
                'data' => $users,
            ],
        ]);

        // return response()->json(["code"=> 200,"message"=> "done","data"=>$users]);

        // return response()->json( $users );
    }
}
