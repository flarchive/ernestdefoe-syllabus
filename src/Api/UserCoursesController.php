<?php

namespace Ernestdefoe\Syllabus\Api;

use Ernestdefoe\Syllabus\Certificate;
use Ernestdefoe\Syllabus\Course;
use Ernestdefoe\Syllabus\Enrolment;
use Ernestdefoe\Syllabus\Presenter;
use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/syllabus/users/{id}/courses — the profile's Courses tab: what a
 * member is taking and has completed, with certificates. Members can hide it
 * from everyone but themselves ("Show my courses on my profile"). Only
 * published courses the viewer may see are listed.
 */
class UserCoursesController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(private Presenter $presenter)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $user = User::query()->whereVisibleTo($actor)->findOrFail($this->routeId($request));
        $own = $actor->id === $user->id;

        if (! $own && ! $user->getPreference('syllabusShowCourses', true) && ! $actor->hasPermission('syllabus.manage')) {
            return new JsonResponse(['data' => [], 'meta' => ['hidden' => true]]);
        }

        $courseIds = Enrolment::query()->where('user_id', $user->id)->orderByRaw('completed_at IS NULL DESC')->orderByDesc('last_seen_at')->limit(100)->pluck('course_id')->all();
        $courses = Course::query()->visibleTo($actor)->where('status', 'published')->whereIn('id', $courseIds ?: [0])->with(['user', 'category'])->get()
            ->sortBy(fn (Course $c) => array_search($c->id, $courseIds, true))->values();

        // The cards carry the profile owner's progress, not the viewer's.
        $data = $this->presenter->courses($courses, $user);
        $codes = Certificate::query()->where('user_id', $user->id)->whereIn('course_id', $courses->pluck('id')->all() ?: [0])->pluck('code', 'course_id')->all();
        foreach ($data as &$card) {
            $card['certificateCode'] = $own || $user->getPreference('syllabusShowCourses', true) ? ($codes[$card['id']] ?? null) : null;
            $card['canEdit'] = false;
        }

        return new JsonResponse(['data' => $data, 'meta' => ['hidden' => false]]);
    }
}
