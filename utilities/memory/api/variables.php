<?php
$memory_array = array();
$memory_reserved_names = array("cooldowns", "limits", "keyValuePairs");

$memory_clearance_table = "memory.clearMemory";
$memory_clearance_tracking_table = "memory.clearMemoryTracking";
$memory_schedulers_table = "memory.schedulers";
$memory_performance_metrics_table = "memory.performanceMetrics";
$memory_processes_table = "memory.processes";
$memory_segments_table = "memory.memorySegments";

$memory_clearance_past = 60; // 1 minute
$memory_clearance_row_limit = 50;
$memory_process_default_seconds = 60;


