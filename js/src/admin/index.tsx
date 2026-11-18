import app from 'flarum/admin/app';
import { registerEditorWidgets } from '../common/fofWidgets';

export { default as extend } from './extend';

app.initializers.add('ernestdefoe-syllabus', () => {
  // FoF's layout editor lists only widgets the admin registers too.
  registerEditorWidgets(app, 'ernestdefoe-syllabus', [
    { key: 'syllabusCourses', icon: 'fas fa-chalkboard-user', title: () => app.translator.trans('ernestdefoe-syllabus.admin.widgets.courses') },
  ]);
});
