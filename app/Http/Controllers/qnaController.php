<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\qna as MetDa;
use App\qna_follow;
use Auth;
use App\group_follow;
use App\user_follow;
use App\qna;
use App\group;
use App\User;
use App\activity;


class qnaController extends Controller
{

    public function quest_home(Request $req){
        if(Auth::id()){
                
                    $skip = 0;
                    $take = 5;

                    if($req->page > 1){
                        $skip = $take * $req->page-1;
                    }

               

                    if($req->search){
                        $filterSearch = "text like '%".$req->search."%'";
                    }else{
                        $filterSearch = "id != ''";
                    }
            
                    
                    $following = group_follow::where("user_id",Auth::id())->pluck("group_id")->toArray();
                    $following_user = user_follow::where("user_id",Auth::id())->pluck("followed_id")->toArray();

                    
                    $metda = qna::orderBy("id","DESC")
                        ->whereIn("group_id",$following)
                        ->OrWhereIn("user_id",$following_user)
                        ->whereRaw($filterSearch)
                        ->with("group")
                        ->with("user")
                        ->with("quest")
                        ->skip($skip)->take($take)
                        ->get();



                    $metda->map(function($q) {

                        if($q->quest){

                            $q->membalas_user = User::find($q->quest->user_id)->username;
                        }

                        $follow = qna_follow::
                            where("user_id",Auth::id())
                            ->where("quest_id",$q->id)
                            ->first();

                        if($follow){
                            $q->followed = true;
                        }
                        
                    });

                    $respons = [
                        "data" => $metda
                    ];

                    return response()->json($respons);
        }
    }

    public function quest_home_explore(Request $req){
        if(Auth::id()){
                
                    $skip = 0;
                    $take = 5;

                    if($req->page > 1){
                        $skip = $take * $req->page-1;
                    }

               

                    if($req->search){
                        $filterSearch = "text like '%".$req->search."%'";
                    }else{
                        $filterSearch = "id != ''";
                    }
            
                    
                    $metda = qna::whereRaw($filterSearch)
                        ->with("group")
                        ->with("user")
                        ->with("quest")
                        ->where("quest_id",null)
                        ->orderBy("activity","DESC")
                        ->orderBy("id","DESC")
                        ->take(100)
                        ->get();



                    $metda->map(function($q) {

                        if($q->quest){

                            $q->membalas_user = User::find($q->quest->user_id)->username;
                        }


                        $follow = qna_follow::
                            where("user_id",Auth::id())
                            ->where("quest_id",$q->id)
                            ->first();

                        if($follow){
                            $q->followed = true;
                        }
                        
                    });

                    

                    $respons = [
                        
                        "data" => $metda->skip($skip)->take($take)->toArray()
                    ];

                    return response()->json($respons);
        }
    }

    public function follow($id){
        if(Auth::id()){
            
            $cek = qna_follow::where("user_id",Auth::id())->where("quest_id",$id)->first();
            if(!$cek){
                $follow = new qna_follow;
                $follow->user_id = Auth::id();
                $follow->quest_id = $id;
                $follow->save();

                $dataAct = [
                    "user_id" => Auth::id(),
                    "quest_id" => $id,
                    "tipe" => 1,
                    "link" => "/quest/$id",
                ];
                activity::create($dataAct);

                $update = qna::find($id);
                if($update){
                    $update->total_qna = qna::where("quest_id",$id)->count();
                    $update->total_follower = qna_follow::where("quest_id",$id)->count();
                    $update->activity = $update->total_qna + $update->total_follower;
                    $update->update();
                }
    

            }

         
       
        }
    }

