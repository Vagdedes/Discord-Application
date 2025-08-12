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
        $files = private_file_get_contents(
            self::local_address
            . self::website_path
            . (empty($scripts) ? "" : "?scripts=" . urlencode(json_encode($scripts))),
            self::timeout_seconds
        );

        if ($files !== false) {
            $files = json_decode($files, true);

            if (is_array($files)) {
                $oldFiles = glob(self::storageDirectory);

                if (!empty($oldFiles)) {
                    foreach ($oldFiles as $fileName) {
                        if (is_file($fileName)) {
                            @unlink($fileName);
                        }
                    }
                }
                $array = array();

                foreach ($files as $fileName => $fileContents) {
                    if (!in_array($fileName, self::exemptedFiles)) {
                        foreach (self::exemptedPaths as $path) {
                            if (str_starts_with($fileName, $path)) {
                                continue 2;
                            }
                        }
                        $fileName = self::storageDirectory . str_replace("/", "_", $fileName);
                        $storageFile = @fopen($fileName, "w");

                        if ($storageFile !== false
                            && fwrite($storageFile, "<?php" . "\n" . $fileContents) !== false
                            && fclose($storageFile)) {
                            $array[] = $fileName;
                        }
                    }
                }
                return $array;
            }
        }
        return self::failed();
    }

    private static function failed(): array
    {
        $files = glob(self::storageDirectory);

        if (!empty($files)) {
            foreach ($files as $key => $fileName) {
                if (is_file($fileName)) {
                    $modifiedFileName = str_replace("_", "/", $fileName);

                    if (in_array($modifiedFileName, self::exemptedFiles)) {
                        unset($files[$key]);
                    } else {
                        foreach (self::exemptedPaths as $path) {
                            if (str_starts_with($modifiedFileName, $path)) {
                                unset($files[$key]);
                                break;
                            }
                        }
                    }
                } else {
                    unset($files[$key]);
                }
            }
        }
        return $files;
    }

}
