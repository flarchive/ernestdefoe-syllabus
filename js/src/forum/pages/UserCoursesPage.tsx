import app from 'flarum/forum/app';
import UserPage from 'flarum/forum/components/UserPage';
import LinkButton from 'flarum/common/components/LinkButton';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type User from 'flarum/common/models/User';
import type Mithril from 'mithril';
import { certificateUrl, userCourses } from '../../common/api';
import type { CourseCard as Card } from '../../common/api';
import CourseCard from '../components/CourseCard';
import { failed, t } from '../components/util';

/** Profile → Courses: what the member is taking and has completed, unless they keep it to themselves. */
export default class UserCoursesPage extends UserPage {
  courses: Card[] | null = null;
  hidden = false;
  shownFor: string | null = null;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.loadUser(m.route.param('username'));
  }

  show(user: User) {
    super.show(user);
    if (this.shownFor === user.id()) return;
    this.shownFor = user.id()!;
    this.courses = null;

    userCourses(user.id()!).then(({ data, meta }) => {
      this.courses = data;
      this.hidden = meta.hidden;
      m.redraw();
    }, failed);
  }

  content(): Mithril.Children {
    if (!this.courses) return <LoadingIndicator display="block" />;
    if (this.hidden) return <p className="SyllabusEmpty">{t('profile.hidden')}</p>;
    if (!this.courses.length) return <p className="SyllabusEmpty">{t('profile.empty')}</p>;

    const doing = this.courses.filter((c) => !c.me?.completedAt);
    const done = this.courses.filter((c) => c.me?.completedAt);

    return (
      <div className="SyllabusUserPage">
        {doing.length ? <h3 className="SyllabusHeading">{t('profile.in_progress')}</h3> : null}
        <div className="SyllabusGrid">
          {doing.map((course) => (
            <CourseCard key={course.id} course={course} />
          ))}
        </div>
        {done.length ? <h3 className="SyllabusHeading">{t('profile.completed')}</h3> : null}
        <div className="SyllabusGrid">
          {done.map((course) => (
            <div className="SyllabusGrid-item" key={course.id}>
              <CourseCard course={course} />
              {course.certificateCode ? (
                <LinkButton className="Button Button--link" icon="fas fa-award" href={certificateUrl(course.certificateCode)}>
                  {t('course.view_certificate')}
                </LinkButton>
              ) : null}
            </div>
          ))}
        </div>
      </div>
    );
  }
}
