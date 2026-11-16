import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import { courseUrl, lessonUrl, listCourses } from '../../common/api';
import type { CourseCard } from '../../common/api';
import ProgressBar from './ProgressBar';
import { t } from './util';

/**
 * What the widget shows: a member's courses in progress, most recently
 * studied first, each with a "continue" link — or, for a guest or a member
 * taking none, the featured courses (the newest, when none are featured).
 */
export default class WidgetBody extends Component<{ limit: number }> {
  courses: CourseCard[] | null = null;
  learning = false;

  oninit(vnode: Mithril.Vnode<{ limit: number }, this>) {
    super.oninit(vnode);
    this.load();
  }

  async load() {
    const limit = this.attrs.limit;
    try {
      if (app.session.user) {
        const { data } = await listCourses({ filter: { mine: '1' }, page: { limit } });
        const going = data.filter((c) => !c.me?.completedAt);
        if (going.length) {
          this.courses = going;
          this.learning = true;
          return;
        }
      }
      const featured = await listCourses({ filter: { featured: '1' }, page: { limit } });
      this.courses = featured.data.length ? featured.data : (await listCourses({ page: { limit } })).data;
    } catch {
      this.courses = [];
    } finally {
      m.redraw();
    }
  }

  view(): Mithril.Children {
    if (!this.courses) return <LoadingIndicator display="block" size="small" />;
    if (!this.courses.length) return <p className="SyllabusWidget-empty">{t('widget.empty')}</p>;

    return (
      <ul className="SyllabusWidget">
        {this.courses.map((course) => {
          const me = course.me;
          const href = this.learning && me?.lastLessonId ? lessonUrl(course, { id: me.lastLessonId }) : courseUrl(course);

          return (
            <li key={course.id}>
              <Link href={href} className="SyllabusWidget-title">
                {course.title}
              </Link>
              {this.learning && me ? (
                <ProgressBar percent={me.percent} compact />
              ) : (
                <span className="SyllabusWidget-meta">{t('course.lessons', { count: course.lessonCount })}</span>
              )}
            </li>
          );
        })}
        <li className="SyllabusWidget-all">
          <Link href={app.route('syllabus.index') + (this.learning ? '?tab=mine' : '')}>{t('widget.all')}</Link>
        </li>
      </ul>
    );
  }
}
