<?php

use Flarum\Database\Migration;

/**
 * The skills a course teaches, as a JSON list of short names. A certificate
 * keeps its own copy (next migration), like the course title, so editing a
 * course never changes what someone already earned.
 */
return Migration::addColumns('syllabus_courses', [
    'skills' => ['text', 'nullable' => true],
]);
