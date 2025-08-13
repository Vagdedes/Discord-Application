<?php
$sql_connections = array();
$sql_credentials = array();
$is_sql_usable = false;
$debug = false;
$sql_last_insert_id = null;

// Connection

function set_sql_credentials(string          $hostname,
                             string          $username,
                             ?string         $password = null,
                             ?string         $database = null,
                             int|string      $port = null, $socket = null,
                             bool            $exit = false,
                             string|int|null $duration = null,
                             bool            $showErrors = false): void
{
    global $sql_credentials;
    $sql_credentials = array(
        $hostname,
        $username,
        $password,
        $database,
        $port,
        $socket,
        $exit,
        $duration,
        $duration === null ? null : get_future_date($duration),
        $showErrors
    );
    $sql_credentials[] = string_to_integer(json_encode($sql_credentials));
}

function has_sql_credentials(): bool
{
    global $sql_credentials;
    return !empty($sql_credentials);
}

function has_sql_connections(): bool
{
    global $sql_connections;
    return !empty($sql_connections);
}

function is_sql_usable(): bool
{
    global $is_sql_usable;
    return $is_sql_usable;
}

function get_sql_connection(): ?object
{
    global $sql_credentials;

    if (!empty($sql_credentials)) {
        global $sql_connections;
        return $sql_connections[$sql_credentials[10]] ?? null;
    } else {
        return null;
    }
}

function get_sql_last_insert_id(): ?int
{
    global $sql_last_insert_id;
    return $sql_last_insert_id;
}

function reset_all_sql_connections(): void
{
    global $sql_credentials;

    if (!empty($sql_credentials)) {
        global $sql_connections,
               $is_sql_usable;
        $sql_connections = array();
        $sql_credentials = array();
        $is_sql_usable = false;
    }
}

function create_sql_connection(): ?object
{
    global $sql_credentials;
    $hash = $sql_credentials[10] ?? null;

    if ($hash !== null) {
        global $sql_connections;
        $expired = $sql_credentials[8] !== null && $sql_credentials[8] < time();

        if ($expired || !array_key_exists($hash, $sql_connections)) {
            if ($expired) {
                $sql_credentials[8] = get_future_date($sql_credentials[7]);
            }
            global $sql_credentials;

            if (sizeof($sql_credentials) === 11) {
                global $is_sql_usable;
                $is_sql_usable = false;
                $sql_connections[$hash] = mysqli_init();
                $sql_connections[$hash]->options(MYSQLI_OPT_CONNECT_TIMEOUT, 1);

                if ($sql_credentials[9]) {
                    $sql_connections[$hash]->real_connect($sql_credentials[0], $sql_credentials[1], $sql_credentials[2],
                        $sql_credentials[3], $sql_credentials[4], $sql_credentials[5]);
                } else {
                    error_reporting(0);
                    $sql_connections[$hash]->real_connect($sql_credentials[0], $sql_credentials[1], $sql_credentials[2],
                        $sql_credentials[3], $sql_credentials[4], $sql_credentials[5]);
                    error_reporting(E_ALL); // In rare occasions, this would be something, but it's recommended to keep it to E_ALL
                }

                if ($sql_connections[$hash]->connect_error) {
                    $is_sql_usable = false;

                    if ($sql_credentials[6]) {
                        exit();
                    }
                } else {
                    $is_sql_usable = true;
                }
            } else {
                exit();
            }
        }
        return $sql_connections[$hash];
    } else {
        return null;
    }
}

function close_sql_connection(bool $clear = false): bool
{
    global $sql_credentials;

    if (!empty($sql_credentials)) {
        global $is_sql_usable;

        if ($is_sql_usable) {
            global $sql_connections;
            $hash = $sql_credentials[10];
            $result = $sql_connections[$hash]->close();
            unset($sql_connections[$hash]);
            $is_sql_usable = false;

            if ($clear) {
                $sql_credentials = array();
            }
            return $result;
        } else {
            global $sql_connections;
            unset($sql_connections[$sql_credentials[10]]);
            $is_sql_usable = false;

            if ($clear) {
                $sql_credentials = array();
            }
        }
    }
    return false;
}

