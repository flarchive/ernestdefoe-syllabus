<?php

namespace Ernestdefoe\Syllabus;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;

/**
 * An uploaded lesson video or assignment file (table syllabus_files).
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $course_id
 * @property int|null $lesson_id
 * @property int|null $submission_id
 * @property string $kind
 * @property string $path
 * @property string $name
 * @property string $mime
 * @property int $size
 * @property Carbon $created_at
 */
class Upload extends AbstractModel
{
    protected $table = 'syllabus_files';

    public $timestamps = false;

    protected $casts = [
        'user_id' => 'integer',
        'course_id' => 'integer',
        'lesson_id' => 'integer',
        'submission_id' => 'integer',
        'size' => 'integer',
        'created_at' => 'datetime',
    ];
}
