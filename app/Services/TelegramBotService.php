<?php

namespace App\Services;

use App\Models\TGSessions;
use App\Models\TGSheduleList;
use App\Models\TgUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use App\Services\DataScrapingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;


class TelegramBotService
{
    protected $token;
    protected $api_end_point;
    protected $headers;
    protected $how_to_use;
    protected $webScrapingController;

    public function __construct(DataScrapingService $webScrapingController)
    {
        $this->webScrapingController = $webScrapingController;
        $this->token = env('TELEGRAM_BOT_TOKEN');
        $this->api_end_point = env('TELEGRAM_API_END_POINT');
        $this->headers = [
            "Content-Type" => "application/json",
            "Accept" => "application/json",
        ];
        $this->how_to_use = 'Welcome to the Bus Ticket Analyser Chatbot! This chatbot is designed to help you find and book bus tickets prices easily and conveniently:


Step 1: Initiate the Bus Search
           * To start searching for bus tickets, type "Search Bus" or Search Bus in Keyboard button .
       

Step 2: Provide Journey Details
           * The chatbot will prompt you to enter your journey details, including the "From" and "To" locations.
           * And Dates of journey , You Must Select Future dates 


Step 3: Choose Sorting Criteria
           * After specifying your travel locations, the chatbot will ask you to select your sorting preference. You can sort the search results by:
                  *  Time (e.g., "Sort by Time")
                  *  Travel Duration (e.g., "Sort by Duration")
                  *  Price (e.g., "Sort by Price")
                  *  Seat Availability (e.g., "Sort by Availability")
       

Step 5: Review Search Results
           * The chatbot will then process your request and present you with a list of available bus options based on your chosen sorting criteria.
           * Each result will include details such as departure time, duration, price, and seat availability.';
    }


    function validate_email($email)
    {
        return (preg_match("/(@.*@)|(\.\.)|(@\.)|(\.@)|(^\.)/", $email) || !preg_match("/^.+\@(\[?)[a-zA-Z0-9\-\.]+\.([a-zA-Z]{2,4}|[0-9]{1,3})(\]?)$/", $email)) ? false : true;
    }

    public function sendMessage($user_id, $first_name, $message_id)
    {
        $result = ['success' => 'false', 'body' => []];
        $url = $this->api_end_point . '/' . $this->token . '/sendMessage';
        $text = "Hiii, " . $first_name . ", Have A Nice Day";
        //creating params
        $params = [
            'chat_id' => $user_id,
            'reply_to_message_id' => $message_id,
            'text' => $text
        ];

        try {
            $response = Http::withHeaders($this->headers)->post($url, $params);
            $result = ['success' => $response->ok(), 'body' => $response->json()];
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }

        \Log::info('TelegramBotService->sendMessage', ['result' => $result]);
        return $result;
    }

