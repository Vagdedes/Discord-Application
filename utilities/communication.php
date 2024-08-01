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

function load_sql_database($file = SqlDatabaseCredentials::STORAGE): void
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
            "5 minutes"
        );
    }
}

function private_file_get_contents($url, $createAndClose = false): bool|string
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
        array(
            "private_verification_key" => $code,
            "private_ip_address" => get_client_ip_address()
        ),
        false,
        $_SERVER['HTTP_USER_AGENT'] ?? "",
        $createAndClose ? 1 : 0
    );
}

// Separator

function is_private_connection($checkClientIP = false): bool
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
                    set_sql_cache();
                    if (!empty(get_sql_query(
                        $administrator_local_server_ip_addresses_table,
                        array("id"),
                        array(
                            array("ip_address", get_local_ip_address()),
                            array("deletion_date", null),
                            null,
                            array("expiration_date", "IS", null, 0),
                            array("expiration_date", ">", get_current_date()),
                            null,
                        ),
                        null,
                        1
                    ))) {
                        if ($checkClientIP) {
                            set_sql_cache();
                            if (!empty(get_sql_query(
                                $administrator_local_server_ip_addresses_table,
                                array("id"),
                                array(
                                    array("ip_address", get_raw_client_ip_address()),
                                    array("deletion_date", null),
                                    null,
                                    array("expiration_date", "IS", null, 0),
                                    array("expiration_date", ">", get_current_date()),
                                    null,
                                ),
                                null,
                                1
                            ))) {
                                $private_connection_access = true;
                                return true;
                            }
                        } else {
                            $private_connection_access = true;
                            return true;
                        }
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

function get_session_account_id()
{
    return is_private_connection() ? ($_POST['session_account_id'] ?? null) : null;
}

function set_session_account_id($id): void
{
    $_POST['session_account_id'] = $id;
}

function has_session_account_id(): bool
{
    return !empty(get_session_account_id());
}