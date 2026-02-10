<?php
$private_connection_access = false;
$current_sql_database = null;
$previous_sql_database = null;

//load_sql_database(); Moved to bot implementation

class __SqlDatabaseServers
{
    public const
        STORAGE = "sql_storage_credentials",
        MEMORY = "sql_memory_credentials";
}

class __SqlDatabaseCommunication
{
    public const
        PRIVATE_CONNECTIONS_TABLE = "localMemory.privateConnections";
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
            false,
            DiscordProperties::SYSTEM_REFRESH_TIME);
    } else {
        load_sql_database();
    }
}

function load_sql_database(string $file = __SqlDatabaseServers::STORAGE): void
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
            false,
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
    $code = random_string(512);
    load_sql_database(__SqlDatabaseServers::MEMORY);
    sql_insert(
        __SqlDatabaseCommunication::PRIVATE_CONNECTIONS_TABLE,
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
        if (isset($_POST['private_verification_key'])) {
            load_sql_database(__SqlDatabaseServers::MEMORY);
            $query = get_sql_query( // No cache required, already in memory table
                __SqlDatabaseCommunication::PRIVATE_CONNECTIONS_TABLE,
                array("id"),
                array(
                    array("code", hash("sha512", $_POST['private_verification_key']))
                ),
                null,
                1
            );

            if (!empty($query)) {
                $private_connection_access = true;
                delete_sql_query(
                    __SqlDatabaseCommunication::PRIVATE_CONNECTIONS_TABLE,
                    array(
                        array("id", "=", $query[0]->id, 0),
                        array("expiration", "<", time())
                    )
                );
                load_previous_sql_database();
                return true;
            }
            load_previous_sql_database();
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