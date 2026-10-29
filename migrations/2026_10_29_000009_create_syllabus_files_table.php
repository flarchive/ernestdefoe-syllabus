<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Uploaded lesson videos and assignment files. They live outside the public
 * folder and are only ever served through Syllabus, after an access check,
 * as downloads (Content-Disposition: attachment) under a random stored name.
 */
return Migration::createTable('syllabus_files', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('user_id')->nullable();
    $table->unsignedInteger('course_id');
    $table->unsignedInteger('lesson_id')->nullable();
    $table->unsignedInteger('submission_id')->nullable();
    // video | submission
    $table->string('kind', 16);
    $table->string('path', 100);
    $table->string('name', 255);
    $table->string('mime', 100);
    $table->unsignedBigInteger('size');
    $table->dateTime('created_at');

    $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
    $table->foreign('course_id')->references('id')->on('syllabus_courses')->cascadeOnDelete();
    $table->foreign('lesson_id')->references('id')->on('syllabus_lessons')->cascadeOnDelete();
    $table->foreign('submission_id')->references('id')->on('syllabus_submissions')->cascadeOnDelete();
    $table->index('submission_id');
    $table->index('lesson_id');
});
