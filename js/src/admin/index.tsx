import app from 'flarum/admin/app';
import Select from 'flarum/common/components/Select';
import { registerEditorWidgets } from '../common/fofWidgets';
import CategoriesSetting from './CategoriesSetting';

/*
 * Syllabus's admin: permissions, categories and a handful of settings.
 * Courses themselves are written and taught on the forum, in the instructor
 * panel, where instructors need no admin access.
 */
app.initializers.add('ernestdefoe-syllabus', () => {
  const t = (key: string, params: Record<string, string> = {}) => app.translator.trans(`ernestdefoe-syllabus.admin.${key}`, params);
  const key = (name: string) => `ernestdefoe-syllabus.${name}`;

  app.registry
    .for('ernestdefoe-syllabus')
    .registerSetting(() => <CategoriesSetting />)
    .registerSetting({ setting: key('show_nav'), type: 'boolean', label: t('settings.show_nav') })
    .registerSetting(function (this: any) {
      const tags = (app.store.all('tags') as any[]).filter((tag) => !tag.isChild?.());
      if (!tags.length) return null;
      const options: Record<string, string> = { '': app.translator.trans('ernestdefoe-syllabus.admin.settings.no_tag') as unknown as string };
      tags.forEach((tag) => (options[tag.id()] = tag.name()));
      const setting = this.setting(key('discussion_tag'));

      return (
        <div className="Form-group">
          <label>{t('settings.discussion_tag')}</label>
          <div className="helpText">{t('settings.discussion_tag_help')}</div>
          <Select options={options} value={setting() || ''} onchange={(value: string) => setting(value)} />
        </div>
      );
    })
    .registerSetting({
      setting: key('certificate_text'),
      type: 'textarea',
      label: t('settings.certificate_text'),
      help: t('settings.certificate_text_help', { name: '{name}', course: '{course}', date: '{date}', forum: '{forum}' }),
    })
    .registerSetting({
      setting: key('certificate_signature'),
      type: 'text',
      label: t('settings.certificate_signature'),
      help: t('settings.certificate_signature_help'),
    })
    .registerSetting({
      setting: key('certificate_logo'),
      type: 'url',
      label: t('settings.certificate_logo'),
      help: t('settings.certificate_logo_help'),
    })
    .registerSetting({ setting: key('max_file_mb'), type: 'number', min: 1, max: 4096, label: t('settings.max_file_mb') })
    .registerSetting({ setting: key('file_types'), type: 'text', label: t('settings.file_types'), help: t('settings.file_types_help') })
    .registerSetting({
      setting: key('max_video_mb'),
      type: 'number',
      min: 0,
      max: 4096,
      label: t('settings.max_video_mb'),
      help: t('settings.max_video_mb_help'),
    })
    .registerSetting({ setting: key('submissions_per_hour'), type: 'number', min: 1, max: 1000, label: t('settings.submissions_per_hour') })
    .registerPermission({ icon: 'fas fa-chalkboard-user', label: t('permissions.view'), permission: 'syllabus.view', allowGuest: true }, 'view')
    .registerPermission({ icon: 'fas fa-right-to-bracket', label: t('permissions.enrol'), permission: 'syllabus.enrol' }, 'reply')
    .registerPermission({ icon: 'fas fa-person-chalkboard', label: t('permissions.create'), permission: 'syllabus.create' }, 'start')
    .registerPermission({ icon: 'fas fa-chalkboard-user', label: t('permissions.manage'), permission: 'syllabus.manage' }, 'moderate');

  // FoF's layout editor lists only widgets the admin registers too.
  registerEditorWidgets(app, 'ernestdefoe-syllabus', [{ key: 'syllabusCourses', icon: 'fas fa-chalkboard-user', title: () => t('widgets.courses') }]);
});
