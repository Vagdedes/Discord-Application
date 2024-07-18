<?php
$max_64bit_Integer = 9223372036854775807;
$min_64bit_Integer = -9223372036854775808;

$max_32bit_Integer = 2147483647;
$min_32bit_Integer = -2147483648;
$unsigned_32bit_full_Integer = 4294967296;

$max_59bit_Integer = 288230376151711743;
$min_59bit_Integer = -288230376151711744;
$unsigned_59bit_full_Integer = 576460752303423488;

$backup_domain = "www.idealistic.ai";
$google_recaptcha_secret_key_directory = "/var/www/.structure/private/google_recaptcha";

$keys_from_file_directory = "/root/discord_bot/private/credentials/";

// Constants

function get_time_limit(): bool|int|float
{
    $limit = ini_get("max_execution_time");
    return is_numeric($limit) ? $limit : false;
}

function get_server_identifier(bool $long = false): int
{
    return string_to_integer(getHostName(), $long);
}

function get_max_script_time(): bool|string
{
    return ini_get('max_execution_time');
}

// File

function get_domain_directory(): string
{
    $directory = explode(".", get_domain());
    return $directory[sizeof($directory) - 2];
}

function get_final_directory(): string
{
    $array = explode("/", getcwd());
    return $array[sizeof($array) - 1];
}

// Google Docs

function get_raw_google_doc(string $url, bool $returnHTML = false, int $timeoutSeconds = 0): ?string
{
    $html = starts_with($url, "http://") || starts_with($url, "https://")
        ? timed_file_get_contents($url, $timeoutSeconds)
        : $url;

    if ($html !== false) {
        $html = explode('doc-content">', $html);

        if (sizeof($html) > 1) {
            $html = explode('<script', $html[1])[0];
            $html = str_replace("</span>", "\n</span>", $html);
            $html = strip_tags($html);

            if ($returnHTML) {
                $html = str_replace("\n", "<br>", $html);
            }
            return $html;
        }
    }
    return null;
}

// Connections

function post_file_get_contents(string                     $url,
                                array                      $parameters = null,
                                bool                       $clearPreviousParameters = false,
                                int|float|string|bool|null $user_agent = null,
                                int                        $timeoutSeconds = 0): bool|string
{
    global $_POST;

    if ($parameters !== null) {
        if ($clearPreviousParameters) {
            $_POST = $parameters;
        } else {
            $_POST = array_merge($_POST, $parameters);
        }
    } else if ($clearPreviousParameters) {
        $_POST = array();
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($_POST));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    if ($timeoutSeconds > 0) {
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);
    }
    return curl_exec($ch);
}

function run_script_via_tmux(string $script, array $parameters, string $tmux = "async"): void
{
    shell_exec("tmux new -s " . $tmux);
    shell_exec('tmux send -t ' . $tmux
        . ' "php /var/www/.structure/scripts/' . $script . '.php '
        . implode(" ", $parameters) . '" ENTER');
}

function get_json_object(string $url, array $postParameters = null, int $timeoutSeconds = 0)
{
    if ($postParameters !== null) {
        $opts = array('http' =>
            array(
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($postParameters),
                "timeout" => $timeoutSeconds
            )
        );
        $contents = file_get_contents($url, false, stream_context_create($opts));
    } else {
        $contents = timed_file_get_contents($url, $timeoutSeconds);
    }
    return $contents !== false ? json_decode($contents) : null;
}

function create_and_close_connection(string $url): bool|string
{
    return timed_file_get_contents($url, 1);
}

function timed_file_get_contents(string $url, int $timeoutSeconds = 0): bool|string
{
    if ($timeoutSeconds > 0) {
        return @file_get_contents($url, 0, stream_context_create(["http" => ["timeout" => $timeoutSeconds]]));
    } else {
        return @file_get_contents($url);
    }
}

function create_and_close_curl_connection(string $url, array $properties = null): bool|string|null
{
    return shell_exec(
        "curl"
        . ($properties !== null ? " --" . implode(" --", $properties) . " " : " ")
        . $url
    );
}

