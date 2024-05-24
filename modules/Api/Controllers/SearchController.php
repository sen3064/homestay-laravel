<?php
namespace Modules\Api\Controllers;
use App\Http\Controllers\Controller;
use App\Models\User as ModelsUser;
use Illuminate\Http\Request;
use Modules\Booking\Models\Service;
use Modules\Flight\Controllers\FlightController;
use App\User;
use Illuminate\Support\Facades\DB;
use Modules\User\Models\User as UserModelsUser;
use App\Models\ShopSetting;
use Illuminate\Support\Facades\Cache;
class SearchController extends Controller
{

    public function search($type = 'hotel'){
        $type = $type ? $type : request()->get('type');
        $filters = [];
        if(empty($type))
        {
            return $this->sendError(__("Type is required"));
        }

        $class = get_bookable_service_by_id($type);
        if(empty($class) or !class_exists($class)){
            return $this->sendError(__("Type does not exists"));
        }
        
        $rows = call_user_func([$class,'search'],request());
        $total = $rows->total();

        // if($type=='hotel'){
        //     $business_names = [];
        //     if(request()->has('checkin')){
        //         if(request()->get('checkin')!=null && request()->get('checkin')!='null' && request()->get('checkin')!=''){
        //             $filters['start_date']=request()->get('checkin');
        //         }
        //         else{
        //             $filters['start_date']=date('Y-m-d',strtotime("+24 Hours"));
        //         }
        //     }else{
        //         $filters['start_date']=date('Y-m-d',strtotime("+24 Hours"));
        //     }
            
        //     if(request()->has('checkout')){
        //         if(request()->get('checkout')!=null && request()->get('checkout')!='null' && request()->get('checkout')!=''){
        //             $filters['end_date']=request()->get('checkout');
        //         }else{
        //             $filters['end_date']=date('Y-m-d',strtotime($filters['start_date']." +24 Hours"));
        //         }
        //     }else{
        //         //$filters['end_date']=date('Y-m-d',strtotime("+48 Hours"));
        //         $filters['end_date']=date('Y-m-d',strtotime($filters['start_date']." +24 Hours"));
        //     }

        //     $c=0;
        //     $unsets=[];
            
        //     foreach($rows as &$row){
        //         // $getSetting = DB::table('shop_settings')->where(['user_id'=>$row->create_user,'object_model'=>'hotel'])->first();
        //         $getSetting = ShopSetting::where(['user_id'=>$row->create_user,'object_model'=>'hotel'])->first();
        //         // $is_open = !$getSetting ? true : ($getSetting->is_open==1 ? true:false);
        //         // if(!$is_open){
        //         //     array_push($unsets,$c);
        //         //     // $c++;
        //         //     // continue;
        //         // }
        //         // $user = User::find($row->create_user);
        //         // if($user->status == 'suspend'){
        //         //     array_push($unsets,$c);
        //         //     // $c++;
        //         //     // continue;
        //         // }
        //         if(!array_key_exists($row->create_user,$business_names)){
        //             if($getSetting){
        //                 $business_names[$row->create_user] = $getSetting->name;
        //             }else{
        //                 $business_names[$row->create_user] = User::find($row->create_user)->business_name;
        //             }
        //         }
        //         $row->business_name = $business_names[$row->create_user];
        //         $filters['id']=$row->id;
        //         $filters['hotel_id']=$row->id;
        //         // dd($filters);
        //         // $row->price_normal = $row->price - $row->discount;
        //         $row->price_normal = $row->price;
        //         $row->price = 0;
        //         // dd($row);
        //         $rooms = $row->getRoomsAvailability($filters);
        //         // dd($rooms);
        //         foreach($rooms as $room){
        //             // dd($room);
        //             if($row->price == 0){
        //                 $row->price = $room['price_final'];
        //             }
        //             if($room['price_final']<$row->price){
        //                 $row->price = $room['price_final'];
        //             }
        //         }
        //         $row->rooms = $rooms;
        //         $row->seller = User::find($row->create_user,['id','first_name','last_name','name as pic_name','email']);
        //         $c++;
        //     }
        //     // foreach($unsets as $k => $v){
        //     //     unset($rows[$v]);
        //     // }
        // }

        // return $this->sendSuccess(
        //     [
        //         'total'=>$total,
        //         'total_pages'=>$rows->lastPage(),
        //         // 'data'=>$rows->map(function($row){
        //         //     return $row->dataForApi();
        //         // }),
        //         'data'=>$rows,
        //     ]
        // );
         if($type == 'hotel') {
            $defaultStartDate = date('Y-m-d', strtotime("+24 Hours"));
            $defaultEndDate = date('Y-m-d', strtotime("+48 Hours"));

            // Check request for dates and set filters accordingly
            $filters['start_date'] = request()->has('checkin') && request()->get('checkin') ? request()->get('checkin') : $defaultStartDate;
            $filters['end_date'] = request()->has('checkout') && request()->get('checkout') ? request()->get('checkout') : date('Y-m-d', strtotime($filters['start_date'] . " +24 Hours"));

            // $business_names = [];
            // $user_ids = $rows->pluck('create_user')->unique()->all();
            // $settings = DB::table('shop_settings')
            //               ->whereIn('user_id', $user_ids)
            //               ->where('object_model', 'hotel')
            //               ->get()
            //               ->keyBy('user_id');
            // $users = User::whereIn('id', $user_ids)->get()->keyBy('id');
            
            $user_ids = $rows->pluck('create_user')->unique()->all();

            // Attempt to retrieve settings from cache or fetch from database and cache the result
            $settings = Cache::remember('shop_settings_hotel', 60, function () use ($user_ids) {
                return DB::table('shop_settings')
                         ->whereIn('user_id', $user_ids)
                         ->where('object_model', 'hotel')
                         ->get()
                         ->keyBy('user_id');
            });
            
            $users = Cache::remember('users_details', 60, function () use ($user_ids) {
                return User::whereIn('id', $user_ids)->get()->keyBy('id');
            });

            foreach($rows as &$row) {
                $getSetting = $settings[$row->create_user] ?? null;
                $user = $users[$row->create_user];

                $business_name = $getSetting ? $getSetting->name : $user->business_name;
                $row->business_name = $business_name;

                $filters['id'] = $row->id;
                $filters['hotel_id'] = $row->id;
                $row->price_normal = $row->price;
                $row->price = 0;

                $rooms = $row->getRoomsAvailability($filters);
                foreach($rooms as $room) {
                    $row->price = $row->price == 0 ? $room['price_final'] : min($row->price, $room['price_final']);
                }
                $row->rooms = $rooms;
                $row->seller = [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'pic_name' => $user->name,
                    'email' => $user->email
                ];
            }
        }

        return $this->sendSuccess(
            [
                'total'=>$total,
                'total_pages'=>$rows->lastPage(),
                'data'=>$rows,
            ]
        );
    }
    
