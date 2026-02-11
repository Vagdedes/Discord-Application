<?php

class __SqlDatabaseFields
{
    public static array
        $sql_connections = array(),
        $sql_credentials = array(),
        $sql_local_memory = array(),
        $sql_database_columns_cache = array();

    public static bool
        $sql_global_memory = true;

    public static ?int
        $sql_last_insert_id = null;

    public static bool|array|string
        $sql_enable_local_memory = false;

    public const
        MEMORY_HOST_NAME = "10.0.0.5",
        SQL_LOCAL_BYTE_LIMIT = 95 * 1024 * 1024; // 95 MB + 5 potential overhead

}

// Connection

function set_sql_credentials(
    string     $hostname,
    string     $username,
    ?string    $password = null,
    ?string    $database = null,
    int|string $port = null,
    mixed      $socket = null
): void
{
    __SqlDatabaseFields::$sql_credentials = array(
        $hostname,
        $username,
        $password,
        $database,
        is_numeric($port) ? (int)$port : 3306,
        $socket
    );
    __SqlDatabaseFields::$sql_credentials[] = array_to_integer(__SqlDatabaseFields::$sql_credentials);
}

function has_sql_credentials(): bool
{
    return !empty(__SqlDatabaseFields::$sql_credentials);
}

function has_sql_connections(): bool
{
    return !empty(__SqlDatabaseFields::$sql_connections);
}

function is_sql_usable(): bool
{
    $conn = create_sql_connection();

    if ($conn instanceof mysqli) {
        try {
            return $conn->ping();
        } catch (Throwable $ignored) {
        }
    }
    return false;
}

function get_sql_last_insert_id(): ?int
{
    return __SqlDatabaseFields::$sql_last_insert_id;
}

function reset_all_sql_connections(): void
{
    __SqlDatabaseFields::$sql_connections = array();
    __SqlDatabaseFields::$sql_credentials = array();
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
                true
            );

            if (($query?->num_rows ?? 0) > 0) {
                while ($row = $query->fetch_assoc()) {
                    if (array_key_exists($tables, __SqlDatabaseFields::$sql_local_memory)) {
                        __SqlDatabaseFields::$sql_local_memory[$tables][$row["hash"]] = array($row["column_names"], $row["results"], $row["last_access_time"]);
                    } else {
                        __SqlDatabaseFields::$sql_local_memory[$tables] = array($row["hash"] => array($row["column_names"], $row["results"], $row["last_access_time"]));
                    }

                    if (memory_get_usage() >= __SqlDatabaseFields::SQL_LOCAL_BYTE_LIMIT) {
                        $query->free();
                        break;
                    }
                }
            }
        } else {
            $query = sql_query(
                "SELECT table_name, hash, results, last_access_time, column_names FROM memory.queryCacheRetriever "
                . ($tables === null ? ""
                    : "WHERE table_name IN ('" . implode("', '", $tables) . "') ")
                . "ORDER BY last_access_time DESC LIMIT $limit;",
                false,
                true
            );

            if (($query?->num_rows ?? 0) > 0) {
                while ($row = $query->fetch_assoc()) {
                    if (array_key_exists($row["table_name"], __SqlDatabaseFields::$sql_local_memory)) {
                        __SqlDatabaseFields::$sql_local_memory[$row["table_name"]][$row["hash"]] = array($row["column_names"], $row["results"], $row["last_access_time"]);
                    } else {
                        __SqlDatabaseFields::$sql_local_memory[$row["table_name"]] = array($row["hash"] => array($row["column_names"], $row["results"], $row["last_access_time"]));
                    }

                    if (memory_get_usage() >= __SqlDatabaseFields::SQL_LOCAL_BYTE_LIMIT) {
                        $query->free();
                        break;
                    }
                }
            }
        }
    };

    if (is_string($boolOrTables)) {
        if (is_array(__SqlDatabaseFields::$sql_enable_local_memory)) {
            if (!in_array($boolOrTables, __SqlDatabaseFields::$sql_enable_local_memory)) {
                __SqlDatabaseFields::$sql_enable_local_memory[] = $boolOrTables;
            }
        } else {
            __SqlDatabaseFields::$sql_enable_local_memory = array($boolOrTables);
        }
        load_sql_database(__SqlDatabaseServers::MEMORY);
        $loadRecentQueries(__SqlDatabaseFields::$sql_enable_local_memory);
        load_previous_sql_database();
    } else {
        __SqlDatabaseFields::$sql_enable_local_memory = $boolOrTables;

        if ($boolOrTables === true) {
            load_sql_database(__SqlDatabaseServers::MEMORY);
            $loadRecentQueries(null);
            load_previous_sql_database();
        } else if ($boolOrTables === false) {
            __SqlDatabaseFields::$sql_local_memory = array();
        } else {
            load_sql_database(__SqlDatabaseServers::MEMORY);
            $loadRecentQueries($boolOrTables);
            load_previous_sql_database();
        }
    }
}

