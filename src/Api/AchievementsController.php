<?php

namespace Ernestdefoe\Syllabus\Api;

use Ernestdefoe\Syllabus\Achievements;
use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/syllabus/users/{id}/achievements — a member's certificates and the
 * skills they earned with them (see Achievements). A member the viewer can't
 * see is a 404; one whose courses are private to the viewer answers
 * {"data": null, "meta": {"hidden": true}}.
 */
class AchievementsController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(private Achievements $achievements)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $member = User::query()->whereVisibleTo($actor)->findOrFail($this->routeId($request));
        $data = $this->achievements->forMember($member, $actor);

        return new JsonResponse(['data' => $data, 'meta' => ['hidden' => $data === null]]);
    }
}
