<?php

namespace Ernestdefoe\Syllabus\Kiosk;

use Carbon\Carbon;
use Ernestdefoe\Kiosk\Unlock\UnlockType;
use Ernestdefoe\Syllabus\Course;
use Flarum\Http\UrlGenerator;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;

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
    public function __construct(private ConnectionInterface $db, private UrlGenerator $url)
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
        $added = $this->db->table('syllabus_enrolments')->insertOrIgnore([
            'course_id' => $targetId,
            'user_id' => $user->id,
            'created_at' => Carbon::now(),
            'last_seen_at' => Carbon::now(),
        ]);

        if ($added) {
            Course::query()->whereKey($targetId)->increment('enrolment_count');
        }
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
