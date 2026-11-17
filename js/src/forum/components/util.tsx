import app from 'flarum/forum/app';
import extractText from 'flarum/common/utils/extractText';

export const t = (key: string, params: Record<string, unknown> = {}) => app.translator.trans(`ernestdefoe-syllabus.forum.${key}`, params);

/** "1 h 30 min", "45 min" */
export function duration(minutes: number | null | undefined): string | null {
  if (!minutes) return null;
  const h = Math.floor(minutes / 60);
  const min = minutes % 60;
  return [h ? extractText(t('units.hours', { count: h })) : '', min ? extractText(t('units.minutes', { count: min })) : ''].filter(Boolean).join(' ');
}

export function fileSize(bytes: number): string {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB';
  return (bytes / 1048576).toFixed(1) + ' MB';
}

export const lessonIcons: Record<string, string> = {
  text: 'fas fa-file-lines',
  video: 'fas fa-circle-play',
  assignment: 'fas fa-pen-ruler',
  quiz: 'fas fa-circle-question',
};

/** Shows an API error the way Flarum does, or a generic one. */
export function failed(error: any): void {
  if (!error?.alert) app.alerts.show({ type: 'error' }, t('errors.generic'));
}

/** A member's avatar from the plain person objects Syllabus's API sends. */
export function avatar(person: { displayName: string; avatarUrl: string | null }): any {
  return person.avatarUrl ? (
    <img className="Avatar" src={person.avatarUrl} alt="" loading="lazy" />
  ) : (
    <span className="Avatar" aria-hidden="true">
      {(person.displayName || '?').charAt(0).toUpperCase()}
    </span>
  );
}
