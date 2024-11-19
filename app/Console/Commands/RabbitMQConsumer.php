<?php

namespace App\Console\Commands;

use App\Http\Controllers\TGController;
use App\Services\SpamTelegramMessage;
use Illuminate\Console\Command;
use App\Jobs\ProcessTelegramMessage;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitMQConsumer extends Command
{
    protected $signature = 'rabbitmq:consume';
    protected $description = 'Consume messages from RabbitMQ and process them';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $connection = new AMQPStreamConnection(
            env('RABBITMQ_HOST'),
            env('RABBITMQ_PORT'),
            env('RABBITMQ_USERNAME'),
            env('RABBITMQ_PASSWORD'),
        );

        $channel = $connection->channel();
        $channel->queue_declare(env('RABBITMQ_QUEUE', 'messages'), false, true, false, false);

        $callback = function($msg) {
            $message = json_decode($msg->body, true);
            $spam = (new TGController())->store($message);
        };

        $channel->basic_consume(env('RABBITMQ_QUEUE', 'messages'), '', false, true, false, false, $callback);

        while($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }
}
