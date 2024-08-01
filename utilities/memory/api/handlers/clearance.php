<?php
function check_clear_memory(): void
{
    global $memory_clearance_table, $memory_clearance_past, $memory_clearance_row_limit;
    load_sql_database(SqlDatabaseCredentials::MEMORY);
    $memory_trackers_query = get_sql_query(
        $memory_clearance_table,
        array("tracker", "array", "abstract_search"),
        array(
            array("creation", ">", time() - $memory_clearance_past),
        ),
        array(
            "DESC",
            "id"
        ),
        $memory_clearance_row_limit
    );

    if (!empty($memory_trackers_query)) {
        global $memory_clearance_tracking_table;
        $memory_client_identifier = get_server_identifier(true);

        foreach ($memory_trackers_query as $row) {
            $array = @unserialize($row->array);

            if (is_array($array)) {
                if (empty(get_sql_query(
                    $memory_clearance_tracking_table,
                    array("tracker"),
                    array(
                        array("tracker", $row->tracker),
                        array("identifier", $memory_client_identifier)
                    ),
                    null,
                    1
                ))) {
                    sql_insert(
                        $memory_clearance_tracking_table,
                        array(
                            "tracker" => $row->tracker,
                            "identifier" => $memory_client_identifier
                        )
                    );
                    clear_memory($array, $row->abstract_search !== null);
                }
            }
        }
    }
    load_previous_sql_database();
}