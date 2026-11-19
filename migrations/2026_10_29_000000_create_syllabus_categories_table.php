<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/** Course categories, in the order an admin dragged them into. */
return Migration::createTable('syllabus_categories', function (Blueprint $table) {
    $table->increments('id');
    $table->string('name', 100);
    $table->string('slug', 100);
    $table->string('description', 500)->nullable();
    $table->unsignedSmallInteger('position')->default(0);
    $table->dateTime('created_at')->nullable();

    $table->index('position');
});
