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

class StartCommand extends Command
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
        //Получаем всю информацию о пользователе
        $userData = $this->getUpdate()->message->from;
        //Получаем его уникальный ID
        $userId = $userData->id;
        //Пробуем найти юзера в БД
        $telegramUser = $this->telegramUsersRepository->findOneUserByUserId($userId);

        //Если юзера не нашли - добавляем
        if (!$telegramUser) {
            $this->addNewTelegramUser($userData);
            return;
        }

        //Если юзер не авторизовался - отправляем дефолтное сообщение
        if (!$telegramUser->is_auth) {
            $this->sendWelcomeMessageIfUserNotAuthorized();
            return;
        }

        //Если все ок - отправляем главное меню
        $this->sendMainMenu();
    }

    /**
     * Добавление пользователя в базу данных. И уведомление в тг группу
     *
     * @param User $userData
     *
     * @return void
     */
    public function addNewTelegramUser(User $userData): void
    {
        $res =  $this->telegramUser->updateOrCreate([
            'user_id' => $userData->id,
        ],[
            'username' => $userData->username,
            'first_name' => $userData->first_name ?? 'Не указано',
            'last_name' => $userData->last_name ?? 'Не указано',
            'is_premium' => $userData->is_premium ?? 0,
            'is_bot' => $userData->is_bot ?? 0,
        ]);

        //Не получилось ничего создать?
        if (!isset($res->id)){
            NotificationHelper::SendNotificationToChannel('Не получилось создать запись', $userData->toArray());
            return;
        }

        //Отправляем письмо с прозьбой авторизоваться
        $this->sendWelcomeMessageIfUserNotAuthorized();

        //Отправляем уведомление о добавлении нового юзера
        NotificationHelper::SendNotificationToChannel('Добавили нового пользователя', $userData->toArray());
    }

    /**
     * Отправка дефолтного сообщения с прозьбой авторизоваться.
     *
     * @return void
     */
    public function sendWelcomeMessageIfUserNotAuthorized(): void
    {
        $reply_markup = self::getWelcomeMessageIfUserNotAuthorized();

        $this->replyWithMessage([
            'text' => 'Привет! Кажется ты тут впервые✋',
            'reply_markup' =>$reply_markup
        ]);
    }

    /**
     * @return EnumeratesValues|Keyboard
     */
    public static function getWelcomeMessageIfUserNotAuthorized(): EnumeratesValues|Keyboard
    {
        return Keyboard::make([
            'inline_keyboard' => [
                [
                    [
                        'text' => 'Авторизоваться в боте',
                        'callback_data' => 'Start_checkIsUserInWhiteList',
                    ],
                ]
            ],
            'resize_keyboard' => true,
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
    public function checkIsUserInWhiteList(int $userId, int $messageId, BotsManager $botsManager): void
    {
        //Пробуем найти есть ли юзер в белом списке
        $whiteListUser = $this->whiteListUserRepository->findUserById($userId);

        //Если юзера нет, зададим сообщение и разметку
        if (!$whiteListUser) {
            $msg = 'К сожалению вас не добавили в белый список. Пожалуйста свяжитесь с администратором';
            $reply_markup = Keyboard::make([
                'inline_keyboard' => [
                    [
                        [
                            'text' => 'Написать администратору',
                            'url' => 'https://t.me/indertruster',
                        ],
                    ]
                ],
                'resize_keyboard' => true,
            ]);
        } else {
            //Если юзер есть в белом списке - изменим его статус авторизации
            $telegramUser = $this->telegramUsersRepository->findOneUserByUserId($userId);
            $telegramUser->is_auth = 1;
            $telegramUser->save();
            //Отправим ему главное меню
            $msg = $this->getMaiMenuMsg();
            $reply_markup = $this->getMainMenuMarkup();
        }

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
     * @return string
     */
    public function getMaiMenuMsg(): string
    {
        return 'Это главное меню бота:';
    }

    /**
     * Получаем разметку главного меню.
     *
     * @return EnumeratesValues|Keyboard
     */
    public function getMainMenuMarkup(): EnumeratesValues|Keyboard
    {
        return Keyboard::make([
            'inline_keyboard' => [
                [
                    [
                        'text' => '👤 Мой профиль',
                        'callback_data' => 'Profile_getMyProfile',
                    ],
                ],
                [
                    [
                        'text' => '📝 Управление моими записями',
                        'callback_data' => 'Appointment_showMyAppointments',
                    ],
                ],
                [
                    [
                        'text' => '🕒 Посмотреть ячейки для записи',
                        'callback_data' => 'Appointment_showAvailableDates',
                    ],
                ],
            ],
            'resize_keyboard' => true,
        ]);
    }

    /**
     * Отправляем главное меню юзеру.
     *
     * @return void
     */
    public function sendMainMenu(): void
    {
        $reply_markup = $this->getMainMenuMarkup();
        $this->replyWithMessage([
            'text' => $this->getMaiMenuMsg(),
            'reply_markup' =>$reply_markup
        ]);
    }

    /**
     * Меняем сообщение на главное меню.
     *
     * @param int $userId
     * @param int $messageId
     * @param BotsManager $botsManager
     *
     * @return void
     *
     * @throws TelegramSDKException
     */
    public function sendMainMenuWithEditMessage(int $userId, int $messageId, BotsManager $botsManager): void
    {
        $reply_markup = $this->getMainMenuMarkup();
        $msg = self::getMaiMenuMsg();
        $bot = $botsManager->bot();
        $bot->editMessageText([
            'chat_id'                  => $userId,
            'message_id'               => $messageId,
            'text'                     => $msg,
            'reply_markup'             => $reply_markup
        ]);
    }
}
