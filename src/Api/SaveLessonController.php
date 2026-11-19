<?php

namespace Ernestdefoe\Syllabus\Api;

use Ernestdefoe\Syllabus\LessonSaver;
use Ernestdefoe\Syllabus\Outline;
use Ernestdefoe\Syllabus\Presenter;
use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** POST /api/syllabus/courses/{id}/lessons (create) and PATCH /api/syllabus/lessons/{id} (update). */
class SaveLessonController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(private LessonSaver $saver, private Presenter $presenter, private Outline $outline)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $creating = $request->getMethod() === 'POST';

        if ($creating) {
            $course = $this->editableCourse($request, $actor);
            $lesson = null;
        } else {
            $lesson = $this->editableLesson($request, $actor);
            $course = $lesson->course;
        }

        [$lesson, $discussionError] = $this->saver->save($course, $lesson, $this->body($request), $actor, $request);

        $course->unsetRelation('lessons');
        $state = $this->outline->build($course, $actor);
        $lesson->setRelation('discussion', $lesson->discussion_id ? Discussion::query()->whereVisibleTo($actor)->find($lesson->discussion_id) : null);
        $lesson->load('videoFile');

        $data = $this->presenter->lesson($state->lesson($lesson->id) ?? $lesson, $state, $actor);
        $data['edit'] = $this->presenter->lessonEditable($lesson);
        $data['discussionError'] = $discussionError;

        return new JsonResponse(['data' => $data], $creating ? 201 : 200);
    }
}
