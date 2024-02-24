<?php
$memory_array = array();
$memory_reserved_names = array("cooldowns", "limits", "keyValuePairs");

$memory_clearance_table = "memory.clearMemory";
$memory_clearance_tracking_table = "memory.clearMemoryTracking";

$memory_clearance_past = 60; // 1 minute
$memory_clearance_row_limit = 50;


