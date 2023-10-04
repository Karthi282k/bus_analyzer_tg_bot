<?php

namespace App\Services;

use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\BusList;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;

class DataScrapingService
{

    public function test()
    {
        return 'busdata';
    }
    function minutes($time)
    {
        $time = explode(':', $time);
        return ($time[0] * 60) + ($time[1]) + ($time[2] / 60);
    }
    public function scrapeData($req_from, $req_to, $req_date)
    {
        try {
            $bus_short_codes = BusList::pluck('site_short_code');
            $optmized_bus_list = array();
            $temp_ar = array();
            // $rb_ar = array();
            // $ab_ar = array();
            // $gobo_ar = array();
            // $ptmb_ar = array();
            $all_bus_ar = array();
            foreach ($bus_short_codes as $single_code) {
                if ($single_code == 'rb') {
                    $from = $req_from;
                    $to = $req_to;
                    $date = Carbon::parse($req_date)->format('d-M-Y');
                    $from_res = Http::get('https://www.redbus.in/Home/SolarSearch?search=' . $from);
                    $to_res = Http::get('https://www.redbus.in/Home/SolarSearch?search=' .  $to);
                    if (empty($to_res->json()) || empty($from_res->json())) {
                        continue;
                    }
                    $src = $from_res['response']['docs'][0]['Name'];
                    $src_id = $from_res['response']['docs'][0]['ID'];
                    $dst = $to_res['response']['docs'][0]['Name'];
                    $dst_id = $to_res['response']['docs'][0]['ID'];
                    $response = Http::post('https://www.redbus.in/search/SearchResults?fromCity=' .  $src_id  . '&toCity=' .   $dst_id . '&src=' . $src . '&' . $dst . '=Madurai&DOJ=' . $date . '&sectionId=0&groupId=0&limit=0&offset=0&sort=0&sortOrder=0&meta=true&returnSearch=0');
                    if ($response->ok()) {
                        foreach ($response['inv'] as $single_res) {
                            $temp_ar['bus_name'] = $single_res['Tvs'];
                            $temp_ar['sound_dex'] = soundex($temp_ar['bus_name']);
                            $temp_ar['bus_type'] = $single_res['bt'];
                            $temp_ar['departure_time'] = Carbon::parse($single_res['dt'])->format('d-M-Y g:i a');
                            $temp_ar['reaching_time'] = Carbon::parse($single_res['at'])->format('d-M-Y g:i a');
                            $temp_ar['travel_hours'] = floor($single_res['dur'] / 60) . ':' . ($single_res['dur'] -   floor($single_res['dur'] / 60) * 60);
                            $temp_ar['bus_fare'] = $single_res['minfr'];
                            $temp_ar['aval_seats'] = $single_res['nsa'];
                            $temp_ar['source'] = 'rb';
                            $temp_ar['link'] = 'https://www.redbus.in/bus-tickets/' . $src . '-to-' . $dst . '?fromCityName=' . $src . '&fromCityId=' . $src_id . '&srcCountry=IND&toCityName=' . $dst . '&toCityId=' . $dst_id . '&destCountry=IND&onward=' . $date . '&opId=' . $single_res['oid'] . '&busType=Any&routeId=' . $single_res['rid'];
                            array_push($all_bus_ar, $temp_ar);
                            unset($temp_ar);
                        }
                    }
                } else if ($single_code == 'ab') {
                    $from = $req_from;
                    $to = $req_to;
                    $date = $req_date;
                    $dt_fr_link = Carbon::parse($date)->format('d-m-Y');
                    $from_res = Http::post('https://www.abhibus.com/SearchStations/?s=' . $from);
                    $to_res = Http::post('https://www.abhibus.com/SearchStations/?s=' . $to);
                    if (empty($to_res->json()) || empty($from_res->json())) {
                        continue;
                    }
                    $src_id =  $from_res[0]['id'];
                    $dst_id = $to_res[0]['id'];
                    $response = Http::post('https://www.abhibus.com/getonewayservices/' . $date . '/' . $src_id . '/' . $dst_id);
                    // dd($response);
                    if ($response->ok()) {
                        foreach ($response['serviceDetailsList'] as $single_res) {
                            $temp_ar['bus_name'] = $single_res['travelerAgentName'];
                            $temp_ar['sound_dex'] = soundex($temp_ar['bus_name']);
                            $temp_ar['bus_type'] = $single_res['busTypeName'];
                            $start_date = Carbon::parse(str_split($single_res['startTimeDateFormat'], 10)[0] . ' ' . $single_res['startTimeTwfFormat']);
                            $temp_ar['departure_time'] = $start_date->format('d-M-Y g:i a');
                            $temp_ar['reaching_time'] = $start_date->addMinutes($this->minutes($single_res['travelTime']))->format('d-M-Y g:i a');
                            $temp_ar['travel_hours'] = $single_res['travelTime'];
                            $temp_ar['bus_fare'] = $single_res['sortFare'];
                            $temp_ar['aval_seats'] = $single_res['availableSeats'];
                            $temp_ar['source'] = 'ab';
                            $temp_ar['link'] = 'https://www.abhibus.com/bus_search/' . $from_res[0]['label'] . '/' . $src_id . '/' . $to_res[0]['label'] . '/' . $dst_id . '/' . $dt_fr_link . '/O';
                            array_push($all_bus_ar, $temp_ar);
                            unset($temp_ar);
                        }
                    }
                } else if ($single_code == 'gobo') {
                    $from = $req_from;
                    $to = $req_to;
                    $date = Carbon::parse($req_date)->format('Ymd');
                    $from_res = Http::get('https://ground-auto-suggest.goibibo.com/api/v1/bus/giautosuggest/search?version=v2&new=1&query=' . $from . '&limit=1')->json();
                    $to_res = Http::get('https://ground-auto-suggest.goibibo.com/api/v1/bus/giautosuggest/search?version=v2&new=1&query=' . $to . '&limit=1')->json();
                    if (empty($to_res) || empty($from_res)) {
                        continue;
                    }
                    $src_name = $from_res['data']['documents'][0]['n'];
                    $src_id = $from_res['data']['documents'][0]['id'];
                    $des_name =  $to_res['data']['documents'][0]['n'];
                    $des_id =  $to_res['data']['documents'][0]['id'];
                    $response = Http::post('https://depot.goibibo.com/apis/v4/search/?format=json&flavour=v2', [
                        "dest" =>   $des_name,
                        "dest_vid" =>  $des_id,
                        "doj" => $date,
                        "src" => $src_name,
                        "src_vid" =>  $src_id

                    ]);
                    if ($response->ok()) {
                        foreach ($response->json()['buses'] as $single_res) {
                            $temp_ar['bus_name'] = $single_res['fl'][0]['cr'];
                            $temp_ar['sound_dex'] = soundex($temp_ar['bus_name']);
                            $temp_ar['bus_type'] = $single_res['fl'][0]['bt'];
                            $temp_ar['departure_time'] = Carbon::parse($single_res['fl'][0]['dd'] . ' ' . $single_res['fl'][0]['dt'])->format('d-M-Y g:i a');
                            $temp_ar['reaching_time'] =  Carbon::parse($single_res['fl'][0]['ad'] . ' ' . $single_res['fl'][0]['at'])->format('d-M-Y g:i a');
                            $temp_ar['travel_hours'] = $single_res['du'];
                            $temp_ar['bus_fare'] = $single_res['fd']['pp'];
                            $temp_ar['aval_seats'] = $single_res['fl'][0]['rd'][0]['SeatsAvailable'];
                            $temp_ar['source'] = 'gobo';
                            $temp_ar['link'] = 'https://www.goibibo.com/bus/search?bid=bus-' . $src_name . '-' . $des_name . '-' .  $date . '-0-0-0-0-' . $src_id . '-' . $des_id;
                            array_push($all_bus_ar, $temp_ar);
                            unset($temp_ar);
                        }
                    }
                } else if ($single_code == 'ptmbs') {
                    $from = $req_from;
                    $to = $req_to;
                    $date = $req_date;
                    $source_response = Http::get('https://travel.paytm.com/bus/v2/cities/' .  $from . '?is_phonetic=true&language=en&locale=en-US')->json();
                    $destination_response = Http::get('https://travel.paytm.com/bus/v2/cities/' .   $to . '?is_phonetic=true&language=en&locale=en-US')->json();
                    if (empty($destination_response) || empty($source_response)) {
                        continue;
                    }
                    $source_city_name = $source_response[0]['city_name'];
                    $source_doc_id = $source_response[0]['city_id'];
                    $destination_doc_id =   $destination_response[0]['city_id'];
                    $destination_city_name = $destination_response[0]['city_name'];
                    $response = Http::post('https://travel.paytm.com/bus/v3/search?client=web', [
                        "date" =>   $date,
                        "departed" => true,
                        "destination_doc_id" =>  $destination_doc_id,
                        "is_deal_applicable" => true,
                        "is_last_minute_booking" => true,
                        "leg_number" => 0,
                        "request_type" => "one_way",
                        "sold" => false,
                        "source_doc_id" => $source_doc_id
                    ]);
                    if ($response->ok()) {
                        foreach ($response->json()['body']['trips'] as $single_res) {
                            $temp_ar['bus_name'] = $single_res['provider_operator_name'];
                            $temp_ar['sound_dex'] = soundex($temp_ar['bus_name']);
                            $temp_ar['bus_type'] = $single_res['bus_type_name'];
                            $temp_ar['departure_time'] = Carbon::parse($single_res['departure_datetime'])->format('d-M-Y g:i a');
                            $temp_ar['reaching_time'] = Carbon::parse($single_res['arrival_datetime'])->format('d-M-Y g:i a');
                            $total_mins = Carbon::parse($single_res['departure_datetime'])->diffInMinutes(Carbon::parse($single_res['arrival_datetime']));
                            $temp_ar['travel_hours'] = floor($total_mins / 60) . ':' . ($total_mins -   floor($total_mins / 60) * 60);
                            $temp_ar['bus_fare'] = $single_res['fare']['prices'][0];
                            $temp_ar['aval_seats'] = $single_res['available_seats'];
                            $temp_ar['source'] = 'ptmb';
                            $temp_ar['link'] = 'https://tickets.paytm.com/bus/search/' . $source_city_name . '/' . $destination_city_name . '/' . $date . '/1';
                            array_push($all_bus_ar, $temp_ar);
                            unset($temp_ar);
                        }
                    }
                }
            }

            $all_bus_ar = collect($all_bus_ar);
            $groupedBysound_dex = $all_bus_ar->groupBy('sound_dex');
            $final_ar = array();
            $final_string = '';
            foreach ($groupedBysound_dex as $key1 => $value1) {
                if ($value1->count() < 2) {
                    array_push($final_ar, $value1[0]);
                } else {
                    $filter_by_departure_time = $groupedBysound_dex[$key1]->groupBy('departure_time')->filter(function (Collection $groups) {
                        return $groups->count() > 1;
                    });

                    foreach ($filter_by_departure_time as $key2 => $value2) {
                        $filter_by_reaching_time =  $filter_by_departure_time[$key2]->groupBy('reaching_time')->filter(function (Collection $groups) {
                            return $groups->count() > 1;
                        });
                        // if ($filter_by_reaching_time->count() < 2) {
                        //     foreach ($filter_by_reaching_time as $single_value3) {
                        //         dd($single_value3);
                        //     }
                        // } else {
                        // }
                        foreach ($filter_by_reaching_time as $single_value3) {
                            array_push($final_ar, $single_value3->sortBy('bus_fare')->first());
                        }
                    }
                }
            }
            $final_ar = collect($final_ar)->sortBy('bus_fare');
            foreach ($final_ar as $single_ar) {
                $final_string = $final_string . 'Bus Name: ' . $single_ar['bus_name'] . "\n"
                    . 'Bus Type: ' . $single_ar['bus_type'] . "\n"
                    . 'Departure Time: ' . $single_ar['departure_time'] . "\n"
                    . 'Reaching Time: ' . $single_ar['reaching_time'] . "\n"
                    . 'Travel Time: ' . $single_ar['travel_hours'] . "\n"
                    . 'Ticket Price: ' . $single_ar['bus_fare'] . "\n"
                    . 'Seat Avalibility: ' . $single_ar['aval_seats'] . "\n"
                    . 'Source Link: ' . $single_ar['link'] . "\n" . "\n";
            }
            //  dd($final_string);
            return $final_string;
            // return response()->json([
            //     'status' => 'success',
            //     'message' => '',
            //     'data' => $final_string
            // ]);
        } catch (Exception $e) {
            //  return 'We couldn’t find any buses in your selection. If you are experiencing technical issues, please contact the administrator.';
            return response()->json([
                'status' => 'failure',
                'exception' => $e->getMessage(),
                'message' => 'We couldn’t find any buses in your selection. If you are experiencing technical issues, please contact the administrator.',
                'data' => $e->getTraceAsString()
            ]);
        }
    }
}
