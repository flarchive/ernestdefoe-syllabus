import Component from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import type Mithril from 'mithril';
import { lessonUrl } from '../../common/api';
import type { OutlineItem } from '../../common/api';
import { duration, lessonIcons, t } from './util';

interface Attrs {
  course: { id: number; slug: string };
  items: OutlineItem[];
  current?: number;
  compact?: boolean;
}

/** The course's sections and lessons, each lesson showing whether it is done, open or locked. */
export default class OutlineList extends Component<Attrs> {
  view(): Mithril.Children {
    const { course, items, current, compact } = this.attrs;
    let n = 0;

    return (
      <ol className={'SyllabusOutline' + (compact ? ' SyllabusOutline--compact' : '')}>
        {items.map((item) => {
          if (item.kind === 'section') {
            return (
              <li key={'s' + item.id} className="SyllabusOutline-section">
                {item.title}
              </li>
            );
          }

          n++;
          const state = item.completed ? 'done' : item.locked ? 'locked' : 'open';
          const icon = item.completed ? 'fas fa-circle-check' : item.locked ? 'fas fa-lock' : lessonIcons[item.type];
          const label = (
            <>
              <span className="SyllabusOutline-icon" aria-hidden="true">
                <Icon name={icon} />
              </span>
              <span className="SyllabusOutline-number">{n}.</span>
              <span className="SyllabusOutline-title">{item.title}</span>
              <span className="sr-only">{t('outline.state_' + state)}</span>
              {item.status === 'draft' ? <span className="SyllabusBadge SyllabusBadge--draft">{t('course.draft')}</span> : null}
              {item.preview && item.locked === null && !item.completed ? <span className="SyllabusBadge">{t('outline.preview')}</span> : null}
              {!compact && item.durationMinutes ? <span className="SyllabusOutline-time">{duration(item.durationMinutes)}</span> : null}
            </>
          );

          return (
            <li
              key={'l' + item.id}
              className={'SyllabusOutline-lesson SyllabusOutline-lesson--' + state + (current === item.id ? ' active' : '')}
              aria-current={current === item.id ? 'page' : undefined}
            >
              <Link href={lessonUrl(course, item)}>{label}</Link>
            </li>
          );
        })}
      </ol>
    );
  }
}
