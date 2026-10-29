import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';
import { certificateUrl, lessonUrl } from '../common/urls';

const t = (key: string, params: Record<string, unknown> = {}) => app.translator.trans(`ernestdefoe-syllabus.forum.notifications.${key}`, params);

/** The course a notification points at, or null if it has since been deleted. */
function course(notification: any): { id: number; slug: string; title: string } | null {
  const subject = notification.subject();
  return subject ? { id: Number(subject.id()), slug: subject.slug?.() || '', title: subject.title?.() || '' } : null;
}

function lessonHref(notification: any): string {
  const c = course(notification);
  const content = notification.content() || {};
  return c ? lessonUrl(c, { id: content.lessonId, slug: content.lessonSlug }) : app.route('syllabus.index');
}

export class AssignmentReviewedNotification extends Notification {
  icon() {
    return (this.attrs.notification.content() as any)?.status === 'passed' ? 'fas fa-circle-check' : 'fas fa-rotate-left';
  }
  href() {
    return lessonHref(this.attrs.notification);
  }
  content() {
    const n = this.attrs.notification;
    return t((n.content() as any)?.status === 'passed' ? 'reviewed_passed' : 'reviewed_needs_work', {
      user: n.fromUser(),
      lesson: (n.content() as any)?.lessonTitle || '',
    });
  }
  excerpt() {
    return course(this.attrs.notification)?.title || null;
  }
}

export class SubmissionReceivedNotification extends Notification {
  icon() {
    return 'fas fa-inbox';
  }
  href() {
    return app.route('syllabus.teach') + '?tab=review';
  }
  content() {
    const n = this.attrs.notification;
    return t('submission', { user: n.fromUser(), lesson: (n.content() as any)?.lessonTitle || '' });
  }
  excerpt() {
    return course(this.attrs.notification)?.title || null;
  }
}

export class NewLessonNotification extends Notification {
  icon() {
    return 'fas fa-book-open';
  }
  href() {
    return lessonHref(this.attrs.notification);
  }
  content() {
    return t('new_lesson', { course: course(this.attrs.notification)?.title || '' });
  }
  excerpt() {
    return (this.attrs.notification.content() as any)?.lessonTitle || null;
  }
}

export class CourseCompletedNotification extends Notification {
  icon() {
    return 'fas fa-award';
  }
  href() {
    const code = (this.attrs.notification.content() as any)?.code;
    return code ? certificateUrl(code) : app.route('syllabus.index');
  }
  content() {
    return t('completed', { course: course(this.attrs.notification)?.title || '' });
  }
  excerpt() {
    return null;
  }
}
