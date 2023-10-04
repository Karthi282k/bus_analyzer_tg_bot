<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\TelegramBotService;
use App\Models\TgUser;
use App\Models\TGSessions;
use Exception;

class TgBotController extends Controller
{
    public function inbound(Request $request, TelegramBotService $telegramBotService)
    {
        try {
            \Log::info($request->all());
            $buttons = array('/frcity','/tocity');
          
            if ($request->has('callback_query')) {
                $user_id = $request->callback_query['from']['id'];
                $message = array_key_exists('text', $request->callback_query['message']) ? $request->callback_query['message']['text'] : '';
                $first_name = $request->callback_query['from']['first_name'];
                $user_name = $request->callback_query['from']['username'];
                $message_id = $request->callback_query['message']['message_id'];
                $button =  $request->callback_query['data'];
                return $telegramBotService->callBackQuery($user_id, $first_name, $message_id, $message, $button);
            }elseif(in_array(substr($request->message['text'],0,7),$buttons)){
                $user_id = $request->message['from']['id'];
                $message = $request->message['text'];
                $first_name = $request->message['from']['first_name'];
                $user_name = $request->message['from']['username'];
                $message_id = $request->message['message_id'];
                return $telegramBotService->findCity($user_id, $first_name, $message_id, $message);

            }else {
               
                $user_id = $request->message['from']['id'];
                $message = $request->message['text'];
                $first_name = $request->message['from']['first_name'];
                $user_name = $request->message['from']['username'];
                $message_id = $request->message['message_id'];
                if (TgUser::where('tg_id', $user_id)->exists()) {
                    \Log::info('Exists');
                } else {
                    return $telegramBotService->newUser($user_id, $first_name, $message_id, $message);
                }
                //\Log::info($request->all());
                return $telegramBotService->existingUser($user_id, $first_name, $message_id, $message);
                // \Log::info(    $message_id);
            }
        } catch (Exception $e) {
        }
    }
}
