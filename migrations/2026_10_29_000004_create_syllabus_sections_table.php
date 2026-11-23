<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * A heading in a course's outline. Sections and lessons share one position
 * sequence per course, so the outline is a single list sorted by position.
 */
return Migration::createTable('syllabus_sections', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('course_id');
    $table->string('title', 200);
    $table->unsignedInteger('position')->default(0);

    $table->foreign('course_id')->references('id')->on('syllabus_courses')->cascadeOnDelete();
    $table->index(['course_id', 'position']);
});
