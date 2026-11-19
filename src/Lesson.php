<?php

namespace Ernestdefoe\Syllabus;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $course_id
 * @property int|null $section_id
 * @property string $title
 * @property string $slug
 * @property string $type
 * @property string|null $body
 * @property string|null $video_url
 * @property int|null $video_file_id
 * @property int|null $quiz_id
 * @property int|null $discussion_id
 * @property int|null $duration_minutes
 * @property string $status
 * @property int $position
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Course $course
 * @property Upload|null $videoFile
 * @property Discussion|null $discussion
 */
class Lesson extends AbstractModel
{
    public const TYPES = ['text', 'video', 'assignment', 'quiz'];

    protected $table = 'syllabus_lessons';

    public $timestamps = true;

    protected $casts = [
        'course_id' => 'integer',
        'section_id' => 'integer',
        'video_file_id' => 'integer',
        'quiz_id' => 'integer',
        'discussion_id' => 'integer',
        'duration_minutes' => 'integer',
        'position' => 'integer',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<Upload, $this> */
    public function videoFile(): BelongsTo
    {
        return $this->belongsTo(Upload::class, 'video_file_id');
    }

    /** @return BelongsTo<Discussion, $this> */
    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
