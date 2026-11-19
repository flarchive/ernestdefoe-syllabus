<?php

namespace Ernestdefoe\Syllabus\Api;

use Ernestdefoe\Syllabus\Access;
use Ernestdefoe\Syllabus\Brainstorm;
use Ernestdefoe\Syllabus\Uploads;
use Flarum\Api\Context;
use Flarum\Api\Schema;

/**
 * Syllabus's fields on the forum resource. Permissions the actor's groups
 * were already loaded for, and settings already in memory: no query.
 */
class ForumFields
{
    public function __construct(private Brainstorm $brainstorm, private Uploads $uploads)
    {
    }

    /** @return list<Schema\Attribute> */
    public function __invoke(): array
    {
        $member = fn ($forum, Context $context) => ! $context->getActor()->isGuest();

        return [
            Schema\Boolean::make('canViewCourses')
                ->get(fn ($forum, Context $context) => $context->getActor()->hasPermission('syllabus.view')),
            Schema\Boolean::make('canCreateCourses')
                ->get(fn ($forum, Context $context) => Access::canCreate($context->getActor())),
            Schema\Boolean::make('syllabusQuizzes')
                ->get(fn () => $this->brainstorm->enabled())
                ->visible(fn ($forum, Context $context) => Access::canCreate($context->getActor())),
            Schema\Str::make('syllabusFileTypes')
                ->get(fn () => implode(',', $this->uploads->fileTypes()))
                ->visible($member),
            Schema\Integer::make('syllabusMaxFileMb')
                ->get(fn () => $this->uploads->mb('max_file_mb'))
                ->visible($member),
        ];
    }
}
