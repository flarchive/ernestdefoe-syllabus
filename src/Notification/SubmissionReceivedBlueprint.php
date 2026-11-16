<?php

namespace Ernestdefoe\Syllabus\Notification;

use Ernestdefoe\Syllabus\Course;
use Ernestdefoe\Syllabus\Submission;
use Flarum\Database\AbstractModel;
use Flarum\Locale\TranslatorInterface;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\User\User;

/** For instructors: "{student} handed in {lesson}". */
class SubmissionReceivedBlueprint implements BlueprintInterface, AlertableInterface, MailableInterface
{
    public function __construct(public Submission $submission)
    {
    }

    public function getFromUser(): ?User
    {
        return $this->submission->user;
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->submission->course;
    }

    public function getData(): array
    {
        return [
            'lessonId' => $this->submission->lesson_id,
            'lessonSlug' => $this->submission->lesson->slug,
            'lessonTitle' => $this->submission->lesson->title,
            'submissionId' => $this->submission->id,
        ];
    }

    public static function getType(): string
    {
        return 'syllabusSubmissionReceived';
    }

    public static function getSubjectModel(): string
    {
        return Course::class;
    }

    public function getEmailViews(): array
    {
        return ['text' => 'ernestdefoe-syllabus::emails.plain.notification', 'html' => 'ernestdefoe-syllabus::emails.html.notification'];
    }

    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('ernestdefoe-syllabus.email.submission.subject', [
            '{student}' => (string) $this->submission->user?->display_name,
            '{lesson}' => $this->submission->lesson->title,
        ]);
    }

    /** @return array{key: string, params: array<string, string>, route: string, routeParams: array<string, string>} */
    public function email(): array
    {
        return [
            'key' => 'ernestdefoe-syllabus.email.submission.body',
            'params' => [
                '{student}' => (string) $this->submission->user?->display_name,
                '{lesson}' => $this->submission->lesson->title,
                '{course}' => $this->submission->course->title,
            ],
            'route' => 'syllabus.teach',
            'routeParams' => [],
        ];
    }
}
