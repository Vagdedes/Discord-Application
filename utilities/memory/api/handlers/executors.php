<?php

function has_memory_limit($key, $countLimit, $futureTime = null): bool
{
    $key = manipulate_memory_key($key);

    if ($key !== false) {
        $futureTime = manipulate_memory_date($futureTime, 60 * 15);

        if ($futureTime !== null) {
            global $memory_reserved_names;
            $memoryBlock = new IndividualMemoryBlock($memory_reserved_names[1] . $key);
            $object = $memoryBlock->get();

            if ($object !== null && isset($object->original_expiration) && isset($object->count)) {
                $object->count++;
                $memoryBlock->set($object, $object->original_expiration);
                return $object->count >= $countLimit;
            }
            $object = new stdClass();
            $object->count = 1;
            $object->original_expiration = $futureTime;
            $memoryBlock->set($object, $futureTime);
        }
    }
    return false;
}

function has_memory_cooldown($key, $futureTime = null, $set = true, $force = false): bool
{
    $key = manipulate_memory_key($key);

    if ($key !== false) {
        $futureTime = manipulate_memory_date($futureTime, 60 * 30);

        if ($futureTime !== null) {
            global $memory_reserved_names;
            $memoryBlock = new IndividualMemoryBlock($memory_reserved_names[0] . $key);

            if (!$force && $memoryBlock->exists()) {
                return true;
            }
            if ($set) {
                $memoryBlock->set(1, $futureTime);
            }
            return false;
        }
    }
    return false;
}

// Separator

function get_key_value_pair($key, $temporaryRedundancyValue = null)
{ // Must call setKeyValuePair() after
    $key = manipulate_memory_key($key);

    if ($key !== false) {
        global $memory_reserved_names;
        $memoryBlock = new IndividualMemoryBlock($memory_reserved_names[2] . $key);
        $object = $memoryBlock->get();

        if ($object !== null) {
            return $object;
        }
        if ($temporaryRedundancyValue !== null) {
            $memoryBlock->set($temporaryRedundancyValue, time() + 1);
        }
    }
    return null;
}

function set_key_value_pair($key, $value = null, $futureTime = null): bool
{ // Must optionally call setKeyValuePair() before
    $key = manipulate_memory_key($key);

    if ($key !== false) {
        $futureTime = manipulate_memory_date($futureTime, 60 * 3);

        if ($futureTime !== null) {
            global $memory_reserved_names;
            $memoryBlock = new IndividualMemoryBlock($memory_reserved_names[2] . $key);
            $memoryBlock->set($value, $futureTime);
            return true;
        }
    }
    return false;
}

// Separator

function clear_memory($keys, $abstractSearch = false, $stopAfterSuccessfulIterations = 0): void
{
    if (!is_array($keys)) {
        return;
    }
    if (!empty($keys)) {
        $hasLimit = $stopAfterSuccessfulIterations > 0;

        if ($hasLimit) {
            $iterations = array();

            foreach (array_keys($keys) as $key) {
                $iterations[$key] = 0;
            }
        }
        if ($abstractSearch) {
            global $memory_array;

            if (!empty($memory_array)) {
                foreach (array_keys($memory_array) as $memoryID) {
                    $memoryBlock = new IndividualMemoryBlock($memoryID);
                    $memoryKey = $memoryBlock->get("key");

                    if ($memoryKey !== null) {
                        foreach ($keys as $arrayKey => $key) {
                            if (is_array($key)) {
                                foreach ($key as $subKey) {
                                    if (!str_contains($memoryKey, $subKey)) {
                                        continue 2;
                                    }
                                }
                                $memoryBlock->clear();

                                if ($hasLimit) {
                                    $iterations[$arrayKey]++;

                                    if ($iterations[$arrayKey] === $stopAfterSuccessfulIterations) {
                                        break 2;
                                    }
                                }
                            } else if (str_contains($memoryKey, $key)) {
                                $memoryBlock->clear();

                                if ($hasLimit) {
                                    $iterations[$arrayKey]++;

                                    if ($iterations[$arrayKey] === $stopAfterSuccessfulIterations) {
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } else {
            global $memory_reserved_names;

            foreach ($memory_reserved_names as $name) {
                foreach ($keys as $key) {
                    $name .= $key;
                    $memoryBlock = new IndividualMemoryBlock($name);
                    $memoryBlock->clear();
                }
            }
        }
    } else {
        global $memory_array;

        if (!empty($memory_array)) {
            foreach (array_keys($memory_array) as $memoryID) {
                $memoryBlock = new IndividualMemoryBlock($memoryID);
                $memoryBlock->clear();
            }
        }
    }
}
