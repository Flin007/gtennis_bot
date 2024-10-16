<?php
namespace App\Http\Controllers;
use App\Classes\Helpers\NotificationHelper;
use App\Commands\AppointmentCommand;
use App\Repositories\TelegramUsersRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Telegram\Bot\BotsManager;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\CallbackQuery;
use Telegram\Bot\Objects\Message;
use Telegram\Bot\Objects\Update;

class WebhookController extends Controller
{
    protected BotsManager $botsManager;
    protected TelegramUsersRepository $telegramUsersRepository;
    protected AppointmentCommand $appointmentCommand;

    public function __construct(
        BotsManager $botsManager,
        TelegramUsersRepository $telegramUsersRepository,
        AppointmentCommand $appointmentCommand
    ) {
        $this->botsManager = $botsManager;
        $this->telegramUsersRepository = $telegramUsersRepository;
        $this->appointmentCommand = $appointmentCommand;
    }

    /**
     * @param Request $request
     *
     * @return Response
     *
     * @throws TelegramSDKException
     */
    public function __invoke(Request $request): Response
    {
        $webhook = $this->botsManager->bot()->commandsHandler(true);

        //Если это просто обновление статуса и нет сообщений, дальше не обрабатываем
        $isJustUpdate = $this->checkMemberStatus($webhook);
        if ($isJustUpdate){
            return response(null, 200);
        }

        //Обрабатываем callback для Inline кнопок
        if ($webhook->callbackQuery instanceof CallbackQuery){
            $this->processCallback($webhook);
        }

        $message = $webhook->getMessage();
        //Обрабатываем сообщение, неизвестный инстанс в ошибки
        if ($message instanceof Message){
            $this->processMessage($message);
        }else{
            NotificationHelper::SendNotificationToChannel('Неизвестный $message instanceof', $webhook);
        }

        return response(null, 200);
    }

    /**
     * Обрабатывает сообщение из вебхука.
     *
     * @param Message $message
     *
     * @return void
     */
    private function processMessage(Message $message): void
    {

    }

    /**
     * Проверяем не изменился ли статус пользователя (member, left, kicked)
     * @see https://core.telegram.org/bots/api#chatmembermember
     *
     * @param $webhook
     *
     * @return boolean
     *
     * @throws TelegramSDKException
     */
    private function checkMemberStatus($webhook): bool
    {
        if ($webhook->my_chat_member) {
            $userId = $webhook->my_chat_member->chat->id;
            $telegramUser = $this->telegramUsersRepository->findBy(['user_id' => $userId])->first();
            if ($telegramUser){
                $telegramUser->update([
                    'status' =>  $webhook->my_chat_member->new_chat_member->status,
                ]);
                $telegramUser->save();
            }else{
                NotificationHelper::SendNotificationToChannel('Изменился статус юзера, но его нет в базе', $webhook);
            }
            return !(count($webhook->getMessage()) > 0);
        }
        return false;
    }

    /**
     * Обрабатываем callback для динамических inline кнопок.
     *
     * @param Update $webhook
     *
     * @return void
     *
     * @throws TelegramSDKException
     */
    private function processCallback(Update $webhook): void
    {
        if (!isset($webhook->getChat()->id)) {
            NotificationHelper::SendNotificationToChannel('Не смогли получтиь $webhook->getChat()->id', $webhook);
            return;
        }
        if (!isset($webhook->getMessage()->messageId)) {
            NotificationHelper::SendNotificationToChannel('$webhook->getMessage()->messageId', $webhook);
            return;
        }

        $userId = $webhook->getChat()->id;
        $messageId = $webhook->getMessage()->messageId;

        //If we found '/' - call the command, or trying parse
        if (str_contains($webhook->callbackQuery->data, '/')){
            $command = ltrim($webhook->callbackQuery->data, '/');
            $update = Telegram::commandsHandler(true);
            Telegram::triggerCommand($command, $update);
            return;
        }

        //Разделяем строку ответа на массив по разделителю '_'
        //[0] -> Название команды, например StartCommand название будет Start
        //[1] -> Метод класса команды, например метод checkIsUserInWhiteList в классе StartCommand
        //[2] -> Применяется как доп параметр, пока только в AppointmentCommand
        $callbackData = explode('_', $webhook->callbackQuery->data);

        if (2 !== count($callbackData) && 3 !== count($callbackData)) {
            NotificationHelper::SendNotificationToChannel(
                'Пытались обработать callback, но пришли неверные параметры, count($callbackData) = '.count($callbackData),
                $webhook
            );
        }

        $className = "App\Commands\\".$callbackData[0]."Command";
        $class = new $className;
        $method = $callbackData[1];
        switch (count($callbackData)) {
            case 2:
                $class->$method($userId, $messageId, $this->botsManager);
                break;
            case 3:
                //Если 3 элемента, значит в 3 какой то доп. параметр
                $class->$method($userId, $messageId, $this->botsManager, $callbackData[2]);
                break;
        }
    }
}
