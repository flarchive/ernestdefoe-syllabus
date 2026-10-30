import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import Button from 'flarum/common/components/Button';
import LinkButton from 'flarum/common/components/LinkButton';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import Switch from 'flarum/common/components/Switch';
import Select from 'flarum/common/components/Select';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import { deleteLesson, editorMeta, lessonUrl, removeVideo, routeId, saveLesson, showCourse, showLesson, upload } from '../../common/api';
import type { Course, EditableLesson, EditorMeta, LessonType } from '../../common/api';
import SyllabusTextEditor from '../components/SyllabusTextEditor';
import Field from '../components/Field';
import OutlineList from '../components/OutlineList';
import { failed, fileSize, lessonIcons, t } from '../components/util';

type DiscussionMode = 'keep' | 'none' | 'auto' | 'link';

/** /courses/:id/lessons/new and /courses/:id/lessons/:lesson/edit. */
export default class LessonEditorPage extends Page {
  courseId = 0;
  lessonId = 0;
  course: Course | null = null;
  meta: EditorMeta | null = null;
  form: EditableLesson | null = null;
  discussion: DiscussionMode = 'keep';
  discussionLink = '';
  saving = false;
  uploading = false;
  host: any = { editor: null };

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.courseId = routeId(m.route.param('id'));
    this.lessonId = routeId(m.route.param('lesson'));
    app.setTitle(extractText(t(this.lessonId ? 'lesson_editor.edit_title' : 'lesson_editor.new_title')));

    editorMeta().then(({ data }) => {
      this.meta = data;
      m.redraw();
    }, failed);
    showCourse(this.courseId).then(({ data }) => {
      this.course = data;
      m.redraw();
    }, failed);

