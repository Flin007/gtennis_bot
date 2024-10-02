<?php
namespace App\Commands;
use App\Classes\Helpers\NotificationHelper;
use App\Classes\Helpers\StringHelper;
use App\Models\Appointment;
use App\Models\TelegramUser;
use App\Repositories\AppointmentRepository;
use App\Repositories\TelegramUsersRepository;
use App\Repositories\WhiteListUserRepository;
use Carbon\Carbon;
use Illuminate\Support\Traits\EnumeratesValues;
use Telegram\Bot\BotsManager;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Objects\User;

class AppointmentCommand extends Command
{
    protected string $name = 'start';
    protected string $description = 'Запуск / Перезапуск бота';
    protected TelegramUser $telegramUser;
    protected WhiteListUserRepository $whiteListUserRepository;
    protected TelegramUsersRepository $telegramUsersRepository;
    protected AppointmentRepository $appointmentRepository;

    public function __construct() {
        //Через app, дабы не прокидывать классы в конструктор из Webhook контроллера
        $this->telegramUser = app(TelegramUser::class);
        $this->whiteListUserRepository = app(WhiteListUserRepository::class);
        $this->telegramUsersRepository = app(TelegramUsersRepository::class);
        $this->appointmentRepository = app(AppointmentRepository::class);
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
     * Показывает список активных записей клиента в боте.
     *
     * @param int $userId
     * @param int $messageId
     * @param BotsManager $botsManager
     *
     * @return void
     *
     * @throws TelegramSDKException
     */
    public function showMyAppointments(int $userId, int $messageId, BotsManager $botsManager): void
    {
        $userAppointments = $this->appointmentRepository->getActiveAppointmentsByUserId($userId);
        if(count($userAppointments) <= 0){
            $msg = 'У вас нет ближайших записей';
            $reply_markup = Keyboard::make([
                'inline_keyboard' => [
                    [
                        [
                            'text' => '🔙 Назад в главное меню',
                            'callback_data' => 'Start_sendMainMenuWithEditMessage',
                        ],
                    ]
                ],
                'resize_keyboard' => true,
            ]);
        } else {
            //Массив кнопок
            $myDates = [];
            //Что бы ограничить кол-во кнопок в ряд
            $counter = 0;
            //Ряд кнопок
            $row = 0;
            foreach ($userAppointments as $appointment) {
                $myDates[$row][] = [
                    'text' => StringHelper::mb_ucfirst(Carbon::parse($appointment->date)->locale('ru_RU')->shortDayName) . ' ' . Carbon::parse($appointment->date)->format('d.m'),
                    'callback_data' => 'Appointment_deleteMyAppointment_'.$appointment->id
                ];
                $counter++;
                if ($counter === 4){
                    $counter = 0;
                    $row++;
                }
            }

            //Добавим в отдельной строчке кнопку возврата в меню
            $myDates[] = [[
                'text' => '🔙 Назад в главное меню',
                'callback_data' => 'Start_sendMainMenuWithEditMessage',
            ]];

            $msg = 'Нажмите на запись для отмены:';
            $reply_markup = Keyboard::make([
                'inline_keyboard' => $myDates,
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

    /**
     * Показываем кнопки след недели и через одну.
     *
     * @param int $userId
     * @param int $messageId
     * @param BotsManager $botsManager
     *
     * @return void
     *
     * @throws TelegramSDKException
     */
    public function showAvailableDates(int $userId, int $messageId, BotsManager $botsManager): void
    {
        $msg = 'Записаться можно на следующую неделю или через одну, т.к. корты быстро бронируют, решать нужно заранее)';
        //Получаем даты начала и конца следующей недели и через одну
        $nextWeekMonday = Carbon::now()->startOfWeek()->addWeek(1)->format('d.m.Y');
        $nextWeekSunday = Carbon::now()->endOfWeek()->addWeek(1)->format('d.m.Y');
        $inAWeekMonday = Carbon::now()->startOfWeek()->addWeek(2)->format('d.m.Y');
        $inAWeekSunday = Carbon::now()->endOfWeek()->addWeek(2)->format('d.m.Y');

        $reply_markup = Keyboard::make([
            'inline_keyboard' => [
                [
                    [
                        'text' => "{$nextWeekMonday} - {$nextWeekSunday}",
                        'callback_data' => 'Appointment_getNextWeekDates_1',
                    ],
                ],
                [
                    [
                        'text' => "{$inAWeekMonday} - {$inAWeekSunday}",
                        'callback_data' => 'Appointment_getNextWeekDates_2',
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
     * Показывает список дат для записи на следующей неделе
     *
     * @param int $userId
     * @param int $messageId
     * @param BotsManager $botsManager
     * @param int $countWeekAdd
     *
     * @return void
     *
     * @throws TelegramSDKException
     */
    public function getNextWeekDates(int $userId, int $messageId, BotsManager $botsManager, int $countWeekAdd): void
    {
        $nextWeekMonday = Carbon::now()->startOfWeek()->addWeek($countWeekAdd);
        $msg = 'Какого числа хотите сыграть?';
        $reply_markup = Keyboard::make([
            'inline_keyboard' => [
                [
                    [
                        'text' => StringHelper::mb_ucfirst($nextWeekMonday->locale('ru_RU')->shortDayName) . ' ' . $nextWeekMonday->format('d.m'),
                        'callback_data' => 'Appointment_createNewAppointment_'.$nextWeekMonday->toDateString(),
                    ],
                    [
                        'text' => StringHelper::mb_ucfirst($nextWeekMonday->addDay()->locale('ru_RU')->shortDayName) . ' ' . $nextWeekMonday->format('d.m'),
                        'callback_data' => 'Appointment_createNewAppointment_'.$nextWeekMonday->toDateString(),
                    ],
                    [
                        'text' => StringHelper::mb_ucfirst($nextWeekMonday->addDay()->locale('ru_RU')->shortDayName) . ' ' . $nextWeekMonday->format('d.m'),
                        'callback_data' => 'Appointment_createNewAppointment_'.$nextWeekMonday->toDateString(),
                    ],
                    [
                        'text' => StringHelper::mb_ucfirst($nextWeekMonday->addDay()->locale('ru_RU')->shortDayName) . ' ' . $nextWeekMonday->format('d.m'),
                        'callback_data' => 'Appointment_createNewAppointment_'.$nextWeekMonday->toDateString(),
                    ],
                ],
                [
                    [
                        'text' => StringHelper::mb_ucfirst($nextWeekMonday->addDay()->locale('ru_RU')->shortDayName) . ' ' . $nextWeekMonday->format('d.m'),
                        'callback_data' => 'Appointment_createNewAppointment_'.$nextWeekMonday->toDateString(),
                    ],
                    [
                        'text' => StringHelper::mb_ucfirst($nextWeekMonday->addDay()->locale('ru_RU')->shortDayName) . ' ' . $nextWeekMonday->format('d.m'),
                        'callback_data' => 'Appointment_createNewAppointment_'.$nextWeekMonday->toDateString(),
                    ],
                    [
                        'text' => StringHelper::mb_ucfirst($nextWeekMonday->addDay()->locale('ru_RU')->shortDayName) . ' ' . $nextWeekMonday->format('d.m'),
                        'callback_data' => 'Appointment_createNewAppointment_'.$nextWeekMonday->toDateString(),
                    ],
                ],
                [
                    [
                        'text' => '🔙 К выбору недель',
                        'callback_data' => 'Appointment_showAvailableDates',
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
     * Создание новой записи для пользователя.
     *
     * @param int $userId
     * @param int $messageId
     * @param BotsManager $botsManager
     * @param string $date
     *
     * @return void
     *
     * @throws TelegramSDKException
     */
    public function createNewAppointment(int $userId, int $messageId, BotsManager $botsManager, string $date): void
    {
        $appointment = new Appointment();
        $res = $appointment->updateOrCreate([
            'user_id' => $userId,
            'date' => $date
        ],[
            'status' => 1,
        ]);

        //Не получилось ничего создать?
        if (!isset($res->id)){
            NotificationHelper::SendNotificationToChannel('Не получилось создать запись', json_encode(['user_id' => $userId, 'date' => $date], JSON_UNESCAPED_UNICODE));
        }

        $msg = 'Ваша запись успешно создана!🔥🔥';
        $reply_markup = Keyboard::make([
            'inline_keyboard' => [
                [
                    [
                        'text' => "📝 Управление моими записями",
                        'callback_data' => 'Appointment_showMyAppointments',
                    ],
                ],
                [
                    [
                        'text' => "🔙 К выбору недель",
                        'callback_data' => 'Appointment_showAvailableDates',
                    ],
                ],
                [
                    [
                        'text' => '🔙🔙 Назад в главное меню',
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
     * Отмена записи пользователя.
     *
     * @param int $userId
     * @param int $messageId
     * @param BotsManager $botsManager
     * @param int $appointmentId
     *
     * @return void
     *
     * @throws TelegramSDKException
     */
    public function deleteMyAppointment(int $userId, int $messageId, BotsManager $botsManager, int $appointmentId): void
    {
        $appointment = Appointment::find($appointmentId);
        if (!isset($appointment->id)){
            NotificationHelper::SendNotificationToChannel('Хотели удалить запись, но не смогли найти', json_encode(['user_id' => $userId, 'id' => $appointmentId], JSON_UNESCAPED_UNICODE));
        }
        $appointment->status = 0;
        $appointment->save();

        //Дальше просто перерендерим список доступных записей через готовый метод.
        $this->showMyAppointments($userId, $messageId, $botsManager);
    }
}
