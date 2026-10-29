<?php

namespace Ernestdefoe\Syllabus;

use Illuminate\Support\Collection;

/**
 * One member's view of one course, worked out once by {@see Outline} and read
 * by the course page, the lesson page, "mark complete" and next/previous.
 */
class CourseState
{
    /**
     * @param Collection<int, Lesson> $lessons in outline order: every lesson for an editor, the countable ones for anyone else
     * @param Collection<int, Section> $sections
     * @param array<int, true> $completed lesson ids
     * @param array<int, string|null> $locks lesson id => null when it may be read, else why not: login | enrol | access | order
     * @param array<int, true> $previews lesson ids open as a free preview
     */
    public function __construct(
        public Course $course,
        public bool $editor,
        public ?Enrolment $enrolment,
        public bool $meetsRule,
        public bool $canEnrol,
        public Collection $lessons,
        public Collection $sections,
        public array $completed,
        public array $locks,
        public array $previews,
        public int $total,
        public int $done,
    ) {
    }

    public function enrolled(): bool
    {
        return $this->enrolment !== null;
    }

    public function percent(): int
    {
        return $this->total > 0 ? (int) min(100, floor($this->done * 100 / $this->total)) : 0;
    }

    public function lesson(int $id): ?Lesson
    {
        return $this->lessons->firstWhere('id', $id);
    }

    public function unlocked(Lesson $lesson): bool
    {
        return array_key_exists($lesson->id, $this->locks) && $this->locks[$lesson->id] === null;
    }

    public function lockReason(Lesson $lesson): ?string
    {
        return $this->locks[$lesson->id] ?? null;
    }

    /** The lesson before or after this one in the outline that the reader can see. */
    public function neighbour(Lesson $lesson, int $step): ?Lesson
    {
        $list = $this->lessons->values();
        $index = $list->search(fn (Lesson $l) => $l->id === $lesson->id);

        return $index === false ? null : $list->get($index + $step);
    }

    /** Where "continue" goes: the first open lesson not yet completed, else the first lesson. */
    public function nextLesson(): ?Lesson
    {
        foreach ($this->lessons as $lesson) {
            if ($lesson->isPublished() && $this->unlocked($lesson) && ! isset($this->completed[$lesson->id])) {
                return $lesson;
            }
        }

        return $this->lessons->first();
    }
}
