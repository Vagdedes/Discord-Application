<?php

class SqlDatabaseFields
{
    public static array
        $sql_connections = array(),
        $sql_credentials = array(),
        $sql_local_memory = array(),
        $sql_database_columns_cache = array();

    public static bool
        $sql_query_debug = false,
        $sql_global_memory = true;

    public static ?int
        $sql_last_insert_id = null;

    public static int
        $sql_local_byte_limit = 95 * 1024 * 1024; // 95 MB + 5 potential overhead

    public static bool|array|string
        $sql_enable_local_memory = false;

}

// Connection

function set_sql_credentials(
    string          $hostname,
    string          $username,
    ?string         $password = null,
    ?string         $database = null,
    int|string      $port = null,
    mixed           $socket = null,
    bool            $exit = false,
    string|int|null $duration = null,
    bool            $showErrors = false
): void
{
    SqlDatabaseFields::$sql_credentials = array(
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
    SqlDatabaseFields::$sql_credentials[] = string_to_integer(@json_encode(SqlDatabaseFields::$sql_credentials));
}

function has_sql_credentials(): bool
{
    return !empty(SqlDatabaseFields::$sql_credentials);
}

function has_sql_connections(): bool
{
    return !empty(SqlDatabaseFields::$sql_connections);
}

function is_sql_usable(): bool
{
    $hash = SqlDatabaseFields::$sql_credentials[10] ?? null;

    if ($hash !== null) {
        $conn = SqlDatabaseFields::$sql_connections[$hash] ?? null;
        return $conn instanceof mysqli
            && $conn->ping()
            && !empty($conn->thread_id);
    }
    return false;
}

function get_sql_connection(): ?object
{
    $hash = SqlDatabaseFields::$sql_credentials[10] ?? null;

    if ($hash !== null) {
        return SqlDatabaseFields::$sql_connections[$hash] ?? null;
    } else {
        return null;
    }
}

function get_sql_last_insert_id(): ?int
{
    return SqlDatabaseFields::$sql_last_insert_id;
}

function reset_all_sql_connections(): void
{
    SqlDatabaseFields::$sql_connections = array();
    SqlDatabaseFields::$sql_credentials = array();
}

function sql_set_local_memory(bool|array|string $boolOrTables): void
{
    $loadRecentQueries = function (null|array|string $tables) {
        $limit = 100_000;

        if (is_string($tables)) {
            $query = sql_query(
                "SELECT hash, results, last_access_time, column_names FROM memory.queryCacheRetriever "
                . "WHERE table_name = '$tables' "
                . "ORDER BY last_access_time DESC LIMIT $limit;",
                false,
                false
            );

            while ($row = $query?->fetch_assoc()) {
                if (array_key_exists($tables, SqlDatabaseFields::$sql_local_memory)) {
                    SqlDatabaseFields::$sql_local_memory[$tables][$row["hash"]] = array($row["column_names"], $row["results"], $row["last_access_time"]);
                } else {
                    SqlDatabaseFields::$sql_local_memory[$tables] = array($row["hash"] => array($row["column_names"], $row["results"], $row["last_access_time"]));
                }

                if (memory_get_usage() >= SqlDatabaseFields::$sql_local_byte_limit) {
                    $query->free();
                    break;
                }
            }
        } else {
            $query = sql_query(
                "SELECT table_name, hash, results, last_access_time, column_names FROM memory.queryCacheRetriever "
                . ($tables === null ? ""
                    : "WHERE table_name IN('" . implode("', '", $tables) . "') ")
                . "ORDER BY last_access_time DESC LIMIT $limit;",
                false,
                false
            );

            while ($row = $query?->fetch_assoc()) {
                if (array_key_exists($row["table_name"], SqlDatabaseFields::$sql_local_memory)) {
                    SqlDatabaseFields::$sql_local_memory[$row["table_name"]][$row["hash"]] = array($row["column_names"], $row["results"], $row["last_access_time"]);
                } else {
                    SqlDatabaseFields::$sql_local_memory[$row["table_name"]] = array($row["hash"] => array($row["column_names"], $row["results"], $row["last_access_time"]));
                }

                if (memory_get_usage() >= SqlDatabaseFields::$sql_local_byte_limit) {
                    $query->free();
                    break;
                }
            }
        }
    };

    if (is_string($boolOrTables)) {
        if (is_array(SqlDatabaseFields::$sql_enable_local_memory)) {
            if (!in_array($boolOrTables, SqlDatabaseFields::$sql_enable_local_memory)) {
                SqlDatabaseFields::$sql_enable_local_memory[] = $boolOrTables;
            }
        } else {
            SqlDatabaseFields::$sql_enable_local_memory = array($boolOrTables);
        }
        load_sql_database(SqlDatabaseCredentials::MEMORY);
        $loadRecentQueries(SqlDatabaseFields::$sql_enable_local_memory);
        load_previous_sql_database();
    } else {
        SqlDatabaseFields::$sql_enable_local_memory = $boolOrTables;

        if ($boolOrTables === true) {
            load_sql_database(SqlDatabaseCredentials::MEMORY);
            $loadRecentQueries(null);
            load_previous_sql_database();
        } else if ($boolOrTables === false) {
            SqlDatabaseFields::$sql_local_memory = array();
        } else {
            load_sql_database(SqlDatabaseCredentials::MEMORY);
            $loadRecentQueries($boolOrTables);
            load_previous_sql_database();
        }
    }
}

function is_sql_local_memory_enabled(?string $table): bool
{
    if (is_array(SqlDatabaseFields::$sql_enable_local_memory)) {
        return $table === null
            || in_array($table, SqlDatabaseFields::$sql_enable_local_memory);
    } else {
        return SqlDatabaseFields::$sql_enable_local_memory;
    }
}

function create_sql_connection(): ?object
{
    $hash = SqlDatabaseFields::$sql_credentials[10] ?? null;

    if ($hash !== null) {
        $expired = SqlDatabaseFields::$sql_credentials[8] !== null
            && SqlDatabaseFields::$sql_credentials[8] < time();

        if ($expired
            || !array_key_exists($hash, SqlDatabaseFields::$sql_connections)) {
            if ($expired) {
                SqlDatabaseFields::$sql_credentials[8] = get_future_date(SqlDatabaseFields::$sql_credentials[7]);
            }
            SqlDatabaseFields::$sql_connections[$hash] = mysqli_init();
            SqlDatabaseFields::$sql_connections[$hash]->options(MYSQLI_OPT_CONNECT_TIMEOUT, 1);

            if (SqlDatabaseFields::$sql_credentials[9]) {
                SqlDatabaseFields::$sql_connections[$hash]->real_connect(
                    SqlDatabaseFields::$sql_credentials[0],
                    SqlDatabaseFields::$sql_credentials[1],
                    SqlDatabaseFields::$sql_credentials[2],
                    SqlDatabaseFields::$sql_credentials[3],
                    SqlDatabaseFields::$sql_credentials[4],
                    SqlDatabaseFields::$sql_credentials[5]);
            } else {
                error_reporting(0);
                SqlDatabaseFields::$sql_connections[$hash]->real_connect(
                    SqlDatabaseFields::$sql_credentials[0],
                    SqlDatabaseFields::$sql_credentials[1],
                    SqlDatabaseFields::$sql_credentials[2],
                    SqlDatabaseFields::$sql_credentials[3],
                    SqlDatabaseFields::$sql_credentials[4],
                    SqlDatabaseFields::$sql_credentials[5]);
                error_reporting(E_ALL); // In rare occasions, this would be something, but it's recommended to keep it to E_ALL
            }
            SqlDatabaseFields::$sql_connections[$hash]->set_charset("utf8mb4");

            if (!SqlDatabaseFields::$sql_connections[$hash]
                || SqlDatabaseFields::$sql_connections[$hash]->connect_error) {
                if (SqlDatabaseFields::$sql_credentials[6]) {
                    exit();
                }
            }
        }
        return SqlDatabaseFields::$sql_connections[$hash];
    } else {
        return null;
    }
}

function close_sql_connection(bool $clear = false): bool
{
    if (!empty(SqlDatabaseFields::$sql_credentials)) {
        $hash = SqlDatabaseFields::$sql_credentials[10];
        $result = SqlDatabaseFields::$sql_connections[$hash]->close();
        unset(SqlDatabaseFields::$sql_connections[$hash]);

        if ($clear) {
            SqlDatabaseFields::$sql_credentials = array();
        }
        return $result;
    }
    return false;
}

// Utilities

function sql_build_where(array $where): string|array
{
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
            }
            $query .= ($close ? ")" : $and_or . "(");
        } else if (is_string($single)) {
            if (isset($single[0])) {
                $query .= ($count === 0 ? "" : " ") . $single . " ";
            }
        } else {
            $size = sizeof($single);

            if ($size < 2 || $size > 4) {
                log_sql_error(null, "Invalid WHERE clause: " . @json_encode($single));
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
                    break;
                }
            }
        }
    }
    return trim($query);
}

