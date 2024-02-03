<?php

function manipulate_memory_key(mixed $key): bool|string
{
    return $key === null ? false : json_encode(is_object($key) ? get_object_vars($key) : $key);
}

function manipulate_memory_date(mixed $cooldown, int $maxTime = 86400)
{
    if ($cooldown === null) {
        return false;
    }
    if (is_array($cooldown)) {
        $cooldown = strtotime("+" . implode(" ", $cooldown));

        if ($cooldown === false) {
            return null;
        }
    } else if (is_numeric($cooldown)) {
        $cooldown = time() + min($cooldown, $maxTime);
    } else {
        $cooldown = strtotime("+" . $cooldown);

        if ($cooldown === false) {
            return null;
        }
    }
    return min($cooldown, time() + $maxTime);
}

function map_to_string(array $array): string
{
    $string = "";

    foreach ($array as $key => $value) {
        $dataToStore = @gzdeflate($value, 9);

        if ($dataToStore !== false) {
            $string .= $key . "\r" . $dataToStore . "\r";
        } else {
            throw new Exception("Failed to deflate string: " . $value);
        }
    }
    return $string;
}

function string_to_map(string $string): array
{
    $explode = preg_split("/(\r)/", $string);
    $array = array();
    $previousValue = null;

    foreach ($explode as $position => $value) {
        if (($position + 1) % 2 == 0) {
            $storedData = @gzinflate($value);

            if ($storedData !== false) {
                $array[$previousValue] = $storedData;
            } else {
                throw new Exception("Failed to inflate string: " . $value);
            }
        } else {
            $previousValue = $value;
        }
    }
    return $array;
}
