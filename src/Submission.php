<?php

namespace Ernestdefoe\Syllabus;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $lesson_id
 * @property int $course_id
 * @property int $user_id
 * @property string|null $body
 * @property string $status
 * @property string|null $feedback
 * @property int|null $reviewer_id
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Lesson $lesson
 * @property Course $course
 * @property User|null $user
 * @property User|null $reviewer
 * @property Collection<int, Upload> $files
 */
class Submission extends AbstractModel
{
    public const STATUSES = ['pending', 'passed', 'needs_work'];

    protected $table = 'syllabus_submissions';

    public $timestamps = true;

    protected $casts = [
        'lesson_id' => 'integer',
        'course_id' => 'integer',
        'user_id' => 'integer',
        'reviewer_id' => 'integer',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

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

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return HasMany<Upload, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(Upload::class)->orderBy('id');
    }
}
