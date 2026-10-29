import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import Button from 'flarum/common/components/Button';
import LinkButton from 'flarum/common/components/LinkButton';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import Select from 'flarum/common/components/Select';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import humanTime from 'flarum/common/helpers/humanTime';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import { courseUrl, lessonUrl, review, students, submissions, teaching } from '../../common/api';
import type { CourseCard, QuizResult, Student, Submission } from '../../common/api';
import ProgressBar from '../components/ProgressBar';
import { avatar, failed, fileSize, t } from '../components/util';

type Tab = 'courses' | 'review' | 'students';

/**
 * /courses/teach — the instructor panel, on the forum: the courses you teach
 * (drafts too), assignments waiting for review, and each course's students
 * and quiz results. No admin access needed.
 */
export default class TeachPage extends Page {
  tab: Tab = 'courses';
  courses: CourseCard[] | null = null;
  queue: Submission[] | null = null;
  queueNext: number | null = null;
  queueStatus = 'pending';
  feedback: Record<number, string> = {};
  reviewing = 0;
  courseId = 0;
  roster: Student[] | null = null;
  rosterNext: number | null = null;
  quizzes: QuizResult[] = [];
  total = 0;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    const title = extractText(t('teach.title'));
    app.setTitle(title);
    app.history.push('teach', title);

    if (!app.forum.attribute('canCreateCourses')) return;

    this.courseId = Number(m.route.param('course')) || 0;
    const tab = m.route.param('tab');
    this.tab = this.courseId ? 'students' : tab === 'review' ? 'review' : 'courses';

