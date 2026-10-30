<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/** Co-instructors a course's owner added. The owner is syllabus_courses.user_id. */
return Migration::createTable('syllabus_instructors', function (Blueprint $table) {
    $table->unsignedInteger('course_id');
    $table->unsignedInteger('user_id');

    $table->primary(['course_id', 'user_id']);
    $table->foreign('course_id')->references('id')->on('syllabus_courses')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
    $table->index('user_id');
});
