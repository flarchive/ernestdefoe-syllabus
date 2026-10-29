<?php

namespace Ernestdefoe\Syllabus;

use Carbon\Carbon;
use Flarum\Extension\ExtensionManager;
use Flarum\Foundation\ValidationException;
use Flarum\Group\Group;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Creating and updating a course from the editor's fields. Only the fields sent are changed. */
class CourseSaver
{
    public function __construct(
        private RichText $rich,
        private ConnectionInterface $db,
        private ExtensionManager $extensions,
        private TranslatorInterface $translator,
        private Uploads $uploads
    ) {
    }

    /** @param array<string, mixed> $data */
    public function save(?Course $course, array $data, User $actor): Course
    {
        $isNew = $course === null;
        $course ??= new Course();
        $has = fn (string $key) => array_key_exists($key, $data);

        if ($isNew || $has('title')) {
            $title = trim(mb_substr((string) ($data['title'] ?? ''), 0, 200));
            if ($title === '') {
                $this->fail('title', 'title_required');
            }
            $course->title = $title;
            $course->slug = Str::limit(Str::slug($title), 190, '') ?: 'course';
        }

        if ($isNew) {
            $course->user_id = $actor->id;
            $course->status = 'draft';
            $course->access = 'everyone';
        }

        if ($has('summary')) {
            $summary = trim(mb_substr((string) $data['summary'], 0, 500));
            $course->summary = $summary === '' ? null : $summary;
        }

        if ($has('coverUrl')) {
            $raw = Video::clean(is_string($data['coverUrl']) ? $data['coverUrl'] : null);
            $url = Video::safeUrl($raw);
            if ($raw !== null && ($url === null || ! str_starts_with(strtolower($url), 'https://'))) {
                $this->fail('coverUrl', 'cover_url');
            }
            $course->cover_url = $url;
            // An address replaces an uploaded cover.
            if ($url !== null && $course->cover_path) {
                $oldCover = $course->cover_path;
                $course->cover_path = null;
            }
        }

        if ($has('categoryId')) {
            $id = (int) $data['categoryId'];
            if ($id && ! Category::query()->whereKey($id)->exists()) {
                $this->fail('categoryId', 'invalid');
            }
            $course->category_id = $id ?: null;
        }

        if ($has('difficulty')) {
            $course->difficulty = in_array($data['difficulty'], Course::DIFFICULTIES, true) ? $data['difficulty'] : null;
        }

        if ($has('estimatedMinutes')) {
            $course->estimated_minutes = $this->int($data['estimatedMinutes'], 0, 65535);
        }

        if ($has('status')) {
            $course->status = $data['status'] === 'published' ? 'published' : 'draft';
        }
        if ($course->status === 'published' && ! $course->published_at) {
            $course->published_at = Carbon::now();
        }

        if ($has('access')) {
            $course->access = $data['access'] === 'groups' ? 'groups' : 'everyone';
        }

        if ($has('previewLessons')) {
            $course->preview_lessons = (int) $this->int($data['previewLessons'], 0, 50);
        }

        foreach (['sequential' => 'sequential', 'notifyNewLessons' => 'notify_new_lessons'] as $key => $column) {
            if ($has($key)) {
                $course->$column = (bool) $data[$key];
            }
        }

        // Featuring a course and attaching a badge are the forum's to decide.
        if ($has('featured') && Access::isManager($actor)) {
            $course->featured = (bool) $data['featured'];
        }
        if ($has('badgeId') && Access::isManager($actor)) {
            $course->badge_id = $this->int($data['badgeId'], 1, 2147483647);
        }

        if ($has('tagId')) {
            $tag = $this->int($data['tagId'], 1, 2147483647);
            if ($tag && $this->extensions->isEnabled('flarum-tags') && ! $this->db->table('tags')->where('id', $tag)->exists()) {
                $this->fail('tagId', 'invalid');
            }
            $course->tag_id = $tag;
        }

        if ($has('description')) {
            $course->description = $this->rich->store((string) $data['description'], $course, $actor);
        }

        $groupIds = null;
        if ($has('groupIds')) {
            $groupIds = array_values(array_unique(array_filter(array_map('intval', array_slice((array) $data['groupIds'], 0, 100)), fn ($id) => $id > 0 && $id !== Group::GUEST_ID)));
            $groupIds = Group::query()->whereIn('id', $groupIds ?: [0])->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        if ($course->access === 'groups' && ($groupIds === [] || ($groupIds === null && ($isNew || ! $course->groups()->exists())))) {
            $this->fail('groupIds', 'groups_required');
        }

        $instructorIds = null;
        if ($has('instructorIds') && ($isNew || Access::canChooseInstructors($actor, $course))) {
            $instructorIds = array_values(array_unique(array_filter(array_map('intval', array_slice((array) $data['instructorIds'], 0, 20)), fn ($id) => $id > 0 && $id !== ($course->user_id ?? $actor->id))));
            $users = User::query()->whereIn('id', $instructorIds ?: [0])->get();
            foreach ($users as $user) {
                if (! $user->hasPermission('syllabus.create') && ! $user->hasPermission('syllabus.manage')) {
                    $this->fail('instructorIds', 'not_an_instructor', ['{name}' => $user->display_name]);
                }
            }
            $instructorIds = $users->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $saved = $this->db->transaction(function () use ($course, $groupIds, $instructorIds) {
            $course->save();

            if ($groupIds !== null) {
                $course->groups()->sync($groupIds);
                $course->unsetRelation('groups');
            }
            if ($instructorIds !== null) {
                $course->instructors()->sync($instructorIds);
                $course->unsetRelation('instructors');
            }

            return $course;
        });

        if (isset($oldCover)) {
            $this->uploads->deleteCover($oldCover);
        }

        return $saved;
    }

    private function int(mixed $value, int $min, int $max): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return max($min, min($max, (int) $value));
    }

    /** @param array<string, string> $params */
    private function fail(string $field, string $key, array $params = []): never
    {
        throw new ValidationException([$field => $this->translator->trans('ernestdefoe-syllabus.api.'.$key, $params)]);
    }
}
