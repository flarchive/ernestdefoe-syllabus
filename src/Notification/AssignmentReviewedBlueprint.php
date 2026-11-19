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

/** "{reviewer} reviewed your assignment {lesson}: passed / needs work." */
class AssignmentReviewedBlueprint implements BlueprintInterface, AlertableInterface, MailableInterface
{
    public function __construct(public Submission $submission, public User $reviewer)
    {
    }

    public function getFromUser(): ?User
    {
        return $this->reviewer;
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
            'status' => $this->submission->status,
            'submissionId' => $this->submission->id,
            'reviewedAt' => $this->submission->reviewed_at?->toIso8601String(),
        ];
    }

    public static function getType(): string
    {
        return 'syllabusAssignmentReviewed';
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
        return $translator->trans('ernestdefoe-syllabus.email.reviewed.subject', ['{lesson}' => $this->submission->lesson->title]);
    }

    /** @return array{key: string, params: array<string, string>, route: string, routeParams: array<string, string>} */
    public function email(): array
    {
        return [
            'key' => 'ernestdefoe-syllabus.email.reviewed.'.($this->submission->status === 'passed' ? 'passed' : 'needs_work'),
            'params' => [
                '{reviewer}' => $this->reviewer->display_name,
                '{lesson}' => $this->submission->lesson->title,
                '{course}' => $this->submission->course->title,
            ],
            'route' => 'syllabus.lesson',
            'routeParams' => [
                'id' => $this->submission->course->id.'-'.$this->submission->course->slug,
                'lesson' => $this->submission->lesson->id.'-'.$this->submission->lesson->slug,
            ],
        ];
    }
}
