import app from 'flarum/common/app';

export type LessonType = 'text' | 'video' | 'assignment' | 'quiz';
export type Lock = 'login' | 'enrol' | 'access' | 'order' | null;

export interface Person {
  id: number;
  username: string;
  displayName: string;
  avatarUrl: string | null;
  slug: string;
}

export interface Me {
  enrolled: boolean;
  done: number;
  percent: number;
  completedAt: string | null;
  lastLessonId: number | null;
  lastSeenAt: string | null;
}

export interface CourseCard {
  id: number;
  slug: string;
  title: string;
  summary: string | null;
  coverUrl: string | null;
  difficulty: 'beginner' | 'intermediate' | 'advanced' | null;
  estimatedMinutes: number | null;
  status: 'draft' | 'published';
  featured: boolean;
  access: 'everyone' | 'groups';
  category: { id: number; name: string; slug: string } | null;
  instructor: Person | null;
  lessonCount: number;
  enrolmentCount: number;
  publishedAt: string | null;
  canEdit: boolean;
  me: Me | null;
  certificateCode?: string | null;
  completedCount?: number;
  pendingCount?: number;
}

export interface SectionItem {
  kind: 'section';
  id: number;
  title: string;
}

export interface LessonItem {
  kind: 'lesson';
  id: number;
  slug: string;
  title: string;
  type: LessonType;
  status: 'draft' | 'published';
  durationMinutes: number | null;
  completed: boolean;
  locked: Lock;
  preview: boolean;
}

export type OutlineItem = SectionItem | LessonItem;

export interface Ref {
  id: number;
  slug: string;
  title: string;
}

export interface EditableCourse {
  title: string;
  summary: string;
  description: string;
  coverUrl: string | null;
  hasUploadedCover: boolean;
  categoryId: number | null;
  difficulty: string | null;
  estimatedMinutes: number | null;
  status: 'draft' | 'published';
  access: 'everyone' | 'groups';
  groupIds: number[];
  previewLessons: number;
  sequential: boolean;
  featured: boolean;
  notifyNewLessons: boolean;
  tagId: number | null;
  badgeId: number | null;
  instructors: Person[];
}

export interface Course extends CourseCard {
  descriptionHtml: string | null;
  sequential: boolean;
  previewLessons: number;
  instructors: Person[];
  groups: { id: number; name: string }[];
  canEnrol: boolean;
  meetsRule: boolean;
  canReview: boolean;
  outline: OutlineItem[];
  certificateCode: string | null;
  nextLesson: Ref | null;
  edit?: EditableCourse;
}

export interface FileRef {
  id: number;
  name: string;
  size: number;
  url: string;
}

export interface Submission {
  id: number;
  lessonId: number;
  courseId: number;
  user: Person | null;
  body: string;
  status: 'pending' | 'passed' | 'needs_work';
  feedback: string | null;
  reviewer: Person | null;
  reviewedAt: string | null;
  createdAt: string | null;
  files: FileRef[];
  lesson?: Ref;
  course?: Ref;
}

export interface EditableLesson {
  title: string;
  type: LessonType;
  body: string;
  videoUrl: string;
  videoFile: { id: number; name: string; size: number } | null;
  quizId: number | null;
  status: 'draft' | 'published';
  durationMinutes: number | null;
  discussionId: number | null;
}

export interface Lesson {
  id: number;
  slug: string;
  title: string;
  type: LessonType;
  status: 'draft' | 'published';
  durationMinutes: number | null;
  course: { id: number; slug: string; title: string; sequential: boolean };
  locked: Lock;
  preview: boolean;
  completed: boolean;
  enrolled: boolean;
  canEnrol: boolean;
  canEdit: boolean;
  canComplete: boolean;
  progress: { done: number; total: number; percent: number };
  prev: Ref | null;
  next: Ref | null;
  discussion: { id: number; slug: string; title: string; commentCount: number } | null;
  outline: OutlineItem[];
  bodyHtml?: string | null;
  video?: { provider: 'youtube' | 'vimeo' | 'file'; embedUrl?: string; fileUrl?: string; mime?: string } | null;
  quiz?: { id: number; slug: string; title: string; published: boolean; url: string } | null;
  submissions?: Submission[];
  canSubmit?: boolean;
  edit?: EditableLesson;
  discussionError?: string | null;
}

export interface EditorMeta {
  categories: { id: number; name: string }[];
  groups: { id: number; name: string }[];
  tags: { id: number; name: string }[];
  defaultTagId: number | null;
  badges: { id: number; name: string; icon: string }[];
  canFeature: boolean;
  canBadge: boolean;
  quizzesEnabled: boolean;
  quizzes: { id: number; title: string; status: string }[];
  maxVideoMb: number;
}

export interface Category {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  courseCount: number;
}

