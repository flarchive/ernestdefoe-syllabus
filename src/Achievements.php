<?php

namespace Ernestdefoe\Syllabus;

use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;

/**
 * What a member has earned in Syllabus: their certificates, and the skills
 * each came with. Part of Syllabus's public contract (see the README): other
 * extensions may resolve this class and call forMember(), and the shape it
 * returns is stable. Also served at GET /api/syllabus/users/{id}/achievements.
 *
 * Who sees it: the member and course managers see every certificate. Anyone
 * else sees nothing if the member keeps their courses private or the viewer
 * may not view courses, and otherwise only the certificates of courses they
 * can see themselves (published, open to them), like the profile's Courses
 * tab. Up to the 100 newest.
 */
class Achievements
{
    public function __construct(private UrlGenerator $url, private SettingsRepositoryInterface $settings)
    {
    }

    public const LIMIT = 100;

    public function canSee(User $actor, User $member): bool
    {
        return $this->seesAll($actor, $member)
            || ($actor->hasPermission('syllabus.view') && (bool) $member->getPreference('syllabusShowCourses', true));
    }

    /** The member themselves, and course managers. */
    private function seesAll(User $actor, User $member): bool
    {
        return (! $actor->isGuest() && $actor->id === $member->id) || Access::isManager($actor);
    }

    /**
     * The member's certificates (newest first) and earned skills, or null if
     * the actor may not see them.
     *
     * @return array{certificates: list<array<string, mixed>>, skills: list<array<string, mixed>>}|null
     */
    public function forMember(User $member, User $actor): ?array
    {
        if (! $this->canSee($actor, $member)) {
            return null;
        }

        $all = $this->seesAll($actor, $member);
        $query = Certificate::query()->where('user_id', $member->id)->orderByDesc('issued_at')->orderByDesc('id')->limit(self::LIMIT);
        if (! $all) {
            // Only certificates of courses this viewer could open themselves.
            $query->whereIn('course_id', Course::query()->visibleTo($actor)->where('status', 'published')->select('id'));
        }
        $certificates = $query->get();
        $courseIds = $certificates->pluck('course_id')->filter()->unique()->values()->all();
        $courses = $courseIds ? Course::query()->whereIn('id', $courseIds)->get()->keyBy('id') : collect();
        $visible = $courseIds ? Course::query()->visibleTo($actor)->whereIn('id', $courseIds)->pluck('id')->all() : [];
        $issuer = (string) $this->settings->get('forum_title');

        $out = ['certificates' => [], 'skills' => []];
        foreach ($certificates as $certificate) {
            $course = $courses->get($certificate->course_id);
            // Older certificates (before 1.4) kept no skills: their course's current ones stand in, if the viewer may see it.
            $skills = $certificate->skills ?? ($course && in_array($course->id, $visible, true) ? ($course->skills ?? []) : []);
            $out['certificates'][] = [
                'code' => $certificate->code,
                'courseTitle' => $certificate->course_title,
                'course' => $course && in_array($course->id, $visible, true) ? ['id' => $course->id, 'slug' => $course->slug] : null,
                'issuer' => $issuer,
                'issuedAt' => $certificate->issued_at->toIso8601String(),
                'verifyUrl' => $this->url->to('forum')->route('syllabus.certificate', ['code' => $certificate->code]),
                'skills' => array_values($skills),
            ];
            foreach ($skills as $skill) {
                $out['skills'][] = ['name' => $skill, 'courseTitle' => $certificate->course_title, 'certificateCode' => $certificate->code];
            }
        }

        return $out;
    }
}
