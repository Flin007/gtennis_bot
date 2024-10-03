<?php

namespace App\Classes\Helpers;

use Telegram\Bot\BotsManager;
use Telegram\Bot\Objects\Update;

class NotificationHelper
{
    /**
     * @param string $title
     * @param array|Update $webhook
     *
     * @return void
     */
    public static function SendNotificationToChannel(string $title, array|Update $webhook = ['Без хука)']): void
    {
        $botManager = app(BotsManager::class);
        $bot = $botManager->bot();
        $response = $title;
        $response .= PHP_EOL . json_encode($webhook, JSON_UNESCAPED_UNICODE);
        $bot->sendMessage([
            'chat_id' => env('ERRORS_CHAT_ID'),
            'text' => $response,
        ]);
    }
}