    public function existingUser($user_id, $first_name, $message_id, $message)
    {
        $result = ['success' => 'false', 'body' => []];
        $url = $this->api_end_point . '/' . $this->token . '/sendMessage';
        $msg_array = explode(" ", $message);
        $inlineKeyboard = array(
            'keyboard' => array(
                array(
                    array('text' => "Register 📝", "callback_data" => 'Register'),
                    array('text' => "Search Bus 🚌", "callback_data" => 'Search Bus')
                ), array(
                    array('text' => "How to use ℹ️", "callback_data" => 'How to use')
                )
            ),
            'one_time_keyboard' => true,
            'resize_keyboard' => true
        );

        \Log::info($msg_array);
        $text = 'working';
        if ($msg_array[0] == 'How') {
            $text = $this->how_to_use;
        } else if ($msg_array[0] == 'Search') {
            // $from = '';
            // $to = '';
            // $date = '';
            $tg_session = TGSessions::where('tg_user_id', $user_id);
            $json_format = $this->getJsonFormatForSearchBus($data = array());

            if ($tg_session->exists()) {
                $tg_session =   $tg_session->first();
                $updated_at = Carbon::parse($tg_session->updated_at)->format('Y-m-d h:m:s');
                $diff_minues =  Carbon::parse($tg_session->updated_at)->diffInMinutes(Carbon::now()->timezone('Asia/Kolkata'));
                if ($diff_minues < 10) {
                    //  \Log::info();
                    $session_data = json_decode($tg_session->session_data, true);
                    \Log::info($session_data);
                    // if (array_key_exists('From',  $session_data)) {
                    //     $from = $session_data['From'];
                    // }
                    // if (array_key_exists('To',  $session_data)) {
                    //     $to =  $session_data['To'];
                    // }
                    // if (array_key_exists('Date',  $session_data)) {
                    //     $date = Carbon::parse($session_data['Date'])->format('d-M-Y');
                    // }
                    $json_format = $this->getJsonFormatForSearchBus($session_data);
                } else {
                    $tg_session->delete();
                    // $text =   Carbon::now()->timezone('Asia/Kolkata')->format('Y-m-d h:m:s');
                    //$text = Carbon::now('IST');
                }
            }
            $text =
                'From : ' .  $json_format['From'] . '
To    ' . '  ' . ': ' . $json_format['To'] . ' 
Date : ' . ' ' . '' .  $json_format['Date'];

            $inlineKeyboard =  $json_format['inlineKeyboard'];
        } else if ($msg_array[0] == 'button_1') {
            $text = 'bt1';
        }




        $inlineKeyboard = json_encode($inlineKeyboard);
        //creating params
        $params = [
            'chat_id' => $user_id,
            'reply_to_message_id' => $message_id,
            'text' => $text,
            'reply_markup' => $inlineKeyboard
        ];

        try {
            $response = Http::withHeaders($this->headers)->post($url, $params);
            $result = ['success' => $response->ok(), 'body' => $response->json()];
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }



        \Log::info('TelegramBotService->sendMessage', ['result' => $result]);
        return $result;
    }

    public function newUser($user_id, $first_name, $message_id, $message)
    {
        $result = ['success' => 'false', 'body' => []];
        $url = $this->api_end_point . '/' . $this->token . '/sendMessage';
        $msg_array = explode(" ", $message);
        $inlineKeyboard = array(
            'keyboard' => array(
                array(
                    array('text' => "Register 📝"),
                    array('text' => "Search Bus 🚌")
                ), array(
                    array('text' => "How to use ℹ️")
                )
            ),
            'one_time_keyboard' => true,
            'resize_keyboard' => true
        );
        $inlineKeyboard = json_encode($inlineKeyboard);

        \Log::info($msg_array[0]);

        if ($message == '/start') {
            $text = "Hello " . $first_name . ", welcome to our incredible bus deals bot! This amazing bot is designed to analyze and present you with the most fantastic deals for booking bus tickets. To enjoy the benefits of this extraordinary service, all you need to do is complete a quick registration process. Simply type 'Register' and let the magic begin!";
        } else if ($msg_array[0] == 'Register') {
            $text = "Please Enter Your Mail Address";
        } else if ($this->validate_email($msg_array[0])) {
            try {
                $user = new TgUser;
                $user->tg_id = $user_id;
                $user->user_name = $first_name;
                $user->email = $msg_array[0];
                $user->save();
                if ($user) {
                    $text = " Congratulations, " . $first_name . "! Your account registration has been completed successfully. Now you can start enjoying the benefits of our exceptional service.";
                } else {
                    $text = "We are currently experiencing some technical problems. Please contact the administrator for assistance.";
                }
            } catch (Exception $e) {
                \Log::info(response()->json([
                    'status' => 'failure',
                    'data' => $e
                ]));
            }
        } else {
            $text = 'To use this bot, you must register.';
        }

        //creating params
        $params = [
            'chat_id' => $user_id,
            'reply_to_message_id' => $message_id,
            'text' => $text,
            'reply_markup' => $inlineKeyboard
        ];

        // $inlineKeyboard = [];

        // $currentTimestamp = time();
        // $dateButtonsCount = 15;

        // for ($i = -$dateButtonsCount; $i <= $dateButtonsCount; $i++) {
        //     $dateTimestamp = strtotime("$i days", $currentTimestamp);
        //     $dateText = date('M d', $dateTimestamp); // Format the date as you like
        //     $callbackData = date('Y-m-d', $dateTimestamp); // Use a standardized date format

        //     $inlineKeyboard[] = [
        //         [
        //             'text' => $dateText,
        //             'callback_data' => $callbackData,
        //         ],
        //     ];
        // }

        // $params = [
        //     'chat_id' => $user_id,
        //     'reply_to_message_id' => $message_id,
        //     'text' => $text,
        //     'reply_markup' => array(
        //         'inline_keyboard' =>  $inlineKeyboard

        //         // 'one_time_keyboard' => true,
        //         // 'resize_keyboard' => true
        //     )
        // ];

        try {
            $response = Http::withHeaders($this->headers)->post($url, $params);
            $result = ['success' => $response->ok(), 'body' => $response->json()];
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }

        \Log::info('TelegramBotService->sendMessage', ['result' => $result]);
        return $result;
    }

