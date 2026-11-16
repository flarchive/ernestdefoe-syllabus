<?php

namespace Ernestdefoe\Syllabus\Api;

use Ernestdefoe\Syllabus\Access;
use Ernestdefoe\Syllabus\Course;
use Ernestdefoe\Syllabus\Lesson;
use Flarum\Foundation\ValidationException;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

/** The lookups every controller starts with. */
trait Concerns
{
    /** The id in the route, or 0 (not found) for one too big for an id column. */
    protected function routeId(ServerRequestInterface $request, string $name = 'id'): int
    {
        $id = (string) Arr::get($request->getAttributes(), 'routeParameters.'.$name);

        return ctype_digit($id) && strlen($id) <= 10 && (int) $id <= 2147483647 ? (int) $id : 0;
    }

    /** @return array<string, mixed> */
    protected function body(ServerRequestInterface $request): array
    {
        return (array) Arr::get((array) $request->getParsedBody(), 'data', []);
    }

    /** A course the actor may see, or a 404 — the same answer whether it is missing or hidden. */
    protected function visibleCourse(ServerRequestInterface $request, User $actor, int $id = 0): Course
    {
        return Course::query()->visibleTo($actor)->with(['user', 'category', 'instructors', 'groups'])->findOrFail($id ?: $this->routeId($request));
    }

    protected function editableCourse(ServerRequestInterface $request, User $actor, int $id = 0): Course
    {
        $course = $this->visibleCourse($request, $actor, $id);

        if (! Access::canEdit($actor, $course)) {
            throw new PermissionDeniedException();
        }

        return $course;
    }

    /** A lesson whose course the actor may see; what they may read of it is the Outline's call. */
    protected function lessonOf(ServerRequestInterface $request, User $actor): Lesson
    {
        $lesson = Lesson::query()->findOrFail($this->routeId($request));
        $course = $this->visibleCourse($request, $actor, $lesson->course_id);
        $lesson->setRelation('course', $course);

        return $lesson;
    }

    protected function editableLesson(ServerRequestInterface $request, User $actor): Lesson
    {
        $lesson = $this->lessonOf($request, $actor);

        if (! Access::canEdit($actor, $lesson->course)) {
            throw new PermissionDeniedException();
        }

        return $lesson;
    }

    protected function notFound(): never
    {
        throw new ModelNotFoundException();
    }

    /** @param array<string, string> $params */
    protected function invalid(string $field, string $key, array $params = []): never
    {
        throw new ValidationException([$field => resolve('translator')->trans('ernestdefoe-syllabus.api.'.$key, $params)]);
    }
}
