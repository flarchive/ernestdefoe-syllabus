<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * A lesson: text, video, assignment or quiz. `body` is formatter XML — the
 * text of a text lesson, the notes under a video, an assignment's
 * instructions, or a quiz's introduction. `quiz_id` names a Brainstorm quiz
 * without a foreign key: Brainstorm is optional.
 */
return Migration::createTable('syllabus_lessons', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('course_id');
    $table->unsignedInteger('section_id')->nullable();
    $table->string('title', 200);
    $table->string('slug', 200);
    // text | video | assignment | quiz
    $table->string('type', 16)->default('text');
    $table->mediumText('body')->nullable();
    $table->string('video_url', 500)->nullable();
    $table->unsignedInteger('video_file_id')->nullable();
    $table->unsignedInteger('quiz_id')->nullable();
    $table->unsignedInteger('discussion_id')->nullable();
    $table->unsignedSmallInteger('duration_minutes')->nullable();
    // draft | published
    $table->string('status', 16)->default('draft');
    $table->unsignedInteger('position')->default(0);
    $table->dateTime('published_at')->nullable();
    $table->timestamps();

    $table->foreign('course_id')->references('id')->on('syllabus_courses')->cascadeOnDelete();
    $table->foreign('section_id')->references('id')->on('syllabus_sections')->nullOnDelete();
    $table->foreign('discussion_id')->references('id')->on('discussions')->nullOnDelete();
    $table->index(['course_id', 'status', 'position']);
    $table->index('quiz_id');
});
