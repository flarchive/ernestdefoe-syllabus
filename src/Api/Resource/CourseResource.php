<?php

namespace Ernestdefoe\Syllabus\Api\Resource;

use Ernestdefoe\Syllabus\Course;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;

/**
 * The course as a notification's subject, and nothing more: no endpoints,
 * just the title and slug a notification needs to say where it points.
 * Everything else Syllabus serves goes through its own controllers.
 *
 * @extends AbstractDatabaseResource<Course>
 */
class CourseResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'syllabus-courses';
    }

    public function model(): string
    {
        return Course::class;
    }

    public function endpoints(): array
    {
        return [];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('title'),
            Schema\Str::make('slug'),
        ];
    }
}
