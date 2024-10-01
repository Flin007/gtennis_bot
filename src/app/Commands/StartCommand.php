<?php
namespace App\Commands;
use App\Classes\Helpers\NotificationHelper;
use App\Models\TelegramUser;
use App\Repositories\TelegramUsersRepository;
use App\Repositories\WhiteListUserRepository;
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
        $telegramUser = $this->telegramUsersRepository->findUserById($userId);

        //Если юзера не нашли - добавляем
        if (!$telegramUser) {
            $this->addNewTelegramUser($userData);
            return;
        }

        //Если юзер не авторизовался - отправляем дефолтное сообщение
        if (!$telegramUser->is_auth) {
            $this->sendWelcomeMessageIfUserNotAuthorized();
        }

        //Если все ок - отправляем главное меню
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
        $userDataArr = [
            'user_id' => $userData->id,
            'username' => $userData->username,
            'first_name' => $userData->first_name ?? '',
            'last_name' => $userData->last_name ?? '',
            'language_code' => $userData->language_code ?? 'ru',
            'is_premium' => $userData->is_premium ?? 0,
            'is_bot' => $userData->is_bot ?? 0,
        ];
        //Добавляем юзера в бд
        $this->telegramUser->insert($userDataArr);

        //Отправляем письмо с прозьбой авторизоваться
        $this->sendWelcomeMessageIfUserNotAuthorized();

        //Отправляем уведомление о добавлении нового юзера
        NotificationHelper::SendNotificationToChannel('Добавили нового пользователя', json_encode($userDataArr, 256));
    }

    /**
     * Отправка дефолтного сообщения с прозьбой авторизоваться.
     *
     * @return void
     */
    public function sendWelcomeMessageIfUserNotAuthorized(): void
    {
        $reply_markup = Keyboard::make([
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

        $this->replyWithMessage([
            'text' => 'Привет! Кажется ты тут впервые✋',
            'reply_markup' =>$reply_markup
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
                            'callback_data' => 'Start_checkIsUserInWhiteList',
                        ],
                    ]
                ],
                'resize_keyboard' => true,
            ]);
        } else {
            $msg = 'Мы нашли вас в белом спике';
            $reply_markup = Keyboard::make([
                'inline_keyboard' => [
                    [
                        [
                            'text' => 'Но дальше пока ничего не работает)',
                            'callback_data' => 'Start_checkIsUserInWhiteList',
                        ],
                    ]
                ],
                'resize_keyboard' => true,
            ]);
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
}
