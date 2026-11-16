import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import Button from 'flarum/common/components/Button';
import LinkButton from 'flarum/common/components/LinkButton';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import type Mithril from 'mithril';
import { certificateUrl, completeLesson, courseUrl, enrol, lessonUrl, routeId, showLesson } from '../../common/api';
import type { Lesson } from '../../common/api';
import OutlineList from '../components/OutlineList';
import ProgressBar from '../components/ProgressBar';
import Assignment from '../components/Assignment';
import { duration, failed, lessonIcons, t } from '../components/util';

/** Brainstorm's own quiz card, found at runtime: Syllabus never bundles any of Brainstorm. */
function brainstormScan(): ((root: Element) => void) | null {
  const mod = (globalThis as any).flarum?.reg?.get?.('ernestdefoe-brainstorm', 'forum/embed/scan');
  const fn = mod?.default ?? mod;
  return typeof fn === 'function' ? fn : null;
}

/** /courses/:id/lessons/:lesson — one lesson, or why it is locked. */
export default class LessonPage extends Page {
  lesson: Lesson | null = null;
  missing = false;
  busy = false;
  certificate: string | null = null;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.load();
  }

  load() {
    return showLesson(routeId(m.route.param('lesson'))).then(
      ({ data }) => {
        // A lesson id under another course's address goes to its own.
        if (data.course.id !== routeId(m.route.param('id'))) {
          m.route.set(lessonUrl(data.course, data), {}, { replace: true });
          return;
        }
        this.lesson = data;
        app.setTitle(data.title);
        app.history.push('lesson', data.title);
        m.redraw();
      },
      () => {
        this.missing = true;
        m.redraw();
      }
    );
  }

  view(): Mithril.Children {
    const lesson = this.lesson;

    if (!lesson) {
      return (
        <PageStructure className="SyllabusLessonPage" loading={!this.missing}>
          {this.missing ? <p className="SyllabusEmpty">{t('lesson.missing')}</p> : null}
        </PageStructure>
      );
    }

    return (
      <PageStructure className="SyllabusLessonPage" sidebar={() => this.sidebar(lesson)}>
        <article className="SyllabusLesson">
          <header className="SyllabusLesson-head">
            <div className="SyllabusLesson-type">
              <Icon name={lessonIcons[lesson.type]} /> {t('types.' + lesson.type)}
              {lesson.durationMinutes ? <span> · {duration(lesson.durationMinutes)}</span> : null}
              {lesson.status === 'draft' ? <span className="SyllabusBadge SyllabusBadge--draft">{t('course.draft')}</span> : null}
            </div>
            <h2 className="SyllabusLesson-title">{lesson.title}</h2>
            {lesson.canEdit ? (
              <LinkButton
                className="Button Button--icon Button--link SyllabusLesson-edit"
                icon="fas fa-pen"
                aria-label={t('lesson.edit') as any}
                href={app.route('syllabus.lesson.edit', { id: lesson.course.id + '-' + lesson.course.slug, lesson: lesson.id + '-' + lesson.slug })}
              />
            ) : null}
          </header>

          {lesson.locked ? this.lockedPanel(lesson) : this.content(lesson)}

          <nav className="SyllabusLesson-nav" aria-label={t('lesson.nav') as any}>
            {lesson.prev ? (
              <LinkButton className="Button" icon="fas fa-arrow-left" href={lessonUrl(lesson.course, lesson.prev)}>
                {lesson.prev.title}
              </LinkButton>
            ) : (
              <span />
            )}
            {lesson.next ? (
              <LinkButton className="Button SyllabusLesson-next" href={lessonUrl(lesson.course, lesson.next)}>
                {lesson.next.title} <Icon name="fas fa-arrow-right" />
              </LinkButton>
            ) : null}
          </nav>
        </article>
      </PageStructure>
    );
  }

  sidebar(lesson: Lesson): Mithril.Children {
    return (
      <nav className="SyllabusSide">
        <Link className="SyllabusSide-course" href={courseUrl(lesson.course)}>
          <Icon name="fas fa-arrow-left" /> {lesson.course.title}
        </Link>
        {lesson.enrolled ? <ProgressBar percent={lesson.progress.percent} done={lesson.progress.done} total={lesson.progress.total} /> : null}
        <OutlineList course={lesson.course} items={lesson.outline} current={lesson.id} compact />
      </nav>
    );
  }

  lockedPanel(lesson: Lesson): Mithril.Children {
    const reason = lesson.locked as string;

    return (
      <div className="SyllabusLocked">
        <Icon name="fas fa-lock" />
        <p>{t('lesson.locked_' + reason)}</p>
        {reason === 'enrol' && lesson.canEnrol ? (
          <Button
            className="Button Button--primary"
            loading={this.busy}
            onclick={() => {
              this.busy = true;
              enrol(lesson.course.id)
                .then(() => this.load(), failed)
                .finally(() => {
                  this.busy = false;
                  m.redraw();
                });
            }}
          >
            {t('course.enrol')}
          </Button>
        ) : null}
        {reason === 'login' ? (
          <Button className="Button Button--primary" onclick={() => app.modal.show(() => import('flarum/forum/components/LogInModal'))}>
            {t('course.login_to_enrol')}
          </Button>
        ) : null}
        {reason === 'access' ? (
          <LinkButton className="Button" href={courseUrl(lesson.course)}>
            {t('lesson.see_course')}
          </LinkButton>
        ) : null}
      </div>
    );
  }

  content(lesson: Lesson): Mithril.Children {
    return (
      <>
        {lesson.type === 'video' ? this.video(lesson) : null}
        {lesson.bodyHtml ? <div className="SyllabusProse Post-body">{m.trust(lesson.bodyHtml)}</div> : null}
        {lesson.type === 'quiz' ? this.quiz(lesson) : null}
        {lesson.type === 'assignment' ? <Assignment lesson={lesson} onchange={() => this.load()} /> : null}

        {lesson.canComplete ? (
          <div className={'SyllabusComplete' + (lesson.completed ? ' is-done' : '')}>
            {lesson.completed ? (
              <>
                <span className="SyllabusComplete-done">
                  <Icon name="fas fa-circle-check" /> {t('lesson.completed')}
                </span>
                <Button className="Button Button--link" loading={this.busy} onclick={() => this.complete(lesson, false)}>
                  {t('lesson.undo')}
                </Button>
              </>
            ) : (
              <Button className="Button Button--primary" icon="fas fa-check" loading={this.busy} onclick={() => this.complete(lesson, true)}>
                {t('lesson.mark_complete')}
              </Button>
            )}
          </div>
        ) : lesson.completed ? (
          <div className="SyllabusComplete is-done">
            <span className="SyllabusComplete-done">
              <Icon name="fas fa-circle-check" /> {t('lesson.completed')}
            </span>
          </div>
        ) : null}

        {this.certificate ? (
          <div className="SyllabusCongrats">
            <Icon name="fas fa-award" />
            <p>{t('lesson.course_done')}</p>
            <LinkButton className="Button Button--primary" href={certificateUrl(this.certificate)}>
              {t('course.view_certificate')}
            </LinkButton>
          </div>
        ) : null}

        {lesson.discussion ? (
          <div className="SyllabusDiscussion">
            <Icon name="far fa-comments" />
            <div>
              <strong>{t('lesson.ask')}</strong>
              <p>{t('lesson.ask_text', { count: Math.max(0, lesson.discussion.commentCount - 1) })}</p>
            </div>
            <LinkButton className="Button" href={app.route('discussion', { id: lesson.discussion.id + '-' + lesson.discussion.slug })}>
              {t('lesson.open_discussion')}
            </LinkButton>
          </div>
        ) : null}
      </>
    );
  }

  video(lesson: Lesson): Mithril.Children {
    const video = lesson.video;
    if (!video) return null;

    if (video.provider === 'file') {
      return (
        <div className="SyllabusVideo">
          <video controls preload="metadata" playsinline src={video.fileUrl} />
        </div>
      );
    }

    return (
      <div className="SyllabusVideo">
        <iframe
          src={video.embedUrl}
          title={lesson.title}
          referrerpolicy="strict-origin-when-cross-origin"
          allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"
          allowfullscreen
        />
      </div>
    );
  }

  quiz(lesson: Lesson): Mithril.Children {
    const quiz = lesson.quiz;
    if (!quiz) return <p className="SyllabusEmpty">{t('lesson.quiz_missing')}</p>;

    return (
      <div className="SyllabusQuiz">
        <div
          className="SyllabusQuiz-embed"
          oncreate={(vnode: Mithril.VnodeDOM) => {
            const scan = brainstormScan();
            if (scan) scan(vnode.dom);
          }}
        >
          <div className="Post-body">
            <p>
              <a href={quiz.url}>{quiz.title}</a>
            </p>
          </div>
        </div>
        {!lesson.completed && lesson.enrolled ? (
          <div className="SyllabusQuiz-check">
            <p>{t('lesson.quiz_hint')}</p>
            <Button className="Button" icon="fas fa-rotate" loading={this.busy} onclick={() => this.recheck()}>
              {t('lesson.quiz_check')}
            </Button>
          </div>
        ) : null}
      </div>
    );
  }

  recheck() {
    this.busy = true;
    this.load().finally(() => {
      this.busy = false;
      m.redraw();
    });
  }

  complete(lesson: Lesson, done: boolean) {
    this.busy = true;
    completeLesson(lesson.id, done).then(
      ({ data }) => {
        lesson.completed = data.completed;
        lesson.progress = data.progress;
        lesson.outline.forEach((item) => {
          if (item.kind === 'lesson' && item.id === lesson.id) item.completed = data.completed;
        });
        if (data.certificateCode) this.certificate = data.certificateCode;
        this.busy = false;
        // An "unlock in order" course opens the next lesson now: refresh the outline.
        if (done && lesson.course.sequential) this.load();
        m.redraw();
      },
      (e: any) => {
        this.busy = false;
        failed(e);
        m.redraw();
      }
    );
  }
}
