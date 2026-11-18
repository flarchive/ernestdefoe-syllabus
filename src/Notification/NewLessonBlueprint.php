<?php

namespace Ernestdefoe\Syllabus\Notification;

use Ernestdefoe\Syllabus\Course;
use Ernestdefoe\Syllabus\Lesson;
use Flarum\Database\AbstractModel;
use Flarum\Locale\TranslatorInterface;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\User\User;

/** For enrolled members: "New lesson in {course}: {lesson}". */
class NewLessonBlueprint implements BlueprintInterface, AlertableInterface, MailableInterface
{
    public function __construct(public Lesson $lesson, public Course $course, public ?User $author = null)
    {
    }

    public function getFromUser(): ?User
    {
        return $this->author;
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->course;
    }

    public function getData(): array
    {
        return ['lessonId' => $this->lesson->id, 'lessonSlug' => $this->lesson->slug, 'lessonTitle' => $this->lesson->title];
    }

    public static function getType(): string
    {
        return 'syllabusNewLesson';
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
        return $translator->trans('ernestdefoe-syllabus.email.new_lesson.subject', ['{course}' => $this->course->title]);
    }

    /** @return array{key: string, params: array<string, string>, route: string, routeParams: array<string, string>} */
    public function email(): array
    {
        return [
            'key' => 'ernestdefoe-syllabus.email.new_lesson.body',
            'params' => ['{lesson}' => $this->lesson->title, '{course}' => $this->course->title],
            'route' => 'syllabus.lesson',
            'routeParams' => ['id' => $this->course->id.'-'.$this->course->slug, 'lesson' => $this->lesson->id.'-'.$this->lesson->slug],
        ];
    }
}
