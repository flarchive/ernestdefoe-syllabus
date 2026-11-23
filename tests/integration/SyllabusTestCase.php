<?php

namespace Ernestdefoe\Syllabus\Tests\integration;

use Flarum\Group\Group;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Laminas\Diactoros\UploadedFile;

/**
 * Users: 1 admin, 2 "normal" (member), 3 carol (member), 4 dave and 5 erin
 * (Instructors: may create courses, not manage them), 6 mo (Moderator), 7
 * pat (member of the paid "Supporters" group 6).
 */
abstract class SyllabusTestCase extends TestCase
{
    use RetrievesAuthorizedUsers;

    public const INSTRUCTORS = 5;
    public const SUPPORTERS = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension(...$this->extensions());

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'carol', 'email' => 'carol@machine.local', 'is_email_confirmed' => 1],
                ['id' => 4, 'username' => 'dave', 'email' => 'dave@machine.local', 'is_email_confirmed' => 1],
                ['id' => 5, 'username' => 'erin', 'email' => 'erin@machine.local', 'is_email_confirmed' => 1],
                ['id' => 6, 'username' => 'mo', 'email' => 'mo@machine.local', 'is_email_confirmed' => 1],
                ['id' => 7, 'username' => 'pat', 'email' => 'pat@machine.local', 'is_email_confirmed' => 1],
            ],
            Group::class => [
                ['id' => self::INSTRUCTORS, 'name_singular' => 'Instructor', 'name_plural' => 'Instructors', 'is_hidden' => 0],
                ['id' => self::SUPPORTERS, 'name_singular' => 'Supporter', 'name_plural' => 'Supporters', 'is_hidden' => 0],
            ],
            'group_user' => [
                ['user_id' => 4, 'group_id' => self::INSTRUCTORS],
                ['user_id' => 5, 'group_id' => self::INSTRUCTORS],
                ['user_id' => 6, 'group_id' => Group::MODERATOR_ID],
                ['user_id' => 7, 'group_id' => self::SUPPORTERS],
            ],
            'group_permission' => [
                ['group_id' => self::INSTRUCTORS, 'permission' => 'syllabus.create'],
            ],
        ]);
    }

    /** @return list<string> */
    protected function extensions(): array
    {
        return ['ernestdefoe-syllabus'];
    }

    /** @return array{0: int, 1: array<string, mixed>|null, 2: string} */
    protected function json(string $method, string $path, ?int $actor = null, ?array $body = null, array $query = []): array
    {
        $options = $actor ? ['authenticatedAs' => $actor] : [];
        if ($body !== null) {
            $options['json'] = $body;
        }

        $request = $this->request($method, $path, $options);
        if ($query) {
            $request = $request->withQueryParams($query);
        }
        $response = $this->send($actor ? $request : $request->withAttribute('bypassCsrfToken', true));
        $raw = (string) $response->getBody();

        return [$response->getStatusCode(), json_decode($raw, true), $raw];
    }

    /** A multipart request with files. @param array<string, mixed> $files */
    protected function multipart(string $path, int $actor, array $fields, array $files): array
    {
        $request = $this->request('POST', $path, ['authenticatedAs' => $actor])
            ->withParsedBody($fields)
            ->withUploadedFiles($files);
        $response = $this->send($request);
        $raw = (string) $response->getBody();

        return [$response->getStatusCode(), json_decode($raw, true), $raw];
    }

    protected function file(string $contents, string $name, string $type = 'application/octet-stream'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'syl');
        file_put_contents($path, $contents);

        return new UploadedFile($path, strlen($contents), UPLOAD_ERR_OK, $name, $type);
    }

    /** @return array<string, mixed> */
    protected function course(array $data = [], int $actor = 1): array
    {
        [$status, $body, $raw] = $this->json('POST', '/api/syllabus/courses', $actor, ['data' => $data + [
            'title' => 'Baking Bread',
            'summary' => 'From starter to loaf.',
            'description' => 'Learn to **bake**.',
            'status' => 'published',
        ]]);
        $this->assertSame(201, $status, $raw);

        return $body['data'];
    }

    /** @return array<string, mixed> */
    protected function lesson(int $courseId, array $data = [], int $actor = 1): array
    {
        [$status, $body, $raw] = $this->json('POST', "/api/syllabus/courses/$courseId/lessons", $actor, ['data' => $data + [
            'title' => 'A lesson',
            'type' => 'text',
            'body' => 'SECRET-BODY lesson text',
            'status' => 'published',
        ]]);
        $this->assertSame(201, $status, $raw);

        return $body['data'];
    }

    protected function enrol(int $courseId, int $actor = 2): array
    {
        [$status, $body, $raw] = $this->json('POST', "/api/syllabus/courses/$courseId/enrolment", $actor);
        $this->assertSame(200, $status, $raw);

        return $body['data'];
    }

    protected function complete(int $lessonId, int $actor = 2): array
    {
        [$status, $body, $raw] = $this->json('POST', "/api/syllabus/lessons/$lessonId/complete", $actor);
        $this->assertSame(200, $status, $raw);

        return $body['data'];
    }
}
