import Extend from 'flarum/common/extenders';
import Model from 'flarum/common/Model';
import { AssignmentReviewedNotification, CourseCompletedNotification, NewLessonNotification, SubmissionReceivedNotification } from './notifications';

/** A course as a notification's subject: its title and slug, nothing more. */
export class SyllabusCourse extends Model {
  title = Model.attribute<string>('title');
  slug = Model.attribute<string>('slug');
}

// Every page is its own chunk, fetched the first time it is opened.
export default [
  new Extend.Store().add('syllabus-courses', SyllabusCourse),
  new Extend.Routes()
    .add('syllabus.index', '/courses', () => import('./pages/CataloguePage'))
    .add('syllabus.teach', '/courses/teach', () => import('./pages/TeachPage'))
    .add('syllabus.new', '/courses/new', () => import('./pages/CourseEditorPage'))
    .add('syllabus.course', '/courses/:id', () => import('./pages/CoursePage'))
    .add('syllabus.edit', '/courses/:id/edit', () => import('./pages/CourseEditorPage'))
    .add('syllabus.lesson.new', '/courses/:id/lessons/new', () => import('./pages/LessonEditorPage'))
    .add('syllabus.lesson', '/courses/:id/lessons/:lesson', () => import('./pages/LessonPage'))
    .add('syllabus.lesson.edit', '/courses/:id/lessons/:lesson/edit', () => import('./pages/LessonEditorPage'))
    .add('syllabus.certificate', '/certificates/:code', () => import('./pages/CertificatePage'))
    .add('user.courses', '/u/:username/courses', () => import('./pages/UserCoursesPage')),
  new Extend.Notification()
    .add('syllabusAssignmentReviewed', AssignmentReviewedNotification)
    .add('syllabusSubmissionReceived', SubmissionReceivedNotification)
    .add('syllabusNewLesson', NewLessonNotification)
    .add('syllabusCourseCompleted', CourseCompletedNotification),
];
