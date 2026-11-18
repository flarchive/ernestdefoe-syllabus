<?php

namespace Ernestdefoe\Syllabus\Api;

use Carbon\Carbon;
use Ernestdefoe\Syllabus\Access;
use Ernestdefoe\Syllabus\Notification\SubmissionReceivedBlueprint;
use Ernestdefoe\Syllabus\Outline;
use Ernestdefoe\Syllabus\Presenter;
use Ernestdefoe\Syllabus\Submission;
use Ernestdefoe\Syllabus\Upload;
use Ernestdefoe\Syllabus\Uploads;
use Flarum\Http\RequestUtil;
use Flarum\Notification\NotificationSyncer;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /api/syllabus/lessons/{id}/submissions — hand in an assignment: text
 * (`body`) and/or files (`files[]`), as multipart or JSON. One submission
 * waits for review at a time; a passed assignment takes no more. Limited per
 * member per hour.
 */
class SubmitController implements RequestHandlerInterface
{
    use Concerns;

    public const MAX_FILES = 5;
    public const MAX_TEXT = 20000;

    public function __construct(
        private Outline $outline,
        private Presenter $presenter,
        private Uploads $uploads,
        private NotificationSyncer $notifications,
        private SettingsRepositoryInterface $settings
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $lesson = $this->lessonOf($request, $actor);
        $course = $lesson->course;
        $state = $this->outline->build($course, $actor);
        $lesson = $state->lesson($lesson->id) ?? $this->notFound();
        $lesson->setRelation('course', $course);

        if ($lesson->type !== 'assignment' || ! $lesson->isPublished() || ! $state->enrolled() || ! $state->unlocked($lesson)) {
            throw new PermissionDeniedException();
        }

        $previous = Submission::query()->where('lesson_id', $lesson->id)->where('user_id', $actor->id)->whereIn('status', ['pending', 'passed'])->value('status');
        if ($previous) {
            $this->invalid('body', $previous === 'passed' ? 'already_passed' : 'already_waiting');
        }

        $hourly = max(1, (int) ($this->settings->get('ernestdefoe-syllabus.submissions_per_hour') ?: 10));
        if (Submission::query()->where('user_id', $actor->id)->where('created_at', '>', Carbon::now()->subHour())->count() >= $hourly) {
            $this->invalid('body', 'slow_down');
        }

        $parsed = $request->getParsedBody();
        $raw = is_array($parsed) ? ($parsed['body'] ?? ($parsed['data']['body'] ?? '')) : '';
        $text = trim(str_replace("\r\n", "\n", (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', mb_substr(is_string($raw) ? $raw : '', 0, self::MAX_TEXT))));

        $files = $request->getUploadedFiles()['files'] ?? [];
        $files = array_values(array_filter(is_array($files) ? $files : [$files], fn ($f) => $f instanceof UploadedFileInterface && $f->getError() !== UPLOAD_ERR_NO_FILE));
        if (count($files) > self::MAX_FILES) {
            $this->invalid('files', 'too_many_files', ['{max}' => (string) self::MAX_FILES]);
        }
        if ($text === '' && ! $files) {
            $this->invalid('body', 'submission_empty');
        }

        $submission = new Submission();
        $submission->lesson_id = $lesson->id;
        $submission->course_id = $course->id;
        $submission->user_id = $actor->id;
        $submission->body = $text === '' ? null : $text;
        $submission->status = 'pending';
        $submission->save();

        try {
            foreach ($files as $file) {
                $this->uploads->storeSubmissionFile($file, $submission);
            }
        } catch (\Throwable $e) {
            foreach (Upload::query()->where('submission_id', $submission->id)->get() as $upload) {
                $this->uploads->delete($upload);
            }
            $submission->delete();

            throw $e;
        }

        $submission->setRelation('lesson', $lesson);
        $submission->setRelation('course', $course);
        $submission->setRelation('user', $actor);
        $submission->load(['files', 'reviewer']);

        // Every instructor who may review it, except the student themselves.
        $instructors = collect([$course->user, ...$course->instructors->all()])
            ->filter(fn (?User $u) => $u && $u->id !== $actor->id && Access::canEdit($u, $course))
            ->unique('id')->values()->all();
        if ($instructors) {
            $this->notifications->sync(new SubmissionReceivedBlueprint($submission), $instructors);
        }

        return new JsonResponse(['data' => $this->presenter->submission($submission)], 201);
    }
}
