<?php

class DiscordRunnables
{

    private array $tasks;

    public function __construct()
    {
        $this->tasks = array();
    }

    public function process(): void
    {
        if (!empty($this->tasks)) {
            foreach ($this->tasks as $key => $task) {
                if (!isset($task[2])) {
                    call_user_func_array($task[0], $task[1]);
                    unset($this->tasks[$key]);
                } else if ($task[2] < microtime(true)) {
                    call_user_func_array($task[0], $task[1]);
                    $task[2] = microtime(true) + $task[3];
                }
            }
        }
    }

    public function addTask($object, string $name, ?array $arguments = null, ?int $milliseconds = null): void
    {
        $task = array(
            $object === null ? $name : array($object, $name),
            $arguments === null ? array() : $arguments,
        );
        if ($milliseconds !== null) {
            $addition = $milliseconds * 1000;
            $task[] = microtime(true) + $addition;
            $task[] = $addition;
        }
        $this->tasks[] = $task;
    }
}
