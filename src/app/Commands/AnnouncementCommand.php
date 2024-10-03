<?php
namespace App\Commands;
use App\Classes\Helpers\NotificationHelper;
use App\Models\TelegramUser;
use App\Repositories\TelegramUsersRepository;
use App\Repositories\WhiteListUserRepository;
use Illuminate\Support\Traits\EnumeratesValues;
use Telegram\Bot\BotsManager;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Objects\User;

class AnnouncementCommand extends Command
{
    protected string $name = 'announcement';
    protected string $description = 'Сообщение в группу по теннису от лица бота';
    protected TelegramUser $telegramUser;
    protected WhiteListUserRepository $whiteListUserRepository;
    protected TelegramUsersRepository $telegramUsersRepository;

    public function __construct() {
        //Через app, дабы не прокидывать классы в конструктор из Webhook контроллера
        $this->telegramUser = app(TelegramUser::class);
        $this->whiteListUserRepository = app(WhiteListUserRepository::class);
        $this->telegramUsersRepository = app(TelegramUsersRepository::class);
    }

    /**
     * Метод, который дергается при вызове команды
     *
     * @return void
     */
    public function handle(): void
    {
        //Получаем всю информацию о пользователе
        $userData = $this->getUpdate()->message->from;
        //Получаем его уникальный ID
        $userId = $userData->id;
        //Пробуем найти юзера в БД
        $telegramUser = $this->telegramUsersRepository->findUserById($userId);

        //Если не нашли юзера, отправим лог с ошибкой
        if (!isset($telegramUser->is_admin)) {
            NotificationHelper::SendNotificationToChannel('Не нашли юзера в /announcement', json_encode($userData, JSON_UNESCAPED_UNICODE));
            return;
        }

        //Если юзер не админ - не даём доступ к анонсам от имени бота
        if (!$telegramUser->is_admin) {
            $this->sendWelcomeMessageIfUserNotAdmin();
            return;
        }

        //Отправляем текст анонса в группу
        $botManager = app(BotsManager::class);
        $bot = $botManager->bot();
        $text = str_replace('/announcement ','',$this->getUpdate()->message->text);
        $bot->sendMessage([
            'chat_id' => env('MAIN_CHAT_ID'),
            'text' => $text,
        ]);
    }

    /**
     * Сообщим что у юзера нет доступа к команде
     *
     * @return void
     */
    public function sendWelcomeMessageIfUserNotAdmin(): void
    {
        $this->replyWithMessage([
            'text' => 'Команда с анонсами доступна только администраторам бота.'
        ]);
    }
}