    public function search2($type = 'hotel'){
        $type = $type ? $type : request()->get('type');
        $filters = [];
        if(empty($type))
        {
            return $this->sendError(__("Type is required"));
        }

        $class = get_bookable_service_by_id($type);
        if(empty($class) or !class_exists($class)){
            return $this->sendError(__("Type does not exists"));
        }
        
        
        $rows = call_user_func([$class,'search'],request());
        $total = $rows->total();

        if($type=='hotel'){
            if(request()->has('checkin')){
                $filters['start_date']=request()->get('checkin');
            }else{
                $filters['start_date']=date('Y-m-d',strtotime("+24 Hours"));
            }
            
            if(request()->has('checkout')){
                $filters['end_date']=request()->get('checkout');
            }else{
                $filters['end_date']=date('Y-m-d',strtotime("+48 Hours"));
            }

            $c=0;
            $unsets=[];

            foreach($rows as &$row){
                $getSetting = DB::table('shop_settings')->where(['user_id'=>$row->create_user,'object_model'=>'hotel'])->first();
                $is_open = !$getSetting ? true : ($getSetting->is_open==1 ? true:false);
                if(!$is_open){
                    array_push($unsets,$c);
                    $c++;
                    continue;
                }
                if(User::find($row->create_user)->status == 'suspend'){
                    array_push($unsets,$c);
                    $c++;
                    continue;
                }
                $filters['id']=$row->id;
                $filters['hotel_id']=$row->id;
                // dd($filters);
                $row->price_normal = $row->price - $row->discount;
                // $row->price = 0;
                // dd($row);
                // $rooms = $row->getRoomsAvailability($filters);
                // // dd($rooms);
                // foreach($rooms as $room){
                //     // dd($room);
                //     if($row->price == 0){
                //         $row->price = $room['price'];
                //     }
                //     if($room['price']<$row->price){
                //         $row->price = $room['price'];
                //     }
                // }
                // $row->rooms = $rooms;
                
                if($getSetting){
                    $row->business_name = $getSetting->name;
                }else{
                    $row->business_name = User::find($row->create_user)->business_name;
                }
                $c++;
            }
            foreach($unsets as $k => $v){
                unset($rows[$v]);
            }
        }

        return $this->sendSuccess(
            [
                'total'=>$total,
                'total_pages'=>$rows->lastPage(),
                // 'data'=>$rows->map(function($row){
                //     return $row->dataForApi();
                // }),
                'data'=>$rows,
            ]
        );
    }
    
