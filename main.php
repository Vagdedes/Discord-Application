<?php
require '/root/discord_bot/utilities/utilities.php';
$token = get_keys_from_file("/root/discord_bot/private/credentials/discord_token", 1);

if ($token === null) {
    var_dump("No token found");
    return;
}
require '/root/discord_bot/utilities/memory/init.php';
ini_set('memory_limit', '-1');
require '/root/vendor/autoload.php';

use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\WebSockets\Event;

$discord = new Discord([
    'token' => $token[0],
    'storeMessages' => false,
    'retrieveBans' => false,
    'loadAllMembers' => false,
    'disabledEvents' => [],
    'dnsConfig' => '1.1.1.1'
]);
$testing = true;
$testingIDs = array(
    394461329236295684 // vagdedes
);

$discord->on('ready', function (Discord $discord) {
    var_dump("ready");

    // Listen for messages.
    $discord->on(Event::MESSAGE_CREATE, function (Message $message, Discord $discord) {
        global $testing, $testingIDs;

        if (!$testing || in_array($message->user_id, $testingIDs)) {
            foreach ($message->mentions as $user) {
                if ($user->id == $discord->id) {
                    $message->reply("I AM ALIVE!");
                    break;
                }
            }
        }
    });
});

$discord->run();
