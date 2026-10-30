<?php

namespace Ernestdefoe\Syllabus\Api;

use Ernestdefoe\Syllabus\Access;
use Ernestdefoe\Syllabus\Brainstorm;
use Ernestdefoe\Syllabus\Category;
use Ernestdefoe\Syllabus\Uploads;
use Flarum\Extension\ExtensionManager;
use Flarum\Group\Group;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Exception\PermissionDeniedException;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/syllabus/editor — what the course and lesson editors offer:
 * categories, groups for the access rule, tags for discussions, badges (for
 * managers) and the actor's Brainstorm quizzes. Asked once, when an editor
 * opens.
 */
class EditorMetaController implements RequestHandlerInterface
{
    public function __construct(
        private Brainstorm $brainstorm,
        private ExtensionManager $extensions,
        private SettingsRepositoryInterface $settings,
        private Uploads $uploads
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        if (! Access::canCreate($actor)) {
            throw new PermissionDeniedException();
        }

        $tags = [];
        if ($this->extensions->isEnabled('flarum-tags') && class_exists('Flarum\Tags\Tag')) {
            $tags = \Flarum\Tags\Tag::query()->whereVisibleTo($actor)->orderBy('position')->get(['id', 'name', 'parent_id', 'position'])
                ->map(fn ($tag) => ['id' => $tag->id, 'name' => $tag->name])->values()->all();
        }

        $badges = [];
        $manager = Access::isManager($actor);
        if ($manager && $this->extensions->isEnabled('fof-badges') && class_exists('FoF\Badges\Badge')) {
            $badges = \FoF\Badges\Badge::query()->orderBy('name')->get(['id', 'name', 'icon'])
                ->map(fn ($badge) => ['id' => $badge->id, 'name' => $badge->name, 'icon' => $badge->icon])->values()->all();
        }

        return new JsonResponse(['data' => [
            'categories' => Category::query()->orderBy('position')->orderBy('id')->get()
                ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name])->values()->all(),
            'groups' => Group::query()->where('id', '!=', Group::GUEST_ID)->orderBy('id')->get()
                ->map(fn (Group $g) => ['id' => $g->id, 'name' => $g->name_plural])->values()->all(),
            'tags' => $tags,
            'defaultTagId' => ((int) $this->settings->get('ernestdefoe-syllabus.discussion_tag')) ?: null,
            'badges' => $badges,
            'canFeature' => $manager,
            'canBadge' => $manager && $badges !== [],
            'quizzesEnabled' => $this->brainstorm->enabled(),
            'quizzes' => $this->brainstorm->options($actor),
            'maxVideoMb' => $this->uploads->mb('max_video_mb', 200),
            // "People who buy it" is offered only when Kiosk can sell it.
            'kiosk' => $this->extensions->isEnabled('ernestdefoe-kiosk'),
        ]]);
    }
}
