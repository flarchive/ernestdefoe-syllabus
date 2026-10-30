<?php

namespace Ernestdefoe\Syllabus;

use Flarum\Database\AbstractModel;
use Flarum\Formatter\Formatter;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Collection;
use s9e\TextFormatter\Utils;

/**
 * Course descriptions and lesson bodies go through the forum's own formatter,
 * the one posts use: whatever the forum's editor writes (Markdown, BBCode,
 * Scribe's HTML) is parsed on save into s9e XML and rendered to safe HTML on
 * read. Nothing a member typed reaches the page any other way.
 */
class RichText
{
    public function __construct(protected Formatter $formatter)
    {
    }

    /** Parse what was typed into the XML that is stored; null when empty. */
    public function store(string $source, AbstractModel $context, User $actor, int $max = 100000): ?string
    {
        $source = trim(mb_substr($source, 0, $max));

        return $source === '' ? null : $this->formatter->parse($source, $context, $actor);
    }

    /** The stored XML as safe HTML, or null. */
    public function html(?string $stored, AbstractModel $context): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        try {
            return $this->formatter->render($stored, $context);
        } catch (\Throwable) {
            // Never let a formatter hiccup take the page down: show the text.
            return '<p>'.nl2br(e(strip_tags($stored))).'</p>';
        }
    }

    /** What the editor is filled with when the text is edited. */
    public function source(?string $stored, AbstractModel $context): string
    {
        return $stored === null || $stored === '' ? '' : (string) $this->formatter->unparse($stored, $context);
    }

    /**
     * flarum/mentions looks up what a text mentions when it is rendered: one
     * query per text, unless its context already carries them. Rendering a
     * page of course descriptions is many texts, so every mention in all of
     * them is looked up here, once per kind. Nothing is queried when nothing
     * is mentioned.
     *
     * @param iterable<AbstractModel> $contexts
     * @param iterable<string|null> $texts
     */
    public function prime(iterable $contexts, iterable $texts): void
    {
        $kinds = [
            'USERMENTION' => ['mentionsUsers', User::class, []],
            'POSTMENTION' => ['mentionsPosts', 'Flarum\Post\Post', ['user', 'discussion']],
            'GROUPMENTION' => ['mentionsGroups', 'Flarum\Group\Group', []],
            'TAGMENTION' => ['mentionsTags', 'Flarum\Tags\Tag', []],
        ];

        $ids = [];
        foreach ($texts as $xml) {
            if ($xml !== null && str_contains($xml, 'MENTION')) {
                foreach ($kinds as $tag => $kind) {
                    foreach (Utils::getAttributeValues($xml, $tag, 'id') as $id) {
                        $ids[$tag][$id] = true;
                    }
                }
            }
        }

        foreach ($ids as $tag => $found) {
            [$relation, $class, $with] = $kinds[$tag];
            if (! class_exists($class)) {
                continue;
            }

            /** @var Collection<int, AbstractModel> $models */
            $models = $class::query()->with($with)->findMany(array_keys($found));

            foreach ($contexts as $context) {
                $context->setRelation($relation, $models);
            }
        }
    }
}
