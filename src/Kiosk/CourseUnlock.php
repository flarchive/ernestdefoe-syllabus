<?php

namespace Ernestdefoe\Syllabus\Kiosk;

use Ernestdefoe\Kiosk\Unlock\UnlockType;
use Ernestdefoe\Syllabus\Course;
use Ernestdefoe\Syllabus\Progress;
use Flarum\Http\UrlGenerator;
use Flarum\User\User;
use Psr\Log\LoggerInterface;

/**
 * Courses sold through Kiosk. Registered only while Kiosk is enabled (see
 * extend.php), so Syllabus never needs Kiosk installed.
 *
 * Buying enrols the member. Losing access (a lapsed subscription, a refund)
 * keeps their enrolment, progress and certificate: the course's "paid" rule
 * stops letting them into lessons, and buying again picks up where they were.
 */
class CourseUnlock implements UnlockType
{
    public function __construct(private Progress $progress, private UrlGenerator $url, private LoggerInterface $log)
    {
    }

    public function label(): string
    {
        return 'ernestdefoe-syllabus.lib.kiosk.course';
    }

    public function icon(): string
    {
        return 'fas fa-chalkboard-user';
    }

    public function search(User $actor, string $query, int $limit): array
    {
        return Course::query()
            ->when($query !== '', fn ($q) => $q->where('title', 'like', '%'.addcslashes($query, '%_\\').'%'))
            ->orderBy('title')
            ->limit($limit)
            ->get(['id', 'title'])
            ->map(fn (Course $c) => ['id' => (int) $c->id, 'label' => (string) $c->title])
            ->all();
    }

    public function describe(array $ids): array
    {
        return Course::query()->whereIn('id', $ids)->get(['id', 'title', 'slug'])
            ->mapWithKeys(fn (Course $c) => [(int) $c->id => [
                'label' => (string) $c->title,
                'url' => $this->url->to('forum')->route('syllabus.course', ['id' => $c->id.($c->slug ? '-'.$c->slug : '')]),
            ]])
            ->all();
    }

    public function grant(User $user, int $targetId): void
    {
        $course = Course::query()->find($targetId);
        if (! $course) {
            // The course was deleted after the product was set up: nothing to grant.
            $this->log->warning("Syllabus: a Kiosk purchase granted course $targetId, which no longer exists.");

            return;
        }
        $this->progress->enrol($course, $user);
    }

    public function revoke(User $user, int $targetId): void
    {
        // Nothing to take away: access is the "paid" rule asking Kiosk.
    }

    /** Selling a course open to everyone makes it paid; a groups-only course keeps its groups too. */
    public function prepare(int $targetId): void
    {
        Course::query()->whereKey($targetId)->where('access', 'everyone')->update(['access' => 'paid']);
    }
}
