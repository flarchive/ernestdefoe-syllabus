<?php

namespace Ernestdefoe\Syllabus;

use Flarum\Api\Client;
use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Each lesson's "Ask about this lesson" discussion. Started through the
 * forum's own API as the instructor, so every rule applies exactly as if they
 * had started it themselves — their permission to post in the tag, approval,
 * the formatter, every other extension's listeners.
 */
class LessonDiscussions
{
    public function __construct(
        private Client $api,
        private ExtensionManager $extensions,
        private SettingsRepositoryInterface $settings,
        private TranslatorInterface $translator,
        private UrlGenerator $url
    ) {
    }

    public function tagsEnabled(): bool
    {
        return $this->extensions->isEnabled('flarum-tags');
    }

    public function tagFor(Course $course): ?int
    {
        $tag = $course->tag_id ?: (int) $this->settings->get('ernestdefoe-syllabus.discussion_tag');

        return $tag > 0 ? $tag : null;
    }

    /** Start the discussion; returns its id, or null with the reason in $error. */
    public function start(Lesson $lesson, Course $course, User $actor, ?ServerRequestInterface $parent, ?string &$error = null): ?int
    {
        $url = $this->url->to('forum')->route('syllabus.lesson', ['id' => $course->id.'-'.$course->slug, 'lesson' => $lesson->id.'-'.$lesson->slug]);

        $data = [
            'type' => 'discussions',
            'attributes' => [
                'title' => mb_substr($this->translator->trans('ernestdefoe-syllabus.api.discussion_title', ['{course}' => $course->title, '{lesson}' => $lesson->title]), 0, 200),
                'content' => $this->translator->trans('ernestdefoe-syllabus.api.discussion_body', ['{lesson}' => $lesson->title, '{course}' => $course->title, '{url}' => $url]),
            ],
        ];

        $tag = $this->tagFor($course);
        if ($tag && $this->tagsEnabled()) {
            $data['relationships']['tags']['data'] = [['type' => 'tags', 'id' => (string) $tag]];
        }

        $client = $this->api->withActor($actor)->withBody(['data' => $data]);
        if ($parent) {
            $client = $client->withParentRequest($parent);
        }

        try {
            $response = $client->post('/discussions');
        } catch (\Throwable $e) {
            $error = $e->getMessage();

            return null;
        }

        $body = json_decode((string) $response->getBody(), true);
        if ($response->getStatusCode() >= 300 || ! isset($body['data']['id'])) {
            $error = (string) ($body['errors'][0]['detail'] ?? $body['errors'][0]['code'] ?? $response->getStatusCode());

            return null;
        }

        return (int) $body['data']['id'];
    }

    /** "123", "/d/123-slug" or a full link to a discussion the actor can see. */
    public function find(mixed $input, User $actor): ?Discussion
    {
        $input = trim((string) (is_scalar($input) ? $input : ''));
        $id = ctype_digit($input) ? (int) $input : (preg_match('~/d/(\d+)~', $input, $m) ? (int) $m[1] : 0);

        if ($id < 1 || $id > 2147483647) {
            return null;
        }

        return Discussion::query()->whereVisibleTo($actor)->find($id);
    }
}
