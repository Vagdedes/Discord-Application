<?php
$administrator_local_server_ip_addresses_table = "administrator.localServerIpAddresses";
$memory_private_connections_table = "memory.privateConnections";

function private_file_get_contents($url, $createAndClose = false): bool|string
{
    global $memory_private_connections_table;
    $code = random_string(512);
    load_sql_database(SqlDatabaseCredentials::MEMORY);
    sql_insert(
        $memory_private_connections_table,
        array(
            "code" => hash("sha512", $code),
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
    global $memory_private_connections_table;

    if (isset($_POST['private_verification_key'])) {
        load_sql_database(SqlDatabaseCredentials::MEMORY);
        $query = get_sql_query(
            $memory_private_connections_table,
            array("id"),
            array(
                array("code", hash("sha512", $_POST['private_verification_key'])),
            ),
            null,
            1
        );
        load_previous_sql_database();

        if (!empty($query)) {
            delete_sql_query(
                $memory_private_connections_table,
                array(
                    array("id", $query[0]->id),
                ),
                null,
                1
            );
            global $administrator_local_server_ip_addresses_table;
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
                    return !empty(get_sql_query(
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
                    ));
                } else {
                    return true;
                }
            }
        }
    }
    return false;
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