    public function index()
    {
        $metda = MetDa::latest()->paginate(5);
        return response($metda);
    }
    public function create(Request $req)
    {


      
        try {
            if(Auth::id()){

                $metda = new MetDa;
               
                if($req->quest_id){
                    $metda->quest_id = $req->quest_id;
                }
                if($req->group_id){
                    $metda->group_id = $req->group_id;
                }
                $metda->text =  preg_replace('/\s+/',' ',$req->text);

                if($req->audio){
                    $metda->audio =  $req->audio;
                }
                if($req->embed){
                    $metda->embed = $req->embed;
                }
                if($req->thumb){
                    $metda->thumb = $req->thumb;
                }
                if($req->img){
                    $metda->img = $req->img;
                    $metda->thumb = "";
                }
                if($req->video){
                    $metda->video = $req->video;
                }
                $metda->user_id = Auth::id();
                $metda->save();


                if($req->quest_id){
                    $update = qna::find($req->quest_id);

                    if($update){
                        $update->total_qna = qna::where("quest_id",$req->quest_id)->count();
        
                        $update->total_follower = qna_follow::where("quest_id",$req->quest_id)->count();
            
                        $update->activity = $update->total_qna + $update->total_follower;
            
                        $update->update();
                        
                            $dataAct = [
                                "user_id" => Auth::id(),
                                "quest_id" => $req->quest_id,
                                "group_id" => $metda->group_id,
                                "quest_balas_id" => $metda->id,
                                "tipe" => 2,
                                "link" => "/quest/$metda->id",
                            ];
                            activity::create($dataAct);
    
                    }
                }
              

                if($req->group_id){
                    $updateg = group::find($req->group_id);
                       
                    if($updateg){
                        $updateg->last_active= strtotime($metda->created_at);
                        $updateg->update();
                    }
                }
              


                if($req->text){
                    $textToArray =  explode(" ",$req->text);

                    foreach($textToArray as $text){
                        if(substr($text, 0, 1) == "@"){
                           
                            // Mentions
                            $dataAct0 = [
                                "user_id" => Auth::id(),
                                "quest_id" => $metda->id,
                                "tipe" => 3,
                                "link" => "/quest/$metda->id",
                                "mention" => $text,
                            ];

                            activity::create($dataAct0);


                        }else if(substr($text, 0, 1) == "#"){

                             // Tagar
                             $dataAct0 = [
                                "user_id" => Auth::id(),
                                "quest_id" => $metda->id,
                                "tipe" => 4,
                                "link" => "",
                                "tagar" => $text,
                            ];

                            activity::create($dataAct0);

                        }
                    }

                }

                return response()->json([
                    'success' => true,
                    'id'=> $metda->id
                ]);
             }
        } catch (\Throwable $th) {

            return response()->json([
                'success' => false,
                'data'=> $th
            ]);
        }
       
        
    }

    public function show($id)
    {
        $metda = MetDa::with("group")
        ->with("user")
        ->with("quest")
        ->find($id);

        if($metda->quest){

            $metda->membalas_user = User::find($metda->quest->user_id)->username;
        }

        $follow = qna_follow::
                where("user_id",Auth::id())
                ->where("quest_id",$id)
                ->first();

            if($follow){
                $metda->followed = true;
            }
        return response($metda);
    }

    public function edit(Request $request,$id)
    {
        try {
            MetDa::find($id)->update($request->all());
            return response()->json([
                'success' => true
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'data'=> $th
            ]);
        }
       
  
    }

    public function delete($id)
    {
        //
         try {
            $metda = MetDa::find($id)->delete();
            return response()->json([
                'success' => true
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'data'=> $th
            ]);
        }
    }

    public function questBalasan(Request $req, $id){
        if(Auth::id()){
                
            $skip = 0;
            $take = 5;

            if($req->page > 1){
                $skip = $take * $req->page-1;
            }

            $metda = qna::
                 with("group")
                ->with("user")
                ->with("quest")
                ->where("quest_id", $id)
                ->orderBy("activity","DESC")
                ->orderBy("id","DESC")
                ->take(100)
                ->get();



            $metda->map(function($q) {

                if($q->quest){

                    $q->membalas_user = User::find($q->quest->user_id)->username;
                }


                $follow = qna_follow::
                    where("user_id",Auth::id())
                    ->where("quest_id",$q->id)
                    ->first();

                if($follow){
                    $q->followed = true;
                }
                
            });

            return response()->json($metda->skip($skip)->take($take)->toArray());
            }
    }
}
