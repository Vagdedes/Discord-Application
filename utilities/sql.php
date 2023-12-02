<?php
// Connection

$sql_max_cache_time = "30 minutes";

$sql_connections = array();
$sql_credentials = array();

$sql_cache_time = false;
$sql_cache_tag = null;

$is_sql_usable = false;

// Connection
function sql_sql_credentials(string          $hostname,
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
    $sql_credentials[] = string_to_integer(serialize($sql_credentials));
}

function has_sql_credentials(): bool
{
    return !empty($sql_credentials);
}

function get_sql_connection(): ?object
{
    global $sql_connections, $sql_credentials;
    return !empty($sql_credentials) ? $sql_connections[$sql_credentials[10]] : null;
}

function reset_all_sql_connections(): void
{
    global $sql_credentials;

    if (!empty($sql_credentials)) {
        global $sql_connections,
               $sql_cache_time, $sql_cache_tag,
               $is_sql_usable;
        $sql_connections = array();
        $sql_credentials = array();
        $sql_cache_time = false;
        $sql_cache_tag = null;
        $is_sql_usable = false;
    }
}

function create_sql_connection(): ?object
{
    global $sql_credentials, $sql_connections;
    $hash = $sql_credentials[10];
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
}

function close_sql_connection(bool $clear = false): bool
{
    global $sql_credentials;

    if (!empty($sql_credentials)) {
        global $is_sql_usable;

        if ($is_sql_usable) {
            global $sql_connections, $sql_cache_time, $sql_cache_tag;
            $hash = $sql_credentials[10];
            $result = $sql_connections[$hash]->close();
            unset($sql_connections[$hash]);
            $sql_cache_time = false;
            $sql_cache_tag = null;
            $is_sql_usable = false;

            if ($clear) {
                $sql_credentials = array();
            }
            return $result;
        } else {
            global $sql_connections, $sql_cache_time, $sql_cache_tag;
            unset($sql_connections[$sql_credentials[10]]);
            $sql_cache_time = false;
            $sql_cache_tag = null;
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
            if (!empty($single)) {
                $query .= " " . $single . " ";

                if ($buildKey) {
                    $queryKey[] = remove_dates($single);
                }
            }
        } else {
            $equals = sizeof($single) === 2;
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

function get_sql_cache_key(mixed $key, mixed $value): string
{
    return substr(
        serialize(array($key => $value)),
        5,
        -1
    );
}

function set_sql_cache($time = null, mixed $tag = null): void
{
    global $sql_cache_time, $sql_cache_tag, $sql_max_cache_time;
    $sql_cache_time = $time === null ? $sql_max_cache_time : (is_array($time) ? implode(" ", $time) : $time);
    $sql_cache_tag = $tag;
}

// Encoding

function properly_sql_encode(string $string, bool $partial = false, bool $extra = false): ?string
{
    global $is_sql_usable;

    if ($extra) {
        $string = extra_sql_encode($string);
    }
    if (!$is_sql_usable) {
        return $partial ? $string : htmlspecialchars($string);
    } else {
        global $sql_connections, $sql_credentials;
        create_sql_connection();
        return $sql_connections[$sql_credentials[10]]->real_escape_string($partial ? $string : htmlspecialchars($string));
    }
}

function extra_sql_encode(string $string): string
{
    return str_replace("_", "\_", str_replace("%", "\%", $string));
}

function reverse_extra_sql_encode(string $string): string
{
    return str_replace("\_", "_", str_replace("\%", "%", $string));
}

// Get

function get_sql_query(string $table, array $select = null, array $where = null, string|array|null $order = null, int $limit = 0): array
{
    global $sql_cache_time;
    $hasWhere = $where !== null;

    if ($hasWhere) {
        $where = sql_build_where($where, true);
    }
    if ($sql_cache_time !== false) {
        global $sql_cache_tag, $sql_credentials;
        $hasCache = true;
        $time = $sql_cache_time;
        $sql_cache_time = false;
        $cacheKey = array(
            $sql_credentials[10],
            $table,
            $select,
            $hasWhere ? $where[1] : null,
            $order,
            $limit
        );

        if ($sql_cache_tag !== null) {
            $cacheKey[] = $sql_cache_tag;
            $sql_cache_tag = null;
        }
        $cache = get_key_value_pair($cacheKey);

        if (is_array($cache)) {
            return $cache;
        }
    } else {
        $hasCache = false;
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
    if ($hasCache) {
        set_key_value_pair($cacheKey, $array, $time);
    }
    return $array;
}

function sql_query_row(?object $query): ?object
{
    return isset($query->num_rows) && $query->num_rows > 0 ? $query->fetch_assoc() : null;
}

/**
 * @throws Exception
 */
function sql_query(string $command, bool $debug = false)
{
    $sqlConnection = create_sql_connection();
    global $is_sql_usable;

    if ($is_sql_usable) {
        if ($debug && !isset($command[0])) {
            throw new Exception("Empty Query: " . getcwd());
        }
        global $sql_credentials;
        $show_sql_errors = $sql_credentials[9];

        if ($show_sql_errors) {
            $query = $sqlConnection->query($command);
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
            $command = "INSERT INTO logs.sqlErrors (creation, file, query, error) VALUES "
                . "('" . time() . "', '" . properly_sql_encode($_SERVER["SCRIPT_NAME"]) . "', '" . $query . "', '" . properly_sql_encode($sqlConnection->error) . "');";

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
        return $query;
    }
    return null;
}

// Custom

class CustomQuery
{
    private array $fetch;
    public int $num_rows, $fetcher, $fetcherLimit;

    function __construct($fetch, int $count)
    {
        $this->fetch = $fetch;
        $this->num_rows = $count;

        $this->fetcher = -1;
        $this->fetcherLimit = $count - 1;
    }

    function fetch_assoc()
    {
        if ($this->fetcher === $this->fetcherLimit) {
            return false;
        }
        $this->fetcher++;
        return $this->fetch[$this->fetcher];
    }
}

function sql_local_query($command): bool|CustomQuery|null
{
    global $sql_credentials;

    if ($sql_credentials[0] === "127.0.0.1") {
        if (substr($command, 0, 6) === "SELECT") {
            $query = array();
            exec('/usr/bin/mysql --user=' . $sql_credentials[1]
                . ' --password=' . $sql_credentials[2]
                . ' -e "' . htmlspecialchars($command) . '"', $query);
            $count = sizeof($query);

            if ($count > 1) {
                $character = chr(9);
                $keys = explode($character, $query[0]);
                $keyCount = sizeof($keys);
                unset($query[0]);
                $fetch = array();

                foreach ($query as $row) {
                    $columns = array();

                    foreach (explode($character, $row, $keyCount) as $position => $contents) { // add support for dates
                        $columns[$keys[$position]] = ($contents === "NULL" ? null : $contents);
                    }
                    $fetch[] = $columns;
                }
                return new CustomQuery($fetch, $count - 1);
            }
        } else {
            return true;
        }
    }
    return null;
}

// Insert

function sql_insert(string $table, array $pairs)
{
    $columnsArray = "";
    $valuesArray = "";

    foreach ($pairs as $column => $value) {
        $columnsArray .= properly_sql_encode($column) . ", ";
        $valuesArray .= ($value === null ? "NULL, " :
            (is_bool($value) ? ($value ? "'1', " : "NULL, ") :
                "'" . properly_sql_encode($value, true) . "', "));
    }
    $columnsArray = substr($columnsArray, 0, -2);
    $valuesArray = substr($valuesArray, 0, -2);
    $table = properly_sql_encode($table);
    return sql_query("INSERT INTO $table ($columnsArray) VALUES ($valuesArray);");
}

// Set

function set_sql_query(string $table, array $what, array $where = null, string|array|null $order = null, int $limit = 0): bool
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
    if ($where !== null) {
        $query .= " WHERE " . sql_build_where($where);
    }
    if ($order !== null) {
        $query .= " ORDER BY " . sql_build_order($order);
    }
    if ($limit > 0) {
        $query .= " LIMIT " . $limit;
    }
    if (sql_query($query . ";")) {
        global $sql_cache_time;

        if ($sql_cache_time !== false) {
            global $sql_cache_tag;
            $sql_cache_time = null;
            $array = array($sql_cache_tag); // Not needed but will help with speed
            $sql_cache_tag = null;
            clear_memory($array, true);
        }
        return true;
    } else {
        global $sql_cache_time;

        if ($sql_cache_time !== false) {
            global $sql_cache_tag;
            $sql_cache_time = null;
            $sql_cache_tag = null;
        }
        return false;
    }
}

// Delete

function delete_sql_query(string $table, array $where, string|array|null $order = null, int $limit = 0): bool
{
    $query = "DELETE FROM " . $table . " WHERE " . sql_build_where($where);

    if ($order !== null) {
        $query .= " ORDER BY " . sql_build_order($order);
    }
    if ($limit > 0) {
        $query .= " LIMIT " . $limit;
    }
    if (sql_query($query . ";")) {
        global $sql_cache_time;

        if ($sql_cache_time !== false) {
            global $sql_cache_tag;
            $sql_cache_time = null;
            $array = array($sql_cache_tag); // Not needed but will help with speed
            $sql_cache_tag = null;
            clear_memory($array, true);
        }
        return true;
    } else {
        global $sql_cache_time;

        if ($sql_cache_time !== false) {
            global $sql_cache_tag;
            $sql_cache_time = null;
            $sql_cache_tag = null;
        }
        return false;
    }
}

// Et ce tera

function get_sql_overhead_rows(string $table, int $minimum = 1, string $column = "id"): array
{
    $query = sql_query("SELECT " . $column . " FROM " . $table . " ORDER BY " . $column . " ASC;");
    $array = array();

    if (!isset($query->num_rows) || $query->num_rows === 0) {
        for ($i = 1; $i <= $minimum; $i++) {
            $array[] = $i;
        }
        return $array;
    }
    $counter = 1;
    $size = 0;

    while ($row = $query->fetch_assoc()) {
        if ($row[$column] != $counter) {
            $array[] = $counter;
            $size++;

            if ($size == $minimum) {
                return $array;
            }
        }
        $counter++;
    }

    for ($i = $counter; $i <= ($counter + ($minimum - $size)); $i++) {
        $array[] = $i;
    }
    return $array;
}

function get_sql_database_tables(string $database): array
{
    $array = array();
    $query = sql_query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '" . $database . "';");

    if (isset($query->num_rows) && $query->num_rows > 0) {
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

    if (isset($query->num_rows) && $query->num_rows > 0) {
        while ($row = $query->fetch_assoc()) {
            if ($row["SCHEMA_NAME"] !== "information_schema") {
                $array[] = $row["SCHEMA_NAME"];
            }
        }
    }
    return $array;
}
