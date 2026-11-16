import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';
import Select from 'flarum/common/components/Select';
import CategoriesSetting from './CategoriesSetting';

/*
 * Syllabus's admin: permissions, categories and a handful of settings.
 * Courses themselves are written and taught on the forum, in the instructor
 * panel, where instructors need no admin access.
 */
const t = (key: string, params: Record<string, string> = {}) => app.translator.trans(`ernestdefoe-syllabus.admin.${key}`, params);
const key = (name: string) => `ernestdefoe-syllabus.${name}`;

export default [
  new Extend.Admin()
    .customSetting(() => <CategoriesSetting />, 100)
    .setting(() => ({ setting: key('show_nav'), type: 'boolean', label: t('settings.show_nav') }), 90)
    .customSetting(function (this: any) {
      const tags = (app.store.all('tags') as any[]).filter((tag) => !tag.isChild?.());
      if (!tags.length) return null;
      const options: Record<string, string> = { '': t('settings.no_tag') as unknown as string };
      tags.forEach((tag) => (options[tag.id()] = tag.name()));
      const setting = this.setting(key('discussion_tag'));

      return (
        <div className="Form-group">
          <label>{t('settings.discussion_tag')}</label>
          <div className="helpText">{t('settings.discussion_tag_help')}</div>
          <Select options={options} value={setting() || ''} onchange={(value: string) => setting(value)} />
        </div>
      );
    }, 80)
    .setting(
      () => ({
        setting: key('certificate_text'),
        type: 'textarea',
        label: t('settings.certificate_text'),
        help: t('settings.certificate_text_help', { name: '{name}', course: '{course}', date: '{date}', forum: '{forum}' }),
      }),
      70
    )
    .setting(
      () => ({
        setting: key('certificate_signature'),
        type: 'text',
        label: t('settings.certificate_signature'),
        help: t('settings.certificate_signature_help'),
      }),
      60
    )
    .setting(
      () => ({ setting: key('certificate_logo'), type: 'url', label: t('settings.certificate_logo'), help: t('settings.certificate_logo_help') }),
      50
    )
    .setting(() => ({ setting: key('max_file_mb'), type: 'number', min: 1, max: 4096, label: t('settings.max_file_mb') }), 40)
    .setting(() => ({ setting: key('file_types'), type: 'text', label: t('settings.file_types'), help: t('settings.file_types_help') }), 30)
    .setting(
      () => ({
        setting: key('max_video_mb'),
        type: 'number',
        min: 0,
        max: 4096,
        label: t('settings.max_video_mb'),
        help: t('settings.max_video_mb_help'),
      }),
      20
    )
    .setting(() => ({ setting: key('submissions_per_hour'), type: 'number', min: 1, max: 1000, label: t('settings.submissions_per_hour') }), 10)
    .permission(() => ({ icon: 'fas fa-chalkboard-user', label: t('permissions.view'), permission: 'syllabus.view', allowGuest: true }), 'view')
    .permission(() => ({ icon: 'fas fa-right-to-bracket', label: t('permissions.enrol'), permission: 'syllabus.enrol' }), 'reply')
    .permission(() => ({ icon: 'fas fa-person-chalkboard', label: t('permissions.create'), permission: 'syllabus.create' }), 'start')
    .permission(() => ({ icon: 'fas fa-chalkboard-user', label: t('permissions.manage'), permission: 'syllabus.manage' }), 'moderate'),
];
