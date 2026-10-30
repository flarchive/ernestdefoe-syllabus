import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import { categories, deleteCategory, orderCategories, saveCategory } from '../common/api';
import type { Category } from '../common/api';
import Sortable from '../common/sortable';

const t = (key: string, params: Record<string, unknown> = {}) => app.translator.trans(`ernestdefoe-syllabus.admin.categories.${key}`, params);

/** Course categories: add, rename, remove, and drag into order (arrow keys on the handle do the same). */
export default class CategoriesSetting extends Component {
  list: Category[] | null = null;
  name = '';
  busy = false;
  sorter!: Sortable<Category>;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.sorter = new Sortable(
      () => this.list || [],
      () => this.order(),
      extractText(t('sortable'))
    );
    categories().then(({ data }) => {
      this.list = data;
      m.redraw();
    });
  }

  view(): Mithril.Children {
    return (
      <div className="Form-group SyllabusCategories">
        <label>{t('title')}</label>
        <div className="helpText">{t('help')}</div>
        {!this.list ? (
          <LoadingIndicator />
        ) : (
          <ol className="SyllabusCategories-list" data-sortable>
            {this.list.map((cat, index) => (
              <li key={cat.id} className={'SyllabusCategories-row' + (this.sorter.dragging === index ? ' is-dragging' : '')}>
                <span className="SyllabusCategories-handle" {...this.sorter.handle(index)} aria-label={extractText(t('move', { name: cat.name }))}>
                  <Icon name="fas fa-grip-vertical" />
                </span>
                <input
                  className="FormControl"
                  value={cat.name}
                  maxlength={100}
                  aria-label={extractText(t('name'))}
                  oninput={(e: InputEvent) => (cat.name = (e.target as HTMLInputElement).value)}
                  onchange={() => saveCategory({ name: cat.name }, cat.id)}
                />
                <span className="helpText">{t('courses', { count: cat.courseCount })}</span>
                <Button
                  className="Button Button--icon Button--link"
                  icon="fas fa-trash"
                  aria-label={extractText(t('delete'))}
                  onclick={() => {
                    if (!confirm(extractText(t('delete_confirm', { name: cat.name })))) return;
                    deleteCategory(cat.id).then(() => {
                      this.list = (this.list || []).filter((c) => c.id !== cat.id);
                      m.redraw();
                    });
                  }}
                />
              </li>
            ))}
          </ol>
        )}
        <div className="SyllabusCategories-add">
          <input
            className="FormControl"
            maxlength={100}
            placeholder={extractText(t('new'))}
            aria-label={extractText(t('new'))}
            value={this.name}
            oninput={(e: InputEvent) => (this.name = (e.target as HTMLInputElement).value)}
            onkeydown={(e: KeyboardEvent) => e.key === 'Enter' && (e.preventDefault(), this.add())}
          />
          <Button className="Button" icon="fas fa-plus" loading={this.busy} disabled={!this.name.trim()} onclick={() => this.add()}>
            {t('add')}
          </Button>
        </div>
      </div>
    );
  }

  add() {
    if (!this.name.trim()) return;
    this.busy = true;
    saveCategory({ name: this.name.trim() })
      .then(({ data }) => {
        this.list = data;
        this.name = '';
      })
      .finally(() => {
        this.busy = false;
        m.redraw();
      });
  }

  order() {
    orderCategories((this.list || []).map((c) => c.id)).then(({ data }) => {
      this.list = data;
      m.redraw();
    });
  }
}
