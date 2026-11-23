<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/** The groups a "groups" course is open to. */
return Migration::createTable('syllabus_course_groups', function (Blueprint $table) {
    $table->unsignedInteger('course_id');
    $table->unsignedInteger('group_id');

    $table->primary(['course_id', 'group_id']);
    $table->foreign('course_id')->references('id')->on('syllabus_courses')->cascadeOnDelete();
    $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
});
