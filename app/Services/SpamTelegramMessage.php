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

    public function choiceDataType($message){

    }

    public function consumer($message)
    {
        // Проверяем, откуда пришло сообщение (бот или группа)
        if (isset($message['callback_query'])) {
            // Обрабатываем callback-запросы (кнопки)
            $this->buttons($message['callback_query']);
            return;
        }

        // Определяем, что пришло - сообщение от пользователя или из группы
        if (isset($message['message'])) {
            // Сообщение от пользователя
            $text = $message['message']['text'];
            $chatId = $message['message']['chat']['id'];
        } elseif (isset($message['channel_post'])) {
            // Сообщение из канала/группы
            $text = $message['channel_post']['text'];
            $chatId = $message['channel_post']['chat']['id'];
        } else {
            // Если данные не содержат ни message, ни channel_post
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
                    // Если слово найдено в тексте, отправляем сообщение
                    $this->sendTelegramMessage($message, $trimmedWord, $chatId);
                    return;
                }
            }
        }
    }

    private function sendTelegramMessage($originalMessage, $matchedWord, $chatId)
    {
        $url = "https://api.telegram.org/bot{$this->telegramToken}/sendMessage";

        // Определяем текст для спам-сообщения
        $message = isset($originalMessage['message']) ? $originalMessage['message']['text'] : $originalMessage['channel_post']['text'];
        $text = "Обнаружено спам-сообщение: \"$message\".\n\nСовпавшее слово: \"$matchedWord\".\n\nЧто вы хотите сделать?";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'Удалить сообщение', 'callback_data' => 'delete_message_' . ($originalMessage['message']['message_id'] ?? $originalMessage['channel_post']['message_id'])],
                    ['text' => 'Оставить сообщение', 'callback_data' => 'keep_message']
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

            if ($votes >= 2) {
                $this->deleteMessage($chatId, $messageId);
                $this->sendConfirmationMessage($chatId, "Сообщение было удалено.");
            } else {
                $this->sendConfirmationMessage($chatId, "Ваш голос принят. Ожидается ещё " . (2 - $votes) . " голос(а).");
            }
        } elseif ($callbackData === "keep_message") {
            //$this->sendConfirmationMessage($chatId, "Сообщение оставлено.");
        }
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
