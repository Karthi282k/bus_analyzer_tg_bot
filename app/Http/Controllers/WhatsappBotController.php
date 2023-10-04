<?php

namespace App\Http\Controllers;

use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;


class WhatsappBotController extends Controller
{

    public function sendWhatsAppMessage(string $message, string $recipient)
    {
        $twilio_whatsapp_number = getenv('TWILIO_WHATSAPP_NUMBER');
        $account_sid = getenv("TWILIO_SID");
        $auth_token = getenv("TWILIO_AUTH_TOKEN");
        $client = new Client($account_sid, $auth_token);
        // $recipient = "whatsapp:".$recipient;
        return $client->messages->create($recipient, array('from' => "whatsapp:$twilio_whatsapp_number", 'body' => $message));
    }

    public function testing()
    {
        $twilio_whatsapp_number = getenv('TWILIO_WHATSAPP_NUMBER');
        $account_sid = getenv("TWILIO_SID");
        $auth_token = getenv("TWILIO_AUTH_TOKEN");
        $client = new Client($account_sid, $auth_token);
        $send = "whatsapp:+918523932878";
        $to_num = "whatsapp:" . $twilio_whatsapp_number;
        $message =  $client->messages
            ->create(
                $send, // to
                array(
                    "from" =>  $to_num,
                    "body" => 'VIjay '
                )
            );
    }
    public function inbound(Request $request)
    {
        Log::info('response', $request->all());
        $from = $request->input('From');
        $name = $request->input('ProfileName');
        $msg = "Hey " . $name . " " . "How are you";
        $body = $request->input('Body');
        $response = $this->sendWhatsAppMessage($msg, $from);
        // Log::info('response',    $response);
        // Log::info('Incoming WhatsApp Message',   $body);
    }
}