// Utilities

function sql_build_where(array $where, bool $buildKey = false): string|array
{
    if ($buildKey) {
        $queryKey = array();
    }
    $query = "";
    $parenthesesCount = 0;
    $whereEnd = sizeof($where) - 1;

    foreach ($where as $count => $single) {
        if ($single === null) {
            $parenthesesCount++;

            if ($count === 0) {
                $close = false;
                $and_or = "";
            } else {
                $close = $parenthesesCount % 2 == 0;
                $previous = $where[$count - 1];
                $and = $previous === null || is_string($previous) || ($previous[3] ?? 1) === 1;
                $and_or = ($and ? " AND " : " OR ");

                if ($buildKey) {
                    $queryKey[] = ($and ? 1 : 0);
                }
            }
            $query .= ($close ? ")" : $and_or . "(");

            if ($buildKey) {
                $queryKey[] = ($close ? 2 : 3);
            }
        } else if (is_string($single)) {
            if (isset($single[0])) {
                $query .= " " . $single . " ";

                if ($buildKey) {
                    $queryKey[] = remove_dates($single);
                }
            }
        } else {
            $size = sizeof($single);

            if ($size < 2 || $size > 4) {
                log_sql_error(null, "Invalid WHERE clause: " . json_encode($single));
            }
            $equals = $size === 2;
            $value = $single[$equals ? 1 : 2];
            $nullValue = $value === null;
            $booleanValue = is_bool($value);
            $query .= $single[0]
                . " " . ($equals ? ($nullValue || $booleanValue && !$value ? "IS" : "=") : $single[1])
                . " " . ($nullValue ? "NULL" :
                    ($booleanValue ? ($value ? "'1'" : "NULL") :
                        "'" . properly_sql_encode($value, true) . "'"));

            if ($buildKey && ($nullValue || $booleanValue || !is_date($value))) {
                if ($equals || $single[1] === "=" || $single[1] === "IS") {
                    $queryKey[$single[0]] = $value;
                } else {
                    $queryKey[$single[0]] = array($single[1], $value);
                }
            }
            $customCount = $count;

            while ($customCount !== $whereEnd) {
                $customCount++;
                $next = $where[$customCount];

                if ($next === null) {
                    break;
                }
                if (!is_string($next) || !empty($next)) {
                    $and = $equals || ($single[3] ?? 1) === 1;
                    $query .= ($and ? " AND " : " OR ");

                    if ($buildKey) {
                        $queryKey[] = ($and ? 1 : 0);
                    }
                    break;
                }
            }
        }
    }
    return $buildKey ? array($query, $queryKey) : $query;
}

function sql_build_order(string|array|null $order): ?string
{
    if (is_array($order)) {
        $orderType = array_shift($order);
        return implode(", ", $order) . " " . $orderType;
    } else {
        return $order;
    }
}

// Cache

function sql_delete_outdated_cache(int $time = 60 * 60): bool
{
    $retrieverTable = "memory.queryCacheRetriever";
    $trackerTable = "memory.queryCacheTracker";
    $query = sql_query(
        "DELETE FROM $retrieverTable "
        . "WHERE last_access_time < '" . (time() - $time) . "';",
        false
    );

    if ($query) {
        $query = sql_query(
            "DELETE FROM $trackerTable "
            . "WHERE last_access_time < '" . (time() - $time) . "';",
            false
        );
        return (bool)$query;
    } else {
        return false;
    }
}

