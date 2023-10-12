<?php
require '/root/discord_bot/utilities/utilities.php';
$token = get_keys_from_file("/root/discord_bot/private/credentials/discord_token", 1);

if ($token === null) {
    exit("No token found");
}
ini_set('memory_limit', '-1');
require '/root/vendor/autoload.php';

require '/root/discord_bot/utilities/memory/init.php';
require '/root/discord_bot/utilities/sql.php';
require '/root/discord_bot/utilities/scheduler.php';

require '/root/discord_bot/database/variables.php';
require '/root/discord_bot/database/bot/DiscordPlan.php';
require '/root/discord_bot/database/bot/DiscordBot.php';

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
$scheduler = new DiscordScheduler();

$discord->on('ready', function (Discord $discord) {
    global $scheduler;
    $discordBot = new DiscordBot($discord->user->id);

    $discord->on(Event::MESSAGE_CREATE, function (Message $message, Discord $discord) use ($discordBot) {
        foreach ($message->mentions as $user) {
            if ($user->id == $discord->id) {
                foreach ($discordBot->plans as $plan) {
                    if ($plan->canAssist($message->guild_id, $message->channel_id, $message->user_id)) {
                        $message->reply("I AM ALIVE!");
                        break;
                    }
                }
                break;
            }
        }
    });
    var_dump($discordBot->plans[1]->canAssist("289384242075533313", "424326222076444673", "394461329236295684"));

    $scheduler->addTask(null, "remove_expired_memory", null, 30_000);
    $scheduler->addTask($discordBot, "refreshWhitelist", null, 60_000);
    $scheduler->addTask($discordBot, "refreshPunishments", null, 60_000);
    //$scheduler->run();
});

$discord->run();
