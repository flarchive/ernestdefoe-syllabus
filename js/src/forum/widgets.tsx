import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import type Mithril from 'mithril';
import { fof, hasFofWidgets, placed } from '../common/fofWidgets';

/**
 * Syllabus's widget — "Continue learning" for a member taking courses,
 * "Featured courses" for everyone else — offered to Bespoke and to FoF Forum
 * Widgets.
 *
 * 🚨 No host is a build-time dependency: each is found at runtime through its
 * own registry or queue, so the bundle is the same with all, some or none
 * installed. What the widget shows is its own lazy chunk, fetched only by a
 * page that actually places it.
 */

let body: any = null;

export class CoursesWidget extends Component<{ limit?: number }> {
  oninit(vnode: Mithril.Vnode<{ limit?: number }, this>) {
    super.oninit(vnode);
    if (!body) {
      import('./components/WidgetBody').then((mod) => {
        body = mod.default;
        m.redraw();
      });
    }
  }

  view(): Mithril.Children {
    if (!app.forum.attribute('canViewCourses')) return null;
    const limit = Math.max(1, Math.min(10, Number(this.attrs.limit) || 4));

    return body ? m(body, { limit }) : <div className="SyllabusWidget" />;
  }
}

const flarumGlobal = (): any => (typeof globalThis !== 'undefined' ? (globalThis as any).flarum : undefined);

export default function registerWidgets(): void {
  const flarum = flarumGlobal();
  const has = (id: string) => !!flarum?.extensions && id in flarum.extensions;

  if (hasFofWidgets() && (app as any).widgets) {
    const Widgets = fof('common/extend/Widgets');
    const WidgetBase = fof('common/components/Widget');

    // A version whose internals moved: decline quietly rather than throw out
    // of an initializer and take the forum's JS down with it.
    if (Widgets && WidgetBase) {
      class FofCoursesWidget extends WidgetBase {
        className() {
          return 'SyllabusFofWidget';
        }
        icon() {
          return 'fas fa-chalkboard-user';
        }
        title() {
          return app.translator.trans(`ernestdefoe-syllabus.forum.widget.${app.session.user ? 'title_member' : 'title_guest'}`);
        }
        content() {
          return <CoursesWidget />;
        }
      }

      new Widgets()
        .add({
          key: 'syllabusCourses',
          component: FofCoursesWidget,
          // 🚨 Opt-in: only where the admin placed it in FoF's layout editor.
          isDisabled: () => !placed(app, 'ernestdefoe-syllabus', 'syllabusCourses') || !app.forum.attribute('canViewCourses'),
          isUnique: true,
          placement: 'end',
          position: 1,
        })
        .extend(app, 'ernestdefoe-syllabus');
    }
  }

  if (has('ernestdefoe-bespoke')) {
    const g = globalThis as any;
    g.BespokeWidgetQueue = g.BespokeWidgetQueue || [];
    // Labels are translation KEYS: Bespoke resolves them itself, in the reader's locale.
    g.BespokeWidgetQueue.push({
      type: 'syllabus-courses',
      label: 'ernestdefoe-syllabus.forum.widget.name',
      icon: 'fas fa-chalkboard-user',
      zones: ['sidebar', 'above-list', 'below-list', 'footer'],
      schema: [
        { key: 'heading', type: 'text', label: 'ernestdefoe-syllabus.forum.widget.heading', default: '' },
        { key: 'limit', type: 'number', label: 'ernestdefoe-syllabus.forum.widget.limit', default: 4 },
      ],
      component: {
        view(vnode: any) {
          const s = vnode.attrs?.settings || {};
          return (
            <div className="SyllabusBespokeWidget">
              <h4 className="SyllabusWidget-heading">
                {s.heading || app.translator.trans(`ernestdefoe-syllabus.forum.widget.${app.session.user ? 'title_member' : 'title_guest'}`)}
              </h4>
              <CoursesWidget limit={s.limit} />
            </div>
          );
        },
      },
    });
  }
}