function sql_clear_cache(string $table, array $columns): bool
{
    $retrieverTable = "memory.queryCacheRetriever";
    $trackerTable = "memory.queryCacheTracker";

    if (!in_array("*", $columns)) {
        $columns[] = "*";
    }
    load_sql_database(SqlDatabaseCredentials::MEMORY);
    $query = sql_query(
        "SELECT id, hash FROM " . $trackerTable
        . " WHERE table_name = '$table' AND column_name IN ('" . implode("', '", $columns) . "');",
        false
    );

    if (($query->num_rows ?? 0) > 0) {
        $ids = array();
        $hashes = array();

        while ($row = $query->fetch_assoc()) {
            $ids[] = $row["id"];

            if (!in_array($row["hash"], $hashes)) {
                $hashes[] = $row["hash"];
            }
        }
        $query = sql_query(
            "DELETE FROM " . $trackerTable
            . " WHERE id IN ('" . implode("', '", $ids) . "');",
            false
        );

        if ($query) {
            $query = sql_query(
                "DELETE FROM " . $retrieverTable
                . " WHERE table_name = '$table' AND hash IN ('" . implode("', '", $hashes) . "');",
                false
            );

        }
        load_previous_sql_database();
        return (bool)$query;
    } else {
        load_previous_sql_database();
        return false;
    }
}

function sql_store_cache(string           $table,
                         array            $query,
                         ?array           $columns,
                         int|string|float $hash,
                         bool             $cacheExists): bool
{
    $time = time();

    foreach ($columns as $key => $column) {
        $columns[$key] = array($table, $column, $hash, $time);
    }
    $store = @json_encode($query, JSON_UNESCAPED_UNICODE);

    if (strlen($store) <= 16312) {
        load_sql_database(SqlDatabaseCredentials::MEMORY);
        $retrieverTable = "memory.queryCacheRetriever";
        $trackerTable = "memory.queryCacheTracker";

        if ($cacheExists) {
            $query = sql_query(
                "UPDATE " . $retrieverTable
                . " SET results = '$store', last_access_time = '$time'"
                . " WHERE table_name = '$table' AND hash = '$hash';"
            );

            if ($query) {
                $query = sql_query(
                    "DELETE FROM " . $trackerTable
                    . " WHERE table_name = '$table' AND hash = '$hash';",
                    false
                );
            }
        } else {
            $query = sql_query(
                "INSERT INTO " . $retrieverTable
                . " (table_name, hash, results, last_access_time) "
                . "VALUES ('$table', '$hash', '$store', '$time');",
                false
            );
        }
        if ($query) {
            $columnsString = array();

            foreach ($columns as $column) {
                $columnsString[] = "('" . implode("', '", $column) . "')";
            }
            $query = sql_query(
                "INSERT INTO " . $trackerTable
                . " (table_name, column_name, hash, last_access_time) "
                . "VALUES " . implode(", ", $columnsString) . ";",
                false
            );
        }
        load_previous_sql_database();
        return (bool)$query;
    } else {
        return false;
    }
}

// Encoding

function properly_sql_encode(string $string, bool $partial = false): ?string
{
    global $is_sql_usable;

    if (!$is_sql_usable) {
        return $partial ? $string : htmlspecialchars($string);
    } else {
        global $sql_connections, $sql_credentials;
        create_sql_connection();
        return $sql_connections[$sql_credentials[10]]->real_escape_string($partial ? $string : htmlspecialchars($string));
    }
}

function abstract_search_sql_encode(string $string): string
{
    return str_replace("_", "\_", str_replace("%", "\%", $string));
}

// Get

function sql_debug(): void
{
    global $debug;
    $debug = true;
}

