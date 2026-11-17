<?php

namespace Ernestdefoe\Syllabus;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Group\Group;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $category_id
 * @property int|null $user_id
 * @property string $title
 * @property string $slug
 * @property string|null $summary
 * @property string|null $description
 * @property string|null $cover_path
 * @property string|null $cover_url
 * @property string|null $difficulty
 * @property int|null $estimated_minutes
 * @property string $status
 * @property string $access
 * @property int $preview_lessons
 * @property bool $sequential
 * @property bool $featured
 * @property bool $notify_new_lessons
 * @property int|null $tag_id
 * @property int|null $badge_id
 * @property int $enrolment_count
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property User|null $user
 * @property Category|null $category
 * @property Collection<int, Group> $groups
 * @property Collection<int, User> $instructors
 * @property Collection<int, Lesson> $lessons
 * @property Collection<int, Section> $sections
 */
class Course extends AbstractModel
{
    public const DIFFICULTIES = ['beginner', 'intermediate', 'advanced'];

    protected $table = 'syllabus_courses';

    public $timestamps = true;

    protected $casts = [
        'category_id' => 'integer',
        'user_id' => 'integer',
        'estimated_minutes' => 'integer',
        'preview_lessons' => 'integer',
        'sequential' => 'boolean',
        'featured' => 'boolean',
        'notify_new_lessons' => 'boolean',
        'tag_id' => 'integer',
        'badge_id' => 'integer',
        'enrolment_count' => 'integer',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsToMany<Group, $this> */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'syllabus_course_groups', 'course_id', 'group_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'syllabus_instructors', 'course_id', 'user_id');
    }

    /** @return HasMany<Lesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<Section, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('position')->orderBy('id');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Published courses, plus the ones this actor teaches or manages.
     *
     * @param Builder<Course> $query
     */
    public function scopeVisibleTo(Builder $query, User $actor): void
    {
        if (! $actor->hasPermission('syllabus.view')) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($actor->hasPermission('syllabus.manage')) {
            return;
        }

        $query->where(function (Builder $q) use ($actor) {
            $q->where('status', 'published');

            if (! $actor->isGuest() && $actor->hasPermission('syllabus.create')) {
                $q->orWhere('user_id', $actor->id)
                    ->orWhereIn('id', fn ($sub) => $sub->select('course_id')->from('syllabus_instructors')->where('user_id', $actor->id));
            }
        });
    }

    /**
     * The courses this actor teaches: their own and the ones they were added
     * to as a co-instructor.
     *
     * @param Builder<Course> $query
     */
    public function scopeTaughtBy(Builder $query, User $actor): void
    {
        $query->where(function (Builder $q) use ($actor) {
            $q->where('user_id', $actor->id)
                ->orWhereIn('id', fn ($sub) => $sub->select('course_id')->from('syllabus_instructors')->where('user_id', $actor->id));
        });
    }
}
