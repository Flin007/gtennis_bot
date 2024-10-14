<?php
namespace App\Commands;
use App\Classes\Helpers\NotificationHelper;
use App\Models\TelegramUser;
use App\Repositories\TelegramUsersRepository;
use App\Repositories\WhiteListUserRepository;
use Carbon\Carbon;
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
        $telegramUser = $this->telegramUsersRepository->findOneUserByUserId($userId);

        //Если не нашли юзера, отправим лог с ошибкой
        if (!isset($telegramUser->id)) {
            NotificationHelper::SendNotificationToChannel('Не нашли юзера в /announcement', $userData->toArray());
            return;
        }

        //Если юзер не админ - не даём доступ к анонсам от имени бота
        if (!$telegramUser->is_admin) {
            $this->sendNotAllowedMessage();
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
    public function sendNotAllowedMessage(): void
    {
        $this->replyWithMessage([
            'text' => 'Команда с анонсами доступна только администраторам бота.'
        ]);
    }

    /**
     * Отправляем всем сообщение что такой то участник создал заявку
     *
     * @param int $userId
     * @param string $date
     *
     * @return void
     */
    public function sendNewAppointmentMessageInGroup(int $userId, string $date): void
    {
        //Пробуем найти юзера
        $telegramUser = $this->telegramUsersRepository->findOneUserByUserId($userId);

        //Если не нашли юзера, отправим лог с ошибкой
        if (!isset($telegramUser->id)) {
            NotificationHelper::SendNotificationToChannel('Не нашли юзера в /announcement', ['userId' => $userId, 'date' => $date]);
            return;
        }

        //Текст сообщения
        $text = "@{$telegramUser->username} хочет сыграть на платном корте "
            . Carbon::parse($date)->format('d.m.Y')
            . ', присоеденяйтесь!🎾🎾';
        //Отправляем
        app(BotsManager::class)
            ->bot()
            ->sendMessage([
            'chat_id' => env('MAIN_CHAT_ID'),
            'text' => $text,
        ]);
    }

    public function sendDeleteAppointmentMessageInGroup(int $userId, string $date): void
    {
        //Пробуем найти юзера
        $telegramUser = $this->telegramUsersRepository->findOneUserByUserId($userId);

        //Если не нашли юзера, отправим лог с ошибкой
        if (!isset($telegramUser->id)) {
            NotificationHelper::SendNotificationToChannel('Не нашли юзера в /announcement', ['userId' => $userId, 'date' => $date]);
            return;
        }

        //Текст сообщения
        $text = "@{$telegramUser->username} не сможет сыграть "
            . Carbon::parse($date)->format('d.m.Y')
            . ' 😢😢';
        //Отправляем
        app(BotsManager::class)
            ->bot()
            ->sendMessage([
                'chat_id' => env('MAIN_CHAT_ID'),
                'text' => $text,
            ]);
    }
}
