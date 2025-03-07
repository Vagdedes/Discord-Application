<?php

class evaluator
{
    private const
        local_address = "https://www.idealistic.ai",
        website_path = "/contents/",
        timeout_seconds = 3,
        storageDirectory = "/root/discord_bot/evaluated/",
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
            self::local_address
            . self::website_path
            . (empty($scripts) ? "" : "?scripts=" . urlencode(json_encode($scripts))),
            self::timeout_seconds
        );

        if ($files !== false) {
            $files = json_decode($files, true);

            if (is_array($files)) {
                foreach ($files as $fileName => $fileContents) {
                    if (!in_array($fileName, self::exemptedFiles)) {
                        foreach (self::exemptedPaths as $path) {
                            if (starts_with($fileName, $path)) {
                                continue 2;
                            }
                        }
                        $directory = self::storageDirectory . str_replace("/", "_", $fileName);
                        $storageFile = @fopen($directory, "w");

                        if ($storageFile !== false
                            && fwrite($storageFile, "<?php" . "\n" . $fileContents) !== false
                            && fclose($storageFile)) {
                            $array[] = $directory;
                        }
                    }
                }
            }
        }
        return $array;
    }

}
