import Extend from 'flarum/common/extenders';
import Model from 'flarum/common/Model';

/** A course as a notification's subject: its title and slug, nothing more. */
export class SyllabusCourse extends Model {
  title = Model.attribute<string>('title');
  slug = Model.attribute<string>('slug');
}

export default [new Extend.Store().add('syllabus-courses', SyllabusCourse)];
