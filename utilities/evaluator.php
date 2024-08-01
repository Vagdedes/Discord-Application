<?php

class evaluator
{
    private const
        local_ip_address = "http://10.0.0.3",
        website_path = "/contents/",
        timeout_seconds = 3,
        exemptedFiles = array(
        "/var/www/.structure/library/base/communication.php",
        "/var/www/.structure/library/base/utilities.php",
        "/var/www/.structure/library/base/sql.php",
        "/var/www/.structure/library/memory/api/handlers/base.php"
    ),
        exemptedPaths = array();

    public static function run(?array $scripts = null): array
    {
        $array = array();

        $files = private_file_get_contents(
            self::local_ip_address
            . self::website_path
            . (empty($scripts) ? "" : "?scripts=" . urlencode(json_encode($scripts))),
            self::timeout_seconds
        );

        if ($files !== false) {
            $files = json_decode($files, true);

            if (is_array($files)) {
                foreach ($files as $fileName => $file) {
                    if (!in_array($fileName, self::exemptedFiles)) {
                        foreach (self::exemptedPaths as $path) {
                            if (starts_with($fileName, $path)) {
                                continue 2;
                            }
                        }
                        $array[$fileName] = $file;
                    }
                }
            }
        }
        return $array;
    }

}
