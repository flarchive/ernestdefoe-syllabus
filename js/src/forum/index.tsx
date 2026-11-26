import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import LinkButton from 'flarum/common/components/LinkButton';
import UserPage from 'flarum/forum/components/UserPage';
import Switch from 'flarum/common/components/Switch';
import registerWidgets from './widgets';

export { default as extend } from './extend';

const t = (key: string) => app.translator.trans(`ernestdefoe-syllabus.forum.${key}`);

/*
 * Everything here runs on every page for every visitor, so it is only the
 * wiring; routes and notifications are registered in extend.ts. Every page is
 * its own chunk, fetched the first time it is opened.
 */
app.initializers.add('ernestdefoe-syllabus', () => {
  extend(IndexSidebar.prototype, 'navItems', function (items) {
    if (!app.forum.attribute('canViewCourses') || app.forum.attribute('syllabusShowNav') === false) return;

    items.add(
      'courses',
      <LinkButton href={app.route('syllabus.index')} icon="fas fa-chalkboard-user">
        {t('nav')}
      </LinkButton>,
      4
    );
  });

  extend(UserPage.prototype, 'navItems', function (this: UserPage, items) {
    if (!this.user || !app.forum.attribute('canViewCourses')) return;

    items.add(
      'courses',
      <LinkButton href={app.route('user.courses', { username: this.user.slug() })} icon="fas fa-chalkboard-user">
        {t('profile.nav')}
      </LinkButton>,
      80
    );
  });

  extend('flarum/forum/components/NotificationGrid', 'notificationTypes', function (items: any) {
    items.add('syllabusAssignmentReviewed', {
      name: 'syllabusAssignmentReviewed',
      icon: 'fas fa-circle-check',
      label: t('settings.notify_reviewed'),
    });
    items.add('syllabusCourseCompleted', { name: 'syllabusCourseCompleted', icon: 'fas fa-award', label: t('settings.notify_completed') });
    items.add('syllabusNewLesson', { name: 'syllabusNewLesson', icon: 'fas fa-book-open', label: t('settings.notify_new_lesson') });
    if (app.forum.attribute('canCreateCourses')) {
      items.add('syllabusSubmissionReceived', { name: 'syllabusSubmissionReceived', icon: 'fas fa-inbox', label: t('settings.notify_submission') });
    }
  });

  extend('flarum/forum/components/SettingsPage', 'privacyItems', function (this: any, items: any) {
    if (!app.forum.attribute('canViewCourses')) return;

    const user = this.user;
    items.add(
      'syllabusShowCourses',
      <Switch
        state={user.preferences()?.syllabusShowCourses !== false}
        loading={this.syllabusLoading}
        onchange={(value: boolean) => {
          this.syllabusLoading = true;
          user.savePreferences({ syllabusShowCourses: value }).finally(() => {
            this.syllabusLoading = false;
            m.redraw();
          });
        }}
      >
        {t('settings.show_courses')}
      </Switch>
    );
  });

  registerWidgets();
});
