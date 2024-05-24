<?php

namespace Modules\Api\Controllers;

use App\Http\Controllers\Controller;
use Modules\Hotel\Models\Hotel;
use Modules\Hotel\Models\HotelRoom;
use Modules\Hotel\Models\BravoHotelRoomDate;
// use App\Models\User;
use App\User;
use Modules\Location\Models\Location;
// use App\Models\Media;
use Modules\Media\Models\MediaFile;
use App\Models\PriceCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class HomestayAPIController extends Controller
{
	function myHomestay(){
		$data = Hotel::where('create_user',Auth::id())->with(['hotelRoom'])->get();

        foreach($data as &$k){
            $k->total_room = HotelRoom::where('parent_id',$k->id)->sum('number');
			$preurl = "https://cdn.mykomodo.kabtour.com/uploads/";
			$banner = MediaFile::find($k->image_id);
			$k->banner = [
                "original"  => $preurl . $banner->file_path,
                "200x150"   => $preurl . $banner->file_resize_200,
                "250x200"   => $preurl . $banner->file_resize_250,
                "400x350"   => $preurl . $banner->file_resize_400,
            ];
            $gall = explode(',', $k->gallery);
            $gallimg = [];
            foreach ($gall as $key => $v) {
                $img = MediaFile::find($v);
                if ($img) {
                    $arrimg = [
                        "original"  => $preurl . $img->file_path,
                        "200x150"   => $preurl . $img->file_resize_200,
                        "250x200"   => $preurl . $img->file_resize_250,
                        "400x350"   => $preurl . $img->file_resize_400,
                    ];
                    array_push($gallimg, $arrimg);
                }
            }
            $k->gallery = $gallimg;
			$k->location_name = Location::find($k->location_id)->name;
			$hterms =  DB::table('bravo_hotel_term')->where('target_id',$k->id)->get('term_id');
			$terms = [];
			foreach($hterms as $ht){
				$terms[]=DB::table('bravo_terms')->find($ht->term_id)->name;
			}
			$k->terms = $terms;
			// dd($k->hotel_room);
			// dd($k->hotelRoom);
			foreach($k->hotelRoom as &$khr){
				$roomBanner = MediaFile::find($khr->image_id);
				$khr->banner = [
					"original"  => $preurl . $roomBanner->file_path,
					"200x150"   => $preurl . $roomBanner->file_resize_200,
					"250x200"   => $preurl . $roomBanner->file_resize_250,
					"400x350"   => $preurl . $roomBanner->file_resize_400,
				];
				$rgall = explode(',', $khr->gallery);
				$rgallimg = [];
				foreach ($rgall as $key => $v) {
					$img = MediaFile::find($v);
					if ($img) {
						$arrimg = [
							"original"  => $preurl . $img->file_path,
							"200x150"   => $preurl . $img->file_resize_200,
							"250x200"   => $preurl . $img->file_resize_250,
							"400x350"   => $preurl . $img->file_resize_400,
						];
						array_push($rgallimg, $arrimg);
					}
				}
				$khr->gallery = $rgallimg;
				$hrterms =  DB::table('bravo_hotel_room_term')->where('target_id',$khr->id)->get('term_id');
				$rterms = [];
				// dd($hrterms);
				foreach($hrterms as $hrt){
					if($hrt->term_id){
						$rterms[]=DB::table('bravo_terms')->find($hrt->term_id)->name;
					}
				}
				// dd($rterms);
				$khr->terms = $rterms;
				// dd($khr->terms);
			}
        } 	

		return response()->json([
			'success'=>true,
			'message'=>'Data fetched',
			'data'=>$data
		]);
	}

	private function generateSlug($uid, $title)
    {
        $slug = strtolower(str_replace(' ','-',$title)) . '-' . $uid . '-';
        $code = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
        $slug .= $code;
		return $slug; 
    }

	function saveHomestay(Request $request){
		$slug = $this->generateSlug(Auth::id(),$request->title);
		$hotel = new Hotel();
        $hotel->title = $request->title;
        $hotel->slug = $slug;
        $hotel->content = $request->content;
        $hotel->location_id = Location::where('slug',Auth::user()->location)->first()->id;
        $hotel->address = $request->address;
        $hotel->check_in_time = $request->checkin ?? '13:00';
        $hotel->check_out_time = $request->checkout ?? '10:00';
        $hotel->status=$request->status;
        $hotel->create_user = Auth::id();
        $hotel->save();
        $hotel->slug.='-'.$hotel->id;
        $hotel->save();

		$this->token = $request->bearerToken();
		
        $cdn = "https://cdn.mykomodo.kabtour.com/v2/media_files";
        $post = Http::withToken($this->token)->withoutVerifying()->withOptions(["verify" => false])->acceptJson();
        $names = [];
        foreach ($request->allFiles() as $k => $v) {
            if(is_array($v)){
                foreach($v as $vk){
                    $name = $vk->getClientOriginalName();
                    $post->attach($k.'[]', file_get_contents($vk), $name);
                    $names[]=$name;
                }
            }else{
                $name = $v->getClientOriginalName();
                $post->attach($k, file_get_contents($v), $name);
                $names[]=$name;
            }
        }
        // dd($names);
        
        $response = $post->post($cdn, ["prefix" => $hotel->slug]);
        // dd($response->json());
        // dd($response->json());
        $result = json_decode(json_encode($response->json()));
        $banner = 0;
        $gallery = [];
        
        $data = '';

        $banner = $result->banner->id;
        if(isset($result->gallery)){
            for ($i = 0; $i < sizeof($result->gallery); $i++) {
                $gallery[] = $result->gallery[$i]->id;
            }
        }

		$hotel->image_id = $banner;
		$hotel->banner_image_id = $banner;
		$hotel->gallery = implode(',',$gallery);
		$hotel->save();
		
        // for($i=0;$i<sizeof($request->terms);$i++){
        //     DB::table('bravo_hotel_term')->insert([
        //         'term_id' => $request->terms[$i],
        //         'target_id' => $hotel->id,
        //         'create_user' => Auth::id()
        //     ]);
        // }
		$data = $hotel;
		
		$rooms = json_decode($request->rooms) ?? null;
        $room_count = 0;
        if($rooms){
            $room_count = sizeof($rooms);
        }

		$tmp_rooms = [];
		for ($i=0; $i < $room_count; $i++) {
			$ro = $rooms[$i];
			$room = new HotelRoom();
			$room->title = $ro->title;
			$room->price = $ro->price;
			$room->price_weekend = $ro->price_weekend;
			$room->price_holiday = $ro->price_holiday;
			$room->discount = $ro->discount;
			$room->discount_weekend = $ro->discount_weekend;
			$room->discount_holiday = $ro->discount_holiday;
			$room->size = $ro->size ?? null;
			$room->beds = $ro->beds ?? null;
			$room->parent_id = $hotel->id;
			$room->number = $ro->total_room;
			$room->max_guests = $ro->max_guests;
			$room->single_bed = $ro->single_bed;
			$room->double_bed = $ro->double_bed;
			$room->twin_bed = $ro->twin_bed;
			$room->create_user = Auth::id();
			$room->status = $ro->status ?? 'publish';
			$room->save();

			if(isset($result->{"room_".($i+1)."_image"})){
				for ($ii = 0; $ii < sizeof($result->{"room_".($i+1)."_image"}); $ii++) {
					if($ii==0){
						$room->image_id = $result->{"room_".($i+1)."_image"}[$ii]->id;
					}else{
						$room->gallery = $result->{"room_".($i+1)."_image"}[$ii]->id;
					}
				}
			}
			$room->save();

			if(!$hotel->price){
				$hotel->price = $ro->price;
			}else{
				if($hotel->price > $ro->price){
					$hotel->price = $ro->price;
				}
			}
			$hotel->save();

			for($ij=0;$ij<sizeof($ro->terms);$ij++){
				DB::table('bravo_hotel_room_term')->insert([
					'term_id' => $ro->terms[$ij],
					'target_id' => $room->id,
					'create_user' => Auth::id()
				]);
			}
			$tmp_rooms[]=$room;
		}
		
		$data->rooms = $tmp_rooms;

		return response()->json(
			[
				'success'=>true,
				'message'=>'Homestay berhasil disimpan',
				'data'=>$data,
			]
		);
	}

	function updateHomestay(Request $request, $id){
		$hotel = Hotel::find($id);
		if($hotel){
			$hotel->title = $request->title;
			$hotel->content = $request->content;
			$hotel->address = $request->address;
			$hotel->check_in_time = $request->checkin;
			$hotel->check_out_time = $request->checkout;
			$hotel->status=$request->status;
			$hotel->save();

			$this->token = $request->bearerToken();
			
			$cdn = "https://cdn.mykomodo.kabtour.com/v2/media_files";
			$post = Http::withToken($this->token)->withoutVerifying()->withOptions(["verify" => false])->acceptJson();
			$names = [];
			foreach ($request->allFiles() as $k => $v) {
				if(is_array($v)){
					foreach($v as $vk){
						$name = $vk->getClientOriginalName();
						$post->attach($k.'[]', file_get_contents($vk), $name);
						$names[]=$name;
					}
				}else{
					$name = $v->getClientOriginalName();
					$post->attach($k, file_get_contents($v), $name);
					$names[]=$name;
				}
			}
			// dd($names);
			$result = '';
			if(sizeof($names)>0){
				$response = $post->post($cdn, ["prefix" => $hotel->slug]);
				// dd($response->json());
				// dd($response->json());
				$result = json_decode(json_encode($response->json()));
				$banner = 0;
				$gallery = [];
				
				$data = '';

				if(isset($result->banner)){
					$banner = $result->banner->id;
					$hotel->image_id = $banner;
					$hotel->banner_image_id = $banner;
				}
				if(isset($result->gallery)){
					for ($i = 0; $i < sizeof($result->gallery); $i++) {
						// # code...
						// if ($i == 0) {
						// 	$banner = $result->gallery[$i]->id;
						// } else {
						// 	$gallery[] = $result->gallery[$i]->id;
						// }
						$gallery[] = $result->gallery[$i]->id;
					}
					$hotel->gallery = implode(',',$gallery);
				}
				$hotel->save();
			}

			$rooms = json_decode($request->rooms) ?? null;
			$room_count = 0;
			if($rooms){
				$room_count = sizeof($rooms);
			}

			$tmp_rooms = [];
			for ($i=0; $i < $room_count; $i++) {
				$ro = $rooms[$i];
				$room = null;
				if($ro->id && $ro->id!='null'){
					$room = HotelRoom::find($ro->id);
				}else{
					$room = new HotelRoom();
				}
				$room->title = $ro->title;
				$room->price = $ro->price;
				$room->price_weekend = $ro->price_weekend;
				$room->price_holiday = $ro->price_holiday;
				$room->discount = $ro->discount;
				$room->discount_weekend = $ro->discount_weekend;
				$room->discount_holiday = $ro->discount_holiday;
				$room->size = $ro->size ?? null;
				$room->beds = $ro->beds ?? null;
				$room->parent_id = $hotel->id;
				$room->number = $ro->total_room;
				$room->max_guests = $ro->max_guests;
				$room->single_bed = $ro->single_bed;
				$room->double_bed = $ro->double_bed;
				$room->twin_bed = $ro->twin_bed;
				$room->create_user = Auth::id();
				$room->status = $ro->status ?? 'publish';
				$room->save();

				if(isset($result->{"room_".($i+1)."_image"})){
					for ($ii = 0; $ii < sizeof($result->{"room_".($i+1)."_image"}); $ii++) {
						if($ii==0){
							$room->image_id = $result->{"room_".($i+1)."_image"}[$ii]->id;
						}else{
							$room->gallery = $result->{"room_".($i+1)."_image"}[$ii]->id;
						}
					}
				}
				$room->save();
	
				if(!$hotel->price){
					$hotel->price = $ro->price;
				}else{
					if($hotel->price > $ro->price){
						$hotel->price = $ro->price;
					}
				}
				$hotel->save();
				
				$oldTerms = DB::table('bravo_hotel_room_term')->where('target_id',$ro->id)->get();
				$oldArray = [];
				$willDelete = [];
				foreach($oldTerms as $ot){
					$oldArray[]=$ot->term_id;
					if(!in_array($ot->term_id,$ro->terms)){
						$willDelete[] = $ot->id;
					}
				}
				for($ij=0;$ij<sizeof($ro->terms);$ij++){
					if(!in_array($ro->terms[$ij],$oldArray)){
						if($ro->terms[$ij]){
							DB::table('bravo_hotel_room_term')->insert([
								'term_id' => $ro->terms[$ij],
								'target_id' => $ro->id,
								'create_user' => Auth::id()
							]);
						}
					}
				}
				for($ik=0;$ik<sizeof($willDelete);$ik++){
					DB::table('bravo_hotel_room_term')->delete($willDelete[$ik]);
				}
				$tmp_rooms[]=$room;
			}

			$data = $hotel;
			return response()->json(
				[
					'success'=>true,
					'message'=>'Data Berhasil Diubah',
					'data'=>$data,
				]
			);
		}
		return response()->json(
			[
				'success'=>false,
				'message'=>'Homestay tidak ditemukan',
				'data'=>null,
			]
		);
	}

	public function deletehomestay($id){
        // $room = HotelRoom::find($id);
        $hotel = Hotel::find($id);
        $room = HotelRoom::where('parent_id',$id)->get();
        foreach($room as &$k){
            $r = HotelRoom::find($k->id);
            DB::table('bravo_hotel_room_term')->where('target_id',$r->id)->delete();
			BravoHotelRoomDate::where('target_id',$r->id)->delete();
            $r->delete();
        }
        DB::table('bravo_hotel_term')->where('target_id',$hotel->id)->delete();
        $hotel->delete();
        // $room->delete();
        return response()->json(
			[
				'success'=>true,
				'message'=>'Data berhasil dihapus'
			]
		);
    }

	public function saveHomestay2(Request $request){
		return response()->json(
			[
				'success'=>false,
				'message'=>'test',
				'req'=>$request->all()
			]
		);
	}

	public function listRoom($parent_id){
		$get_holiday = Http::withOptions([
            'verify' => false,
        ])->get('https://raw.githubusercontent.com/guangrei/Json-Indonesia-holidays/master/calendar.json')->json();
        $check_date = date("Y-m-d", strtotime("+8 Hours"));
		$rooms = HotelRoom::where('parent_id', $parent_id)->get();
		foreach($rooms as &$k){
			$preurl = "https://cdn.mykomodo.kabtour.com/uploads/";
			$banner = MediaFile::find($k->image_id);
			$k->banner = [
                "original"  => $preurl . $banner->file_path,
                "200x150"   => $preurl . $banner->file_resize_200,
                "250x200"   => $preurl . $banner->file_resize_250,
                "400x350"   => $preurl . $banner->file_resize_400,
            ];
            $gall = explode(',', $k->gallery);
            $gallimg = [];
            foreach ($gall as $key => $v) {
                $img = MediaFile::find($v);
                if ($img) {
                    $arrimg = [
                        "original"  => $preurl . $img->file_path,
                        "200x150"   => $preurl . $img->file_resize_200,
                        "250x200"   => $preurl . $img->file_resize_250,
                        "400x350"   => $preurl . $img->file_resize_400,
                    ];
                    array_push($gallimg, $arrimg);
                }
            }
            $k->gallery = $gallimg;
			// $k->location_name = Location::find($k->location_id)->name;
			$k->price_normal = $k->price;
            $k->price = $k->price_normal - $k->discount;
            if ($k->price_weekend && $k->price_weekend > 0) {
                if (date("D", strtotime($check_date)) === "Sat" || date("D", strtotime($check_date)) === "Sun") {
                    $k->price = $k->price_weekend - $k->discount_weekend;
                }
                if (array_key_exists($check_date, $get_holiday)) {
                    $k->price = $k->price_weekend - $k->discount_weekend;
                }
            }
            // $getCalendars = Product::find($k->id)->price_calendars;
            $getCalendars = PriceCalendar::where(['create_user'=>$k->create_user,'object_model'=>'homestay','price_date'=>$check_date,'status'=>1])->first();
            if ($getCalendars) {
                if ($k->price_holiday && $k->price_holiday > 0) {
                    // $arrDates = explode(',', $getCalendars);
                    // if (in_array($check_date, $arrDates)) {
                    //     $k->price = $k->price_holiday - $k->discount_holiday;
                    // }
                    $k->price = $k->price_holiday - $k->discount_holiday;
                }
            }
			$checkCal = BravoHotelRoomDate::where('target_id',$k->id)->where('price_date',$check_date)->where('active',1)->first();
            if($checkCal){
                $k->price = $k->price_holiday;
            }
            $k->price = (string)$k->price;
			$k->special_dates = BravoHotelRoomDate::where('target_id',$k->id)->get();
		}
		return response()->json([
			'success'=>true,
			'message'=>'room fetched',
			'data'=>$rooms
		]);
	}

	public function saveRoom(Request $request, $hotel_id){
		// return response()->json(
		// 	[
		// 		'success'=>'false',
		// 		'message'=>'test',
		// 		'data'=>$request->all()
		// 	]
		// );
		$hotel = Hotel::find($hotel_id);
        $room = new HotelRoom();
        $room->title = $request->title;
        $room->price = $request->price;
		$room->price_weekend = $request->price_weekend;
		$room->price_holiday = $request->price_holiday;
		$room->discount = $request->discount;
		$room->discount_weekend = $request->discount_weekend;
		$room->discount_holiday = $request->discount_holiday;
        $room->size = $request->size ?? null;
        $room->beds = $request->beds ?? null;
        $room->parent_id = $hotel_id;
        $room->number = $request->total_room;
		$room->max_guests = $request->max_guests;
		$room->single_bed = $request->single_bed;
		$room->double_bed = $request->double_bed;
		$room->twin_bed = $request->twin_bed;
        // $room->image_id = $hotel->image_id;
        // $room->gallery = $hotel->gallery;
        $room->create_user = Auth::id();
        $room->status = $request->status ?? 'publish';
        $room->save();
        // for($i=0;$i<sizeof($request->terms);$i++){
        //     DB::table('bravo_hotel_room_term')->insert([
        //         'term_id' => $request->terms[$i],
        //         'target_id' => $room->id,
        //         'create_user' => Auth::id()
        //     ]);
        // }
		$this->token = $request->bearerToken();
		
        $cdn = "https://cdn.mykomodo.kabtour.com/v2/media_files";
        $post = Http::withToken($this->token)->withoutVerifying()->withOptions(["verify" => false])->acceptJson();
        $names = [];
        foreach ($request->allFiles() as $k => $v) {
            if(is_array($v)){
                foreach($v as $vk){
                    $name = $vk->getClientOriginalName();
                    $post->attach($k.'[]', file_get_contents($vk), $name);
                    $names[]=$name;
                }
            }else{
                $name = $v->getClientOriginalName();
                $post->attach($k, file_get_contents($v), $name);
                $names[]=$name;
            }
        }
        // dd($names);
        
        $response = $post->post($cdn, ["prefix" => $hotel->slug.'-'.$room->id]);
        // dd($response->json());
        // dd($response->json());
        $result = json_decode(json_encode($response->json()));
        $banner = 0;
        $gallery = [];
		// return response()->json([
		// 	'success'=>true,
		// 	'message'=>'Kamar berhasil ditambahkan',
		// 	'data'=>$room,
		// 	'res'=>$result
		// ]);

        $banner = $result->banner->id;
        if(isset($result->gallery)){
            for ($i = 0; $i < sizeof($result->gallery); $i++) {
                $gallery[] = $result->gallery[$i]->id;
            }
        }
		$room->image_id = $banner;
		$room->gallery = implode(',',$gallery);
		$room->save();

        if(!$hotel->price){
            $hotel->price = $request->price;
        }else{
            if($hotel->price > $request->price){
                $hotel->price = $request->price;
            }
        }
        $hotel->save();

		if($request->holiday_price_status){
			if($room->price_holiday>0){
				if($request->has('dates')){
					$dates = $request->dates;
					if(!is_array($request->dates)){
						$temp = str_replace('[','',$request->dates);
						$temp = str_replace(']','',$request->dates);
						$temp = str_replace(' ','',$request->dates);
						$dates = explode(',',$temp);
					}
					for($i=0;$i<sizeof($dates);$i++){
						BravoHotelRoomDate::updateOrCreate(
							[
								'target_id'=>$room->id,
								'price_date'=>$dates[$i]
							],
							[
								'active'=>$request->holiday_price_status ? 1 : 0,
							]
						);
					}
					BravoHotelRoomDate::where('target_id',$room->id)->whereNotIn('price_date',$dates)->update(['active'=>0]);
				}
			}
            if($room->price_holiday==0){
                BravoHotelRoomDate::where('target_id',$room->id)->update(['active' => 0]);
            }
        }else{
            BravoHotelRoomDate::where('target_id',$room->id)->update(['active' => 0]);
        }

		return response()->json([
			'success'=>true,
			'message'=>'Kamar berhasil ditambahkan',
			'data'=>$room,
			'res'=>$result
		]);
	}

	public function updateRoom(Request $request, $id){
        $room = HotelRoom::find($id);
		if($room){
			$hotel = Hotel::find($room->parent_id);
			$room->title = $request->title ?? $room->title;
			$room->price = $request->price ?? $room->price;
			$room->price_weekend = $request->price_weekend ?? $room->price_weekend;
			$room->price_holiday = $request->price_holiday ?? $room->price_holiday;
			$room->discount = $request->discount ?? $room->discount;
			$room->discount_weekend = $request->discount_weekend ?? $room->discount_weekend;
			$room->discount_holiday = $request->discount_holiday ?? $room->discount_holiday;
			$room->size = $request->size ?? null;
			$room->beds = $request->beds ?? null;
			$room->number = $request->total_room ?? $room->number;
			$room->max_guests = $request->max_guests ?? $room->max_guests;
			$room->single_bed = $request->single_bed ?? $room->single_bed;
			$room->double_bed = $request->double_bed ?? $room->double_bed;
			$room->twin_bed = $request->twin_bed ?? $room->twin_bed;
			// $room->image_id = $hotel->image_id;
			// $room->gallery = $hotel->gallery;
			$room->create_user = Auth::id();
			$room->status = $request->status ?? 'publish';
			$room->save();
			// for($i=0;$i<sizeof($request->terms);$i++){
			//     DB::table('bravo_hotel_room_term')->insert([
			//         'term_id' => $request->terms[$i],
			//         'target_id' => $room->id,
			//         'create_user' => Auth::id()
			//     ]);
			// }
			$this->token = $request->bearerToken();
			
			$cdn = "https://cdn.mykomodo.kabtour.com/v2/media_files";
			$post = Http::withToken($this->token)->withoutVerifying()->withOptions(["verify" => false])->acceptJson();
			$names = [];
			foreach ($request->allFiles() as $k => $v) {
				if(is_array($v)){
					foreach($v as $vk){
						$name = $vk->getClientOriginalName();
						$post->attach($k.'[]', file_get_contents($vk), $name);
						$names[]=$name;
					}
				}else{
					$name = $v->getClientOriginalName();
					$post->attach($k, file_get_contents($v), $name);
					$names[]=$name;
				}
			}
			// dd($names);
			if(sizeof($names)>0){
				$response = $post->post($cdn, ["prefix" => $hotel->slug.'-'.$room->id]);
				// dd($response->json());
				// dd($response->json());
				$result = json_decode(json_encode($response->json()));
				$banner = 0;
				$gallery = [];
				if(isset($result->banner)){
					$banner = $result->banner->id;
				}
				if(isset($result->gallery)){
					for ($i = 0; $i < sizeof($result->gallery); $i++) {
						$gallery[] = $result->gallery[$i]->id;
					}
				}
				// dd([$result,$banner,$gallery]);
				$room->image_id = $banner;
				$room->gallery = implode(',',$gallery);
				$room->save();
			}

			if(!$hotel->price){
				$hotel->price = $request->price;
			}else{
				if($hotel->price > $request->price){
					$hotel->price = $request->price;
				}
			}
			$hotel->save();
			$isHoliday = false;
			if($request->holiday_price_status){
				$isHoliday = true;
                if($room->price_holiday>0){
					if($request->has('dates')){
						$dates = $request->dates;
						if(!is_array($request->dates)){
							$temp = str_replace('[','',$request->dates);
							$temp = str_replace(']','',$request->dates);
							$temp = str_replace(' ','',$request->dates);
							$dates = explode(',',$temp);
						}
						for($i=0;$i<sizeof($dates);$i++){
							BravoHotelRoomDate::updateOrCreate(
								[
									'target_id'=>$room->id,
									'price_date'=>$dates[$i]
								],
								[
									'active'=>$request->holiday_price_status ? 1 : 0,
								]
							);
						}
						BravoHotelRoomDate::where('target_id',$room->id)->whereNotIn('price_date',$dates)->update(['active'=>0]);
					}
				}
                if($room->price_holiday==0){
                    BravoHotelRoomDate::where('target_id',$room->id)->update(['active' => 0]);
                }
            }else{
                BravoHotelRoomDate::where('target_id',$room->id)->update(['active' => 0]);
            }

			return response()->json([
				'success'=>true,
				'message'=>'Data berhasil diubah',
				'req'=>$request->all(),
				'data'=>$room,
				'ish'=>$isHoliday,
				'test'=>true
			]);
		}
        return response()->json([
			'success'=>false,
			'message'=>'Kamar tidak ditemukan',
			'data'=>null
		]);
	}

	public function deleteRoom($id){
        $room = HotelRoom::find($id);
        // $parent_id = $room->parent_id;
        // DB::table('bravo_hotel_room_term')->where('target_id',$room->id)->delete();
		BravoHotelRoomDate::where('target_id',$room->id)->delete();
        $room->delete();
        // $room->delete();
        return response()->json([
			'success'=>true,
			'message'=>'Data berhasil dhapus',
		]);
    }
}