    teaching().then(({ data }) => {
      this.courses = data;
      if (this.tab === 'students' && !this.courseId && data[0]) this.courseId = data[0].id;
      m.redraw();
    }, failed);
    if (this.tab === 'review') this.loadQueue();
    if (this.tab === 'students' && this.courseId) this.loadRoster();
  }

  switchTo(tab: Tab) {
    this.tab = tab;
    if (tab === 'review' && !this.queue) this.loadQueue();
    if (tab === 'students') {
      if (!this.courseId && this.courses?.[0]) this.courseId = this.courses[0].id;
      if (this.courseId && !this.roster) this.loadRoster();
    }
  }

  loadQueue(more = false) {
    const params: Record<string, unknown> = { filter: { status: this.queueStatus } };
    if (more && this.queueNext) params.page = { offset: this.queueNext };
    if (!more) this.queue = null;
    submissions(params).then(({ data, meta }) => {
      this.queue = more ? [...(this.queue || []), ...data] : data;
      this.queueNext = meta.next;
      m.redraw();
    }, failed);
  }

  loadRoster(more = false) {
    if (!more) this.roster = null;
    students(this.courseId, more ? this.rosterNext || 0 : 0).then(({ data, meta }) => {
      this.roster = more ? [...(this.roster || []), ...data] : data;
      this.rosterNext = meta.next;
      this.quizzes = meta.quizzes;
      this.total = meta.total;
      m.redraw();
    }, failed);
  }

  view(): Mithril.Children {
    if (!app.forum.attribute('canCreateCourses')) {
      return (
        <PageStructure className="SyllabusTeachPage" sidebar={() => <IndexSidebar />}>
          <p className="SyllabusEmpty">{t('teach.not_allowed')}</p>
        </PageStructure>
      );
    }

    return (
      <PageStructure className="SyllabusTeachPage" sidebar={() => <IndexSidebar />}>
        <div className="SyllabusPageHead">
          <h2 className="SyllabusPageHead-title">{t('teach.title')}</h2>
          <LinkButton className="Button Button--primary" icon="fas fa-plus" href={app.route('syllabus.new')}>
            {t('catalogue.new')}
          </LinkButton>
        </div>
        <div className="SyllabusTabs" role="tablist">
          {(['courses', 'review', 'students'] as Tab[]).map((tab) => (
            <Button
              className={'Button Button--flat' + (this.tab === tab ? ' active' : '')}
              role="tab"
              aria-selected={this.tab === tab}
              onclick={() => this.switchTo(tab)}
            >
              {t('teach.tabs.' + tab)}
              {tab === 'review' && this.pending() ? <span className="SyllabusCount">{this.pending()}</span> : null}
            </Button>
          ))}
        </div>
        {this.tab === 'courses' ? this.coursesTab() : this.tab === 'review' ? this.reviewTab() : this.studentsTab()}
      </PageStructure>
    );
  }

  pending(): number {
    return (this.courses || []).reduce((n, c) => n + (c.pendingCount || 0), 0);
  }

  coursesTab(): Mithril.Children {
    if (!this.courses) return <LoadingIndicator display="block" />;
    if (!this.courses.length) return <p className="SyllabusEmpty">{t('teach.no_courses')}</p>;

    return (
      <table className="SyllabusTable">
        <thead>
          <tr>
            <th>{t('teach.course')}</th>
            <th>{t('teach.lessons')}</th>
            <th>{t('teach.students')}</th>
            <th>{t('teach.completed')}</th>
            <th>{t('teach.waiting')}</th>
            <th>
              <span className="sr-only">{t('teach.actions')}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          {this.courses.map((course) => (
            <tr>
              <td>
                <Link href={courseUrl(course)}>{course.title}</Link>{' '}
                {course.status === 'draft' ? <span className="SyllabusBadge SyllabusBadge--draft">{t('course.draft')}</span> : null}
              </td>
              <td>{course.lessonCount}</td>
              <td>{course.enrolmentCount}</td>
              <td>{course.completedCount || 0}</td>
              <td>{course.pendingCount ? <strong>{course.pendingCount}</strong> : 0}</td>
              <td className="SyllabusTable-actions">
                <LinkButton
                  className="Button Button--link"
                  icon="fas fa-pen"
                  href={app.route('syllabus.edit', { id: course.id + '-' + course.slug })}
                >
                  {t('course.edit')}
                </LinkButton>
                <Button
                  className="Button Button--link"
                  icon="fas fa-user-graduate"
                  onclick={() => {
                    this.courseId = course.id;
                    this.roster = null;
                    this.switchTo('students');
                  }}
                >
                  {t('teach.tabs.students')}
                </Button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    );
  }

  reviewTab(): Mithril.Children {
    const statuses: Record<string, string> = {};
    ['pending', 'needs_work', 'passed', 'all'].forEach((s) => (statuses[s] = extractText(t('teach.filter_' + s))));

    return (
      <div className="SyllabusReview">
        <div className="SyllabusToolbar">
          <Select
            options={statuses}
            value={this.queueStatus}
            aria-label={extractText(t('teach.filter'))}
            onchange={(v: string) => {
              this.queueStatus = v;
              this.loadQueue();
            }}
          />
        </div>
        {!this.queue ? <LoadingIndicator display="block" /> : null}
        {this.queue && !this.queue.length ? <p className="SyllabusEmpty">{t('teach.nothing_to_review')}</p> : null}
        {(this.queue || []).map((s) => this.submission(s))}
        {this.queueNext ? (
          <Button className="Button SyllabusMore" onclick={() => this.loadQueue(true)}>
            {t('catalogue.more')}
          </Button>
        ) : null}
      </div>
    );
  }

  submission(s: Submission): Mithril.Children {
    return (
      <div className={'SyllabusSubmission SyllabusSubmission--' + s.status} key={s.id}>
        <div className="SyllabusSubmission-head">
          {s.user ? (
            <span className="SyllabusPerson">
              {avatar(s.user)} {s.user.displayName}
            </span>
          ) : null}
          {s.course && s.lesson ? <Link href={lessonUrl(s.course, s.lesson)}>{s.course.title + ' · ' + s.lesson.title}</Link> : null}
          <span className={'SyllabusStatus SyllabusStatus--' + s.status}>{t('assignment.status.' + s.status)}</span>
          {s.createdAt ? <span className="helpText">{humanTime(new Date(s.createdAt))}</span> : null}
        </div>
        {/* A student's text, shown as text: never HTML. */}
        {s.body ? <div className="SyllabusSubmission-body">{s.body}</div> : null}
        {s.files.length ? (
          <ul className="SyllabusFileList">
            {s.files.map((file) => (
              <li>
                <a href={file.url} rel="noopener" download>
                  <Icon name="fas fa-file-arrow-down" /> {file.name}
                </a>{' '}
                <span className="helpText">{fileSize(file.size)}</span>
              </li>
            ))}
          </ul>
        ) : null}
        <div className="SyllabusSubmission-review">
          <textarea
            className="FormControl"
            rows={3}
            maxlength={5000}
            placeholder={extractText(t('teach.feedback'))}
            aria-label={extractText(t('teach.feedback'))}
            value={this.feedback[s.id] ?? s.feedback ?? ''}
            oninput={(e: InputEvent) => (this.feedback[s.id] = (e.target as HTMLTextAreaElement).value)}
          />
          <div className="SyllabusSubmission-buttons">
            <Button className="Button Button--primary" icon="fas fa-check" loading={this.reviewing === s.id} onclick={() => this.review(s, 'passed')}>
              {t('teach.pass')}
            </Button>
            <Button className="Button" icon="fas fa-rotate-left" disabled={this.reviewing === s.id} onclick={() => this.review(s, 'needs_work')}>
              {t('teach.needs_work')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  review(s: Submission, status: 'passed' | 'needs_work') {
    this.reviewing = s.id;
    review(s.id, status, this.feedback[s.id] ?? s.feedback ?? '').then(
      ({ data }) => {
        this.reviewing = 0;
        if (this.queueStatus === 'pending') {
          this.queue = (this.queue || []).filter((x) => x.id !== s.id);
          const course = (this.courses || []).find((c) => c.id === s.courseId);
          if (course && course.pendingCount) course.pendingCount--;
        } else {
          this.queue = (this.queue || []).map((x) => (x.id === s.id ? data : x));
        }
        app.alerts.show({ type: 'success' }, t('teach.reviewed'));
        m.redraw();
      },
      (e: any) => {
        this.reviewing = 0;
        failed(e);
        m.redraw();
      }
    );
  }

  studentsTab(): Mithril.Children {
    if (!this.courses) return <LoadingIndicator display="block" />;
    if (!this.courses.length) return <p className="SyllabusEmpty">{t('teach.no_courses')}</p>;
    const options: Record<string, string> = {};
    this.courses.forEach((c) => (options[String(c.id)] = c.title));

    return (
      <div className="SyllabusStudents">
        <div className="SyllabusToolbar">
          <Select
            options={options}
            value={String(this.courseId)}
            aria-label={extractText(t('teach.course'))}
            onchange={(v: string) => {
              this.courseId = Number(v);
              this.loadRoster();
            }}
          />
        </div>
        {this.quizzes.length ? (
          <div className="SyllabusQuizResults">
            <h3 className="SyllabusHeading">{t('teach.quiz_results')}</h3>
            <ul>
              {this.quizzes.map((q) => (
                <li>
                  {q.lessonTitle}: {t('teach.quiz_passed', { count: q.passed })}{' '}
                  {q.quizTitle ? <a href={app.route('brainstorm.quiz', { id: q.quizId + '-' + q.quizSlug })}>{t('teach.quiz_details')}</a> : null}
                </li>
              ))}
            </ul>
          </div>
        ) : null}
        {!this.roster ? <LoadingIndicator display="block" /> : null}
        {this.roster && !this.roster.length ? <p className="SyllabusEmpty">{t('teach.no_students')}</p> : null}
        {this.roster && this.roster.length ? (
          <table className="SyllabusTable">
            <thead>
              <tr>
                <th>{t('teach.student')}</th>
                <th>{t('teach.progress')}</th>
                <th>{t('teach.last_seen')}</th>
              </tr>
            </thead>
            <tbody>
              {this.roster.map((row) => (
                <tr>
                  <td>
                    {row.user ? (
                      <Link className="SyllabusPerson" href={app.route('user', { username: row.user.slug })}>
                        {avatar(row.user)} {row.user.displayName}
                      </Link>
                    ) : null}
                  </td>
                  <td>
                    {row.completedAt ? (
                      <span className="SyllabusStatus SyllabusStatus--passed">{t('course.completed')}</span>
                    ) : (
                      <ProgressBar percent={row.percent} done={row.done} total={this.total} />
                    )}
                  </td>
                  <td>{row.lastSeenAt ? humanTime(new Date(row.lastSeenAt)) : '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : null}
        {this.rosterNext ? (
          <Button className="Button SyllabusMore" onclick={() => this.loadRoster(true)}>
            {t('catalogue.more')}
          </Button>
        ) : null}
      </div>
    );
  }
}
