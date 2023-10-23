<?php

class IndividualMemoryBlock
{
    private mixed $originalKey;
    private int $key;

    public function __construct(mixed $key)
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

        if (array_key_exists($this->key, $memory_array)) {
            return strlen(serialize($memory_array[$this->key]));
        } else {
            return 0;
        }
    }

    public function set(mixed $value, $expiration = false): void
    {
        global $memory_array;

        if (!empty($memory_array)) {
            foreach ($memory_array as $arrayKey => $arrayValue) {
                if ($arrayValue->expiration !== false && $arrayValue->expiration < time()) {
                    unset($memory_array[$arrayKey]);
                }
            }
        }
        $object = new stdClass();
        $object->key = $this->originalKey;
        $object->value = $value;
        $object->creation = time();
        $object->expiration = is_numeric($expiration) ? $expiration : false;
        $memory_array[$this->key] = $object;
    }

    private function getRaw(): ?object
    {
        global $memory_array;

        if (array_key_exists($this->key, $memory_array)) {
            if ($memory_array[$this->key]->expiration === false
                || $memory_array[$this->key]->expiration >= time()) {
                return $memory_array[$this->key];
            } else {
                unset($memory_array[$this->key]);
                return null;
            }
        } else {
            return null;
        }
    }

    public function get(string $objectKey = "value"): mixed
    {
        $raw = $this->getRaw();
        return $raw?->{$objectKey};
    }

    public function exists(): bool
    {
        return $this->getRaw() !== null;
    }

    public function clear(): void
    {
        global $memory_array;
        unset($memory_array[$this->key]);
    }
}