function get_curl(string $url, string $type, array $headers, mixed $arguments,
                  int    $timeoutSeconds = 25): mixed // 5 because 30 is the max script time usually
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    if ($arguments != null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $arguments);
    }
    if ($type != null) {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
    }
    if ($timeoutSeconds > 0) {
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

function send_file_download(mixed $file, bool $exit = true): void
{
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=' . basename($file));
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file));
    ob_clean();
    flush();
    readfile($file);

    if ($exit) {
        exit();
    }
}

function copy_and_send_file_download(mixed $file, string $directory, bool $exit = true)
{
    if (json_decode($file)) {
        $fileCopy = $directory . "data.json";

        if (@file_put_contents($fileCopy, $file) === false) {
            return "Failed to write to file.";
        }
    } else {
        $fileCopy = $directory . $file;

        if (!copy($file, $fileCopy)) {
            $errors = error_get_last();
            return isset($errors["message"]) ?
                "Failed to prepare file copy: " . $errors["message"] :
                "Failed to prepare file copy.";
        }
    }
    if (!file_exists($fileCopy)) {
        return "Failed to find copied file.";
    }
    send_file_download($fileCopy, false);
    unlink($fileCopy);

    if ($exit) {
        exit();
    }
}

function can_reach_server(string $ip): bool
{
    exec("/bin/ping -c2 -w2 $ip", $outcome, $status);
    return $status == 0;
}

function get_local_ip_address(): string
{
    return getHostByName(getHostName());
}

function get_client_ip_address(): ?string
{
    if (function_exists("get_private_ip_address")) {
        $privateIpAddress = get_private_ip_address();

        if ($privateIpAddress !== null) {
            return $privateIpAddress;
        }
    }
    return get_raw_client_ip_address();
}

function get_raw_client_ip_address(): ?string
{
    if (getenv('HTTP_CLIENT_IP')) {
        $ipAddress = getenv('HTTP_CLIENT_IP');
    } else if (getenv('HTTP_X_FORWARDED_FOR')) {
        $ipAddress = getenv('HTTP_X_FORWARDED_FOR');
    } else if (getenv('HTTP_X_FORWARDED')) {
        $ipAddress = getenv('HTTP_X_FORWARDED');
    } else if (getenv('HTTP_FORWARDED_FOR')) {
        $ipAddress = getenv('HTTP_FORWARDED_FOR');
    } else if (getenv('HTTP_FORWARDED')) {
        $ipAddress = getenv('HTTP_FORWARDED');
    } else if (getenv('REMOTE_ADDR')) {
        $ipAddress = getenv('REMOTE_ADDR');
    } else if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
    } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else if (isset($_SERVER['HTTP_X_FORWARDED'])) {
        $ipAddress = $_SERVER['HTTP_X_FORWARDED'];
    } else if (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ipAddress = $_SERVER['HTTP_FORWARDED_FOR'];
    } else if (isset($_SERVER['HTTP_FORWARDED'])) {
        $ipAddress = $_SERVER['HTTP_FORWARDED'];
    } else if (isset($_SERVER['REMOTE_ADDR'])) {
        $ipAddress = $_SERVER['REMOTE_ADDR'];
    } else {
        return "";
    }
    return explode(",", $ipAddress, 2)[0];
}

function redirect_to_url(string $url, mixed $argumentBlacklist = null): void
{
    if (is_iterable($argumentBlacklist)) {
        global $_GET;
        $arguments = array();

        foreach ($_GET as $key => $value) {
            if (!in_array($key, $argumentBlacklist)) {
                $arguments[] = $key . "=" . utf8_encode($value);
            }
        }

        if (!empty($arguments)) {
            header("Location: $url" . (!str_contains($url, "/?") ? "?" : "&") . implode("&", $arguments));
            exit();
        } else {
            header("Location: $url");
            exit();
        }
    } else {
        header("Location: $url");
        exit();
    }
}

function getSSHConnection(string $ip, string $user, string $password)
{
    try {
        set_include_path('/usr/share/php/phpseclib');
        require_once('Net/SSH2.php');
        $ssh = new SSH2($ip);
        $ssh->setTimeout(1);
        return $ssh->login($user, $password);
    } catch (Exception $e) {
        return false;
    }
}