function get_sql_query(string $table, ?array $select = null, ?array $where = null, string|array|null $order = null, int $limit = 0): array
{
    global $debug;
    $hasWhere = !empty($where);

    if ($select === null) {
        $columns = get_sql_database_columns($table);

        if ($hasWhere) {
            $where = sql_build_where($where, true);
        }
    } else {
        $columns = $select;

        if ($hasWhere) {
            foreach ($where as $single) {
                if (is_array($single)
                    && !in_array($single[0], $columns)) {
                    $columns[] = $single[0];
                }
            }
            $where = sql_build_where($where, true);
        }
    }
    $query = "SELECT " . ($select === null ? "*" : implode(", ", $select)) . " FROM " . $table;

    if ($hasWhere) {
        $query .= " WHERE " . $where[0];
    }
    if ($order !== null) {
        $query .= " ORDER BY " . sql_build_order($order);
    }
    if ($limit > 0) {
        $query .= " LIMIT " . $limit;
    }

    $hash = array_to_integer(
        array(
            $table,
            $select,
            $hasWhere ? $where[1] : null,
            $order,
            $limit
        ),
        true
    );
    load_sql_database(SqlDatabaseCredentials::MEMORY);
    $cache = sql_query(
        "SELECT results FROM memory.queryCacheRetriever "
        . "WHERE table_name = '$table' AND hash = '$hash' "
        . "LIMIT 1;",
        false
    );
    load_previous_sql_database();

    if (!$debug && ($cache->num_rows ?? 0) > 0) {
        $row = $cache->fetch_assoc();
        $results = json_decode($row["results"], false);

        if (is_array($results)) {
            return $results;
        }
        $cacheExists = true;
    } else {
        $cacheExists = false;
    }
    $query = sql_query($query . ";");
    $array = array();

    if (($query->num_rows ?? 0) > 0) {
        while ($row = $query->fetch_assoc()) {
            $object = new stdClass();

            foreach ($row as $key => $value) {
                $object->{$key} = $value;
            }
            $array[] = $object;
        }
    }
    sql_store_cache($table, $array, $columns, $hash, $cacheExists);
    return $array;
}

/**
 * @throws Exception
 */
function sql_query(string $command, bool $localDebug = true): mixed
{
    $sqlConnection = create_sql_connection();
    global $is_sql_usable;

    if ($localDebug) {
        global $debug;

        if ($debug) {
            $debug = false;
            var_dump($command);
        }
    }
    if ($is_sql_usable) {
        global $sql_credentials;
        $show_sql_errors = $sql_credentials[9];

        if ($show_sql_errors) {
            global $sql_last_insert_id;
            $query = $sqlConnection->query($command);
            $sql_last_insert_id = $sqlConnection->insert_id;

            if (!is_numeric($sql_last_insert_id)
                || $sql_last_insert_id == 0) {
                $sql_last_insert_id = null;
            }
        } else {
            error_reporting(0);

            try {
                $query = $sqlConnection->query($command);
            } catch (Exception $e) {
                $query = false;
            }
            error_reporting(E_ALL);
        }
        if (!$query) {
            $query = $command;
            log_sql_error($query, $sqlConnection->error, $sqlConnection);
        }
        return $query;
    }
    return null;
}

function log_sql_error(?string $query, mixed $error, mixed $sqlConnection = null): void
{
    if (is_object($error)
        || is_array($error)) {
        $error = json_encode($error);

        if ($error === false) {
            return;
        }
    }
    global $sql_credentials;
    $show_sql_errors = $sql_credentials[9];
    $command = "INSERT INTO logs.sqlErrors (creation, file, query, error) VALUES "
        . "('" . time() . "', '" . properly_sql_encode($_SERVER["SCRIPT_NAME"])
        . "', '" . $query . "', '" . $error . "');";

    if ($sqlConnection === null) {
        $sqlConnection = get_sql_connection();
    }
    if ($show_sql_errors) {
        $sqlConnection->query($command);
    } else {
        error_reporting(0);

        try {
            $sqlConnection->query($command);
        } catch (Exception $e) {
        }
        error_reporting(E_ALL);
    }
}

// Insert

function sql_insert(string $table, array $pairs): mixed
{
    $columnsArray = array();
    $valuesArray = array();

    foreach ($pairs as $column => $value) {
        $columnsArray [] = properly_sql_encode($column);
        $valuesArray [] = ($value === null ? "NULL" :
            (is_bool($value) ? ($value ? "'1'" : "NULL") :
                "'" . properly_sql_encode($value, true) . "'"));
    }
    $columnsArray = implode(", ", $columnsArray);
    $valuesArray = implode(", ", $valuesArray);
    $table = properly_sql_encode($table);
    $result = sql_query("INSERT INTO $table ($columnsArray) VALUES ($valuesArray);");

    if ($result) {
        sql_clear_cache($table, array_keys($pairs));
    }
    return $result;
}

