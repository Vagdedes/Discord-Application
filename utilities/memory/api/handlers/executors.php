<?php

function get_memory_segment_ids(): array
{
    global $memory_array;
    return array_keys($memory_array);
}
