<?php

namespace Ernestdefoe\Syllabus;

use ErnestDefoe\Brainstorm\Access as QuizAccess;
use ErnestDefoe\Brainstorm\Quiz;
use Flarum\Extension\ExtensionManager;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;

/**
 * Everything Syllabus knows about Brainstorm, an optional extension. Quiz
 * lessons exist only while Brainstorm is enabled; Syllabus reads its quizzes
 * through Brainstorm's own model and access rules and its `quiz_bests` record
 * of who passed what, and never writes to either.
 */
class Brainstorm
{
    private ?bool $enabled = null;

    public function __construct(private ExtensionManager $extensions, private ConnectionInterface $db)
    {
    }

    public function enabled(): bool
    {
        return $this->enabled ??= $this->extensions->isEnabled('ernestdefoe-brainstorm') && class_exists(Quiz::class);
    }

    /** A quiz this actor may put in a lesson: one they may edit in Brainstorm. */
    public function canUse(User $actor, int $quizId): bool
    {
        if (! $this->enabled() || $quizId < 1) {
            return false;
        }

        $quiz = Quiz::query()->find($quizId);

        return $quiz instanceof Quiz && QuizAccess::canEdit($actor, $quiz);
    }

    /**
     * The quizzes an instructor may pick from, newest first.
     *
     * @return list<array{id: int, title: string, status: string}>
     */
    public function options(User $actor): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $query = Quiz::query()->visibleTo($actor);
        if (! $actor->hasPermission('brainstorm.manage')) {
            $query->where('user_id', $actor->id);
        }

        return $query->orderByDesc('id')->limit(200)->get(['id', 'title', 'status', 'user_id'])
            ->filter(fn (Quiz $quiz) => QuizAccess::canEdit($actor, $quiz))
            ->map(fn (Quiz $quiz) => ['id' => $quiz->id, 'title' => $quiz->title, 'status' => $quiz->status])
            ->values()->all();
    }

    /**
     * id => {id, slug, title} for the quizzes named, in one query.
     *
     * @param list<int> $ids
     * @return array<int, array{id: int, slug: string, title: string, published: bool}>
     */
    public function quizzes(array $ids): array
    {
        if (! $this->enabled() || ! $ids) {
            return [];
        }

        $out = [];
        foreach (Quiz::query()->whereIn('id', $ids)->get(['id', 'slug', 'title', 'status']) as $quiz) {
            $out[$quiz->id] = ['id' => $quiz->id, 'slug' => $quiz->slug, 'title' => $quiz->title, 'published' => $quiz->isPublished()];
        }

        return $out;
    }

    /**
     * Which of these quizzes the member has passed, from Brainstorm's one
     * best-result row per member and quiz.
     *
     * @param list<int> $quizIds
     * @return list<int>
     */
    public function passed(int $userId, array $quizIds): array
    {
        if (! $this->enabled() || ! $quizIds) {
            return [];
        }

        return $this->db->table('quiz_bests')
            ->where('user_id', $userId)
            ->whereIn('quiz_id', $quizIds)
            ->where('passed', true)
            ->pluck('quiz_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
