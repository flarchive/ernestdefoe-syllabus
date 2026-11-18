import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import Button from 'flarum/common/components/Button';
import LinkButton from 'flarum/common/components/LinkButton';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import type Mithril from 'mithril';
import { certificateUrl, courseUrl, enrol, leave, lessonUrl, routeId, showCourse } from '../../common/api';
import type { Course } from '../../common/api';
import OutlineList from '../components/OutlineList';
import ProgressBar from '../components/ProgressBar';
import { avatar, duration, failed, t } from '../components/util';

/** /courses/:id — the overview: what the course is, who teaches it, its outline and your progress. */
export default class CoursePage extends Page {
  course: Course | null = null;
  missing = false;
  busy = false;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.load();
  }

  load() {
    showCourse(routeId(m.route.param('id'))).then(
      ({ data }) => {
        this.course = data;
        app.setTitle(data.title);
        app.history.push('course', data.title);
        m.redraw();
      },
      () => {
        this.missing = true;
        m.redraw();
      }
    );
  }

  toggle(join: boolean) {
    if (!this.course) return;
    if (!join && !confirm(t('course.leave_confirm') as unknown as string)) return;
    this.busy = true;
    (join ? enrol : leave)(this.course.id).then(
      ({ data }) => {
        this.course = data;
        this.busy = false;
        m.redraw();
      },
      (e: any) => {
        this.busy = false;
        failed(e);
        m.redraw();
      }
    );
  }

  view(): Mithril.Children {
    const course = this.course;

    if (!course) {
      return (
        <PageStructure className="SyllabusCoursePage" loading={!this.missing}>
          {this.missing ? <p className="SyllabusEmpty">{t('course.missing')}</p> : null}
        </PageStructure>
      );
    }

    return (
      <PageStructure className="SyllabusCoursePage" hero={() => this.hero(course)} sidebar={() => this.sidebar(course)}>
        {course.descriptionHtml ? <div className="SyllabusProse Post-body">{m.trust(course.descriptionHtml)}</div> : null}
        <h3 className="SyllabusHeading">{t('course.outline')}</h3>
        {this.offer(course)}
        {course.outline.length ? <OutlineList course={course} items={course.outline} /> : <p className="SyllabusEmpty">{t('course.no_lessons')}</p>}
      </PageStructure>
    );
  }

  hero(course: Course): Mithril.Children {
    return (
      <header className={'Hero SyllabusHero' + (course.coverUrl ? ' SyllabusHero--cover' : '')}>
        {course.coverUrl ? <img className="SyllabusHero-cover" src={course.coverUrl} alt="" /> : null}
        <div className="container">
          <div className="containerNarrow">
            <div className="SyllabusHero-crumbs">
              <Link href={app.route('syllabus.index')}>{t('catalogue.title')}</Link>
              {course.category ? <span> · {course.category.name}</span> : null}
            </div>
            <h1 className="Hero-title">{course.title}</h1>
            {course.summary ? <div className="Hero-subtitle">{course.summary}</div> : null}
          </div>
        </div>
      </header>
    );
  }

  /**
   * A paid course the member doesn't hold: Kiosk's offer, drawn by Kiosk's own
   * component so every Buy button on the forum is the same. Nothing at all
   * without Kiosk (the course then simply stays locked).
   */
  offer(course: Course): Mithril.Children {
    if (course.access !== 'paid' || course.meetsRule || !app.session.user) return null;

    let LockedNotice: any = null;
    try {
      const mod = (window as any).flarum?.reg?.get('ernestdefoe-kiosk', 'forum/components/LockedNotice');
      LockedNotice = mod?.default || mod;
    } catch {
      LockedNotice = null;
    }

    return LockedNotice ? <LockedNotice type="syllabus-course" id={course.id} name={course.title} /> : null;
  }

  sidebar(course: Course): Mithril.Children {
    const me = course.me;
    const next = course.nextLesson;

    return (
      <nav className="SyllabusSide">
        <div className="SyllabusSide-card">
          {course.status === 'draft' ? <p className="SyllabusBadge SyllabusBadge--draft">{t('course.draft_notice')}</p> : null}
          {me ? (
            <>
              <ProgressBar percent={me.completedAt ? 100 : me.percent} done={me.done} total={course.lessonCount} />
              {course.certificateCode ? (
                <LinkButton className="Button Button--primary Button--block" icon="fas fa-award" href={certificateUrl(course.certificateCode)}>
                  {t('course.view_certificate')}
                </LinkButton>
              ) : null}
              {next ? (
                <LinkButton
                  className={'Button Button--block' + (course.certificateCode ? '' : ' Button--primary')}
                  icon="fas fa-play"
                  href={lessonUrl(course, next)}
                >
                  {me.done ? t('course.continue') : t('course.start')}
                </LinkButton>
              ) : null}
            </>
          ) : course.canEnrol ? (
            <Button
              className="Button Button--primary Button--block"
              icon="fas fa-right-to-bracket"
              loading={this.busy}
              onclick={() => this.toggle(true)}
            >
              {t('course.enrol')}
            </Button>
          ) : !app.session.user ? (
            <Button
              className="Button Button--primary Button--block"
              onclick={() => app.modal.show(() => import('flarum/forum/components/LogInModal'))}
            >
              {t('course.login_to_enrol')}
            </Button>
          ) : (
            <p className="SyllabusSide-note">
              <Icon name="fas fa-lock" />{' '}
              {course.access === 'paid'
                ? t('course.paid_only')
                : course.groups.length
                  ? t('course.groups_only', { groups: course.groups.map((g) => g.name).join(', ') })
                  : t('course.no_access')}
            </p>
          )}
          {!me && course.previewLessons ? <p className="SyllabusSide-note">{t('course.preview_note', { count: course.previewLessons })}</p> : null}
        </div>

        <ul className="SyllabusFacts SyllabusFacts--stacked">
          <li>
            <Icon name="fas fa-list-ol" /> {t('course.lessons', { count: course.lessonCount })}
          </li>
          {course.difficulty ? (
            <li>
              <Icon name="fas fa-signal" /> {t('difficulty.' + course.difficulty)}
            </li>
          ) : null}
          {course.estimatedMinutes ? (
            <li>
              <Icon name="far fa-clock" /> {duration(course.estimatedMinutes)}
            </li>
          ) : null}
          <li>
            <Icon name="fas fa-user-graduate" /> {t('course.students', { count: course.enrolmentCount })}
          </li>
          {course.sequential ? (
            <li>
              <Icon name="fas fa-arrow-down-1-9" /> {t('course.sequential')}
            </li>
          ) : null}
        </ul>

        {course.instructors.length ? (
          <div className="SyllabusSide-instructors">
            <h4>{t('course.instructors')}</h4>
            {course.instructors.map((person) => (
              <Link className="SyllabusPerson" href={app.route('user', { username: person.slug })}>
                {avatar(person)}
                <span>{person.displayName}</span>
              </Link>
            ))}
          </div>
        ) : null}

        {course.canEdit ? (
          <div className="SyllabusSide-actions">
            <LinkButton className="Button Button--block" icon="fas fa-pen" href={app.route('syllabus.edit', { id: course.id + '-' + course.slug })}>
              {t('course.edit')}
            </LinkButton>
            <LinkButton className="Button Button--block" icon="fas fa-person-chalkboard" href={app.route('syllabus.teach') + '?course=' + course.id}>
              {t('teach.title')}
            </LinkButton>
          </div>
        ) : null}
        {me && !me.completedAt ? (
          <Button className="Button Button--link SyllabusSide-leave" loading={this.busy} onclick={() => this.toggle(false)}>
            {t('course.leave')}
          </Button>
        ) : null}
        <Link className="SyllabusSide-back" href={courseUrl(course) === m.route.get() ? app.route('syllabus.index') : courseUrl(course)}>
          <Icon name="fas fa-arrow-left" /> {t('catalogue.back')}
        </Link>
      </nav>
    );
  }
}
