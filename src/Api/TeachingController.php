<?php

namespace Ernestdefoe\Syllabus\Api;

use Ernestdefoe\Syllabus\Access;
use Ernestdefoe\Syllabus\Course;
use Ernestdefoe\Syllabus\Presenter;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Database\ConnectionInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/syllabus/teaching — the instructor panel's overview: every course
 * the actor teaches (all of them for a manager), drafts included, with its
 * students, completions and assignments waiting. Three grouped queries for
 * the whole list.
 */
class TeachingController implements RequestHandlerInterface
{
    public function __construct(private Presenter $presenter, private ConnectionInterface $db)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        if (! Access::canCreate($actor)) {
            throw new PermissionDeniedException();
        }

        $query = Course::query()->visibleTo($actor)->with(['user', 'category', 'instructors'])->orderByDesc('updated_at')->limit(200);
        if (! Access::isManager($actor)) {
            $query->taughtBy($actor);
        }
        $courses = $query->get()->filter(fn (Course $c) => Access::canEdit($actor, $c))->values();
        $ids = $courses->pluck('id')->all();

        $completed = $pending = [];
        if ($ids) {
            $completed = $this->db->table('syllabus_enrolments')->whereIn('course_id', $ids)->whereNotNull('completed_at')
                ->groupBy('course_id')->selectRaw('course_id, COUNT(*) as aggregate')->pluck('aggregate', 'course_id')->all();
            $pending = $this->db->table('syllabus_submissions')->whereIn('course_id', $ids)->where('status', 'pending')
                ->groupBy('course_id')->selectRaw('course_id, COUNT(*) as aggregate')->pluck('aggregate', 'course_id')->all();
        }

        $data = $this->presenter->courses($courses, $actor);
        foreach ($data as &$card) {
            $card['completedCount'] = (int) ($completed[$card['id']] ?? 0);
            $card['pendingCount'] = (int) ($pending[$card['id']] ?? 0);
        }

        return new JsonResponse(['data' => $data]);
    }
}
