<?php

class DiscordScheduler
{

    private bool $enabled, $add;
    private int $pause;
    private array $tasks;

    public function __construct()
    {
        $this->enabled = false;
        $this->add = false;
        $this->pause = 0;
    }

    public function run(): void
    {
        if (!$this->enabled) {
            $this->enabled = true;

            while ($this->enabled) {
                if ($this->pause !== 0) {
                    $pause = $this->pause;
                    $this->pause = 0;
                    usleep($pause);
                }
                if (!empty($this->tasks)) {
                    foreach ($this->tasks as $key => $task) {
                        if ($this->add) {
                            usleep(1);
                        }
                        if (!isset($task[2])) {
                            call_user_func_array($task[0], $task[1]);
                            unset($this->tasks[$key]);
                        } else if ($task[2] < microtime(true)) {
                            call_user_func_array($task[0], $task[1]);
                            $task[2] = microtime(true) + $task[3];
                        }
                    }
                }
                usleep(1000);
            }
        }
    }

    public function pause(?int $milliseconds): void
    {
        $this->pause = max(max($milliseconds, 1) * 1000, $this->pause);
    }

    public function stop(bool $clear = true): void
    {
        $this->enabled = false;

        if ($clear) {
            $this->add = false;
            $this->tasks = array();
        }
    }

    public function addTask($object, string $name, ?array $arguments = null, ?int $milliseconds = null): void
    {
        $this->add = true;
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
        $this->add = false;
    }
}
