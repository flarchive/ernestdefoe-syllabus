import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import Button from 'flarum/common/components/Button';
import LinkButton from 'flarum/common/components/LinkButton';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import Switch from 'flarum/common/components/Switch';
import Select from 'flarum/common/components/Select';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import { courseUrl, deleteCourse, editorMeta, removeCover, routeId, saveCourse, saveOutline, showCourse, upload } from '../../common/api';
import type { Course, EditableCourse, EditorMeta, OutlineItem, Person } from '../../common/api';
import Sortable from '../../common/sortable';
import SyllabusTextEditor from '../components/SyllabusTextEditor';
import Field from '../components/Field';
import { avatar, failed, lessonIcons, t } from '../components/util';

const blank = (): EditableCourse => ({
  title: '',
  summary: '',
  description: '',
  coverUrl: null,
  hasUploadedCover: false,
  categoryId: null,
  difficulty: null,
  estimatedMinutes: null,
  status: 'draft',
  access: 'everyone' as 'everyone' | 'groups' | 'paid',
  groupIds: [],
  previewLessons: 0,
  sequential: false,
  featured: false,
  notifyNewLessons: true,
  tagId: null,
  badgeId: null,
  instructors: [],
});

type Row = OutlineItem & { key: string };

/** /courses/new and /courses/:id/edit — the course's settings and its drag-and-drop outline. */
export default class CourseEditorPage extends Page {
  id = 0;
  course: Course | null = null;
  form: EditableCourse = blank();
  meta: EditorMeta | null = null;
  rows: Row[] = [];
  saving = false;
  coverUrl: string | null = null;
  host: any = { editor: null };
  sorter!: Sortable<Row>;
  search = '';
  found: Person[] = [];
  searchTimer = 0;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.id = routeId(m.route.param('id'));
    app.setTitle(extractText(t(this.id ? 'editor.edit_title' : 'editor.new_title')));
    this.sorter = new Sortable(
      () => this.rows,
      () => this.saveOutline(),
      extractText(t('editor.sortable'))
    );

    editorMeta().then(({ data }) => {
      this.meta = data;
      if (!this.id && data.defaultTagId) this.form.tagId = null;
      m.redraw();
    }, failed);

