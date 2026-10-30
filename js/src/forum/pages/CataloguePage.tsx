import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import Button from 'flarum/common/components/Button';
import LinkButton from 'flarum/common/components/LinkButton';
import Select from 'flarum/common/components/Select';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import { categories, listCourses } from '../../common/api';
import type { Category, CourseCard as Card } from '../../common/api';
import CourseCard from '../components/CourseCard';
import { failed, t } from '../components/util';

type Tab = 'all' | 'mine';

/** /courses — the catalogue: categories, search, filters and the member's own courses. */
export default class CataloguePage extends Page {
  courses: Card[] = [];
  cats: Category[] = [];
  loading = true;
  next: number | null = null;
  canCreate = false;
  tab: Tab = 'all';
  category = '';
  difficulty = '';
  query = '';
  timer = 0;
  asked = 0;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    const title = extractText(t('catalogue.title'));
    app.setTitle(title);
    app.history.push('courses', title);

    this.tab = m.route.param('tab') === 'mine' && app.session.user ? 'mine' : 'all';
    categories().then(({ data }) => {
      this.cats = data;
      m.redraw();
    }, failed);
    this.load();
  }

  load(more = false) {
    this.loading = true;
    const filter: Record<string, string> = {};
    if (this.tab === 'mine') filter.mine = '1';
    if (this.category) filter.category = this.category;
    if (this.difficulty) filter.difficulty = this.difficulty;
    if (this.query.trim()) filter.q = this.query.trim();
    const params: Record<string, unknown> = { filter };
    if (more && this.next) params.page = { offset: this.next };

    const ask = ++this.asked;
    listCourses(params).then(
      ({ data, meta }) => {
        // An older search answering late doesn't overwrite a newer one.
        if (ask !== this.asked) return;
        this.courses = more ? [...this.courses, ...data] : data;
        this.next = meta.next;
        this.canCreate = meta.canCreate;
        this.loading = false;
        m.redraw();
      },
      (e: any) => {
        this.loading = false;
        failed(e);
        m.redraw();
      }
    );
  }

  view(): Mithril.Children {
    const catOptions: Record<string, string> = { '': extractText(t('catalogue.all_categories')) };
    this.cats.forEach((c) => (catOptions[String(c.id)] = c.name));
    const levels: Record<string, string> = { '': extractText(t('catalogue.all_levels')) };
    ['beginner', 'intermediate', 'advanced'].forEach((d) => (levels[d] = extractText(t('difficulty.' + d))));

    return (
      <PageStructure className="SyllabusCataloguePage" sidebar={() => <IndexSidebar />}>
        <div className="SyllabusPageHead">
          <h2 className="SyllabusPageHead-title">{t('catalogue.title')}</h2>
          {this.canCreate ? (
            <div className="SyllabusPageHead-actions">
              <LinkButton className="Button" icon="fas fa-person-chalkboard" href={app.route('syllabus.teach')}>
                {t('teach.title')}
              </LinkButton>
              <LinkButton className="Button Button--primary" icon="fas fa-plus" href={app.route('syllabus.new')}>
                {t('catalogue.new')}
              </LinkButton>
            </div>
          ) : null}
        </div>

        <div className="SyllabusToolbar">
          {app.session.user ? (
            <div className="SyllabusTabs" role="tablist">
              {(['all', 'mine'] as Tab[]).map((tab) => (
                <Button
                  className={'Button Button--flat' + (this.tab === tab ? ' active' : '')}
                  role="tab"
                  aria-selected={this.tab === tab}
                  onclick={() => {
                    this.tab = tab;
                    this.load();
                  }}
                >
                  {t('catalogue.tabs.' + tab)}
                </Button>
              ))}
            </div>
          ) : null}
          {this.cats.length ? (
            <Select
              options={catOptions}
              value={this.category}
              aria-label={extractText(t('catalogue.category'))}
              onchange={(v: string) => {
                this.category = v;
                this.load();
              }}
            />
          ) : null}
          <Select
            options={levels}
            value={this.difficulty}
            aria-label={extractText(t('catalogue.level'))}
            onchange={(v: string) => {
              this.difficulty = v;
              this.load();
            }}
          />
          <input
            className="FormControl SyllabusToolbar-search"
            type="search"
            placeholder={extractText(t('catalogue.search'))}
            aria-label={extractText(t('catalogue.search'))}
            value={this.query}
            oninput={(e: InputEvent) => {
              this.query = (e.target as HTMLInputElement).value;
              window.clearTimeout(this.timer);
              this.timer = window.setTimeout(() => this.load(), 300);
            }}
          />
        </div>

        <div className="SyllabusGrid">
          {this.courses.map((course) => (
            <CourseCard key={course.id} course={course} />
          ))}
        </div>
        {this.loading ? <LoadingIndicator display="block" /> : null}
        {!this.loading && !this.courses.length ? (
          <p className="SyllabusEmpty">{t(this.tab === 'mine' ? 'catalogue.empty_mine' : this.query ? 'catalogue.no_results' : 'catalogue.empty')}</p>
        ) : null}
        {!this.loading && this.next ? (
          <Button className="Button SyllabusMore" onclick={() => this.load(true)}>
            {t('catalogue.more')}
          </Button>
        ) : null}
      </PageStructure>
    );
  }
}
