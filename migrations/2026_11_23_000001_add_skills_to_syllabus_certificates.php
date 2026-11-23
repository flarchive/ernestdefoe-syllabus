<?php

use Flarum\Database\Migration;

/** The skills a certificate was issued with; see the courses migration before this one. */
return Migration::addColumns('syllabus_certificates', [
    'skills' => ['text', 'nullable' => true],
]);
