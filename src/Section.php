<?php

namespace Ernestdefoe\Syllabus;

use Flarum\Database\AbstractModel;

/**
 * @property int $id
 * @property int $course_id
 * @property string $title
 * @property int $position
 */
class Section extends AbstractModel
{
    protected $table = 'syllabus_sections';

    public $timestamps = false;

    protected $casts = [
        'course_id' => 'integer',
        'position' => 'integer',
    ];
}