    public function getRooms($hotel_id){
        $type='hotel';
        if(request()->has('checkin')){
            $filters['start_date']=request()->get('checkin');
        }else{
            $filters['start_date']=date('Y-m-d',strtotime("+24 Hours"));
        }
            
        if(request()->has('checkout')){
            $filters['end_date']=request()->get('checkout');
        }else{
            $filters['end_date']=date('Y-m-d',strtotime("+48 Hours"));
        }
            
        $class = get_bookable_service_by_id($type);
        
        if(empty($class) or !class_exists($class)){
            return $this->sendError(__("Type does not exists"));
        }

        $row = $class::find($hotel_id);
        if(empty($row))
        {
            return $this->sendError(__("Resource not found"));
        }

        $filters['id']=$row->id;
        $filters['hotel_id']=$row->id;
        // dd($filters);
        $row->price_normal = $row->price - $row->discount;
        $row->price = 0;
        $rooms = $row->getRoomsAvailability($filters);
        // dd($rooms);
        foreach($rooms as $room){
            // dd($room);
            if($row->price == 0){
                $row->price = $room['price'];
            }
            if($room['price']<$row->price){
                $row->price = $room['price'];
            }
        }
        $row->rooms = $rooms;
        
        return $this->sendSuccess(
            [
                'total'=>sizeof($rooms),
                'total_pages'=>1,
                'data'=>$rooms,
            ]
        );
    }


    public function searchServices(){
        $rows = call_user_func([new Service(),'search'],request());
        $total = $rows->total();
        return $this->sendSuccess(
            [
                'total'=>$total,
                'total_pages'=>$rows->lastPage(),
                'data'=>$rows->map(function($row){
                    return $row->dataForApi();
                }),
            ]
        );
    }

    public function getFilters($type = ''){
        $type = $type ? $type : request()->get('type');
        if(empty($type))
        {
            return $this->sendError(__("Type is required"));
        }
        $class = get_bookable_service_by_id($type);
        if(empty($class) or !class_exists($class)){
            return $this->sendError(__("Type does not exists"));
        }
        $data = call_user_func([$class,'getFiltersSearch'],request());
        return $this->sendSuccess(
            [
                'data'=>$data
            ]
        );
    }

    public function getFormSearch($type = ''){
        $type = $type ? $type : request()->get('type');
        if(empty($type))
        {
            return $this->sendError(__("Type is required"));
        }
        $class = get_bookable_service_by_id($type);
        if(empty($class) or !class_exists($class)){
            return $this->sendError(__("Type does not exists"));
        }
        $data = call_user_func([$class,'getFormSearch'],request());
        return $this->sendSuccess(
            [
                'data'=>$data
            ]
        );
    }

    public function detail($id = '')
    {
        $type = 'hotel';
        if(empty($type)){
            return $this->sendError(__("Resource is not available"));
        }
        if(empty($id)){
            return $this->sendError(__("Resource ID is not available"));
        }

        $class = get_bookable_service_by_id($type);
        if(empty($class) or !class_exists($class)){
            return $this->sendError(__("Type does not exists"));
        }

        $row = $class::find($id);
        if(empty($row))
        {
            return $this->sendError(__("Resource not found"));
        }

        if($type=='flight'){
            return (new FlightController())->getData(\request(),$id);
        }

        return $this->sendSuccess([
            'data'=>$row->dataForApi(true)
        ]);

    }

    public function checkAvailability(Request $request ,$id = ''){
        $type = 'hotel';
        if(empty($type)){
            return $this->sendError(__("Resource is not available"));
        }
        if(empty($id)){
            return $this->sendError(__("Resource ID is not available"));
        }
        $class = get_bookable_service_by_id($type);
        if(empty($class) or !class_exists($class)){
            return $this->sendError(__("Type does not exists"));
        }
        $classAvailability = $class::getClassAvailability();
        $classAvailability = new $classAvailability();
        // dd($classAvailability);
        $request->merge(['id' => $id]);
        if($type == "hotel"){
            $request->merge(['hotel_id' => $id]);
            return $classAvailability->checkAvailability($request);
        }
        return $classAvailability->loadDates($request);
    }

    public function checkBoatAvailability(Request $request ,$id = ''){
        if(empty($id)){
            return $this->sendError(__("Boat ID is not available"));
        }
        $class = get_bookable_service_by_id('boat');
        $classAvailability = $class::getClassAvailability();
        $classAvailability = new $classAvailability();
        $request->merge(['id' => $id]);
        return $classAvailability->availabilityBooking($request);
    }
}
