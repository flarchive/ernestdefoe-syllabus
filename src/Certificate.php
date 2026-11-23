<?php

namespace Ernestdefoe\Syllabus;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $course_id
 * @property int $user_id
 * @property string $code
 * @property string $course_title
 * @property string $user_name
 * @property Carbon $issued_at
 * @property list<string>|null $skills the course's skills on the day it was issued
 * @property Course|null $course
 * @property User|null $user
 */
class Certificate extends AbstractModel
{
    protected $table = 'syllabus_certificates';

    public $timestamps = false;

    protected $casts = [
        'skills' => 'array',
        'course_id' => 'integer',
        'user_id' => 'integer',
        'issued_at' => 'datetime',
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

    /** 128 random bits, as 32 hex characters. */
    public static function newCode(): string
    {
        return bin2hex(random_bytes(16));
    }
}