    public function callBackQuery($user_id, $first_name, $message_id, $message, $button)
    {

        $result = ['success' => 'false', 'body' => []];
        $url = $this->api_end_point . '/' . $this->token . '/sendMessage';
        // $msg_array = explode(" ", $message);
        $inlineKeyboard = array(
            'keyboard' => array(
                array(
                    array('text' => "Register 📝"),
                    array('text' => "Search Bus 🚌")
                ), array(
                    array('text' => "How to use ℹ️")
                )
            ),
            'one_time_keyboard' => true,
            'resize_keyboard' => true
        );


        \Log::info($message);
        $tg_session = TGSessions::where('tg_user_id', $user_id);

        if ($button == 'button_from') {
            $text = 'Please enter the name of the city from which you will be boarding. Remember to include "/frcity" before typing the name of your city. 
            For example: "/frcity Chennai"';
            $inlineKeyboard = array(
                'inline_keyboard' => [
                    [
                        // [
                        //     'text' => 'Confrim',
                        //     'callback_data' => 'button_Confrim',
                        // ],
                        // [
                        //     'text' => 'Back',
                        //     'callback_data' => 'button_back_from',
                        // ]
                    ],
                ],
                'one_time_keyboard' => true,
                'resize_keyboard' => true
            );
        } else if ($button == 'button_to') {
            $text = 'Please enter the name of the city where you will be dropping off. Remember to include "/tocity" before typing the name of your city. For example: "/tocity Madurai".';
            $inlineKeyboard = array(
                'inline_keyboard' => [
                    [
                        // [
                        //     'text' => 'Confrim',
                        //     'callback_data' => 'button_Confrim',
                        // ],
                        // [
                        //     'text' => 'Back',
                        //     'callback_data' => 'button_back_from',
                        // ]
                    ],
                ],
                'one_time_keyboard' => true,
                'resize_keyboard' => true
            );
        } else if ($button == 'button_date') {
            $text = 'Please select the date of your travel.';
            $inlineKeyboard = $this->getDate();
        } else if ($button == 'confirm_from' || $button == 'confirm_to') {
            if ($tg_session->exists()) {
                $tg_session =   $tg_session->first();
                $updated_at = Carbon::parse($tg_session->updated_at)->format('Y-m-d h:m:s');
                $diff_minues =  Carbon::parse($tg_session->updated_at)->diffInMinutes(Carbon::now()->timezone('Asia/Kolkata'));
                if ($diff_minues < 10) {
                    //  \Log::info();
                    $msg_arr = explode(" ", $message);
                    $session_data = json_decode($tg_session->session_data, true);
                    \Log::info($session_data);
                    $session_data[$msg_arr[0]] = $msg_arr[2];

                    $tg_session->session_data = json_encode($session_data, true);
                    $tg_session->save();

                    $json_format = $this->getJsonFormatForSearchBus($session_data);
                } else {
                    $tg_session->delete();
                    $msg_arr = explode(" ", $message);
                    $create_json[$msg_arr[0]] = $msg_arr[2];
                    $json = json_encode($create_json, true);
                    $json_ar = json_decode($json, true);
                    \Log::info($json_ar);
                    $text =   $json;
                    $save_session = new TGSessions;
                    $save_session->tg_user_id  = $user_id;
                    $save_session->session_data = json_encode($json_ar, true);
                    $save_session->save();
                    \Log::info($save_session);
                    // if ($save_session) {
                    //     $text =  'Working';
                    // } else {
                    //     $text  = 'We run into some techinal issue kindly contact the admin';
                    // }
                    $json_format = $this->getJsonFormatForSearchBus($json_ar);
                }
            } else {
                $msg_arr = explode(" ", $message);
                $create_json[$msg_arr[0]] = $msg_arr[2];
                $json = json_encode($create_json, true);
                $json_ar = json_decode($json, true);
                \Log::info($json_ar);
                $text =   $json;
                $save_session = new TGSessions;
                $save_session->tg_user_id  = $user_id;
                $save_session->session_data = json_encode($json_ar, true);
                $save_session->save();
                \Log::info($save_session);
                $json_format = $this->getJsonFormatForSearchBus($json_ar);
            }
            $text =
                'From : ' .  $json_format['From'] . '
To    ' . '  ' . ': ' . $json_format['To'] . ' 
Date : ' . ' ' . '' .  $json_format['Date'];
            $inlineKeyboard =  $json_format['inlineKeyboard'];
        } else if ($button == 'button_search') {
            if ($tg_session->exists()) {
                $tg_session =   $tg_session->first();
                $session_data = json_decode($tg_session->session_data, true);
                if (array_key_exists('From', $session_data) && array_key_exists('To', $session_data) && array_key_exists('Date', $session_data)) {
                    \Log::info('working line 393');
                    \Log::info($session_data['From']);
                    \Log::info($session_data['To']);
                    \Log::info($session_data['Date']);
                    // $tg_shedule_list = TGSheduleList::where('tg_id', $user_id);
                    // if ($tg_shedule_list->exists()) {
                    //     $tg_shedule_list->delete();
                    // }
                    // $new_shedule = new TGSheduleList;
                    // $new_shedule->tg_id = $user_id;
                    // $new_shedule->save();
                    $bus_list = 'Please wait minute or two we will fetching your results';
                    $text = $bus_list;
                    return $this->SendBusList($session_data['From'], $session_data['To'], $session_data['Date'], $user_id);

                    \Log::info($bus_list);
                } else {
                    $text = 'Please select the "From," "To," and "Date" fields to initiate the search.';
                }
            } else {
                $text = 'Please select the "From," "To," and "Date" fields to initiate the search.';
            }
        } else if ($button != 'button_back_for_search_bus' && $button != 'button_search') {
            if (checkdate(substr($button, 5, -3), substr($button, 8), substr($button, 0, -6))) {
                if ($tg_session->exists()) {
                    $tg_session =   $tg_session->first();
                    $updated_at = Carbon::parse($tg_session->updated_at)->format('Y-m-d h:m:s');
                    $diff_minues =  Carbon::parse($tg_session->updated_at)->diffInMinutes(Carbon::now()->timezone('Asia/Kolkata'));
                    if ($diff_minues < 10) {
                        $session_data = json_decode($tg_session->session_data, true);
                        \Log::info($session_data);
                        $session_data['Date'] = $button;
                        $tg_session->session_data = json_encode($session_data, true);
                        $tg_session->save();

                        $json_format = $this->getJsonFormatForSearchBus($session_data);
                        \Log::info($json_format);
                    } else {
                        $tg_session->delete();
                        $create_json['date'] = $button;
                        $json = json_encode($create_json, true);
                        $json_ar = json_decode($json, true);
                        \Log::info($json_ar);
                        $text =   $json;
                        $save_session = new TGSessions;
                        $save_session->tg_user_id  = $user_id;
                        $save_session->session_data = json_encode($json_ar, true);
                        $save_session->save();
                        \Log::info($save_session);
                        $json_format = $this->getJsonFormatForSearchBus($json_ar);
                    }
                } else {
                    $create_json['Date'] = $button;
                    $json = json_encode($create_json, true);
                    $json_ar = json_decode($json, true);
                    \Log::info($json_ar);
                    $text =   $json;
                    $save_session = new TGSessions;
                    $save_session->tg_user_id  = $user_id;
                    $save_session->session_data = json_encode($json_ar, true);
                    $save_session->save();
                    \Log::info($save_session);
                    $json_format = $this->getJsonFormatForSearchBus($json_ar);
                }
                $text =
                    'From : ' .  $json_format['From'] . '
To    ' . '  ' . ': ' . $json_format['To'] . ' 
Date : ' . ' ' . '' .  $json_format['Date'];
                $inlineKeyboard =  $json_format['inlineKeyboard'];
            }
        }
        $inlineKeyboard = json_encode($inlineKeyboard);
        //creating params
        $params = [
            'chat_id' => $user_id,
            'reply_to_message_id' => $message_id,
            'text' => $text,
            'reply_markup' => $inlineKeyboard
        ];

        try {
            $response = Http::withHeaders($this->headers)->post($url, $params);
            $result = ['success' => $response->ok(), 'body' => $response->json()];
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }

        \Log::info('TelegramBotService->sendMessage', ['result' => $result]);
        return $result;
    }

