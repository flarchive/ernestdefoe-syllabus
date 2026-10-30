<?php

/*
 * Syllabus — courses inside a Flarum forum.
 *
 * The shape of the extension: a course is an ordered outline of sections and
 * lessons (text, video, assignment and — with Brainstorm — quiz). One class,
 * Outline, decides what each member may read; every endpoint that serves
 * lesson content asks it. Completing every lesson completes the course once,
 * as a dated fact, and issues a certificate with a public verification code.
 */

use Ernestdefoe\Syllabus\Access;
use Ernestdefoe\Syllabus\Api;
use Ernestdefoe\Syllabus\Brainstorm;
use Ernestdefoe\Syllabus\Content\CertificateDocument;
use Ernestdefoe\Syllabus\Content\CourseDocument;
use Ernestdefoe\Syllabus\Listener\CompleteQuizLessons;
use Ernestdefoe\Syllabus\Notification;
use Ernestdefoe\Syllabus\Uploads;
use Flarum\Api\Context;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\Foundation\Paths;

$course = '{id:\d+(?:-[^/]*)?}';
$lesson = '{lesson:\d+(?:-[^/]*)?}';

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        // The catalogue, course, lesson, editor, instructor and certificate
        // pages are their own chunks.
        ->jsDirectory(__DIR__.'/js/dist/forum')
        ->css(__DIR__.'/less/forum.less')
        // Static paths before {id}: FastRoute refuses a static route that an
        // earlier variable one already matches.
        ->route('/courses', 'syllabus.index')
        ->route('/courses/teach', 'syllabus.teach')
        ->route('/courses/new', 'syllabus.new')
        ->route("/courses/$course", 'syllabus.course', CourseDocument::class)
        ->route("/courses/$course/edit", 'syllabus.edit')
        ->route("/courses/$course/lessons/new", 'syllabus.lesson.new')
        ->route("/courses/$course/lessons/$lesson", 'syllabus.lesson')
        ->route("/courses/$course/lessons/$lesson/edit", 'syllabus.lesson.edit')
        ->route('/certificates/{code:[0-9a-fA-F]{32}}', 'syllabus.certificate', CertificateDocument::class)
        ->route('/u/{username}/courses', 'user.courses'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\View())
        ->namespace('ernestdefoe-syllabus', __DIR__.'/views'),

    (new Extend\Routes('api'))
        ->get('/syllabus/courses', 'syllabus.courses.index', Api\ListCoursesController::class)
        ->post('/syllabus/courses', 'syllabus.courses.create', Api\SaveCourseController::class)
        ->get('/syllabus/teaching', 'syllabus.teaching', Api\TeachingController::class)
        ->get('/syllabus/editor', 'syllabus.editor', Api\EditorMetaController::class)
        ->get('/syllabus/categories', 'syllabus.categories.index', Api\CategoriesController::class)
        ->post('/syllabus/categories', 'syllabus.categories.create', Api\CategoriesController::class)
        ->post('/syllabus/categories/order', 'syllabus.categories.order', Api\CategoriesController::class)
        ->patch('/syllabus/categories/{id:[0-9]+}', 'syllabus.categories.update', Api\CategoriesController::class)
        ->delete('/syllabus/categories/{id:[0-9]+}', 'syllabus.categories.delete', Api\CategoriesController::class)
        ->get('/syllabus/courses/{id:[0-9]+}', 'syllabus.courses.show', Api\ShowCourseController::class)
        ->patch('/syllabus/courses/{id:[0-9]+}', 'syllabus.courses.update', Api\SaveCourseController::class)
        ->delete('/syllabus/courses/{id:[0-9]+}', 'syllabus.courses.delete', Api\DeleteCourseController::class)
        ->post('/syllabus/courses/{id:[0-9]+}/cover', 'syllabus.courses.cover', Api\CoverController::class)
        ->delete('/syllabus/courses/{id:[0-9]+}/cover', 'syllabus.courses.cover.delete', Api\CoverController::class)
        ->post('/syllabus/courses/{id:[0-9]+}/enrolment', 'syllabus.courses.enrol', Api\EnrolmentController::class)
        ->delete('/syllabus/courses/{id:[0-9]+}/enrolment', 'syllabus.courses.leave', Api\EnrolmentController::class)
        ->patch('/syllabus/courses/{id:[0-9]+}/outline', 'syllabus.courses.outline', Api\OutlineController::class)
        ->get('/syllabus/courses/{id:[0-9]+}/students', 'syllabus.courses.students', Api\StudentsController::class)
        ->post('/syllabus/courses/{id:[0-9]+}/lessons', 'syllabus.lessons.create', Api\SaveLessonController::class)
        ->get('/syllabus/lessons/{id:[0-9]+}', 'syllabus.lessons.show', Api\ShowLessonController::class)
        ->patch('/syllabus/lessons/{id:[0-9]+}', 'syllabus.lessons.update', Api\SaveLessonController::class)
        ->delete('/syllabus/lessons/{id:[0-9]+}', 'syllabus.lessons.delete', Api\DeleteLessonController::class)
        ->post('/syllabus/lessons/{id:[0-9]+}/complete', 'syllabus.lessons.complete', Api\CompleteLessonController::class)
        ->delete('/syllabus/lessons/{id:[0-9]+}/complete', 'syllabus.lessons.uncomplete', Api\CompleteLessonController::class)
        ->post('/syllabus/lessons/{id:[0-9]+}/video', 'syllabus.lessons.video', Api\VideoController::class)
        ->delete('/syllabus/lessons/{id:[0-9]+}/video', 'syllabus.lessons.video.delete', Api\VideoController::class)
        ->post('/syllabus/lessons/{id:[0-9]+}/submissions', 'syllabus.submissions.create', Api\SubmitController::class)
        ->get('/syllabus/submissions', 'syllabus.submissions.index', Api\SubmissionsController::class)
        ->patch('/syllabus/submissions/{id:[0-9]+}', 'syllabus.submissions.review', Api\SubmissionsController::class)
        ->get('/syllabus/files/{id:[0-9]+}', 'syllabus.files.show', Api\FileController::class)
        ->get('/syllabus/certificates/{code:[0-9a-fA-F]{32}}', 'syllabus.certificates.show', Api\CertificateController::class)
        ->get('/syllabus/users/{id:[0-9]+}/courses', 'syllabus.users.courses', Api\UserCoursesController::class),

    // Lesson videos and assignment files: outside the public folder, served
    // only through FileController.
    (new Extend\Filesystem())
        ->disk('syllabus-files', fn (Paths $paths) => ['root' => $paths->storage.'/syllabus']),

    new Extend\ApiResource(Api\Resource\CourseResource::class),

    (new Extend\Notification())
        ->type(Notification\AssignmentReviewedBlueprint::class, ['alert', 'email'])
        ->type(Notification\SubmissionReceivedBlueprint::class, ['alert', 'email'])
        ->type(Notification\CourseCompletedBlueprint::class, ['alert', 'email'])
        ->type(Notification\NewLessonBlueprint::class, ['alert']),

    (new Extend\Settings())
        ->default('ernestdefoe-syllabus.show_nav', true)
        ->default('ernestdefoe-syllabus.max_file_mb', 10)
        ->default('ernestdefoe-syllabus.max_video_mb', 200)
        ->default('ernestdefoe-syllabus.file_types', Uploads::DEFAULT_FILE_TYPES)
        ->default('ernestdefoe-syllabus.submissions_per_hour', 10)
        ->serializeToForum('syllabusShowNav', 'ernestdefoe-syllabus.show_nav', 'boolval'),

    (new Extend\User())
        ->registerPreference('syllabusShowCourses', 'boolval', true),

    // Permissions the actor's groups were already loaded for, and settings
    // already in memory: no query.
    (new Extend\ApiResource(ForumResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('canViewCourses')
                ->get(fn ($forum, Context $context) => $context->getActor()->hasPermission('syllabus.view')),
            Schema\Boolean::make('canCreateCourses')
                ->get(fn ($forum, Context $context) => Access::canCreate($context->getActor())),
            Schema\Boolean::make('syllabusQuizzes')
                ->get(fn () => resolve(Brainstorm::class)->enabled())
                ->visible(fn ($forum, Context $context) => Access::canCreate($context->getActor())),
            Schema\Str::make('syllabusFileTypes')
                ->get(fn () => implode(',', resolve(Uploads::class)->fileTypes()))
                ->visible(fn ($forum, Context $context) => ! $context->getActor()->isGuest()),
            Schema\Integer::make('syllabusMaxFileMb')
                ->get(fn () => resolve(Uploads::class)->mb('max_file_mb', 10))
                ->visible(fn ($forum, Context $context) => ! $context->getActor()->isGuest()),
        ]),

    (new Extend\Conditional())
        // Sell courses through Kiosk; see Kiosk\CourseUnlock.
        ->whenExtensionEnabled('ernestdefoe-kiosk', fn () => [
            new \Ernestdefoe\Kiosk\Extend\UnlockType('syllabus-course', \Ernestdefoe\Syllabus\Kiosk\CourseUnlock::class),
        ])
        ->whenExtensionEnabled('ernestdefoe-brainstorm', fn () => [
            (new Extend\Event())->listen(\ErnestDefoe\Brainstorm\Event\AttemptFinished::class, CompleteQuizLessons::class),
        ]),
];