function is_sql_local_memory_enabled(?string $table): bool
{
    if (is_array(__SqlDatabaseFields::$sql_enable_local_memory)) {
        return $table === null
            || in_array($table, __SqlDatabaseFields::$sql_enable_local_memory);
    } else {
        return __SqlDatabaseFields::$sql_enable_local_memory;
    }
}

function create_sql_connection(bool $force = false): ?mysqli
{
    $hash = __SqlDatabaseFields::$sql_credentials[6] ?? null;

    if ($hash !== null) {
        if ($force
            || !array_key_exists($hash, __SqlDatabaseFields::$sql_connections)) {
            unset(__SqlDatabaseFields::$sql_connections[$hash]);

            try {
                __SqlDatabaseFields::$sql_connections[$hash] = mysqli_init();
                __SqlDatabaseFields::$sql_connections[$hash]->options(MYSQLI_OPT_CONNECT_TIMEOUT, 1);
                __SqlDatabaseFields::$sql_connections[$hash]->real_connect(
                    __SqlDatabaseFields::$sql_credentials[0],
                    __SqlDatabaseFields::$sql_credentials[1],
                    __SqlDatabaseFields::$sql_credentials[2],
                    __SqlDatabaseFields::$sql_credentials[3],
                    __SqlDatabaseFields::$sql_credentials[4],
                    __SqlDatabaseFields::$sql_credentials[5]
                );
                __SqlDatabaseFields::$sql_connections[$hash]->set_charset("utf8mb4");
            } catch (Throwable $e) {
                unset(__SqlDatabaseFields::$sql_connections[$hash]);

                if (__SqlDatabaseFields::$sql_credentials[0] === __SqlDatabaseFields::MEMORY_HOST_NAME) {
                    __SqlDatabaseFields::$sql_global_memory = false;
                } else {
                    log_sql_error(null, $e->getMessage(), $e->getTraceAsString());
                }
            }
        }
        $object = __SqlDatabaseFields::$sql_connections[$hash] ?? null;

        if ($object instanceof mysqli
            && $object->connect_error === null) {
            return $object;
        }
    }
    return null;
}

