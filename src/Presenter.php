<?php

namespace Ernestdefoe\Syllabus;

use Flarum\Http\SlugManager;
use Flarum\Http\UrlGenerator;
use Flarum\User\User;
use Illuminate\Support\Collection;

/**
 * Everything Syllabus sends to a browser is built here, field by field.
 *
 * 🚨 A lesson's content — its body, its video, its quiz, its assignment — is
 * added only when {@see CourseState::unlocked()} says the reader may have it.
 * The outline carries titles and states, never content.
 */
class Presenter
{
    public function __construct(
        private RichText $rich,
        private SlugManager $slugs,
        private Outline $outline,
        private Uploads $uploads,
        private Brainstorm $brainstorm,
        private UrlGenerator $url
    ) {
    }

    /** @return array{id: int, username: string, displayName: string, avatarUrl: string|null, slug: string}|null */
    public function person(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'username' => $user->username,
            'displayName' => $user->display_name,
            'avatarUrl' => $user->avatar_url,
            'slug' => $this->slugs->forResource(User::class)->toSlug($user),
        ];
    }

    /**
     * Course cards with the actor's own progress. A fixed number of queries
     * whatever the count: enrolments, lesson totals and completed lessons are
     * each one grouped query for the whole list.
     *
     * @param Collection<int, Course> $courses with `user` and `category` loaded
     * @return list<array<string, mixed>>
     */
    public function courses(Collection $courses, User $actor): array
    {
        $ids = $courses->pluck('id')->all();
        $totals = $this->outline->totals($ids);
        $enrolments = $done = [];

        if ($ids && ! $actor->isGuest()) {
            $enrolments = Enrolment::query()->where('user_id', $actor->id)->whereIn('course_id', $ids)->get()->keyBy('course_id')->all();
            if ($enrolments) {
                $done = $this->outline->done(array_keys($enrolments), $actor->id);
            }
        }

        return $courses->map(fn (Course $course) => $this->card($course, $actor, $totals[$course->id] ?? 0, $enrolments[$course->id] ?? null, $done[$course->id] ?? 0))->values()->all();
    }

    /** @return array<string, mixed> */
    public function card(Course $course, User $actor, int $total, ?Enrolment $enrolment, int $done): array
    {
        return [
            'id' => $course->id,
            'slug' => $course->slug,
            'title' => $course->title,
            'summary' => $course->summary,
            'coverUrl' => $this->uploads->coverUrl($course),
            'difficulty' => $course->difficulty,
            'estimatedMinutes' => $course->estimated_minutes,
            'status' => $course->status,
            'featured' => $course->featured,
            'access' => $course->access,
            'category' => $course->category ? ['id' => $course->category->id, 'name' => $course->category->name, 'slug' => $course->category->slug] : null,
            'instructor' => $this->person($course->user),
            'lessonCount' => $total,
            'enrolmentCount' => $course->enrolment_count,
            'publishedAt' => $course->published_at?->toIso8601String(),
            'canEdit' => Access::canEdit($actor, $course),
            'me' => $enrolment ? [
                'enrolled' => true,
                'done' => $done,
                'percent' => $total > 0 ? (int) min(100, floor($done * 100 / $total)) : 0,
                'completedAt' => $enrolment->completed_at?->toIso8601String(),
                'lastLessonId' => $enrolment->last_lesson_id,
                'lastSeenAt' => $enrolment->last_seen_at?->toIso8601String(),
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    public function course(CourseState $state, User $actor): array
    {
        $course = $state->course;
        $out = $this->card($course, $actor, $state->total, $state->enrolment, $state->done);
        $this->rich->prime([$course], [$course->description]);

        $certificate = $state->enrolment?->completed_at
            ? Certificate::query()->where('course_id', $course->id)->where('user_id', $actor->id)->value('code')
            : null;
        $next = $state->nextLesson();

        return $out + [
            'descriptionHtml' => $this->rich->html($course->description, $course),
            'sequential' => $course->sequential,
            'previewLessons' => $course->preview_lessons,
            'instructors' => array_values(array_filter([$this->person($course->user), ...$course->instructors->map(fn (User $u) => $this->person($u))->all()])),
            'groups' => $course->access === 'groups' ? $course->groups->map(fn ($g) => ['id' => $g->id, 'name' => $g->name_plural])->values()->all() : [],
            'canEnrol' => $state->canEnrol,
            'meetsRule' => $state->meetsRule || $state->editor,
            'canReview' => $state->editor,
            'outline' => $this->outlineItems($state),
            'certificateCode' => $certificate,
            'nextLesson' => $next ? $this->ref($next) : null,
        ];
    }

    /**
     * Sections and lessons in one list, sorted by their shared positions.
     * Titles and states only.
     *
     * @return list<array<string, mixed>>
     */
    public function outlineItems(CourseState $state): array
    {
        $items = [];
        foreach ($state->sections as $section) {
            $items[] = ['sort' => [$section->position, 0, $section->id], 'item' => ['kind' => 'section', 'id' => $section->id, 'title' => $section->title]];
        }
        foreach ($state->lessons as $lesson) {
            $items[] = ['sort' => [$lesson->position, 1, $lesson->id], 'item' => [
                'kind' => 'lesson',
                'id' => $lesson->id,
                'slug' => $lesson->slug,
                'title' => $lesson->title,
                'type' => $lesson->type,
                'status' => $lesson->status,
                'durationMinutes' => $lesson->duration_minutes,
                'completed' => isset($state->completed[$lesson->id]),
                'locked' => $state->lockReason($lesson),
                'preview' => isset($state->previews[$lesson->id]),
            ]];
        }

        usort($items, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        return array_map(fn ($i) => $i['item'], $items);
    }

    /** @return array{id: int, slug: string, title: string} */
    public function ref(Lesson $lesson): array
    {
        return ['id' => $lesson->id, 'slug' => $lesson->slug, 'title' => $lesson->title];
    }

    /** @return array<string, mixed> */
    public function editable(Course $course): array
    {
        return [
            'title' => $course->title,
            'summary' => (string) $course->summary,
            'description' => $this->rich->source($course->description, $course),
            'coverUrl' => $course->cover_path ? null : $course->cover_url,
            'hasUploadedCover' => (bool) $course->cover_path,
            'categoryId' => $course->category_id,
            'difficulty' => $course->difficulty,
            'estimatedMinutes' => $course->estimated_minutes,
            'status' => $course->status,
            'access' => $course->access,
            'groupIds' => $course->groups->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'previewLessons' => $course->preview_lessons,
            'sequential' => $course->sequential,
            'featured' => $course->featured,
            'notifyNewLessons' => $course->notify_new_lessons,
            'tagId' => $course->tag_id,
            'badgeId' => $course->badge_id,
            'instructors' => $course->instructors->map(fn (User $u) => $this->person($u))->values()->all(),
        ];
    }

    /**
     * One lesson. Its content only when the reader may have it.
     *
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public function lesson(Lesson $lesson, CourseState $state, User $actor, array $extra = []): array
    {
        $course = $state->course;
        $unlocked = $state->unlocked($lesson);
        $prev = $state->neighbour($lesson, -1);
        $next = $state->neighbour($lesson, 1);

        $out = [
            'id' => $lesson->id,
            'slug' => $lesson->slug,
            'title' => $lesson->title,
            'type' => $lesson->type,
            'status' => $lesson->status,
            'durationMinutes' => $lesson->duration_minutes,
            'course' => ['id' => $course->id, 'slug' => $course->slug, 'title' => $course->title, 'sequential' => $course->sequential],
            'locked' => $state->lockReason($lesson),
            'preview' => isset($state->previews[$lesson->id]),
            'completed' => isset($state->completed[$lesson->id]),
            'enrolled' => $state->enrolled(),
            'canEnrol' => $state->canEnrol,
            'canEdit' => $state->editor,
            'canComplete' => $unlocked && $state->enrolled() && in_array($lesson->type, ['text', 'video'], true) && $lesson->isPublished(),
            'progress' => ['done' => $state->done, 'total' => $state->total, 'percent' => $state->percent()],
            'prev' => $prev ? $this->ref($prev) : null,
            'next' => $next ? $this->ref($next) : null,
            'discussion' => null,
            'outline' => $this->outlineItems($state),
        ];

        if (! $unlocked) {
            return $out + $extra;
        }

        $out['bodyHtml'] = $this->rich->html($lesson->body, $lesson);

        if ($lesson->discussion && $lesson->discussion->id) {
            $out['discussion'] = [
                'id' => $lesson->discussion->id,
                'slug' => $lesson->discussion->slug,
                'title' => $lesson->discussion->title,
                'commentCount' => (int) $lesson->discussion->comment_count,
            ];
        }

        if ($lesson->type === 'video') {
            $video = Video::parse($lesson->video_url);
            if ($lesson->videoFile) {
                $out['video'] = ['provider' => 'file', 'fileUrl' => $this->fileUrl($lesson->videoFile), 'mime' => $lesson->videoFile->mime];
            } elseif ($video) {
                $out['video'] = ['provider' => $video['provider'], 'embedUrl' => Video::embedUrl($video)];
            } else {
                $out['video'] = null;
            }
        }

        if ($lesson->type === 'quiz' && $lesson->quiz_id) {
            $quiz = $this->brainstorm->quizzes([$lesson->quiz_id])[$lesson->quiz_id] ?? null;
            $out['quiz'] = $quiz ? [
                'id' => $quiz['id'],
                'slug' => $quiz['slug'],
                'title' => $quiz['title'],
                'published' => $quiz['published'],
                'url' => $this->url->to('forum')->route('brainstorm.quiz', ['id' => $quiz['id'].'-'.$quiz['slug']]),
            ] : null;
        }

        return $out + $extra;
    }

    /** @return array<string, mixed> */
    public function lessonEditable(Lesson $lesson): array
    {
        return [
            'title' => $lesson->title,
            'type' => $lesson->type,
            'body' => $this->rich->source($lesson->body, $lesson),
            'videoUrl' => (string) $lesson->video_url,
            'videoFile' => $lesson->videoFile ? ['id' => $lesson->videoFile->id, 'name' => $lesson->videoFile->name, 'size' => $lesson->videoFile->size] : null,
            'quizId' => $lesson->quiz_id,
            'status' => $lesson->status,
            'durationMinutes' => $lesson->duration_minutes,
            'discussionId' => $lesson->discussion_id,
        ];
    }

    /** @return array<string, mixed> */
    public function submission(Submission $submission, bool $withLesson = false): array
    {
        $out = [
            'id' => $submission->id,
            'lessonId' => $submission->lesson_id,
            'courseId' => $submission->course_id,
            'user' => $this->person($submission->user),
            // Plain text, shown as text by the browser: never HTML.
            'body' => (string) $submission->body,
            'status' => $submission->status,
            'feedback' => $submission->feedback,
            'reviewer' => $this->person($submission->reviewer),
            'reviewedAt' => $submission->reviewed_at?->toIso8601String(),
            'createdAt' => $submission->created_at?->toIso8601String(),
            'files' => $submission->files->map(fn (Upload $f) => ['id' => $f->id, 'name' => $f->name, 'size' => $f->size, 'url' => $this->fileUrl($f)])->values()->all(),
        ];

        if ($withLesson) {
            $out['lesson'] = $this->ref($submission->lesson);
            $out['course'] = ['id' => $submission->course->id, 'slug' => $submission->course->slug, 'title' => $submission->course->title];
        }

        return $out;
    }

    public function fileUrl(Upload $upload): string
    {
        return $this->url->to('api')->route('syllabus.files.show', ['id' => $upload->id]);
    }
}