    if (this.id) {
      showCourse(this.id, true).then(({ data }) => this.take(data), failed);
    }
  }

  take(course: Course) {
    this.course = course;
    this.form = course.edit || blank();
    this.coverUrl = course.coverUrl;
    this.rows = course.outline.map((item) => ({ ...item, key: item.kind + item.id }));
    m.redraw();
  }

  view(): Mithril.Children {
    if (!app.forum.attribute('canCreateCourses')) {
      return (
        <PageStructure className="SyllabusEditorPage">
          <p className="SyllabusEmpty">{t('teach.not_allowed')}</p>
        </PageStructure>
      );
    }

    const loading = !this.meta || (this.id && !this.course);

    return (
      <PageStructure className="SyllabusEditorPage" sidebar={() => <IndexSidebar />} loading={!!loading}>
        {loading ? null : this.content()}
      </PageStructure>
    );
  }

  content(): Mithril.Children {
    const f = this.form;
    const meta = this.meta!;
    const options = (list: { id: number; name: string }[], none: string) => {
      const out: Record<string, string> = { '': extractText(t(none)) };
      list.forEach((item) => (out[String(item.id)] = item.name));
      return out;
    };
    const levels: Record<string, string> = { '': extractText(t('editor.no_level')) };
    ['beginner', 'intermediate', 'advanced'].forEach((d) => (levels[d] = extractText(t('difficulty.' + d))));

    return (
      <div className="SyllabusEditor">
        <div className="SyllabusPageHead">
          <h2 className="SyllabusPageHead-title">{t(this.id ? 'editor.edit_title' : 'editor.new_title')}</h2>
          {this.course ? (
            <LinkButton className="Button" icon="fas fa-eye" href={courseUrl(this.course)}>
              {t('editor.view')}
            </LinkButton>
          ) : null}
        </div>

        <form className="Form" onsubmit={(e: Event) => this.save(e)}>
          {Field(
            t('editor.title'),
            <input
              id="syl-title"
              className="FormControl"
              maxlength={200}
              required
              value={f.title}
              oninput={(e: InputEvent) => (f.title = (e.target as HTMLInputElement).value)}
            />,
            null,
            'syl-title'
          )}
          {Field(
            t('editor.summary'),
            <textarea
              id="syl-summary"
              className="FormControl"
              rows={2}
              maxlength={500}
              value={f.summary}
              oninput={(e: InputEvent) => (f.summary = (e.target as HTMLTextAreaElement).value)}
            />,
            t('editor.summary_help'),
            'syl-summary'
          )}
          <div className="Form-group">
            <label>{t('editor.description')}</label>
            <div className="SyllabusTextEditor">
              <SyllabusTextEditor composer={this.host} value={f.description} placeholder="" onchange={(value: string) => (f.description = value)} />
            </div>
          </div>

          <fieldset className="SyllabusFieldset">
            <legend>{t('editor.cover')}</legend>
            {this.coverUrl ? <img className="SyllabusEditor-cover" src={this.coverUrl} alt="" /> : null}
            <div className="SyllabusEditor-row">
              {this.course ? (
                <>
                  <label className="Button" for="syl-cover-file">
                    <Icon name="fas fa-upload" /> {t('editor.cover_upload')}
                  </label>
                  <input
                    id="syl-cover-file"
                    className="sr-only"
                    type="file"
                    accept="image/png,image/jpeg,image/gif,image/webp"
                    onchange={(e: Event) => this.uploadCover((e.target as HTMLInputElement).files?.[0])}
                  />
                </>
              ) : null}
              <input
                className="FormControl"
                type="url"
                placeholder="https://"
                aria-label={extractText(t('editor.cover_url'))}
                value={f.hasUploadedCover ? '' : f.coverUrl || ''}
                oninput={(e: InputEvent) => {
                  f.coverUrl = (e.target as HTMLInputElement).value;
                  f.hasUploadedCover = false;
                }}
              />
              {this.coverUrl && this.course ? (
                <Button className="Button Button--link" onclick={() => this.dropCover()}>
                  {t('editor.cover_remove')}
                </Button>
              ) : null}
            </div>
            <div className="helpText">{t(this.course ? 'editor.cover_help' : 'editor.cover_help_new')}</div>
          </fieldset>

          <div className="SyllabusEditor-grid">
            {meta.categories.length
              ? Field(
                  t('editor.category'),
                  <Select
                    options={options(meta.categories, 'editor.no_category')}
                    value={String(f.categoryId || '')}
                    onchange={(v: string) => (f.categoryId = v ? Number(v) : null)}
                  />
                )
              : null}
            {Field(
              t('editor.difficulty'),
              <Select options={levels} value={f.difficulty || ''} onchange={(v: string) => (f.difficulty = v || null)} />
            )}
            {Field(
              t('editor.minutes'),
              <input
                id="syl-minutes"
                className="FormControl"
                type="number"
                min="0"
                max="65535"
                value={f.estimatedMinutes ?? ''}
                oninput={(e: InputEvent) => {
                  const v = (e.target as HTMLInputElement).value;
                  f.estimatedMinutes = v === '' ? null : Number(v);
                }}
              />,
              null,
              'syl-minutes'
            )}
            {Field(
              t('editor.preview'),
              <input
                id="syl-preview"
                className="FormControl"
                type="number"
                min="0"
                max="50"
                value={f.previewLessons}
                oninput={(e: InputEvent) => (f.previewLessons = Number((e.target as HTMLInputElement).value) || 0)}
              />,
              t('editor.preview_help'),
              'syl-preview'
            )}
          </div>

          <fieldset className="SyllabusFieldset">
            <legend>{t('editor.access')}</legend>
            {(meta.kiosk || f.access === 'paid' ? (['everyone', 'groups', 'paid'] as const) : (['everyone', 'groups'] as const)).map((value) => (
              <label className="checkbox">
                <input type="radio" name="syl-access" checked={f.access === value} onchange={() => (f.access = value)} />
                {t('editor.access_' + value)}
              </label>
            ))}
            {f.access === 'groups' ? (
              <div className="SyllabusEditor-groups">
                {meta.groups.map((group) => (
                  <label className="checkbox">
                    <input
                      type="checkbox"
                      checked={f.groupIds.includes(group.id)}
                      onchange={(e: Event) => {
                        const on = (e.target as HTMLInputElement).checked;
                        f.groupIds = on ? [...f.groupIds, group.id] : f.groupIds.filter((id) => id !== group.id);
                      }}
                    />
                    {group.name}
                  </label>
                ))}
              </div>
            ) : null}
            <div className="helpText">{t('editor.access_help')}</div>
          </fieldset>

          <div className="SyllabusEditor-switches">
            <Switch state={f.sequential} onchange={(v: boolean) => (f.sequential = v)}>
              {t('editor.sequential')}
            </Switch>
            <Switch state={f.notifyNewLessons} onchange={(v: boolean) => (f.notifyNewLessons = v)}>
              {t('editor.notify_new_lessons')}
            </Switch>
            {meta.canFeature ? (
              <Switch state={f.featured} onchange={(v: boolean) => (f.featured = v)}>
                {t('editor.featured')}
              </Switch>
            ) : null}
          </div>

          <div className="SyllabusEditor-grid">
            {meta.tags.length
              ? Field(
                  t('editor.tag'),
                  <Select
                    options={options(meta.tags, 'editor.default_tag')}
                    value={String(f.tagId || '')}
                    onchange={(v: string) => (f.tagId = v ? Number(v) : null)}
                  />,
                  t('editor.tag_help')
                )
              : null}
            {meta.canBadge
              ? Field(
                  t('editor.badge'),
                  <Select
                    options={options(meta.badges, 'editor.no_badge')}
                    value={String(f.badgeId || '')}
                    onchange={(v: string) => (f.badgeId = v ? Number(v) : null)}
                  />,
                  t('editor.badge_help')
                )
              : null}
          </div>

          {this.instructorsField()}

          <div className="SyllabusEditor-save">
            <Switch state={f.status === 'published'} onchange={(v: boolean) => (f.status = v ? 'published' : 'draft')}>
              {t('editor.published')}
            </Switch>
            <Button type="submit" className="Button Button--primary" loading={this.saving}>
              {t('editor.save')}
            </Button>
          </div>
        </form>

        {this.course ? this.outline() : <p className="helpText">{t('editor.outline_after_save')}</p>}

        {this.course ? (
          <div className="SyllabusDanger">
            <Button className="Button Button--danger" icon="fas fa-trash" onclick={() => this.destroy()}>
              {t('editor.delete')}
            </Button>
          </div>
        ) : null}
      </div>
    );
  }

  instructorsField(): Mithril.Children {
    const f = this.form;
    const ownerOrManager =
      !this.course || (this.course.instructor && this.course.instructor.id === Number(app.session.user?.id())) || this.meta?.canFeature;
    if (!ownerOrManager) return null;

    return (
      <fieldset className="SyllabusFieldset">
        <legend>{t('editor.co_instructors')}</legend>
        <ul className="SyllabusPeople">
          {f.instructors.map((person) => (
            <li>
              {avatar(person)} {person.displayName}
              <Button
                className="Button Button--icon Button--link"
                icon="fas fa-xmark"
                aria-label={extractText(t('editor.remove'))}
                onclick={() => (f.instructors = f.instructors.filter((p) => p.id !== person.id))}
              />
            </li>
          ))}
        </ul>
        <input
          className="FormControl"
          type="search"
          placeholder={extractText(t('editor.find_member'))}
          aria-label={extractText(t('editor.find_member'))}
          value={this.search}
          oninput={(e: InputEvent) => {
            this.search = (e.target as HTMLInputElement).value;
            window.clearTimeout(this.searchTimer);
            this.searchTimer = window.setTimeout(() => this.findPeople(), 300);
          }}
        />
        {this.found.length ? (
          <ul className="SyllabusPeople SyllabusPeople--found">
            {this.found.map((person) => (
              <li>
                <Button
                  className="Button Button--link"
                  onclick={() => {
                    if (!f.instructors.some((p) => p.id === person.id)) f.instructors = [...f.instructors, person];
                    this.found = [];
                    this.search = '';
                  }}
                >
                  {avatar(person)} {person.displayName}
                </Button>
              </li>
            ))}
          </ul>
        ) : null}
        <div className="helpText">{t('editor.co_instructors_help')}</div>
      </fieldset>
    );
  }

  findPeople() {
    const q = this.search.trim();
    if (q.length < 2) {
      this.found = [];
      m.redraw();
      return;
    }
    app.store.find<any[]>('users', { filter: { q }, page: { limit: 5 } }).then((users) => {
      this.found = users.map((u: any) => ({
        id: Number(u.id()),
        username: u.username(),
        displayName: u.displayName(),
        avatarUrl: u.avatarUrl(),
        slug: u.slug(),
      }));
      m.redraw();
    });
  }

  outline(): Mithril.Children {
    const meta = this.meta!;
    const course = this.course!;
    const types = ['text', 'video', 'assignment', ...(meta.quizzesEnabled ? ['quiz'] : [])];
    const lessonEdit = (row: Row) =>
      app.route('syllabus.lesson.edit', { id: course.id + '-' + course.slug, lesson: row.id + '-' + (row as any).slug });

    return (
      <section className="SyllabusOutlineEditor">
        <h3 className="SyllabusHeading">{t('editor.outline')}</h3>
        <p className="helpText">{t('editor.outline_help')}</p>
        <ol className="SyllabusOutlineEditor-list" data-sortable>
          {this.rows.map((row, index) => (
            <li
              key={row.key}
              className={'SyllabusOutlineEditor-row SyllabusOutlineEditor-row--' + row.kind + (this.sorter.dragging === index ? ' is-dragging' : '')}
            >
              <span
                className="SyllabusOutlineEditor-handle"
                {...this.sorter.handle(index)}
                aria-label={extractText(t('editor.move', { title: row.title }))}
              >
                <Icon name="fas fa-grip-vertical" />
              </span>
              {row.kind === 'section' ? (
                <input
                  className="FormControl SyllabusOutlineEditor-section"
                  value={row.title}
                  maxlength={200}
                  aria-label={extractText(t('editor.section_title'))}
                  oninput={(e: InputEvent) => (row.title = (e.target as HTMLInputElement).value)}
                  onchange={() => this.saveOutline()}
                />
              ) : (
                <Link className="SyllabusOutlineEditor-lesson" href={lessonEdit(row)}>
                  <Icon name={lessonIcons[(row as any).type]} /> {row.title}
                  {(row as any).status === 'draft' ? <span className="SyllabusBadge SyllabusBadge--draft">{t('course.draft')}</span> : null}
                </Link>
              )}
              {row.kind === 'section' ? (
                <Button
                  className="Button Button--icon Button--link"
                  icon="fas fa-trash"
                  aria-label={extractText(t('editor.remove_section'))}
                  onclick={() => {
                    this.rows.splice(index, 1);
                    this.saveOutline();
                  }}
                />
              ) : null}
            </li>
          ))}
        </ol>
        <div className="SyllabusOutlineEditor-add">
          <Button className="Button" icon="fas fa-heading" onclick={() => this.addSection()}>
            {t('editor.add_section')}
          </Button>
          {types.map((type) => (
            <LinkButton
              className="Button"
              icon={lessonIcons[type]}
              href={app.route('syllabus.lesson.new', { id: course.id + '-' + course.slug }) + '?type=' + type}
            >
              {t('editor.add_' + type)}
            </LinkButton>
          ))}
        </div>
      </section>
    );
  }

  addSection() {
    this.rows.push({ kind: 'section', id: 0, title: extractText(t('editor.new_section')), key: 'new' + Date.now() } as Row);
    this.saveOutline();
  }

  saveOutline() {
    if (!this.course) return;
    const items = this.rows.map((row) =>
      row.kind === 'section' ? { kind: 'section', id: row.id || undefined, title: row.title } : { kind: 'lesson', id: row.id }
    );
    saveOutline(this.course.id, items).then(({ data }) => {
      this.rows = data.outline.map((item) => ({ ...item, key: item.kind + item.id }));
      m.redraw();
    }, failed);
  }

  save(e: Event) {
    e.preventDefault();
    this.saving = true;
    const f = this.form;
    const data: any = { ...f, instructorIds: f.instructors.map((p) => p.id) };
    delete data.instructors;
    delete data.hasUploadedCover;
    if (f.hasUploadedCover) delete data.coverUrl;

    saveCourse(data, this.id || undefined).then(
      ({ data: course }) => {
        this.saving = false;
        app.alerts.show({ type: 'success' }, t('editor.saved'));
        if (!this.id) {
          m.route.set(app.route('syllabus.edit', { id: course.id + '-' + course.slug }));
          return;
        }
        this.take(course);
      },
      (error: any) => {
        this.saving = false;
        failed(error);
        m.redraw();
      }
    );
  }

  uploadCover(file?: File) {
    if (!file || !this.course) return;
    const form = new FormData();
    form.append('cover', file);
    upload<{ data: { coverUrl: string } }>(`/courses/${this.course.id}/cover`, form).then(({ data }) => {
      this.coverUrl = data.coverUrl;
      this.form.hasUploadedCover = true;
      this.form.coverUrl = null;
      m.redraw();
    }, failed);
  }

  dropCover() {
    if (!this.course) return;
    removeCover(this.course.id).then(() => {
      this.coverUrl = null;
      this.form.coverUrl = null;
      this.form.hasUploadedCover = false;
      m.redraw();
    }, failed);
  }

  destroy() {
    if (!this.course || !confirm(extractText(t('editor.delete_confirm', { title: this.course.title })))) return;
    deleteCourse(this.course.id).then(() => m.route.set(app.route('syllabus.teach')), failed);
  }
}