function sql_insert_multiple(string $table, array $columns, array $values): mixed
{
    $columnsArray = array();
    $valuesArray = array();

    foreach ($columns as $column) {
        $columnsArray [] = properly_sql_encode($column);
    }
    foreach ($values as $subValues) {
        foreach ($subValues as $key => $value) {
            $subValues[$key] = ($value === null ? "NULL" :
                (is_bool($value) ? ($value ? "'1'" : "NULL") :
                    "'" . properly_sql_encode($value, true) . "'"));
        }
        $valuesArray [] = "(" . implode(", ", $subValues) . ")";
    }
    $columnsArray = implode(", ", $columnsArray);
    $valuesArray = implode(", ", $valuesArray);
    $table = properly_sql_encode($table);
    $result = sql_query("INSERT INTO $table ($columnsArray) VALUES $valuesArray;");

    if ($result) {
        sql_clear_cache($table, $columns);
    }
    return $result;
}

// Set

function set_sql_query(string $table, array $what, ?array $where = null, string|array|null $order = null, int $limit = 0): mixed
{
    $query = "UPDATE " . $table . " SET ";
    $counter = 0;
    $whatSize = sizeof($what);

    foreach ($what as $key => $value) {
        $query .= properly_sql_encode($key) . " = " . ($value === null ? "NULL" :
                (is_bool($value) ? ($value ? "'1'" : "NULL") :
                    "'" . properly_sql_encode($value, true) . "'"));
        $counter++;

        if ($counter !== $whatSize) {
            $query .= ", ";
        }
    }
    if (!empty($where)) {
        $query .= " WHERE " . sql_build_where($where);
    }
    if ($order !== null) {
        $query .= " ORDER BY " . sql_build_order($order);
    }
    if ($limit > 0) {
        $query .= " LIMIT " . $limit;
    }
    $query = sql_query($query . ";");

    if ($query) {
        sql_clear_cache($table, array_keys($what));
    }
    return $query;
}

// Delete

function delete_sql_query(string $table, array $where, string|array|null $order = null, int $limit = 0): mixed
{
    $columnsQuery = sql_query("SELECT * FROM " . $table . " LIMIT 1;");
    $query = "DELETE FROM " . $table . " WHERE " . sql_build_where($where);

    if ($order !== null) {
        $query .= " ORDER BY " . sql_build_order($order);
    }
    if ($limit > 0) {
        $query .= " LIMIT " . $limit;
    }
    $query = sql_query($query . ";");

    if ($query) {
        if (($columnsQuery->num_rows ?? 0) > 0) {
            sql_clear_cache($table, array_keys($columnsQuery->fetch_assoc()));
        }
    }
    return $query;
}

// Local

function get_sql_database_tables(string $database): array
{
    $array = array();
    $query = sql_query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '" . $database . "';");

    if (($query->num_rows ?? 0) > 0) {
        while ($row = $query->fetch_assoc()) {
            $array[] = $row["TABLE_NAME"];
        }
    }
    return $array;
}

function get_sql_database_schemas(): array
{
    $array = array();
    $query = sql_query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA;");

    if (($query->num_rows ?? 0) > 0) {
        while ($row = $query->fetch_assoc()) {
            if ($row["SCHEMA_NAME"] !== "information_schema") {
                $array[] = $row["SCHEMA_NAME"];
            }
        }
    }
    return $array;
}

function get_sql_database_columns(string $table): array
{
    $array = array();
    $query = sql_query("SHOW COLUMNS FROM " . $table . ";");

    if ($query instanceof mysqli_result) {
        while ($row = $query->fetch_assoc()) {
            $array[] = $row["Field"];
        }
    }
    return $array;
}
