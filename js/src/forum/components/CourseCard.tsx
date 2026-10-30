import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import type Mithril from 'mithril';
import { courseUrl, lessonUrl } from '../../common/api';
import type { CourseCard as Card } from '../../common/api';
import ProgressBar from './ProgressBar';
import { duration, t } from './util';

/** One course in a grid: cover, title, summary, facts and, for its students, progress. */
export default class CourseCard extends Component<{ course: Card }> {
  view(): Mithril.Children {
    const course = this.attrs.course;
    const me = course.me;
    const href = me && me.lastLessonId && !me.completedAt ? lessonUrl(course, { id: me.lastLessonId }) : courseUrl(course);

    return (
      <article className={'SyllabusCard' + (course.status === 'draft' ? ' SyllabusCard--draft' : '')}>
        <Link href={courseUrl(course)} className="SyllabusCard-cover" aria-hidden="true" tabindex="-1">
          {course.coverUrl ? <img src={course.coverUrl} alt="" loading="lazy" /> : <Icon name="fas fa-chalkboard-user" />}
        </Link>
        <div className="SyllabusCard-body">
          <div className="SyllabusCard-badges">
            {course.status === 'draft' ? <span className="SyllabusBadge SyllabusBadge--draft">{t('course.draft')}</span> : null}
            {course.featured ? <span className="SyllabusBadge SyllabusBadge--featured">{t('course.featured')}</span> : null}
            {course.access === 'groups' ? (
              <span className="SyllabusBadge">
                <Icon name="fas fa-lock" /> {t('course.restricted')}
              </span>
            ) : null}
            {course.category ? <span className="SyllabusBadge SyllabusBadge--category">{course.category.name}</span> : null}
          </div>
          <h3 className="SyllabusCard-title">
            <Link href={courseUrl(course)}>{course.title}</Link>
          </h3>
          {course.summary ? <p className="SyllabusCard-summary">{course.summary}</p> : null}
          <ul className="SyllabusFacts">
            <li>
              <Icon name="fas fa-list-ol" /> {t('course.lessons', { count: course.lessonCount })}
            </li>
            {course.difficulty ? <li>{t('difficulty.' + course.difficulty)}</li> : null}
            {course.estimatedMinutes ? (
              <li>
                <Icon name="far fa-clock" /> {duration(course.estimatedMinutes)}
              </li>
            ) : null}
            {course.instructor ? <li>{t('course.by', { name: course.instructor.displayName })}</li> : null}
          </ul>
          {me ? (
            <div className="SyllabusCard-progress">
              <ProgressBar percent={me.completedAt ? 100 : me.percent} compact />
              <Link className="Button Button--link SyllabusCard-continue" href={href}>
                {me.completedAt ? t('course.completed') : me.done ? t('course.continue') : t('course.start')}
              </Link>
            </div>
          ) : null}
        </div>
      </article>
    );
  }
}