function getSSHReply(string $ip, string $user, string $password, string $command)
{
    try {
        $ssh = getSSHConnection($ip, $user, $password);

        if ($ssh) {
            return $ssh->exec($command);
        }
    } catch (Exception $e) {
    }
    return null;
}

function getSSHReplies(string $ip, string $user, string $password, array $commands): ?array
{
    try {
        $ssh = getSSHConnection($ip, $user, $password);

        if ($ssh) {
            $reply = array();

            foreach ($commands as $key => $command) {
                $reply[$key] = $ssh->exec($command);
            }
            return $reply;
        }
    } catch (Exception $e) {
    }
    return array();
}

function get_user_url(): string
{
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        $url = "https://";
    } else {
        $url = "http://";
    }
    return $url . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

function get_user_agent(): string
{
    return $_SERVER['HTTP_USER_AGENT'] ?? "";
}

function get_domain(bool $subdomains = true): string
{
    if ($subdomains) {
        $domain = $_SERVER['SERVER_NAME'] ?? "";
    } else if (isset($_SERVER['SERVER_NAME'])) {
        $domain = explode(".", $_SERVER['SERVER_NAME']);
        $size = sizeof($domain);
        $domainEnd = $domain[$size - 1];

        if (is_alpha($domainEnd)) {
            $domain = $domain[$size - 2] . "." . $domainEnd;
        } else {
            $domain = null;
        }
    } else {
        $domain = null;
    }
    if (empty($domain) || is_ip_address($domain)) {
        global $backup_domain;

        if ($subdomains) {
            return $backup_domain;
        } else {
            $domain = explode(".", $backup_domain);
            $size = sizeof($domain);
            return $domain[$size - 2] . "." . $domain[$size - 1];
        }
    } else {
        return $domain;
    }
}

// Validators

function is_valid_text_time(string $string): bool
{
    $string = explode(" ", $string);

    if (sizeof($string) === 2) {
        $number = $string[0];

        if (is_numeric($number) && $number > 0) {
            $floorNumber = floor($number);

            if ($floorNumber == $number) {
                foreach (array(
                             "second",
                             "seconds",
                             "minute",
                             "minutes",
                             "hour",
                             "hours",
                             "day",
                             "days",
                             "week",
                             "weeks",
                             "month",
                             "months",
                             "year",
                             "years"
                         ) as $unit) {
                    if (strtolower($string[1]) === $unit) {
                        return true;
                    }
                }
            }
        }
    }
    return false;
}

function is_ip_address(?string $address): bool
{
    return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) != false
        || filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) != false;
}

function is_port(int|string|null $port): bool
{
    return is_numeric($port) && $port >= 0 && $port <= 65535;
}

function is_email(?string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) != false;
}

function is_date(?string $date): bool
{
    return DateTime::createFromFormat('Y-m-d H:i:s', $date) !== false;
}

function is_phone_number(?string $number): bool
{
    if (isset($number[0])
        && $number[0] !== "-"
        && is_numeric(str_replace(" ", "", $number))) {
        $len = strlen($number);
        return $len >= 4 && $len <= 19;
    }
    return false;
}

function is_url(?string $url): bool
{
    return filter_var($url, FILTER_VALIDATE_URL) != false;
}

function prepare_phone_number(string $number): string
{
    return $number[0] === "+" ? $number : ("+" . $number);
}

function is_google_captcha_valid(): bool
{
    $key = "g-recaptcha-response";

    if (isset($_POST[$key])) {
        global $google_recaptcha_secret_key_directory;
        $info = $_POST[$key];
        $secret = get_keys_from_file($google_recaptcha_secret_key_directory, 1);

        if ($secret === null) {
            return false;
        }
        $secret = $secret[0];
        $ip = $_SERVER['REMOTE_ADDR'];
        $response = timed_file_get_contents(
            "https://www.google.com/recaptcha/api/siteverify?secret=$secret&response=$info&remoteip=$ip",
            3
        );

        if ($response === false) {
            return false;
        }
        $responseKeys = json_decode($response, true);
        return intval($responseKeys["success"]) === 1;
    }
    return false;
}

function is_uuid(?string $string): bool
{
    return is_string($string)
        && preg_match('/^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}$/', $string) === 1;
}

