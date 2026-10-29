import Component from 'flarum/common/Component';
import type Mithril from 'mithril';
import { t } from './util';

/** A labelled progress bar: "3 of 8 lessons · 37%". */
export default class ProgressBar extends Component<{ percent: number; done?: number; total?: number; compact?: boolean }> {
  view(): Mithril.Children {
    const { percent, done, total, compact } = this.attrs;
    const label = t('progress.label', { percent });

    return (
      <div className={'SyllabusProgress' + (compact ? ' SyllabusProgress--compact' : '')}>
        <div
          className="SyllabusProgress-bar"
          role="progressbar"
          aria-valuemin="0"
          aria-valuemax="100"
          aria-valuenow={percent}
          aria-label={label as any}
        >
          <span style={{ width: percent + '%' }} />
        </div>
        {!compact ? (
          <div className="SyllabusProgress-text">
            {total !== undefined ? t('progress.lessons', { done: done || 0, total }) : null}
            <span className="SyllabusProgress-percent">{label}</span>
          </div>
        ) : null}
      </div>
    );
  }
}
