<?php
use HwBot\Bot;
use Utility\Utility;

require_once __DIR__ . '/util/Utility.php';
require_once __DIR__ . '/config/config.php';

Utility::set_timezone();

$request = \HwBot\DEV_MODE ?[
    'type' => 'message_new',
    'object' => [
        'text' => 'Что задали?', // Заменить для проверки реакции бота на сообщение
        'peer_id' => 000000000,
        'from_id' => 000000000,
        'id' => 123
    ]
] : $request = Utility::get_vk_request();;
if(!isset($request['type'])) exit('unavailable');

require_once 'modules/Bot.php';
require_once 'modules/Parser.php';

$bot = new Bot($request['object']);
$bot->handle_event($request);
echo 'ok';

