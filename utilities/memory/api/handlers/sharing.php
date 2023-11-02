<?php
$memory_clearance_table = "memory.clearMemory";

function share_clear_memory(array $keys, bool $abstractSearch): void
{
    global $memory_clearance_table;
    $serialize = serialize($keys);
    $tracker = overflow_long((string_to_integer($serialize, true) * 31) + boolean_to_integer($abstractSearch));
    load_sql_database(SqlDatabaseCredentials::MEMORY);

    if (empty(get_sql_query(
        $memory_clearance_table,
        array("tracker"),
        array(
            array("tracker", $tracker),
        ),
        null,
        1
    ))) {
        if (sql_insert(
            $memory_clearance_table,
            array(
                "tracker" => $tracker,
                "creation" => time(),
                "array" => $serialize,
                "abstract_search" => $abstractSearch
            )
        )) {
            $sql = true;
            $delete = false;
        } else {
            $sql = false;
            // No need to implement delete, never reached
        }
    } else if (set_sql_query(
        $memory_clearance_table,
        array(
            "creation" => time(),
            "array" => $serialize,
            "abstract_search" => $abstractSearch
        ),
        array(
            array("tracker", $tracker),
        ),
        null,
        1
    )) {
        $sql = true;
        $delete = true;
    } else {
        $sql = false;
        // No need to implement delete, never reached
    }

    if ($sql) {
        global $memory_clearance_tracking_table;

        if ($delete) {
            delete_sql_query(
                $memory_clearance_tracking_table,
                array(
                    array("tracker", $tracker),
                ),
                null,
                1
            );
        }
        sql_insert(
            $memory_clearance_tracking_table,
            array(
                "tracker" => $tracker,
                "identifier" => get_server_identifier(true),
            )
        );
    }
    load_previous_sql_database();
}
