import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import humanTime from 'flarum/common/helpers/humanTime';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import { upload } from '../../common/api';
import type { Lesson, Submission } from '../../common/api';
import { failed, fileSize, t } from './util';

/** An assignment lesson's hand-in form and the student's own submissions with their reviews. */
export default class Assignment extends Component<{ lesson: Lesson; onchange: () => void }> {
  text = '';
  files: File[] = [];
  busy = false;

  view(): Mithril.Children {
    const lesson = this.attrs.lesson;
    const mine = lesson.submissions || [];
    const types = String(app.forum.attribute('syllabusFileTypes') || '');
    const maxMb = Number(app.forum.attribute('syllabusMaxFileMb') || 10);

    return (
      <section className="SyllabusAssignment">
        <h3 className="SyllabusHeading">{t('assignment.title')}</h3>

        {!lesson.enrolled ? <p className="SyllabusEmpty">{t('assignment.enrol_first')}</p> : null}

        {lesson.canSubmit ? (
          <form className="SyllabusAssignment-form" onsubmit={(e: Event) => this.submit(e)}>
            <label className="sr-only" for="syllabus-submission">
              {t('assignment.text_label')}
            </label>
            <textarea
              id="syllabus-submission"
              className="FormControl"
              rows={6}
              maxlength={20000}
              placeholder={extractText(t('assignment.text_placeholder'))}
              value={this.text}
              oninput={(e: InputEvent) => (this.text = (e.target as HTMLTextAreaElement).value)}
            />
            <div className="SyllabusAssignment-files">
              <label className="Button" for="syllabus-files">
                <Icon name="fas fa-paperclip" /> {t('assignment.attach')}
              </label>
              <input
                id="syllabus-files"
                className="sr-only"
                type="file"
                multiple
                accept={types
                  .split(',')
                  .filter(Boolean)
                  .map((ext) => '.' + ext)
                  .join(',')}
                onchange={(e: Event) => {
                  this.files = Array.from((e.target as HTMLInputElement).files || []).slice(0, 5);
                }}
              />
              <span className="helpText">{t('assignment.limits', { mb: maxMb, types: types.split(',').join(', ') })}</span>
              {this.files.length ? (
                <ul className="SyllabusFileList">
                  {this.files.map((file) => (
                    <li>
                      {file.name} <span className="helpText">{fileSize(file.size)}</span>
                    </li>
                  ))}
                </ul>
              ) : null}
            </div>
            <Button type="submit" className="Button Button--primary" loading={this.busy} disabled={!this.text.trim() && !this.files.length}>
              {t('assignment.submit')}
            </Button>
          </form>
        ) : null}

        {mine.map((submission) => this.submission(submission))}
      </section>
    );
  }

  submission(s: Submission): Mithril.Children {
    return (
      <div className={'SyllabusSubmission SyllabusSubmission--' + s.status}>
        <div className="SyllabusSubmission-head">
          <span className={'SyllabusStatus SyllabusStatus--' + s.status}>{t('assignment.status.' + s.status)}</span>
          {s.createdAt ? <span className="helpText">{humanTime(new Date(s.createdAt))}</span> : null}
        </div>
        {/* Plain text, shown as text: never HTML. */}
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
        {s.feedback || s.reviewer ? (
          <div className="SyllabusSubmission-feedback">
            <strong>{t('assignment.feedback_from', { name: s.reviewer?.displayName || '' })}</strong>
            {s.feedback ? <div className="SyllabusSubmission-body">{s.feedback}</div> : null}
          </div>
        ) : null}
      </div>
    );
  }

  submit(e: Event) {
    e.preventDefault();
    const form = new FormData();
    form.append('body', this.text);
    this.files.forEach((file) => form.append('files[]', file));

    this.busy = true;
    upload(`/lessons/${this.attrs.lesson.id}/submissions`, form).then(
      () => {
        this.text = '';
        this.files = [];
        this.busy = false;
        app.alerts.show({ type: 'success' }, t('assignment.sent'));
        this.attrs.onchange();
      },
      (error: any) => {
        this.busy = false;
        failed(error);
        m.redraw();
      }
    );
  }
}
