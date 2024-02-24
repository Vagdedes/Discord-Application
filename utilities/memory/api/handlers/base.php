<?php

class IndividualMemoryBlock
{
    private mixed $originalKey;
    private int $key;

    public function __construct(mixed $key)
    {
        if (is_integer($key)) {
            $this->originalKey = $key;
            $this->key = $key;
        } else {
            $this->originalKey = $key;
            $this->key = string_to_integer($key);
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

    private function getObject(): ?object
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
        $raw = $this->getObject();
        return $raw?->{$objectKey};
    }

    public function exists(): bool
    {
        return $this->getObject() !== null;
    }

    public function delete(): void
    {
        global $memory_array;
        unset($memory_array[$this->key]);
    }
}