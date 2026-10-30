<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * A certificate of completion. The course title and the member's name are
 * copied in when it is issued, the way an order line copies a product title:
 * renaming or deleting the course later does not rewrite what was awarded.
 * `code` is 128 random bits, hex: the public verification link.
 */
return Migration::createTable('syllabus_certificates', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('course_id')->nullable();
    $table->unsignedInteger('user_id');
    $table->string('code', 64);
    $table->string('course_title', 200);
    $table->string('user_name', 255);
    $table->dateTime('issued_at');

    $table->foreign('course_id')->references('id')->on('syllabus_courses')->nullOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
    $table->unique('code');
    $table->index(['user_id', 'course_id']);
});
