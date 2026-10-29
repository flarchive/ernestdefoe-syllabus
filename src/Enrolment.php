<?php

namespace Ernestdefoe\Syllabus;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $course_id
 * @property int $user_id
 * @property int|null $last_lesson_id
 * @property Carbon $created_at
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $completed_at
 * @property Course $course
 * @property User $user
 */
class Enrolment extends AbstractModel
{
    protected $table = 'syllabus_enrolments';

    public $timestamps = false;

    protected $casts = [
        'course_id' => 'integer',
        'user_id' => 'integer',
        'last_lesson_id' => 'integer',
        'created_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
