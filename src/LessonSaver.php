<?php

namespace Ernestdefoe\Syllabus;

use Carbon\Carbon;
use Ernestdefoe\Syllabus\Notification\NewLessonBlueprint;
use Flarum\Foundation\ValidationException;
use Flarum\Notification\NotificationSyncer;
use Flarum\User\User;
use Illuminate\Support\Str;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Creating and updating a lesson. Only the fields sent are changed. */
class LessonSaver
{
    public function __construct(
        private RichText $rich,
        private Brainstorm $brainstorm,
        private LessonDiscussions $discussions,
        private NotificationSyncer $notifications,
        private TranslatorInterface $translator
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array{0: Lesson, 1: string|null} the lesson, and why its discussion could not be started, if it could not
     */
    public function save(Course $course, ?Lesson $lesson, array $data, User $actor, ?ServerRequestInterface $request = null): array
    {
        $isNew = $lesson === null;
        if ($lesson === null) {
            $lesson = new Lesson();
            $lesson->course_id = $course->id;
            $lesson->status = 'draft';
            $lesson->type = 'text';
            $lesson->position = 1 + max(
                (int) Lesson::query()->where('course_id', $course->id)->max('position'),
                (int) Section::query()->where('course_id', $course->id)->max('position')
            );
        }
        $lesson->setRelation('course', $course);
        $has = fn (string $key) => array_key_exists($key, $data);
        $wasPublished = $lesson->published_at !== null;

        if ($isNew || $has('title')) {
            $title = trim(mb_substr((string) ($data['title'] ?? ''), 0, 200));
            if ($title === '') {
                $this->fail('title', 'title_required');
            }
            $lesson->title = $title;
            $lesson->slug = Str::limit(Str::slug($title), 190, '') ?: 'lesson';
        }

        if ($has('type')) {
            $type = in_array($data['type'], Lesson::TYPES, true) ? $data['type'] : 'text';
            if ($type === 'quiz' && ! $this->brainstorm->enabled()) {
                $this->fail('type', 'quiz_needs_brainstorm');
            }
            $lesson->type = $type;
        }

        if ($has('body')) {
            $lesson->body = $this->rich->store((string) $data['body'], $lesson, $actor);
        }

        if ($has('videoUrl')) {
            $url = Video::clean(is_string($data['videoUrl']) ? $data['videoUrl'] : null);
            if ($url !== null && Video::parse($url) === null) {
                $this->fail('videoUrl', 'video_url');
            }
            $lesson->video_url = $url;
        }

        if ($has('quizId')) {
            $quizId = is_numeric($data['quizId']) ? (int) $data['quizId'] : 0;
            // A quiz already in the lesson stays even if this editor could not pick it.
            if ($quizId && $quizId !== $lesson->quiz_id && ! $this->brainstorm->canUse($actor, $quizId)) {
                $this->fail('quizId', 'quiz_not_yours');
            }
            $lesson->quiz_id = $quizId ?: null;
        }
        if ($lesson->type === 'quiz' && ! $lesson->quiz_id) {
            $this->fail('quizId', 'quiz_required');
        }

        if ($has('durationMinutes')) {
            $value = $data['durationMinutes'];
            $lesson->duration_minutes = is_numeric($value) && (int) $value > 0 ? min(1440, (int) $value) : null;
        }

        if ($has('status')) {
            $lesson->status = $data['status'] === 'published' ? 'published' : 'draft';
        }
        $firstPublish = $lesson->status === 'published' && ! $wasPublished;
        if ($firstPublish) {
            $lesson->published_at = Carbon::now();
        }

        $discussionError = null;
        $mode = is_array($data['discussion'] ?? null) ? (string) ($data['discussion']['mode'] ?? '') : '';
        if ($mode === 'none') {
            $lesson->discussion_id = null;
        } elseif ($mode === 'link') {
            $discussion = $this->discussions->find($data['discussion']['link'] ?? '', $actor);
            if (! $discussion) {
                $this->fail('discussion', 'discussion_not_found');
            }
            $lesson->discussion_id = $discussion->id;
        }

        $lesson->save();

        // "Start one for me": now if the lesson is published, else when it is.
        if ($mode === 'auto' && ! $lesson->discussion_id && $lesson->isPublished()) {
            $id = $this->discussions->start($lesson, $course, $actor, $request, $discussionError);
            if ($id) {
                $lesson->discussion_id = $id;
                $lesson->save();
            }
        }

        if ($firstPublish && $course->isPublished() && $course->notify_new_lessons) {
            $this->notifyStudents($lesson, $course, $actor);
        }

        return [$lesson, $discussionError];
    }

    /** Tell enrolled members about a newly published lesson; they can turn this off per type. */
    private function notifyStudents(Lesson $lesson, Course $course, User $actor): void
    {
        // One sync for everyone: the syncer hides this notification from anyone left out of the list.
        $users = User::query()
            ->whereIn('id', Enrolment::query()->where('course_id', $course->id)->where('user_id', '!=', $actor->id)->select('user_id'))
            ->get()->all();

        if ($users) {
            $this->notifications->sync(new NewLessonBlueprint($lesson, $course, $actor), $users);
        }
    }

    /** @param array<string, string> $params */
    private function fail(string $field, string $key, array $params = []): never
    {
        throw new ValidationException([$field => $this->translator->trans('ernestdefoe-syllabus.api.'.$key, $params)]);
    }
}
