<?php

namespace App\Http\Controllers;

use App\Jobs\SendToRabbitMQ;
use App\Services\SpamTelegramMessage;
use Illuminate\Http\Request;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class TGController extends Controller
{
    public function index(Request $request)
    {
        \Log::info($request->all());
        $connection = new AMQPStreamConnection(
            env('RABBITMQ_HOST'),
            env('RABBITMQ_PORT'),
            env('RABBITMQ_USERNAME'),
            env('RABBITMQ_PASSWORD')
        );

        $channel = $connection->channel();
        $channel->queue_declare('spam-candidate', false, true, false, false);

        $msg = json_encode($request->all(), JSON_UNESCAPED_UNICODE);
        $message = new AMQPMessage($msg, [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
        ]);

        $channel->basic_publish($message, 'spam-candidate-exchanges');

        $channel->close();
        $connection->close();
    }

    public function store($date)
    {
        if (isset($date['callback_query'])) {
            $data = new SpamTelegramMessage("8045477325:AAFlqxE4-jMfukWeDqq-PvDVe_XgJRXeAHg", $date['callback_query']['message']['chat']['id']);
            $data->consumer($date);
            return;
        }

        if (isset($date['message'])) {
            $data = new SpamTelegramMessage("8045477325:AAFlqxE4-jMfukWeDqq-PvDVe_XgJRXeAHg", $date['message']['chat']['id']);
            $data->consumer($date);
            return;
        }

        if (isset($date['channel_post'])) {
            $data = new SpamTelegramMessage("8045477325:AAFlqxE4-jMfukWeDqq-PvDVe_XgJRXeAHg", $date['channel_post']['chat']['id']);
            $data->consumer($date);
            return;
        }

        // Если тип сообщения неизвестен
        return response()->json(['error' => 'Invalid message type'], 400);
    }

}
