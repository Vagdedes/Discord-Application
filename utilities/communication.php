<?php
$administrator_local_server_ip_addresses_table = "administrator.localServerIpAddresses";
$memory_private_connections_table = "memory.privateConnections";
$private_connection_access = false;
$current_sql_database = null;
$previous_sql_database = null;

//load_sql_database(); Moved to bot implementation

class SqlDatabaseCredentials
{
    public const
        STORAGE = "sql_storage_credentials",
        MEMORY = "sql_memory_credentials";
}

function load_previous_sql_database(): void
{
    global $previous_sql_database;

    if ($previous_sql_database !== null) {
        global $current_sql_database;
        $current_sql_database = $previous_sql_database;
        $previous_sql_database = null;
        set_sql_credentials($current_sql_database[0],
            $current_sql_database[1],
            $current_sql_database[2],
            null,
            null,
            null,
            true,
            DiscordProperties::SYSTEM_REFRESH_TIME);
    } else {
        load_sql_database();
    }
}

function load_sql_database(string $file = SqlDatabaseCredentials::STORAGE): void
{
    global $current_sql_database, $previous_sql_database;
    $previous_sql_database = $current_sql_database;
    $current_sql_database = get_keys_from_file(
        $file,
        3
    );

    if ($current_sql_database === null) {
        exit("database failure: " . $file);
    } else {
        set_sql_credentials(
            $current_sql_database[0],
            $current_sql_database[1],
            $current_sql_database[2],
            null,
            null,
            null,
            true,
            DiscordProperties::SYSTEM_REFRESH_TIME
        );
    }
}

function private_file_get_contents(
    string $url,
    int    $timeout = 0,
    array  $parameters = [],
    bool   $clearPreviousParameters = false
): bool|string
{
    global $memory_private_connections_table;
    $code = random_string(512);
    load_sql_database(SqlDatabaseCredentials::MEMORY);
    sql_insert(
        $memory_private_connections_table,
        array(
            "code" => hash("sha512", $code),
            "expiration" => time() + 60
        )
    );
    load_previous_sql_database();

    return post_file_get_contents(
        $url,
        array_merge(
            array(
                "private_verification_key" => $code,
                "private_ip_address" => get_client_ip_address()
            ),
            $parameters
        ),
        $clearPreviousParameters,
        $_SERVER['HTTP_USER_AGENT'] ?? "",
        $timeout
    );
}

// Separator

function is_private_connection(): bool
{
    global $private_connection_access;

    if ($private_connection_access) {
        return true;
    } else {
        global $memory_private_connections_table;

        if (isset($_POST['private_verification_key'])) {
            global $administrator_local_server_ip_addresses_table;
            load_sql_database(SqlDatabaseCredentials::MEMORY);
            $query = get_sql_query( // No cache required, already in memory table
                $memory_private_connections_table,
                array("id"),
                array(
                    array("code", hash("sha512", $_POST['private_verification_key']))
                ),
                null,
                1
            );
            $cacheKey = array(__METHOD__, get_raw_client_ip_address());

            if (!empty($query)) {
                if (!function_exists("has_memory_cooldown")
                    || !has_memory_cooldown($cacheKey, null, false)) {
                    delete_sql_query(
                        $memory_private_connections_table,
                        array(
                            array("id", "=", $query[0]->id, 0),
                            array("expiration", "<", time())
                        )
                    );
                    load_previous_sql_database();
                    $localAddress = get_local_ip_address();
                    $queryArgs = array(
                        array("deletion_date", null),
                        null,
                        array("expiration_date", "IS", null, 0),
                        array("expiration_date", ">", get_current_date()),
                        null,
                        null
                    );
                    $explode = explode(".", $localAddress);
                    $size = sizeof($explode);
                    $last = (1 << $size);

                    for ($i = 0; $i < $last; $i++) {
                        $loopLocalAddress = [];

                        for ($j = 0; $j < $size; $j++) {
                            if ($i & (1 << $j)) {
                                $loopLocalAddress[] = "X";
                            } else {
                                $loopLocalAddress[] = $explode[$j];
                            }
                        }
                        if ($i === $last - 1) {
                            $queryArgs[] = array("ip_address", "=", implode(".", $loopLocalAddress));
                        } else {
                            $queryArgs[] = array("ip_address", "=", implode(".", $loopLocalAddress), 0);
                        }
                    }
                    $queryArgs[] = null;

                    if (!empty(get_sql_query(
                        $administrator_local_server_ip_addresses_table,
                        array("id"),
                        $queryArgs,
                        null,
                        1
                    ))) {
                        $private_connection_access = true;
                        return true;
                    }
                }
            } else {
                if (function_exists("has_memory_cooldown")) {
                    has_memory_cooldown($cacheKey, "1 minute", true, true);
                }
                load_previous_sql_database();
            }
        }
        return false;
    }
}

function get_private_ip_address(): ?string
{
    return is_private_connection() ? ($_POST['private_ip_address'] ?? null) : null;
}

// Separator

function set_communication_key(string $key, mixed $value): void
{
    $_POST[$key] = $value;
}

function get_communication_key(string $key): mixed
{
    return is_private_connection() ? ($_POST[$key] ?? null) : null;
}