<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Who is taking which course. `completed_at` is a dated fact, set once: a
 * lesson added later changes how far through someone is, never whether they
 * finished.
 */
return Migration::createTable('syllabus_enrolments', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('course_id');
    $table->unsignedInteger('user_id');
    $table->unsignedInteger('last_lesson_id')->nullable();
    $table->dateTime('created_at');
    $table->dateTime('last_seen_at')->nullable();
    $table->dateTime('completed_at')->nullable();

    $table->foreign('course_id')->references('id')->on('syllabus_courses')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
    $table->unique(['course_id', 'user_id']);
    $table->index(['user_id', 'last_seen_at']);
    $table->index(['course_id', 'completed_at']);
});
