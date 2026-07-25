<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserController extends Controller
{
    //

    function index(){
        $users=[
            ['id'=>1,'name'=> 'ahmed'],
            ['id'=>2,'name'=> 'omar'],
            ['id'=>3,'name'=> 'ali'],
        ];

            return response()->json([
        "data" => [
            "code" => 200,
            "message" => "done",
            "data" => $users
        ]
    ]);

       // return response()->json(["code"=> 200,"message"=> "done","data"=>$users]);

       // return response()->json( $users );
    }

}
