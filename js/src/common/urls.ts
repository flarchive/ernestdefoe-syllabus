import app from 'flarum/common/app';

const idSlug = (thing: { id: number; slug?: string }) => thing.id + (thing.slug ? '-' + thing.slug : '');

/** "/courses/12-baking-bread" */
export const courseUrl = (course: { id: number; slug?: string }) => app.route('syllabus.course', { id: idSlug(course) });
export const lessonUrl = (course: { id: number; slug?: string }, lesson: { id: number; slug?: string }) =>
  app.route('syllabus.lesson', { id: idSlug(course), lesson: idSlug(lesson) });
export const certificateUrl = (code: string) => app.route('syllabus.certificate', { code });

/** The numeric id at the front of an "12-some-slug" route parameter. */
export const routeId = (param: string | undefined) => parseInt(String(param || ''), 10) || 0;