    public function findCity($user_id, $first_name, $message_id, $message)
    {
        $result = ['success' => 'false', 'body' => []];
        $url = $this->api_end_point . '/' . $this->token . '/sendMessage';
        $msg_array = explode(" ", $message);
        // $inlineKeyboard = array();
        // // $inlineKeyboard = array(
        // //     'inline_keyboard' => [
        // //         [
        // //             [
        // //                 'text' => 'Confrim',
        // //                 'callback_data' => 'from_Confrim',
        // //             ],
        // //             [
        // //                 'text' => 'Back',
        // //                 'callback_data' => 'button_back_from',
        // //             ]
        // //         ],
        // //     ],
        // //     'one_time_keyboard' => true,
        // //     'resize_keyboard' => true
        // // );

        \Log::info($msg_array);
        $user_enter_city =  $msg_array[1];
        $res_city = Http::get('https://travel.paytm.com/bus/v2/cities/' .   $user_enter_city . '?is_phonetic=true&language=en&locale=en-US')->json()[0]['city_name'];
        // $params = [
        //     'chat_id' => $user_id,
        //     'reply_to_message_id' => $message_id,
        //     'text' => $text,
        //     // 'reply_markup' => $inlineKeyboard
        // ];
        \Log::info($msg_array[0]);
        if (count($msg_array) != 2) {
            $text = 'Please Enter Valid City Name';
            $params = [
                'chat_id' => $user_id,
                'reply_to_message_id' => $message_id,
                'text' => $text,
                // 'reply_markup' => $inlineKeyboard
            ];
        } else if ($msg_array[0] == '/frcity') {
            $text = 'From : ' . $res_city;
            $inlineKeyboard = array(
                'inline_keyboard' => [
                    [
                        [
                            'text' => 'Confrim',
                            'callback_data' => 'confirm_from',
                        ],
                        [
                            'text' => 'Back',
                            'callback_data' => 'button_from',
                        ]
                    ],
                ],
                'one_time_keyboard' => true,
                'resize_keyboard' => true
            );
            $params = [
                'chat_id' => $user_id,
                'reply_to_message_id' => $message_id,
                'text' => $text,
                'reply_markup' => $inlineKeyboard
            ];
        } else if ($msg_array[0] == '/tocity') {
            $text = 'To : ' . $res_city;
            $inlineKeyboard = array(
                'inline_keyboard' => [
                    [
                        [
                            'text' => 'Confrim',
                            'callback_data' => 'confirm_to',
                        ],
                        [
                            'text' => 'Back',
                            'callback_data' => 'button_to',
                        ]
                    ],
                ],
                'one_time_keyboard' => true,
                'resize_keyboard' => true
            );
            $params = [
                'chat_id' => $user_id,
                'reply_to_message_id' => $message_id,
                'text' => $text,
                'reply_markup' => $inlineKeyboard
            ];
        }

        $inlineKeyboard = json_encode($inlineKeyboard);
        //creating params

        try {
            $response = Http::withHeaders($this->headers)->post($url, $params);
            $result = ['success' => $response->ok(), 'body' => $response->json()];
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }



        \Log::info('TelegramBotService->sendMessage', ['result' => $result]);
        return $result;
    }