    if (this.lessonId) {
      showLesson(this.lessonId, true).then(({ data }) => {
        this.form = data.edit || null;
        this.discussion = data.edit?.discussionId ? 'keep' : 'none';
        m.redraw();
      }, failed);
    } else {
      const type = m.route.param('type') as LessonType;
      this.form = {
        title: '',
        type: ['text', 'video', 'assignment', 'quiz'].includes(type) ? type : 'text',
        body: '',
        videoUrl: '',
        videoFile: null,
        quizId: null,
        status: 'draft',
        durationMinutes: null,
        discussionId: null,
      };
      this.discussion = 'none';
    }
  }

  view(): Mithril.Children {
    if (!app.forum.attribute('canCreateCourses')) {
      return (
        <PageStructure className="SyllabusEditorPage SyllabusLessonEditorPage">
          <p className="SyllabusEmpty">{t('teach.not_allowed')}</p>
        </PageStructure>
      );
    }

    const ready = this.meta && this.course && this.form;

    return (
      <PageStructure
        className="SyllabusEditorPage SyllabusLessonEditorPage"
        loading={!ready}
        sidebar={() =>
          this.course ? (
            <nav className="SyllabusSide">
              <Link className="SyllabusSide-course" href={app.route('syllabus.edit', { id: this.course.id + '-' + this.course.slug })}>
                <Icon name="fas fa-arrow-left" /> {this.course.title}
              </Link>
              <OutlineList course={this.course} items={this.course.outline} current={this.lessonId} compact />
            </nav>
          ) : null
        }
      >
        {ready ? this.content() : null}
      </PageStructure>
    );
  }

  content(): Mithril.Children {
    const f = this.form!;
    const meta = this.meta!;
    const types: LessonType[] = ['text', 'video', 'assignment', ...(meta.quizzesEnabled || f.type === 'quiz' ? (['quiz'] as LessonType[]) : [])];
    const quizzes: Record<string, string> = { '': extractText(t('lesson_editor.pick_quiz')) };
    meta.quizzes.forEach((q) => (quizzes[String(q.id)] = q.title + (q.status === 'draft' ? ' (' + extractText(t('course.draft')) + ')' : '')));
    if (f.quizId && !quizzes[String(f.quizId)]) quizzes[String(f.quizId)] = '#' + f.quizId;

    return (
      <div className="SyllabusEditor">
        <div className="SyllabusPageHead">
          <h2 className="SyllabusPageHead-title">{t(this.lessonId ? 'lesson_editor.edit_title' : 'lesson_editor.new_title')}</h2>
          {this.lessonId && this.course ? (
            <LinkButton className="Button" icon="fas fa-eye" href={lessonUrl(this.course, { id: this.lessonId })}>
              {t('editor.view')}
            </LinkButton>
          ) : null}
        </div>

        <form className="Form" onsubmit={(e: Event) => this.save(e)}>
          <div className="Form-group">
            <label>{t('lesson_editor.type')}</label>
            <div className="SyllabusTypes" role="radiogroup">
              {types.map((type) => (
                <Button
                  className={'Button' + (f.type === type ? ' Button--primary' : '')}
                  icon={lessonIcons[type]}
                  role="radio"
                  aria-checked={f.type === type}
                  onclick={() => (f.type = type)}
                >
                  {t('types.' + type)}
                </Button>
              ))}
            </div>
          </div>

          {Field(
            t('editor.title'),
            <input
              id="syl-lesson-title"
              className="FormControl"
              maxlength={200}
              required
              value={f.title}
              oninput={(e: InputEvent) => (f.title = (e.target as HTMLInputElement).value)}
            />,
            null,
            'syl-lesson-title'
          )}

          {f.type === 'video' ? this.videoFields(f, meta) : null}

          {f.type === 'quiz'
            ? Field(
                t('lesson_editor.quiz'),
                <Select options={quizzes} value={String(f.quizId || '')} onchange={(v: string) => (f.quizId = v ? Number(v) : null)} />,
                meta.quizzes.length ? t('lesson_editor.quiz_help') : t('lesson_editor.no_quizzes')
              )
            : null}

          <div className="Form-group">
            <label>{t('lesson_editor.body_' + f.type)}</label>
            <div className="SyllabusTextEditor">
              <SyllabusTextEditor composer={this.host} value={f.body} placeholder="" onchange={(value: string) => (f.body = value)} />
            </div>
          </div>

          {Field(
            t('lesson_editor.duration'),
            <input
              id="syl-duration"
              className="FormControl SyllabusEditor-short"
              type="number"
              min="0"
              max="1440"
              value={f.durationMinutes ?? ''}
              oninput={(e: InputEvent) => {
                const v = (e.target as HTMLInputElement).value;
                f.durationMinutes = v === '' ? null : Number(v);
              }}
            />,
            null,
            'syl-duration'
          )}

          <fieldset className="SyllabusFieldset">
            <legend>{t('lesson_editor.discussion')}</legend>
            {(f.discussionId ? (['keep', 'none', 'link'] as DiscussionMode[]) : (['none', 'auto', 'link'] as DiscussionMode[])).map((mode) => (
              <label className="checkbox">
                <input type="radio" name="syl-discussion" checked={this.discussion === mode} onchange={() => (this.discussion = mode)} />
                {t('lesson_editor.discussion_' + mode)}
              </label>
            ))}
            {this.discussion === 'link' ? (
              <input
                className="FormControl"
                placeholder={extractText(t('lesson_editor.discussion_link_placeholder'))}
                aria-label={extractText(t('lesson_editor.discussion_link_placeholder'))}
                value={this.discussionLink}
                oninput={(e: InputEvent) => (this.discussionLink = (e.target as HTMLInputElement).value)}
              />
            ) : null}
            <div className="helpText">{t('lesson_editor.discussion_help')}</div>
          </fieldset>

          <div className="SyllabusEditor-save">
            <Switch state={f.status === 'published'} onchange={(v: boolean) => (f.status = v ? 'published' : 'draft')}>
              {t('editor.published')}
            </Switch>
            <Button type="submit" className="Button Button--primary" loading={this.saving}>
              {t('editor.save')}
            </Button>
          </div>
        </form>

        {this.lessonId ? (
          <div className="SyllabusDanger">
            <Button className="Button Button--danger" icon="fas fa-trash" onclick={() => this.destroy()}>
              {t('lesson_editor.delete')}
            </Button>
          </div>
        ) : null}
      </div>
    );
  }

  videoFields(f: EditableLesson, meta: EditorMeta): Mithril.Children {
    return (
      <fieldset className="SyllabusFieldset">
        <legend>{t('lesson_editor.video')}</legend>
        <input
          className="FormControl"
          type="url"
          placeholder="https://www.youtube.com/watch?v=…"
          aria-label={extractText(t('lesson_editor.video_url'))}
          value={f.videoUrl}
          oninput={(e: InputEvent) => (f.videoUrl = (e.target as HTMLInputElement).value)}
        />
        <div className="helpText">{t('lesson_editor.video_url_help')}</div>
        {meta.maxVideoMb > 0 ? (
          <div className="SyllabusEditor-row">
            {f.videoFile ? (
              <>
                <span>
                  <Icon name="fas fa-film" /> {f.videoFile.name} <span className="helpText">{fileSize(f.videoFile.size)}</span>
                </span>
                <Button className="Button Button--link" onclick={() => this.dropVideo()}>
                  {t('lesson_editor.video_remove')}
                </Button>
              </>
            ) : this.lessonId ? (
              <>
                <label className="Button" for="syl-video-file">
                  <Icon name="fas fa-upload" /> {this.uploading ? t('lesson_editor.uploading') : t('lesson_editor.video_upload')}
                </label>
                <input
                  id="syl-video-file"
                  className="sr-only"
                  type="file"
                  accept="video/mp4,video/webm,video/ogg"
                  disabled={this.uploading}
                  onchange={(e: Event) => this.uploadVideo((e.target as HTMLInputElement).files?.[0])}
                />
                <span className="helpText">{t('lesson_editor.video_limits', { mb: meta.maxVideoMb })}</span>
              </>
            ) : (
              <span className="helpText">{t('lesson_editor.video_after_save')}</span>
            )}
          </div>
        ) : null}
      </fieldset>
    );
  }

  save(e: Event) {
    e.preventDefault();
    const f = this.form!;
    const data: Record<string, unknown> = {
      title: f.title,
      type: f.type,
      body: f.body,
      status: f.status,
      durationMinutes: f.durationMinutes,
    };
    if (f.type === 'video') data.videoUrl = f.videoUrl;
    if (f.type === 'quiz') data.quizId = f.quizId;
    if (this.discussion !== 'keep') data.discussion = { mode: this.discussion, link: this.discussionLink };

    this.saving = true;
    saveLesson(data, this.courseId, this.lessonId || undefined).then(
      ({ data: lesson }) => {
        this.saving = false;
        if (lesson.discussionError) {
          app.alerts.show({ type: 'error' }, t('lesson_editor.discussion_failed', { reason: lesson.discussionError }));
        } else {
          app.alerts.show({ type: 'success' }, t('editor.saved'));
        }
        if (!this.lessonId) {
          m.route.set(app.route('syllabus.lesson.edit', { id: lesson.course.id + '-' + lesson.course.slug, lesson: lesson.id + '-' + lesson.slug }));
          return;
        }
        this.form = lesson.edit || this.form;
        this.discussion = lesson.edit?.discussionId ? 'keep' : 'none';
        m.redraw();
      },
      (error: any) => {
        this.saving = false;
        failed(error);
        m.redraw();
      }
    );
  }

  uploadVideo(file?: File) {
    if (!file || !this.lessonId) return;
    const form = new FormData();
    form.append('video', file);
    this.uploading = true;
    upload<{ data: { videoFile: EditableLesson['videoFile'] } }>(`/lessons/${this.lessonId}/video`, form)
      .then(({ data }) => {
        this.form!.videoFile = data.videoFile;
      }, failed)
      .finally(() => {
        this.uploading = false;
        m.redraw();
      });
  }

  dropVideo() {
    removeVideo(this.lessonId).then(() => {
      this.form!.videoFile = null;
      m.redraw();
    }, failed);
  }

  destroy() {
    if (!confirm(extractText(t('lesson_editor.delete_confirm')))) return;
    deleteLesson(this.lessonId).then(() => m.route.set(app.route('syllabus.edit', { id: this.courseId })), failed);
  }
}
