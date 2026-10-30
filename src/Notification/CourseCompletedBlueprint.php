<?php

namespace Ernestdefoe\Syllabus\Notification;

use Ernestdefoe\Syllabus\Certificate;
use Ernestdefoe\Syllabus\Course;
use Flarum\Database\AbstractModel;
use Flarum\Locale\TranslatorInterface;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\User\User;

/** "You completed {course} — your certificate is ready." */
class CourseCompletedBlueprint implements BlueprintInterface, AlertableInterface, MailableInterface
{
    public function __construct(public Course $course, public Certificate $certificate)
    {
    }

    public function getFromUser(): ?User
    {
        return null;
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->course;
    }

    public function getData(): array
    {
        return ['code' => $this->certificate->code];
    }

    public static function getType(): string
    {
        return 'syllabusCourseCompleted';
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
        return $translator->trans('ernestdefoe-syllabus.email.completed.subject', ['{course}' => $this->course->title]);
    }

    /** @return array{key: string, params: array<string, string>, route: string, routeParams: array<string, string>} */
    public function email(): array
    {
        return [
            'key' => 'ernestdefoe-syllabus.email.completed.body',
            'params' => ['{course}' => $this->course->title],
            'route' => 'syllabus.certificate',
            'routeParams' => ['code' => $this->certificate->code],
        ];
    }
}
