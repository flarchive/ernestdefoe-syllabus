<?php

use Flarum\Database\Migration;

/**
 * A course's own certificate design, as JSON: the template, the signer's name
 * and title, and the paths of an uploaded logo, background and signature. Any
 * part left out falls back to the forum's default (see Certificates\Design).
 */
return Migration::addColumns('syllabus_courses', [
    'certificate_design' => ['text', 'nullable' => true],
]);