function is_alpha_numeric(?string $s): bool
{
    return $s != null && strlen($s) > 0 && ctype_alnum($s);
}

function is_alpha(?string $s): bool
{
    return $s != null && strlen($s) > 0 && ctype_alpha($s);
}

function is_base64_image(?string $string): bool
{
    $pointer = "data:image/";
    return strlen($string) > strlen($pointer) && str_contains($string, $pointer);
}

// Strings

function get_urls_from_string(string $string): array
{
    preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $string, $match);
    return array_unique($match[0]);
}

function find_character_occurrences(string $string, string $character): array
{
    $array = array();

    for ($i = 0; $i < strlen($string); $i++) {
        if ($string[$i] == $character) {
            $array[] = $i;
        }
    }
    return $array;
}


function unstuck_words_from_capital_letters(string $word): string
{
    $rebuild = strtoupper($word[0]);
    $word = substr($word, 1);

    for ($i = 0; $i < strlen($word); $i++) {
        $letter = $word[$i];
        $upperLetter = strtoupper($letter);

        if ($letter === $upperLetter) {
            $rebuild .= " " . $upperLetter;
        } else {
            $rebuild .= $letter;
        }
    }
    return $rebuild;
}

function get_keys_from_file(string $file, int $amount = 1, bool $custom = false): ?array
{
    global $keys_from_file_directory;
    $contents = @file_get_contents(($custom ? "" : $keys_from_file_directory) . $file);

    if ($contents !== false) {
        $keys = explode("\n", $contents);
        return sizeof($keys) != $amount ? null : $keys;
    } else {
        return null;
    }
}

function strpos_array(string $haystack, array $needle): bool|int
{
    foreach ($needle as $what) {
        if (($pos = strpos($haystack, $what)) !== false) {
            return $pos;
        }
    }
    return false;
}

function get_domain_from_url(string $string, bool $removeSubdomains = false): array|string
{
    $string = explode("/",
        str_replace("http://", "", str_replace("https://", "", $string)),
        2)[0];

    if ($removeSubdomains) {
        $string = explode(".", $string);
        $size = sizeof($string);

        if ($size >= 2) {
            $string = $string[$size - 2] . "." . $string[$size - 1];
        }
    }
    return $string;
}

function make_alpha_numeric(string $string): array|string|null
{
    return preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '', $string));
}

function starts_with(string $haystack, string $needle): bool
{
    $length = strlen($needle);
    return $length == 0 || (substr($haystack, 0, $length) === $needle);
}

function ends_with(string $haystack, string $needle): bool
{
    $length = strlen($needle);
    return $length == 0 || substr($haystack, -$length) === $needle;
}