export interface Certificate {
  code: string;
  userName: string;
  courseTitle: string;
  issuedAt: string;
  course: { id: number; slug: string } | null;
  isOwner: boolean;
  text: string;
  signature: string;
  logoUrl: string | null;
  verifyUrl: string;
}

export interface Student {
  user: Person | null;
  enrolledAt: string;
  lastSeenAt: string | null;
  completedAt: string | null;
  done: number;
  percent: number;
}

export interface QuizResult {
  lessonId: number;
  lessonTitle: string;
  quizId: number;
  quizSlug: string;
  quizTitle: string | null;
  passed: number;
}

const api = (path: string) => app.forum.attribute<string>('apiUrl') + '/syllabus' + path;

function call<T>(method: string, path: string, body?: object, params?: Record<string, unknown>): Promise<T> {
  return app.request<T>({ method, url: api(path), body: body ? { data: body } : undefined, params });
}

/** A multipart upload: `serialize` hands the FormData to the browser untouched. */
export function upload<T>(path: string, form: FormData): Promise<T> {
  return app.request<T>({ method: 'POST', url: api(path), body: form, serialize: (raw: any) => raw });
}

export const listCourses = (params: Record<string, unknown>) =>
  call<{ data: CourseCard[]; meta: { canCreate: boolean; next: number | null } }>('GET', '/courses', undefined, params);
export const showCourse = (id: number, edit = false) => call<{ data: Course }>('GET', `/courses/${id}`, undefined, edit ? { edit: 1 } : undefined);
export const saveCourse = (data: Partial<EditableCourse> & { instructorIds?: number[] }, id?: number) =>
  call<{ data: Course }>(id ? 'PATCH' : 'POST', id ? `/courses/${id}` : '/courses', data);
export const deleteCourse = (id: number) => call<void>('DELETE', `/courses/${id}`);
export const removeCover = (id: number) => call<{ data: { coverUrl: string | null } }>('DELETE', `/courses/${id}/cover`);
export const enrol = (id: number) => call<{ data: Course }>('POST', `/courses/${id}/enrolment`);
export const leave = (id: number) => call<{ data: Course }>('DELETE', `/courses/${id}/enrolment`);
export const saveOutline = (id: number, items: { kind: string; id?: number; title?: string }[]) =>
  call<{ data: { outline: OutlineItem[] } }>('PATCH', `/courses/${id}/outline`, { items });
export const students = (id: number, offset = 0) =>
  call<{ data: Student[]; meta: { total: number; enrolled: number; next: number | null; quizzes: QuizResult[] } }>(
    'GET',
    `/courses/${id}/students`,
    undefined,
    offset ? { offset } : undefined
  );
export const teaching = () => call<{ data: CourseCard[] }>('GET', '/teaching');
export const editorMeta = () => call<{ data: EditorMeta }>('GET', '/editor');
export const categories = () => call<{ data: Category[] }>('GET', '/categories');
export const saveCategory = (data: { name?: string; description?: string }, id?: number) =>
  call<{ data: Category[] }>(id ? 'PATCH' : 'POST', id ? `/categories/${id}` : '/categories', data);
export const deleteCategory = (id: number) => call<void>('DELETE', `/categories/${id}`);
export const orderCategories = (ids: number[]) => call<{ data: Category[] }>('POST', '/categories/order', { ids });

export const showLesson = (id: number, edit = false) => call<{ data: Lesson }>('GET', `/lessons/${id}`, undefined, edit ? { edit: 1 } : undefined);
export const saveLesson = (data: Record<string, unknown>, courseId: number, id?: number) =>
  call<{ data: Lesson }>(id ? 'PATCH' : 'POST', id ? `/lessons/${id}` : `/courses/${courseId}/lessons`, data);
export const deleteLesson = (id: number) => call<void>('DELETE', `/lessons/${id}`);
export const removeVideo = (id: number) => call<void>('DELETE', `/lessons/${id}/video`);
export const completeLesson = (id: number, done = true) =>
  call<{
    data: {
      completed: boolean;
      progress: { done: number; total: number; percent: number };
      courseCompleted: boolean;
      certificateCode: string | null;
      nextUnlocked: boolean | null;
    };
  }>(done ? 'POST' : 'DELETE', `/lessons/${id}/complete`);
export const submissions = (params: Record<string, unknown>) =>
  call<{ data: Submission[]; meta: { next: number | null } }>('GET', '/submissions', undefined, params);
export const review = (id: number, status: 'passed' | 'needs_work', feedback: string) =>
  call<{ data: Submission }>('PATCH', `/submissions/${id}`, { status, feedback });
export const certificate = (code: string) => call<{ data: Certificate }>('GET', `/certificates/${code}`);
export const userCourses = (userId: string) => call<{ data: CourseCard[]; meta: { hidden: boolean } }>('GET', `/users/${userId}/courses`);

export { courseUrl, lessonUrl, certificateUrl, routeId } from './urls';
