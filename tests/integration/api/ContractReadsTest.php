<?php

namespace Ernestdefoe\Syllabus\Tests\integration\api;

use Ernestdefoe\Syllabus\Courses;
use Ernestdefoe\Syllabus\Enrolments;
use Ernestdefoe\Syllabus\Tests\integration\SyllabusTestCase;
use Flarum\User\Guest;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

/** The public reads other extensions build on: Enrolments::courseIdsFor and Courses::attachableBy. */
class ContractReadsTest extends SyllabusTestCase
{
    private function user(int $id): User
    {
        return User::query()->findOrFail($id);
    }

    #[Test]
    public function course_ids_for_is_a_subquery_of_the_courses_a_member_is_enrolled_in(): void
    {
        $a = $this->course(['title' => 'A']);
        $b = $this->course(['title' => 'B']);
        $this->enrol($a['id']);
        $this->enrol($b['id'], 3);
        $this->json('DELETE', '/api/syllabus/courses/'.$b['id'].'/enrolment', 3);

        $enrolments = $this->app()->getContainer()->make(Enrolments::class);
        $ids = fn (User $user) => $this->database()->table('syllabus_courses')
            ->whereIn('id', $enrolments->courseIdsFor($user))->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertSame([$a['id']], $ids($this->user(2)), 'enrolled');
        $this->assertSame([], $ids($this->user(3)), 'left the course');
        $this->assertSame([], $ids($this->user(4)), 'never enrolled');
        $this->assertSame([], $ids(new Guest()), 'guest');

        // One query for the caller's list, whatever the size of the subquery.
        $member = $this->user(2);
        $this->database()->enableQueryLog();
        $this->database()->flushQueryLog();
        $ids($member);
        $this->assertCount(1, $this->database()->getQueryLog());
    }

    #[Test]
    public function attachable_by_lists_the_published_courses_a_user_may_attach_to(): void
    {
        $this->course(['title' => 'Daves course'], 4);
        $erins = $this->course(['title' => 'Erins course'], 5);
        $this->json('PATCH', '/api/syllabus/courses/'.$erins['id'], 5, ['data' => ['instructorIds' => [4]]]);
        $this->course(['title' => 'Daves draft', 'status' => 'draft'], 4);
        $admins = $this->course(['title' => 'Admins course']);

        $courses = $this->app()->getContainer()->make(Courses::class);
        $titles = fn (User $user, int $limit = 50) => array_column($courses->attachableBy($user, $limit), 'title');

        // Instructors: what they teach and may edit, published only (dave co-teaches erin's).
        $this->assertSame(['Daves course', 'Erins course'], $titles($this->user(4)));
        $this->assertSame(['Erins course'], $titles($this->user(5)));
        // Members and guests: nothing.
        $this->assertSame([], $titles($this->user(2)));
        $this->assertSame([], $courses->attachableBy(new Guest()));
        // A manager (moderator) and an admin: every published course, by title.
        $this->assertSame(['Admins course', 'Daves course', 'Erins course'], $titles($this->user(6)));
        $this->assertSame(['Admins course', 'Daves course', 'Erins course'], $titles($this->user(1)));
        // Capped, and shaped as id + title.
        $this->assertSame(['Admins course'], $titles($this->user(1), 1));
        $this->assertSame([['id' => $admins['id'], 'title' => 'Admins course']], $courses->attachableBy($this->user(1), 1));

        // An instructor who lost "Create and teach courses" attaches to nothing.
        $this->database()->table('group_user')->where('user_id', 4)->delete();
        $this->assertSame([], $titles($this->user(4)));
    }
}
