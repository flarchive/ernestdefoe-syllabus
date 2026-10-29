<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/** One row per lesson a member has completed. */
return Migration::createTable('syllabus_progress', function (Blueprint $table) {
    $table->unsignedInteger('lesson_id');
    $table->unsignedInteger('user_id');
    $table->unsignedInteger('course_id');
    $table->dateTime('completed_at');

    $table->primary(['lesson_id', 'user_id']);
    $table->foreign('lesson_id')->references('id')->on('syllabus_lessons')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
    $table->foreign('course_id')->references('id')->on('syllabus_courses')->cascadeOnDelete();
    $table->index(['user_id', 'course_id']);
});
