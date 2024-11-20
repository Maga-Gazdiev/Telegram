<?php

namespace App\Services;

use PDO;

class SpamTelegramMessage
{
    private $telegramToken;
    private $chatId;

    public function __construct($telegramToken, $chatId)
    {
        $this->telegramToken = $telegramToken;
        $this->chatId = $chatId;
    }

    public function consumer($message)
    {
        if (isset($message['callback_query'])) {
            $this->buttons($message['callback_query']);
            return;
        }

        if (isset($message['message'])) {
            $text = $message['message']['text'];
            $chatId = $message['message']['chat']['id'];
        } elseif (isset($message['channel_post'])) {
            $text = $message['channel_post']['text'];
            $chatId = $message['channel_post']['chat']['id'];
        } else {
            return;
        }

        $pdo = new PDO('mysql:host=localhost;dbname=laravel', 'muhammad', 'Mgfudhn@006mrgb');
        $query = "SELECT words FROM data";
        $statement = $pdo->prepare($query);
        $statement->execute();

        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $row) {
            $words = explode(',', $row['words']);

            foreach ($words as $word) {
                $trimmedWord = trim($word);

                if (stripos($text, $trimmedWord) !== false) {
                    $this->sendTelegramMessage($message, $trimmedWord, $chatId);
                    return;
                }
            }
        }
    }

    private function sendTelegramMessage($originalMessage, $matchedWord, $chatId)
    {
        $url = "https://api.telegram.org/bot{$this->telegramToken}/sendMessage";

        $message = isset($originalMessage['message']) ? $originalMessage['message']['text'] : $originalMessage['channel_post']['text'];
        $text = "Это спам?: \"$message\"";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'Спам', 'callback_data' => 'delete_message_' . ($originalMessage['message']['message_id'] ?? $originalMessage['channel_post']['message_id'])],
                    ['text' => 'Не спам', 'callback_data' => 'keep_message']
                ]
            ]
        ];

        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => json_encode($keyboard)
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_exec($ch);
        curl_close($ch);
    }

    public function buttons($data)
    {
        $chatId = $data['message']['chat']['id'];
        $callbackData = $data['data'];
        $moderatorId = $data['from']['id'];

        $pdo = new PDO('mysql:host=localhost;dbname=laravel', 'muhammad', 'Mgfudhn@006mrgb');

        $moderatorCheck = $pdo->prepare("SELECT COUNT(*) FROM moderators WHERE telegram_id = ?");
        $moderatorCheck->execute([$moderatorId]);
        if ($moderatorCheck->fetchColumn() == 0) {
            $this->sendConfirmationMessage($chatId, "У вас нет прав модератора.");
            return;
        }

        if (strpos($callbackData, "delete_message_") === 0) {
            $messageId = substr($callbackData, strlen("delete_message_"));

            $voteInsert = $pdo->prepare("INSERT IGNORE INTO message_votes (message_id, moderator_id) VALUES (?, ?)");
            $voteInsert->execute([$messageId, $moderatorId]);

            $voteCount = $pdo->prepare("SELECT COUNT(*) FROM message_votes WHERE message_id = ?");
            $voteCount->execute([$messageId]);
            $votes = $voteCount->fetchColumn();

            if ($votes > 1) {
                $keyboard = [
                    'inline_keyboard' => []
                ];

                $this->updateMessageButtons($chatId, $data["message"]["message_id"], $keyboard);
                $this->deleteMessage($chatId, $data["message"]["message_id"]);

                $this->deleteMessage($chatId, $messageId);
            } else {
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Спам (' . $votes . ')', 'callback_data' => 'delete_message_' . $messageId],
                            ['text' => 'Не спам', 'callback_data' => 'keep_message']
                        ]
                    ]
                ];

                $this->updateMessageButtons($chatId, $data["message"]["message_id"], $keyboard);
            }
        } elseif ($callbackData === "keep_message") {
            // Дополнительная логика для оставления сообщения
        }
    }

    private function updateMessageButtons($chatId, $messageId, $keyboard)
    {
        $url = "https://api.telegram.org/bot{$this->telegramToken}/editMessageReplyMarkup";

        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => json_encode($keyboard)
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_exec($ch);
        curl_close($ch);
    }


    private function deleteMessage($chatId, $messageId)
    {
        $url = "https://api.telegram.org/bot{$this->telegramToken}/deleteMessage";

        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    private function sendConfirmationMessage($chatId, $text)
    {
        $url = "https://api.telegram.org/bot{$this->telegramToken}/sendMessage";

        $data = [
            'chat_id' => $chatId,
            'text' => $text
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
}