    public function getJsonFormatForSearchBus($session_data)
    {
        $response = array();
        $response['From'] = '';
        $response['To'] = '';
        $response['Date'] = '';
        if (array_key_exists('From',  $session_data)) {
            $response['From'] = $session_data['From'];
        }
        if (array_key_exists('To',  $session_data)) {
            $response['To'] =  $session_data['To'];
        }
        if (array_key_exists('Date',  $session_data)) {
            $response['Date'] = Carbon::parse($session_data['Date'])->format('d-M-Y');
        }
        $response['inlineKeyboard'] = array(
            'inline_keyboard' => [
                [[
                    'text' => 'Search',
                    'callback_data' => 'button_search',
                ]],
                [
                    [
                        'text' => 'From',
                        'callback_data' => 'button_from',
                    ],
                    [
                        'text' => 'To',
                        'callback_data' => 'button_to',
                    ],
                    [
                        'text' => 'Date',
                        'callback_data' => 'button_date',
                    ],
                ],
                [
                    [
                        'text' => 'Back',
                        'callback_data' => 'button_back_for_search_bus',
                    ],
                ],
            ],
            'one_time_keyboard' => true,
            'resize_keyboard' => true
        );

        return $response;
    }

    public function getDate()
    {

        if (Carbon::tomorrow()->diffInHours(Carbon::now()) > 2) {
            $current_date = Carbon::today();
        } else {
            $current_date = Carbon::tomorrow();
        }
        $current_date =  $current_date->format('Y-m-d');
        $first_row = array();
        $secound_row = array();
        $third_row = array();
        $fourth_row = array();
        $temp_ar = array();
        for ($i = 0; $i < 10; $i++) {
            if ($i == 0) {
                $temp_ar['text'] = Carbon::parse($current_date)->addDays($i)->format('d-M-Y');
                $temp_ar['callback_data'] = Carbon::parse($current_date)->addDays($i)->format('Y-m-d');
                array_push($first_row, $temp_ar);
                unset($temp_ar);
            } else if ($i == 1 || $i == 2 || $i == 3 || $i == 4) {
                $temp_ar['text'] = Carbon::parse($current_date)->addDays($i)->format('d-M-Y');
                $temp_ar['callback_data'] = Carbon::parse($current_date)->addDays($i)->format('Y-m-d');
                array_push($secound_row, $temp_ar);
                unset($temp_ar);
            } else if ($i == 5 || $i == 6 || $i == 7 || $i == 8) {
                $temp_ar['text'] = Carbon::parse($current_date)->addDays($i)->format('d-M-Y');
                $temp_ar['callback_data'] = Carbon::parse($current_date)->addDays($i)->format('Y-m-d');
                array_push($third_row, $temp_ar);
                unset($temp_ar);
            } else {
                $temp_ar['text'] = Carbon::parse($current_date)->addDays($i)->format('d-M-Y');
                $temp_ar['callback_data'] = Carbon::parse($current_date)->addDays($i)->format('Y-m-d');
                array_push($fourth_row, $temp_ar);
                unset($temp_ar);
            }
        }
        $key_board_ar = array($first_row, $secound_row, $third_row, $fourth_row);
        $inlineKeyboard = array(
            'inline_keyboard' => $key_board_ar,
            'one_time_keyboard' => true,
            'resize_keyboard' => true
        );
        return $inlineKeyboard;
    }

    public function SendBusList($from, $to, $date, $user_id)
    {
        $result = ['success' => 'false', 'body' => []];
        $url = $this->api_end_point . '/' . $this->token . '/sendMessage';
        $text = "";
        //creating params
        \Log::info('working line 693');
        $params = [
            'chat_id' => $user_id,
            // 'reply_to_message_id' => $message_id,
            'text' => $text
        ];

        try {
            $chunks = str_split($this->webScrapingController->scrapeData($from, $to, $date,), 4096);
            foreach ($chunks as $chunk) {
                $params = [
                    'chat_id' => $user_id,
                    // 'reply_to_message_id' => $message_id,
                    'text' => $chunk
                ];
                $response = Http::withHeaders($this->headers)->post($url, $params);
                $result = ['success' => $response->ok(), 'body' => $response->json()];
            }
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }

        \Log::info('TelegramBotService->sendMessage', ['result' => $result]);
        return $result;
    }
}
