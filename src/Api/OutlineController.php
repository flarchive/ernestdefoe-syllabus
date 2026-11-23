<?php

namespace Ernestdefoe\Syllabus\Api;

use Ernestdefoe\Syllabus\Lesson;
use Ernestdefoe\Syllabus\Outline;
use Ernestdefoe\Syllabus\Presenter;
use Ernestdefoe\Syllabus\Section;
use Flarum\Http\RequestUtil;
use Illuminate\Database\ConnectionInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PATCH /api/syllabus/courses/{id}/outline — the order the instructor dragged
 * sections and lessons into, as one list:
 * `{items: [{kind: "section", id?, title}, {kind: "lesson", id}]}`.
 *
 * A section without an id is new; a section left out is removed (its lessons
 * stay). Each lesson belongs to the section above it. Lessons left out keep
 * their order after the ones listed.
 */
class OutlineController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(private Outline $outline, private Presenter $presenter, private ConnectionInterface $db)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $course = $this->editableCourse($request, $actor);
        $items = array_slice(array_values(array_filter((array) ($this->body($request)['items'] ?? []), 'is_array')), 0, 1000);

        $lessons = Lesson::query()->where('course_id', $course->id)->orderBy('position')->orderBy('id')->get()->keyBy('id');
        $sections = Section::query()->where('course_id', $course->id)->get()->keyBy('id');

        $this->db->transaction(function () use ($items, $lessons, $sections, $course) {
            $position = 0;
            $sectionId = null;
            $placed = $keptSections = $moves = [];

            foreach ($items as $item) {
                if (($item['kind'] ?? null) === 'section') {
                    $title = trim(mb_substr((string) ($item['title'] ?? ''), 0, 200));
                    $id = (int) ($item['id'] ?? 0);
                    $section = $id ? $sections->get($id) : null;
                    if ($title === '' && ! $section) {
                        continue;
                    }
                    $section ??= new Section();
                    $section->course_id = $course->id;
                    $section->title = $title !== '' ? $title : $section->title;
                    $section->position = ++$position;
                    $section->save();
                    $sectionId = $section->id;
                    $keptSections[$section->id] = true;
                } elseif (($item['kind'] ?? null) === 'lesson' && ($lesson = $lessons->get((int) ($item['id'] ?? 0))) && ! isset($placed[$lesson->id])) {
                    $this->place($moves, $lesson, ++$position, $sectionId);
                    $placed[$lesson->id] = true;
                }
            }

            foreach ($lessons as $lesson) {
                if (! isset($placed[$lesson->id])) {
                    $this->place($moves, $lesson, ++$position, $sectionId);
                }
            }

            // Every moved lesson in one statement, however long the course.
            if ($moves) {
                $this->db->table('syllabus_lessons')->upsert($moves, ['id'], ['position', 'section_id']);
            }

            Section::query()->where('course_id', $course->id)->whereNotIn('id', array_keys($keptSections) ?: [0])->delete();
        });

        $course->unsetRelation('lessons');

        return new JsonResponse(['data' => ['outline' => $this->presenter->outlineItems($this->outline->build($course, $actor))]]);
    }

    /** @param list<array<string, mixed>> $moves */
    private function place(array &$moves, Lesson $lesson, int $position, ?int $sectionId): void
    {
        if ($lesson->position !== $position || $lesson->section_id !== $sectionId) {
            // The insert half of the upsert never runs (every id exists), but
            // its columns must still satisfy the table's NOT NULLs.
            $moves[] = [
                'id' => $lesson->id,
                'course_id' => $lesson->course_id,
                'title' => $lesson->title,
                'slug' => $lesson->slug,
                'position' => $position,
                'section_id' => $sectionId,
            ];
        }
    }
}
