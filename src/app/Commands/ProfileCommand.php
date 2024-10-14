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

class ProfileCommand extends Command
{
    protected string $name = 'start';
    protected string $description = 'Запуск / Перезапуск бота';
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

    }

    /**
     * @param int $userId
     * @param int $messageId
     * @param BotsManager $botsManager
     *
     * @return void
     *
     * @throws TelegramSDKException
     */
    public function getMyProfile(int $userId, int $messageId, BotsManager $botsManager):void
    {
        //Пробуем найти есть ли юзер в белом списке
        $telegramUser = $this->telegramUsersRepository->findOneUserByUserId($userId);

        if (!$telegramUser) {
            NotificationHelper::SendNotificationToChannel('Не согли найти юзера с id = '.$userId);
            return;
        }

        $msg = 'Уникальный id: '.$telegramUser->user_id.PHP_EOL
                .'Логин: @'.$telegramUser->username.PHP_EOL
                .'Имя: '.$telegramUser->first_name.PHP_EOL
                .'Фамилия: '.$telegramUser->last_name.PHP_EOL;

        $reply_markup = Keyboard::make([
            'inline_keyboard' => [
                [
                    [
                        'text' => '✏ Изменить имя',
                        'callback_data' => 'Profile_changeFirstName',
                    ],
                ],
                [
                    [
                        'text' => '✏ Изменить Фамилию',
                        'callback_data' => 'Profile_changeLastName',
                    ],
                ],
                [
                    [
                        'text' => '❌ Удалить аккаунт из бота',
                        'callback_data' => 'Profile_deleteAccount',
                    ],
                ],
                [
                    [
                        'text' => '🔙 Назад в главное меню',
                        'callback_data' => 'Start_sendMainMenuWithEditMessage',
                    ],
                ]
            ],
            'resize_keyboard' => true,
        ]);

        //Отправка ответа с изменение сообщения
        $bot = $botsManager->bot();
        $bot->editMessageText([
            'chat_id'                  => $userId,
            'message_id'               => $messageId,
            'text'                     => $msg,
            'reply_markup'             => $reply_markup
        ]);
    }

    /**
     * @param int $userId
     * @param int $messageId
     * @param BotsManager $botsManager
     *
     * @return void
     *
     * @throws TelegramSDKException
     */
    public function deleteAccount(int $userId, int $messageId, BotsManager $botsManager): void
    {
        //Отключаем авторизацию юзера в боте
        $telegramUser = $this->telegramUsersRepository->findOneUserByUserId($userId);
        $telegramUser->is_auth = 0;
        $telegramUser->save();
        //Разметку приветственного сообщения
        $reply_markup = StartCommand::getWelcomeMessageIfUserNotAuthorized();
        $msg = 'Ваш аккаунт был успешно отключен';
        //Отправка ответа с изменение сообщения
        $bot = $botsManager->bot();
        $bot->editMessageText([
            'chat_id'                  => $userId,
            'message_id'               => $messageId,
            'text'                     => $msg,
            'reply_markup'             => $reply_markup
        ]);
    }
}