function close_sql_connection(bool $clear = false): bool
{
    if (!empty(__SqlDatabaseFields::$sql_credentials)) {
        $hash = __SqlDatabaseFields::$sql_credentials[6];
        $result = __SqlDatabaseFields::$sql_connections[$hash]->close();
        unset(__SqlDatabaseFields::$sql_connections[$hash]);

        if ($clear) {
            __SqlDatabaseFields::$sql_credentials = array();
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
        $memory = __SqlDatabaseFields::$sql_local_memory[$table] ?? null;

        if ($memory !== null) {
            foreach ($memory as $hash => $value) {
                if (is_string($value[0])) {
                    $value[0] = json_decode($value[0], false);

                    if (is_array($value[0])) {
                        __SqlDatabaseFields::$sql_local_memory[$table][$hash][0] = $value[0];
                    } else {
                        unset(__SqlDatabaseFields::$sql_local_memory[$table][$hash]);
                        continue;
                    }
                }
                foreach ($columns as $column) {
                    if (in_array($column, $value[0])) {
                        unset(__SqlDatabaseFields::$sql_local_memory[$table][$hash]);
                        break;
                    }
                }
            }
        }
    }

    if (!__SqlDatabaseFields::$sql_global_memory) {
        return false;
    }
    $retrieverTable = "memory.queryCacheRetriever";
    $trackerTable = "memory.queryCacheTracker";

    if (!in_array(" * ", $columns)) {
        $columns[] = " * ";
    }
    load_sql_database(__SqlDatabaseServers::MEMORY);
    $query = sql_query(
        "SELECT id, hash FROM " . $trackerTable
        . " WHERE table_name = '$table' and column_name IN ('" . implode("', '", $columns) . "');",
        true,
        true
    );

    if (($query?->num_rows ?? 0) > 0) {
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
            true,
            true
        );

        if ($query) {
            $query = sql_query(
                "DELETE FROM " . $retrieverTable
                . " WHERE table_name = '$table' and hash IN ('" . implode("', '", $hashes) . "');",
                true,
                true
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

    if (is_sql_local_memory_enabled($table)) {
        if (array_key_exists($table, __SqlDatabaseFields::$sql_local_memory)) {
            if (array_key_exists($hash, __SqlDatabaseFields::$sql_local_memory[$table])
                || memory_get_usage() < __SqlDatabaseFields::SQL_LOCAL_BYTE_LIMIT) {
                __SqlDatabaseFields::$sql_local_memory[$table][$hash] = array($columns, $query, $time);
            }
        } else if (memory_get_usage() < __SqlDatabaseFields::SQL_LOCAL_BYTE_LIMIT) {
            __SqlDatabaseFields::$sql_local_memory[$table] = array($hash => array($columns, $query, $time));
        }
    }
    if (!__SqlDatabaseFields::$sql_global_memory) {
        return false;
    }
    $originalColumns = $columns;

    foreach ($columns as $key => $column) {
        $columns[$key] = array($table, $column, $hash, $time);
    }
    $limit = 15_250;
    $store = @json_encode($query, JSON_UNESCAPED_UNICODE);

    if (is_string($store)
        && strlen($store) <= $limit) {
        $store = properly_sql_encode($store, true);
    } else {
        return false;
    }
    if (strlen($store) <= $limit) {
        load_sql_database(__SqlDatabaseServers::MEMORY);
        $retrieverTable = "memory.queryCacheRetriever";
        $trackerTable = "memory.queryCacheTracker";

        if ($cacheExists) {
            $query = sql_query(
                "UPDATE " . $retrieverTable
                . " SET results = '$store', last_access_time = '$time'"
                . " WHERE table_name = '$table' and hash = '$hash';",
                true,
                true
            );

            if ($query) {
                $query = sql_query(
                    "DELETE FROM " . $trackerTable
                    . " WHERE table_name = '$table' and hash = '$hash';",
                    true,
                    true
                );
            }
        } else {
            $query = sql_query(
                "INSERT INTO " . $retrieverTable
                . " (table_name, hash, results, last_access_time, column_names) "
                . "VALUES ('$table', '$hash', '$store', '$time', '" . @json_encode($originalColumns) . "');",
                true,
                true
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
                true,
                true
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
    try {
        $text = create_sql_connection()?->real_escape_string($partial ? $string : htmlspecialchars($string));

        if ($text == null) {
            $text = $partial ? $string : htmlspecialchars($string);
        }
    } catch (Throwable $e) {
        if (__SqlDatabaseFields::$sql_credentials[0] === __SqlDatabaseFields::MEMORY_HOST_NAME) {
            __SqlDatabaseFields::$sql_global_memory = false;
        } else {
            log_sql_error(null, $e->getMessage(), $e->getTraceAsString());
            $text = $partial ? $string : htmlspecialchars($string);
        }
    }
    return $text;
}

function abstract_search_sql_encode(string $string): string
{
    return str_replace("_", "\_", str_replace(" % ", "\%", $string));
}

// Get

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

    if (is_sql_local_memory_enabled($table)) {
        if (array_key_exists($table, __SqlDatabaseFields::$sql_local_memory)) {
            $value = __SqlDatabaseFields::$sql_local_memory[$table][$hash] ?? null;

            if ($value !== null) {
                $results = $value[1];

                if (is_string($results)) {
                    $results = @json_decode($results, false);
                    $value[0] = $columns;
                    $value[1] = $results;
                    __SqlDatabaseFields::$sql_local_memory[$table][$hash] = $value;
                }
                if (is_array($results)) {
                    $value[2] = time();
                    __SqlDatabaseFields::$sql_local_memory[$table][$hash] = $value;
                    return $results;
                }
            }
        }
    }

    if (__SqlDatabaseFields::$sql_global_memory) {
        load_sql_database(__SqlDatabaseServers::MEMORY);
        $cache = sql_query(
            "SELECT results, last_access_time FROM memory.queryCacheRetriever "
            . "WHERE table_name = '$table' and hash = '$hash' "
            . "LIMIT 1;",
            true,
            true
        );
        load_previous_sql_database();

        if (($cache->num_rows ?? 0) > 0) {
            $row = $cache->fetch_assoc();
            $results = json_decode($row["results"], false);

            if (is_array($results)) {
                if (is_sql_local_memory_enabled($table)) {
                    if (array_key_exists($table, __SqlDatabaseFields::$sql_local_memory)) {
                        if (array_key_exists($hash, __SqlDatabaseFields::$sql_local_memory[$table])
                            || memory_get_usage() < __SqlDatabaseFields::SQL_LOCAL_BYTE_LIMIT) {
                            __SqlDatabaseFields::$sql_local_memory[$table][$hash] = array($columns, $results, $row["last_access_time"]);
                        }
                    } else if (memory_get_usage() < __SqlDatabaseFields::SQL_LOCAL_BYTE_LIMIT) {
                        __SqlDatabaseFields::$sql_local_memory[$table] = array($hash => array($columns, $results, $row["last_access_time"]));
                    }
                }
                return $results;
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

function sql_query(
    string $command,
    bool   $buffer = true,
    bool   $memoryQuery = false,
    bool   $redundant = true
): mysqli_result|bool
{
    $sqlConnection = create_sql_connection(!$redundant);

    try {
        $query = $sqlConnection?->query(
            $command,
            $buffer
                ? MYSQLI_STORE_RESULT
                : MYSQLI_USE_RESULT
        );

        if (!$query) {
            if ($sqlConnection === null) {
                $query = false;
            } else {
                log_sql_error($command, $sqlConnection->error . " (Code: " . $sqlConnection->errno . ")");
            }
        }
    } catch (Throwable $e) {
        if (is_sql_usable()) {
            log_sql_error($command, $e->getMessage(), $e->getTraceAsString());
        }
        $query = false;
    }

    if ($redundant) {
        if (!$query
            && !is_sql_usable()) {
            return sql_query($command, $buffer, $memoryQuery, false);
        }
    } else if (!$query
        && __SqlDatabaseFields::$sql_credentials[0] === __SqlDatabaseFields::MEMORY_HOST_NAME) {
        __SqlDatabaseFields::$sql_global_memory = false;
    }
    return $query;
}

function log_sql_error(?string $query, mixed $error, ?string $exception = null): void
{
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
    if ($exception !== null) {
        error_log($exception);
    } else {
        error_log(@json_encode(debug_backtrace()));
    }
}

// Insert

function sql_insert(string $table, array $pairs): mysqli_result|bool
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
        __SqlDatabaseFields::$sql_last_insert_id = create_sql_connection()?->insert_id;

        if (!is_numeric(__SqlDatabaseFields::$sql_last_insert_id)
            || __SqlDatabaseFields::$sql_last_insert_id == 0) {
            __SqlDatabaseFields::$sql_last_insert_id = null;
        }
        sql_clear_cache($table, array_keys($pairs));
    }
    return $result;
}

function multiple_sql_insert(string $table, array $columns, array $rows): mysqli_result|bool
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
        __SqlDatabaseFields::$sql_last_insert_id = create_sql_connection()?->insert_id;

        if (!is_numeric(__SqlDatabaseFields::$sql_last_insert_id)
            || __SqlDatabaseFields::$sql_last_insert_id == 0) {
            __SqlDatabaseFields::$sql_last_insert_id = null;
        }
        sql_clear_cache($table, $columns);
    }
    return $result;
}

function sql_insert_multiple(string $table, array $columns, array $values): mysqli_result|bool
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

function set_sql_query(string $table, array $what, ?array $where = null, string|array|null $order = null, int $limit = 0): mysqli_result|bool
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

function delete_sql_query(string $table, array $where, string|array|null $order = null, int $limit = 0): mysqli_result|bool
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
    $query = sql_query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA . TABLES WHERE TABLE_SCHEMA = '" . $database . "';");

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
    $query = sql_query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA . SCHEMATA;");

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
        $array = __SqlDatabaseFields::$sql_database_columns_cache[$table] ?? null;

        if (is_array($array)) {
            return $array;
        }
    }
    $array = array();
    $query = sql_query("SHOW COLUMNS FROM " . $table . ";");

    while ($row = $query?->fetch_assoc()) {
        $array[] = $row["Field"];
    }
    __SqlDatabaseFields::$sql_database_columns_cache[$table] = $array;
    return $array;
}