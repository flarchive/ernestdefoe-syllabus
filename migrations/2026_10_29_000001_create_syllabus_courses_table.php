<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * A course. `description` holds the forum formatter's XML, like a post.
 *
 * `access` is "everyone" (anyone who may enrol) or "groups" (only the groups
 * in syllabus_course_groups) — how paid courses work: whatever grants a group
 * on payment unlocks the course. `tag_id` and `badge_id` name a flarum/tags
 * tag and a fof/badges badge without a foreign key: both extensions are
 * optional, and a missing one is simply skipped.
 */
return Migration::createTable('syllabus_courses', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('category_id')->nullable();
    $table->unsignedInteger('user_id')->nullable();
    $table->string('title', 200);
    $table->string('slug', 200);
    $table->string('summary', 500)->nullable();
    $table->mediumText('description')->nullable();
    $table->string('cover_path', 100)->nullable();
    $table->string('cover_url', 500)->nullable();
    // beginner | intermediate | advanced, or null
    $table->string('difficulty', 16)->nullable();
    $table->unsignedSmallInteger('estimated_minutes')->nullable();
    // draft | published
    $table->string('status', 16)->default('draft');
    // everyone | groups
    $table->string('access', 16)->default('everyone');
    $table->unsignedSmallInteger('preview_lessons')->default(0);
    $table->boolean('sequential')->default(false);
    $table->boolean('featured')->default(false);
    $table->boolean('notify_new_lessons')->default(true);
    $table->unsignedInteger('tag_id')->nullable();
    $table->unsignedInteger('badge_id')->nullable();
    $table->unsignedInteger('enrolment_count')->default(0);
    $table->dateTime('published_at')->nullable();
    $table->timestamps();

    $table->foreign('category_id')->references('id')->on('syllabus_categories')->nullOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
    $table->index(['status', 'published_at']);
    $table->index(['status', 'featured']);
    $table->index('category_id');
    $table->index('user_id');
});
