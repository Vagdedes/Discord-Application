<?php

function remove_expired_memory(): void
{
    global $memory_array;

    if (!empty($memory_array)) {
        foreach ($memory_array as $key => $value) {
            if ($value->expiration !== false && $value->expiration < time()) {
                unset($memory_array[$key]);
            }
        }
    }
}

class IndividualMemoryBlock
{
    private $originalKey;
    private int $key;

    public function __construct($key)
    {
        if (is_integer($key)) { // Used for reserved or existing keys
            $keyToInteger = $key;
            $this->originalKey = $key;
        } else {
            $keyToInteger = string_to_integer($key);
            $this->originalKey = $key;
        }
        $this->key = $keyToInteger;
    }

    public function getKey(): int
    {
        return $this->key;
    }

    public function getOriginalKey(): int
    {
        return $this->originalKey;
    }

    public function getSize(): int
    {
        global $memory_array;

        if (isset($memory_array[$this->key])) {
            return strlen(serialize($memory_array[$this->key]));
        } else {
            return 0;
        }
    }

    public function set($value, $expiration = false): bool
    {
        $this->internalSet($value, $expiration);
        return true;
    }

    public function getRaw(): object|null
    {
        global $memory_array;
        return $memory_array[$this->key] ?? null;
    }

    public function get($objectKey = "value")
    {
        $raw = $this->getRaw();
        return $raw?->{$objectKey};
    }

    public function exists(): bool
    {
        return isset($memory_array[$this->key]);
    }

    public function clear(): void
    {
        global $memory_array;
        unset($memory_array[$this->key]);
    }

    // Separator

    public function internalSet($value, $expiration): void
    {
        global $memory_array;
        $object = new stdClass();
        $object->key = $this->originalKey;
        $object->value = $value;
        $object->creation = time();
        $object->expiration = is_numeric($expiration) ? $expiration : false;
        $memory_array[$this->key] = $object;
    }
}