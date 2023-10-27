<?php

class LoadBalancer
{
    private const
        local_ip_address = "http://10.0.0.3",
        website_path = "/contents/",
        timeout_seconds = 3;

    public static function getFiles(array $directories): array
    {
        $array = array();

        foreach ($directories as $directory) {
            $files = private_file_get_contents(
                self::local_ip_address
                . self::website_path
                . "?scripts=" . $directory,
                self::timeout_seconds
            );

            if ($files !== false) {
                $files = json_decode($files, true);

                if (is_array($files)) {
                    foreach ($files as $file) {
                        $array[] = $file;
                    }
                }
            }
        }
        return $array;
    }
}
