<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * A student's work for an assignment lesson. `body` and `feedback` are plain
 * text, shown as text: nothing here is ever rendered as HTML.
 */
return Migration::createTable('syllabus_submissions', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('lesson_id');
    $table->unsignedInteger('course_id');
    $table->unsignedInteger('user_id');
    $table->text('body')->nullable();
    // pending | passed | needs_work
    $table->string('status', 16)->default('pending');
    $table->text('feedback')->nullable();
    $table->unsignedInteger('reviewer_id')->nullable();
    $table->dateTime('reviewed_at')->nullable();
    $table->timestamps();

    $table->foreign('lesson_id')->references('id')->on('syllabus_lessons')->cascadeOnDelete();
    $table->foreign('course_id')->references('id')->on('syllabus_courses')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
    $table->foreign('reviewer_id')->references('id')->on('users')->nullOnDelete();
    $table->index(['lesson_id', 'user_id']);
    $table->index(['course_id', 'status', 'created_at']);
    $table->index(['user_id', 'created_at']);
});