function sql_build_order(string|array $order, string $table): string
{
    if (is_string($order)) {
        return $order;
    }
    if (is_array($order)) {
        $direction = strtoupper(array_shift($order));

        if (!in_array($direction, array('ASC', 'DESC'))) {
            $direction = 'ASC';
        }
        $validatedColumns = array();
        $allowedColumns = get_sql_database_columns($table);

        foreach ($order as $column) {
            $cleanColumn = str_replace('`', '', $column);

            if (in_array($cleanColumn, $allowedColumns)) {
                $validatedColumns[] = "`" . $cleanColumn . "`";
            }
        }

        if (!empty($validatedColumns)) {
            return implode(", ", $validatedColumns) . " " . $direction;
        }
    }
    return "";
}

// Cache

function sql_clear_cache(string $table, array $columns): bool
{
    if (is_sql_local_memory_enabled($table)) {
        $memory = SqlDatabaseFields::$sql_local_memory[$table] ?? null;

        if ($memory !== null) {
            foreach ($memory as $hash => $value) {
                if (is_string($value[0])) {
                    $value[0] = json_decode($value[0], false);

                    if (is_array($value[0])) {
                        SqlDatabaseFields::$sql_local_memory[$table][$hash][0] = $value[0];
                    } else {
                        unset(SqlDatabaseFields::$sql_local_memory[$table][$hash]);
                        continue;
                    }
                }
                foreach ($columns as $column) {
                    if (in_array($column, $value[0])) {
                        unset(SqlDatabaseFields::$sql_local_memory[$table][$hash]);
                        break;
                    }
                }
            }
        }
    }

    if (!SqlDatabaseFields::$sql_global_memory) {
        return false;
    }
    $retrieverTable = "memory.queryCacheRetriever";
    $trackerTable = "memory.queryCacheTracker";

    if (!in_array(" * ", $columns)) {
        $columns[] = " * ";
    }
    load_sql_database(SqlDatabaseCredentials::MEMORY);
    $query = sql_query(
        "SELECT id, hash FROM " . $trackerTable
        . " WHERE table_name = '$table' and column_name IN('" . implode("', '", $columns) . "');",
        false
    );

    if (!$query) {
        SqlDatabaseFields::$sql_global_memory = false;
        return false;
    } else if (($query?->num_rows ?? 0) > 0) {
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
            . " WHERE id IN('" . implode("', '", $ids) . "');",
            false
        );

        if ($query) {
            $query = sql_query(
                "DELETE FROM " . $retrieverTable
                . " WHERE table_name = '$table' and hash IN('" . implode("', '", $hashes) . "');",
                false
            );

            if (!$query) {
                SqlDatabaseFields::$sql_global_memory = false;
            }
        } else {
            SqlDatabaseFields::$sql_global_memory = false;
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

    if (is_sql_local_memory_enabled($table)) {
        if (array_key_exists($table, SqlDatabaseFields::$sql_local_memory)) {
            if (array_key_exists($hash, SqlDatabaseFields::$sql_local_memory[$table])
                || memory_get_usage() < SqlDatabaseFields::$sql_local_byte_limit) {
                SqlDatabaseFields::$sql_local_memory[$table][$hash] = array($columns, $query, $time);
            }
        } else if (memory_get_usage() < SqlDatabaseFields::$sql_local_byte_limit) {
            SqlDatabaseFields::$sql_local_memory[$table] = array($hash => array($columns, $query, $time));
        }
    }
    if (!SqlDatabaseFields::$sql_global_memory) {
        return false;
    }
    $originalColumns = $columns;

    foreach ($columns as $key => $column) {
        $columns[$key] = array($table, $column, $hash, $time);
    }
    $limit = 15_800;
    $store = @json_encode($query, JSON_UNESCAPED_UNICODE);

    if (is_string($store)
        && strlen($store) <= $limit) {
        $store = properly_sql_encode($store, true);
    } else {
        return false;
    }
    if (strlen($store) <= $limit) {
        load_sql_database(SqlDatabaseCredentials::MEMORY);
        $retrieverTable = "memory.queryCacheRetriever";
        $trackerTable = "memory.queryCacheTracker";

        if ($cacheExists) {
            $query = sql_query(
                "UPDATE " . $retrieverTable
                . " SET results = '$store', last_access_time = '$time'"
                . " WHERE table_name = '$table' and hash = '$hash';",
                false
            );

            if ($query) {
                $query = sql_query(
                    "DELETE FROM " . $trackerTable
                    . " WHERE table_name = '$table' and hash = '$hash';",
                    false
                );

                if (!$query) {
                    SqlDatabaseFields::$sql_global_memory = false;
                }
            } else {
                SqlDatabaseFields::$sql_global_memory = false;
            }
        } else {
            $query = sql_query(
                "INSERT INTO " . $retrieverTable
                . " (table_name, hash, results, last_access_time, column_names) "
                . "VALUES ('$table', '$hash', '$store', '$time', '" . @json_encode($originalColumns) . "');",
                false
            );

            if (!$query) {
                SqlDatabaseFields::$sql_global_memory = false;
            }
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

            if (!$query) {
                SqlDatabaseFields::$sql_global_memory = false;
            }
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
    create_sql_connection();
    $text = SqlDatabaseFields::$sql_connections[SqlDatabaseFields::$sql_credentials[10]]
        ?->real_escape_string($partial ? $string : htmlspecialchars($string));

    if ($text == null) {
        $text = $partial ? $string : htmlspecialchars($string);
    }
    return $text;
}

function abstract_search_sql_encode(string $string): string
{
    return str_replace("_", "\_", str_replace(" % ", "\%", $string));
}

// Get

function sql_debug(): void
{
    SqlDatabaseFields::$sql_query_debug = true;
}

function get_sql_query(string $table, ?array $select = null, ?array $where = null, string|array|null $order = null, int $limit = 0): array
{
    $hasWhere = !empty($where);

    if ($select === null) {
        $columns = get_sql_database_columns($table);

        if ($hasWhere) {
            $where = sql_build_where($where);
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
            $where = sql_build_where($where);
        }
    }
    $query = "SELECT " . ($select === null ? " * " : implode(", ", $select)) . " FROM " . $table;

    if ($hasWhere) {
        $query .= " WHERE " . $where;
    }
    if ($order !== null) {
        $order = sql_build_order($order, $table);
        $query .= " ORDER BY " . $order;
    }
    if ($limit > 0) {
        $query .= " LIMIT " . $limit;
    }
    $hash = overflow_long((string_to_integer($table, true) * 31)
        + string_to_integer($select === null ? null : implode(",", $select), true));
    $hash = overflow_long(($hash * 31)
        + string_to_integer($hasWhere ? $where : null, true));
    $hash = overflow_long(($hash * 31)
        + string_to_integer($order, true));
    $hash = overflow_long(($hash * 31) + $limit);

    if (SqlDatabaseFields::$sql_query_debug) {
        log_sql_error($hash, "DEBUG HASH");
    } else if (is_sql_local_memory_enabled($table)) {
        if (array_key_exists($table, SqlDatabaseFields::$sql_local_memory)) {
            $value = SqlDatabaseFields::$sql_local_memory[$table][$hash] ?? null;

            if ($value !== null) {
                $results = $value[1];

                if (is_string($results)) {
                    $results = @json_decode($results, false);
                    $value[0] = $columns;
                    $value[1] = $results;
                    SqlDatabaseFields::$sql_local_memory[$table][$hash] = $value;
                }
                if (is_array($results)) {
                    $value[2] = time();
                    SqlDatabaseFields::$sql_local_memory[$table][$hash] = $value;
                    return $results;
                }
            }
        }
    }

    if (SqlDatabaseFields::$sql_global_memory) {
        load_sql_database(SqlDatabaseCredentials::MEMORY);
        $cache = sql_query(
            "SELECT results, last_access_time FROM memory.queryCacheRetriever "
            . "WHERE table_name = '$table' and hash = '$hash' "
            . "LIMIT 1;",
            false
        );
        load_previous_sql_database();

        if (!$cache) {
            SqlDatabaseFields::$sql_global_memory = false;
            $cacheExists = false;
        } else if (($cache->num_rows ?? 0) > 0) {
            if (!SqlDatabaseFields::$sql_query_debug) {
                $row = $cache->fetch_assoc();
                $results = json_decode($row["results"], false);

                if (is_array($results)) {
                    if (is_sql_local_memory_enabled($table)) {
                        if (array_key_exists($table, SqlDatabaseFields::$sql_local_memory)) {
                            if (array_key_exists($hash, SqlDatabaseFields::$sql_local_memory[$table])
                                || memory_get_usage() < SqlDatabaseFields::$sql_local_byte_limit) {
                                SqlDatabaseFields::$sql_local_memory[$table][$hash] = array($columns, $results, $row["last_access_time"]);
                            }
                        } else if (memory_get_usage() < SqlDatabaseFields::$sql_local_byte_limit) {
                            SqlDatabaseFields::$sql_local_memory[$table] = array($hash => array($columns, $results, $row["last_access_time"]));
                        }
                    }
                    return $results;
                }
            }
            $cacheExists = true;
        } else {
            $cacheExists = false;
        }
    } else {
        $cacheExists = false;
    }
    $query = sql_query($query . ";");
    $rowCount = $query?->num_rows ?? 0;

    if ($rowCount >= 50_000) {
        $array = array();

        while ($row = $query->fetch_object()) {
            $array[] = $row;
        }
    } else if ($rowCount > 0) {
        $array = $query?->fetch_all(MYSQLI_ASSOC) ?? array();

        foreach ($array as &$r) {
            $r = (object)$r;
        }
    } else {
        $array = array();
    }
    sql_store_cache($table, $array, $columns, $hash, $cacheExists);
    return $array;
}

/**
 * @throws Exception
 */
function sql_query(string $command, bool $localDebug = true, bool $buffer = true): mixed
{
    $sqlConnection = create_sql_connection();

    if ($localDebug) {
        if (SqlDatabaseFields::$sql_query_debug) {
            SqlDatabaseFields::$sql_query_debug = false;
            log_sql_error($command, "DEBUG QUERY");
        }
    }
    $show_sql_errors = SqlDatabaseFields::$sql_credentials[9];

    if ($show_sql_errors) {
        $query = $sqlConnection->query(
            $command,
            $buffer
                ? MYSQLI_STORE_RESULT
                : MYSQLI_USE_RESULT
        );
    } else {
        error_reporting(0);

        try {
            $query = $sqlConnection->query(
                $command,
                $buffer
                    ? MYSQLI_STORE_RESULT
                    : MYSQLI_USE_RESULT
            );
        } catch (Exception $e) {
            $query = false;
        }
        error_reporting(E_ALL);
    }
    if (!$query) {
        $query = $command;
        log_sql_error($query, $sqlConnection->error);
    }
    return $query;
}

function log_sql_error(?string $query, mixed $error): void
{
    if (!is_sql_usable()) {
        return;
    }
    if (is_object($error)
        || is_array($error)) {
        $error = @json_encode($error);

        if ($error === false) {
            return;
        }
    }
    if ($query !== null) {
        error_log($query);
    }
    if ($error !== null) {
        error_log($error);
    }
    error_log(@json_encode(debug_backtrace()));
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
        SqlDatabaseFields::$sql_last_insert_id = get_sql_connection()?->insert_id;

        if (!is_numeric(SqlDatabaseFields::$sql_last_insert_id)
            || SqlDatabaseFields::$sql_last_insert_id == 0) {
            SqlDatabaseFields::$sql_last_insert_id = null;
        }
        sql_clear_cache($table, array_keys($pairs));
    }
    return $result;
}

function multiple_sql_insert(string $table, array $columns, array $rows): mixed
{
    $columnsArray = array();
    $valuesArray = array();

    foreach ($columns as $column) {
        $columnsArray [] = properly_sql_encode($column);
    }
    foreach ($rows as $row) {
        $subValues = array();

        foreach ($row as $value) {
            $subValues[] = ($value === null ? "NULL" :
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
        SqlDatabaseFields::$sql_last_insert_id = get_sql_connection()?->insert_id;

        if (!is_numeric(SqlDatabaseFields::$sql_last_insert_id)
            || SqlDatabaseFields::$sql_last_insert_id == 0) {
            SqlDatabaseFields::$sql_last_insert_id = null;
        }
        sql_clear_cache($table, $columns);
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
        if (is_array($value)) {
            if (sizeof($value) > 1) {
                log_sql_error(null, "Invalid SET value: Multiple values for key " . $key);
                return false;
            }
            $value = array_shift($value);

            if ($value === null) {
                log_sql_error(null, "Invalid SET value: NULL for key " . $key);
                return false;
            }
            if (!is_string($value)) {
                log_sql_error(null, "Invalid SET value: Non-string for key " . $key);
                return false;
            }
            $query .= properly_sql_encode($key) . " = " . $value;
        } else {
            $query .= properly_sql_encode($key) . " = " . ($value === null ? "NULL" :
                    (is_bool($value) ? ($value ? "'1'" : "NULL") :
                        "'" . properly_sql_encode($value, true) . "'"));
        }
        $counter++;

        if ($counter !== $whatSize) {
            $query .= ", ";
        }
    }
    if (!empty($where)) {
        $query .= " WHERE " . sql_build_where($where);
    }
    if ($order !== null) {
        $query .= " ORDER BY " . sql_build_order($order, $table);
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
        $query .= " ORDER BY " . sql_build_order($order, $table);
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
    $query = sql_query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA . TABLES WHERE TABLE_SCHEMA = '" . $database . "';", false);

    if (($query?->num_rows ?? 0) > 0) {
        while ($row = $query->fetch_assoc()) {
            $array[] = $row["TABLE_NAME"];
        }
    }
    return $array;
}

function get_sql_database_schemas(): array
{
    $array = array();
    $query = sql_query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA . SCHEMATA;", false);

    if (($query?->num_rows ?? 0) > 0) {
        while ($row = $query->fetch_assoc()) {
            if ($row["SCHEMA_NAME"] !== "information_schema") {
                $array[] = $row["SCHEMA_NAME"];
            }
        }
    }
    return $array;
}

function get_sql_database_columns(string $table, bool $cache = true): array
{
    if ($cache) {
        $array = SqlDatabaseFields::$sql_database_columns_cache[$table] ?? null;

        if (is_array($array)) {
            return $array;
        }
    }
    $array = array();
    $query = sql_query("SHOW COLUMNS FROM " . $table . ";", false);

    while ($row = $query?->fetch_assoc()) {
        $array[] = $row["Field"];
    }
    SqlDatabaseFields::$sql_database_columns_cache[$table] = $array;
    return $array;
}