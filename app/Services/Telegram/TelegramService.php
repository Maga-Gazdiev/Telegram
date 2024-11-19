<?php

namespace App\Services\Telegram;

use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;
use App\Services\SpamTelegramMessage;
use DefStudio\Telegraph\DTO\Message;
use DefStudio\Telegraph\Keyboard\ReplyButton;
use DefStudio\Telegraph\Keyboard\ReplyKeyboard;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use function Symfony\Component\Translation\t;

class TelegramService extends WebhookHandler
{
    public TelegraphBot $bot;
    public TelegraphChat $chat;

    public function handleChatMessage($text): void
    {
        \Log::info($text);
         $spam = new SpamTelegramMessage("8045477325:AAFlqxE4-jMfukWeDqq-PvDVe_XgJRXeAHg", $this->chat->chat_id);
         $spam->consumer($text);
    }

    public function start(){
        \Log::info("tesst");
        $this->chat->message("Вы уже были авторизованы в чате, поэтому продолжим с того места, где вы остановились");
    }
}