function random_string(int $length = 10, bool $lower = true, bool $capital = true, bool $numbers = true): ?string
{
    $characters = "";

    if ($lower) {
        $characters .= "abcdefghijklmnopqrstuvwxyz";
    }
    if ($capital) {
        $characters .= "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    }
    if ($numbers) {
        $characters .= "0123456789";
    }
    if (!empty($capital)) {
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    return null;
}

function cut_string_at_first_number(string $string): string
{
    $rebuild = "";

    for ($pos = 0; $pos < strlen($string); $pos++) {
        $character = $string[$pos];
        $exit = false;

        for ($num = 0; $num < 10; $num++) {
            if ($character == $num) {
                $exit = true;
                break;
            }
        }

        if (!$exit) {
            $rebuild .= $character;
        }
    }
    return strlen($rebuild) == 0 ? $string : $rebuild;
}

// Numbers

function overflow_integer(int $v): int
{
    global $max_32bit_Integer, $unsigned_32bit_full_Integer;
    $v = $v % $unsigned_32bit_full_Integer;

    if ($v > $max_32bit_Integer) {
        return $v - $unsigned_32bit_full_Integer;
    } else {
        global $min_32bit_Integer;

        if ($v < $min_32bit_Integer) {
            return $v + $unsigned_32bit_full_Integer;
        } else {
            return $v;
        }
    }
}

function overflow_long(int $v): int
{
    global $max_59bit_Integer, $unsigned_59bit_full_Integer;
    $v = $v % $unsigned_59bit_full_Integer;

    if ($v > $max_59bit_Integer) {
        return $v - $unsigned_59bit_full_Integer;
    } else {
        global $min_59bit_Integer;

        if ($v < $min_59bit_Integer) {
            return $v + $unsigned_59bit_full_Integer;
        } else {
            return $v;
        }
    }
}

function boolean_to_integer(bool $boolean): int
{
    return $boolean ? 1231 : 1237;
}

function string_to_integer(?string $string, bool $long = false): int
{
    if ($string === null) {
        return 0;
    }
    $result = 1;

    if (!empty($string)) {
        foreach (unpack("C*", $string) as $byte) {
            $result = $long ? overflow_long(($result * 31) + $byte) : overflow_integer(($result * 31) + $byte);
        }
    }
    return $result;
}

function array_to_integer(?array $array, bool $long = false): int
{
    if (empty($array)) {
        return 0;
    }
    $result = 1;

    foreach ($array as $value) {
        if (is_integer($value)) {
            $result = ($result * 31) + $value;
        } else {
            $result = ($result * 31) + string_to_integer(serialize($value), $long);
        }
    }
    return $result;
}

function random_number(int $length = 9): int|string
{
    $characters = '0123456789';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function add_ordinal_number(int $num): string
{
    if (!in_array(($num % 100), array(11, 12, 13))) {
        switch ($num % 10) {
            case 1:
                return $num . 'st';
            case 2:
                return $num . 'nd';
            case 3:
                return $num . 'rd';
            default:
                break;
        }
    }
    return $num . 'th';
}

function cut_decimal(float $value, int $cut): float
{
    $cut = (int)pow(10, $cut);
    $value *= $cut;
    return floor($value) / $cut;
}

// Debug

function manage_errors(mixed $display = null, mixed $log = null): void // NULL leaves it unaffected in relation to its current
{
    if ($display !== null) {
        ini_set('display_errors', $display ? 1 : 0);
        ini_set('display_startup_errors', $display ? 1 : 0);
    }
    if ($log !== null) {
        error_reporting($log);
    }
}

// Dates

function remove_dates($string): array|string|null
{
    return preg_replace('/(\d{4}[\.\/\-][01]\d[\.\/\-][0-3]\d)/', '', $string);
}

function manipulate_date(string $date, int|string $time): string
{
    return date('Y-m-d H:i:s', strtotime($date . " " . $time));
}

function get_future_date(int|string $time): string
{
    return date('Y-m-d H:i:s', strtotime("+" . $time));
}

function get_past_date(int|string $time): string
{
    return date('Y-m-d H:i:s', strtotime("-" . $time));
}

function time_to_date(int $time): string
{
    return date('Y-m-d H:i:s', $time);
}

function get_date_days_difference(string $date): float
{
    return round(get_date_seconds_difference($date) / (60 * 60 * 24));
}

function get_date_hours_difference(string $date): float
{
    return round(get_date_seconds_difference($date) / (60 * 60));
}

function get_date_minutes_difference(string $date): float
{
    return round(get_date_seconds_difference($date) / 60);
}

function get_date_seconds_difference(string $date): float|int
{
    return abs(time() - strtotime($date));
}

function get_full_date(string $date): string
{
    if ($date == null) {
        return "None";
    }
    $date = substr($date, 0, 10);
    $year = substr($date, 0, 4);
    $month = substr($date, 5, -3);
    $day = substr($date, 8, 10);

    switch ($day) {
        case "01":
            $day = "1";
            break;
        case "02":
            $day = "2";
            break;
        case "03":
            $day = "3";
            break;
        case "04":
            $day = "4";
            break;
        case "05":
            $day = "5";
            break;
        case "06":
            $day = "6";
            break;
        case "07":
            $day = "7";
            break;
        case "08":
            $day = "8";
            break;
        case "09":
            $day = "9";
            break;
    }
    $day = add_ordinal_number($day);

    switch ($month) {
        case "01":
            $month = "January";
            break;
        case "02":
            $month = "February";
            break;
        case "03":
            $month = "March";
            break;
        case "04":
            $month = "April";
            break;
        case "05":
            $month = "May";
            break;
        case "06":
            $month = "June";
            break;
        case "07":
            $month = "July";
            break;
        case "08":
            $month = "August";
            break;
        case "09":
            $month = "September";
            break;
        case "10":
            $month = "October";
            break;
        case "11":
            $month = "November";
            break;
        case "12":
            $month = "December";
            break;
        default:
            $month = "Month";
            break;
    }
    return $day . " of " . $month . " " . $year;
}

function get_current_date(): string
{
    return date("Y-m-d H:i:s");
}

// Lists

function find_object_from_key_match(mixed $iterable, string $key, mixed $match, bool $associative = false)
{
    if (is_iterable($iterable)) {
        foreach ($iterable as $object) {
            if ($associative) {
                if (isset($object[$key]) && $object[$key] == $match) {
                    return $object;
                }
            } else {
                if (isset($object->{$key}) && $object->{$key} == $match) {
                    return $object;
                }
            }
        }
    }
    return null;
}

// Knowledge

function code_to_country(string $code): string
{
    $code = strtoupper($code);
    $countryList = array(
        'AF' => 'Afghanistan',
        'AX' => 'Aland Islands',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AS' => 'American Samoa',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AQ' => 'Antarctica',
        'AG' => 'Antigua and Barbuda',
        'AR' => 'Argentina',
        'AM' => 'Armenia',
        'AW' => 'Aruba',
        'AU' => 'Australia',
        'AT' => 'Austria',
        'AZ' => 'Azerbaijan',
        'BS' => 'Bahamas the',
        'BH' => 'Bahrain',
        'BD' => 'Bangladesh',
        'BB' => 'Barbados',
        'BY' => 'Belarus',
        'BE' => 'Belgium',
        'BZ' => 'Belize',
        'BJ' => 'Benin',
        'BM' => 'Bermuda',
        'BT' => 'Bhutan',
        'BO' => 'Bolivia',
        'BA' => 'Bosnia and Herzegovina',
        'BW' => 'Botswana',
        'BV' => 'Bouvet Island (Bouvetoya)',
        'BR' => 'Brazil',
        'IO' => 'British Indian Ocean Territory (Chagos Archipelago)',
        'VG' => 'British Virgin Islands',
        'BN' => 'Brunei Darussalam',
        'BG' => 'Bulgaria',
        'BF' => 'Burkina Faso',
        'BI' => 'Burundi',
        'KH' => 'Cambodia',
        'CM' => 'Cameroon',
        'CA' => 'Canada',
        'CV' => 'Cape Verde',
        'KY' => 'Cayman Islands',
        'CF' => 'Central African Republic',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China',
        'CX' => 'Christmas Island',
        'CC' => 'Cocos (Keeling) Islands',
        'CO' => 'Colombia',
        'KM' => 'Comoros the',
        'CD' => 'Congo',
        'CG' => 'Congo the',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'CI' => 'Cote d\'Ivoire',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DM' => 'Dominica',
        'DO' => 'Dominican Republic',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'GQ' => 'Equatorial Guinea',
        'ER' => 'Eritrea',
        'EE' => 'Estonia',
        'ET' => 'Ethiopia',
        'FO' => 'Faroe Islands',
        'FK' => 'Falkland Islands (Malvinas)',
        'FJ' => 'Fiji the Fiji Islands',
        'FI' => 'Finland',
        'FR' => 'France, French Republic',
        'GF' => 'French Guiana',
        'PF' => 'French Polynesia',
        'TF' => 'French Southern Territories',
        'GA' => 'Gabon',
        'GM' => 'Gambia the',
        'GE' => 'Georgia',
        'DE' => 'Germany',
        'GH' => 'Ghana',
        'GI' => 'Gibraltar',
        'GR' => 'Greece',
        'GL' => 'Greenland',
        'GD' => 'Grenada',
        'GP' => 'Guadeloupe',
        'GU' => 'Guam',
        'GT' => 'Guatemala',
        'GG' => 'Guernsey',
        'GN' => 'Guinea',
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HM' => 'Heard Island and McDonald Islands',
        'VA' => 'Holy See (Vatican City State)',
        'HN' => 'Honduras',
        'HK' => 'Hong Kong',
        'HU' => 'Hungary',
        'IS' => 'Iceland',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IR' => 'Iran',
        'IQ' => 'Iraq',
        'IE' => 'Ireland',
        'IM' => 'Isle of Man',
        'IL' => 'Israel',
        'IT' => 'Italy',
        'JM' => 'Jamaica',
        'JP' => 'Japan',
        'JE' => 'Jersey',
        'JO' => 'Jordan',
        'KZ' => 'Kazakhstan',
        'KE' => 'Kenya',
        'KI' => 'Kiribati',
        'KP' => 'Korea',
        'KR' => 'Korea',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyz Republic',
        'LA' => 'Lao',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libyan Arab Jamahiriya',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MO' => 'Macao',
        'MK' => 'Macedonia',
        'MG' => 'Madagascar',
        'MW' => 'Malawi',
        'MY' => 'Malaysia',
        'MV' => 'Maldives',
        'ML' => 'Mali',
        'MT' => 'Malta',
        'MH' => 'Marshall Islands',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'YT' => 'Mayotte',
        'MX' => 'Mexico',
        'FM' => 'Micronesia',
        'MD' => 'Moldova',
        'MC' => 'Monaco',
        'MN' => 'Mongolia',
        'ME' => 'Montenegro',
        'MS' => 'Montserrat',
        'MA' => 'Morocco',
        'MZ' => 'Mozambique',
        'MM' => 'Myanmar',
        'NA' => 'Namibia',
        'NR' => 'Nauru',
        'NP' => 'Nepal',
        'AN' => 'Netherlands Antilles',
        'NL' => 'Netherlands',
        'NC' => 'New Caledonia',
        'NZ' => 'New Zealand',
        'NI' => 'Nicaragua',
        'NE' => 'Niger',
        'NG' => 'Nigeria',
        'NU' => 'Niue',
        'NF' => 'Norfolk Island',
        'MP' => 'Northern Mariana Islands',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PS' => 'Palestinian Territory',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PN' => 'Pitcairn Islands',
        'PL' => 'Poland',
        'PT' => 'Portugal, Portuguese Republic',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'RE' => 'Reunion',
        'RO' => 'Romania',
        'RU' => 'Russian Federation',
        'RW' => 'Rwanda',
        'BL' => 'Saint Barthelemy',
        'SH' => 'Saint Helena',
        'KN' => 'Saint Kitts and Nevis',
        'LC' => 'Saint Lucia',
        'MF' => 'Saint Martin',
        'PM' => 'Saint Pierre and Miquelon',
        'VC' => 'Saint Vincent and the Grenadines',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome and Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SK' => 'Slovakia (Slovak Republic)',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia, Somali Republic',
        'ZA' => 'South Africa',
        'GS' => 'South Georgia and the South Sandwich Islands',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard & Jan Mayen Islands',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland, Swiss Confederation',
        'SY' => 'Syrian Arab Republic',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TL' => 'Timor-Leste',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
        'TO' => 'Tonga',
        'TT' => 'Trinidad and Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks and Caicos Islands',
        'TV' => 'Tuvalu',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'US' => 'United States of America',
        'UM' => 'United States Minor Outlying Islands',
        'VI' => 'United States Virgin Islands',
        'UY' => 'Uruguay, Eastern Republic of',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'WF' => 'Wallis and Futuna',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe'
    );
    return $countryList[$code] ?? $code;
}

// Images

function url_to_base64_image(string $url, int $timeoutSeconds = 0): ?string
{
    $explode = explode(".", $url);
    $size = sizeof($explode);

    if ($size === 1) {
        return null;
    }
    $contents = timed_file_get_contents($url, $timeoutSeconds);
    return $contents === false ? null : "data:image/" . $explode[$size - 1] . ";base64," . base64_encode($contents);
}

function resize_image_by_percentage(string $base64Image, int|float $qualityRatio): string
{
    $base64Image = explode(",", $base64Image, 2);
    $data = base64_decode($base64Image[sizeof($base64Image) - 1]);
    $hasExtraBase64Data = isset($data[1]);

    // Creation
    $imageFromString = imagecreatefromstring($data);
    $width = imagesx($imageFromString);
    $height = imagesy($imageFromString);
    $newWidth = $width * $qualityRatio;
    $newHeight = $height * $qualityRatio;
    $newImage = resize_image($imageFromString, $newWidth, $newHeight);

    // Buffering
    ob_start();
    imagepng($newImage);
    $data = ob_get_contents();
    ob_end_clean();
    return ($hasExtraBase64Data ? $base64Image[0] . "," : "") . base64_encode($data);
}

function resize_image($image, int $newWidth, int $newHeight)
{
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    imagealphablending($newImage, false);
    imagesavealpha($newImage, true);
    $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
    imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, imagesx($image), imagesy($image));
    return $newImage;
}

// HTML

function get_title(string $url, int $timeoutSeconds = 0): ?string
{
    $page = timed_file_get_contents($url, $timeoutSeconds);
    return preg_match('/<title[^>]*>(.*?)<\/title>/ims', $page, $match) ? $match[1] : null;
}

function get_text_list_from_iterable(mixed $iterable, int $count = 0, bool $simplify = false): ?string
{
    if (is_object($iterable)) {
        $iterable = get_object_vars($iterable);
    }
    if (is_iterable($iterable)) {
        if (empty($iterable)) {
            return " {}<br>";
        } else {
            $list = '<ol>';
            $nullValues = array();

            foreach ($iterable as $key => $value) {
                if (is_array($value)) {
                    if ($simplify && sizeof($value) === 1) {
                        $valueKeys = array_keys($value);
                        $subKey = array_shift($valueKeys);
                        $value = array_shift($value);

                        if ($value !== null) {
                            $count++;

                            if ($count % 2 == 0) {
                                $list .= "<b style='color: slategray'>$key" . "[$subKey]</b>";
                            } else {
                                $list .= "<b style='color: white'>$key" . "[$subKey]</b>";
                            }
                            if (is_object($value)) {
                                $list .= get_text_list_from_iterable($value, $count, $simplify);
                            } else {
                                $list .= " " . strip_tags($value) . "<br>";
                            }
                        }
                    } else {
                        $count++;

                        if ($count % 2 == 0) {
                            $list .= "<b style='color: slategray'>$key</b>";
                        } else {
                            $list .= "<b style='color: white'>$key</b>";
                        }
                        $list .= get_text_list_from_iterable($value, $count, $simplify);
                    }
                } else if (is_array($value) || is_object($value)) {
                    $count++;

                    if ($count % 2 == 0) {
                        $list .= "<b style='color: slategray'>$key</b>";
                    } else {
                        $list .= "<b style='color: white'>$key</b>";
                    }
                    $list .= get_text_list_from_iterable($value, $count, $simplify);
                } else {
                    if ($value !== null) {
                        $count++;

                        if ($count % 2 == 0) {
                            $list .= "<b style='color: slategray'>$key</b>";
                        } else {
                            $list .= "<b style='color: white'>$key</b>";
                        }
                        $list .= " " . strip_tags($value) . "<br>";
                    } else {
                        if ($simplify) {
                            $nullValues[] = $key;
                        } else {
                            $count++;

                            if ($count % 2 == 0) {
                                $list .= "<b style='color: slategray'>$key</b> NULL<br>";
                            } else {
                                $list .= "<b style='color: white'>$key</b> NULL<br>";
                            }
                        }
                    }
                }
            }

            if ($simplify && !empty($nullValues)) {
                $count++;

                if ($count % 2 == 0) {
                    $list .= "<b style='color: slategray'>NULL</b>";
                } else {
                    $list .= "<b style='color: white'>NULL</b>";
                }
                $list .= " " . implode(", ", $nullValues);
            }
            $list .= '</ol>';
            return $list;
        }
    }
    return null;
}

// Objects & Arrays

function get_object_depth_key(object $object, string $keys, string $separator = "."): array
{
    $keys = explode($separator, $keys);

    if (sizeof($keys) === 1) {
        $keys = $keys[0];

        if (isset($object->{$keys})) {
            return array(true, $object->{$keys});
        } else {
            return array(false, null);
        }
    } else {
        $key = array_shift($keys);

        if ($key !== null && isset($object->{$key})) {
            return get_object_depth_key($object->{$key}, implode($separator, $keys));
        } else {
            return array(false, null);
        }
    }
}