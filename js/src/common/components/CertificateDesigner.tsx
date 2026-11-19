import app from 'flarum/common/app';
import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import { CERTIFICATE_IMAGES, CERTIFICATE_TEMPLATES, designPreviewUrl, removeDesignImage, uploadDesignImage } from '../api';
import type { CertificateDesign } from '../api';
import CertificateSvg, { loadSvg } from './CertificateSvg';

export interface CertificateDesignerAttrs extends ComponentAttrs {
  design: CertificateDesign;
  /** A course's design; without one, the forum's default. */
  courseId?: number;
  /** Called after the design changes; images are saved at once, text with the form. */
  onchange?: (design: CertificateDesign) => void;
}

const t = (key: string, params: Record<string, unknown> = {}) => app.translator.trans(`ernestdefoe-syllabus.lib.designer.${key}`, params);

/**
 * The certificate designer: a template, a logo, a background, the signer's
 * signature, name and title, and a live preview drawn by the server — the
 * same SVG the certificate page shows, prints and downloads.
 * In a course, every part can be left to the forum's default.
 */
export default class CertificateDesigner extends Component<CertificateDesignerAttrs> {
  /** Which request is the latest, so a slow answer never replaces a newer one. */
  version = 0;
  svg = '';
  loading = true;
  busy = '';
  timer = 0;

  oninit(vnode: Mithril.Vnode<CertificateDesignerAttrs, this>) {
    super.oninit(vnode);
    this.fetch();
  }

  onremove() {
    window.clearTimeout(this.timer);
  }

  fetch() {
    const version = ++this.version;
    this.loading = true;
    const done = (svg: string) => {
      if (version !== this.version) return;
      this.svg = svg;
      this.loading = false;
      m.redraw();
    };
    loadSvg(designPreviewUrl(this.attrs.design, this.attrs.courseId)).then(done, () => {
      // Keep the last preview, and say this one couldn't be drawn.
      app.alerts.show({ type: 'error' }, t('preview_failed'));
      done(this.svg);
    });
  }

  /** Redraws the preview: at once for a click, after a pause for typing. */
  refresh(wait = 0) {
    window.clearTimeout(this.timer);
    this.timer = window.setTimeout(() => this.fetch(), wait);
    this.attrs.onchange?.(this.attrs.design);
  }

  view(): Mithril.Children {
    const design = this.attrs.design;
    const inCourse = !!this.attrs.courseId;
    const templates = inCourse ? ['', ...CERTIFICATE_TEMPLATES] : CERTIFICATE_TEMPLATES;
    const chosen = design.template || (inCourse ? '' : 'classic');

    return (
      <div className="SyllabusDesigner">
        <div className="SyllabusDesigner-controls">
          <div className="Form-group">
            <label>{t('template')}</label>
            <div className="SyllabusDesigner-templates" role="radiogroup" aria-label={extractText(t('template'))}>
              {templates.map((name) => (
                <label className={'SyllabusDesigner-template' + (chosen === name ? ' is-chosen' : '')}>
                  <input
                    type="radio"
                    name={'syl-template-' + (this.attrs.courseId || 'forum')}
                    checked={chosen === name}
                    onchange={() => {
                      design.template = name;
                      this.refresh();
                    }}
                  />
                  <span className={'SyllabusDesigner-swatch SyllabusDesigner-swatch--' + (name || 'default')} aria-hidden="true" />
                  <span>{t(name ? 'templates.' + name : 'forum_default')}</span>
                </label>
              ))}
            </div>
          </div>

          <div className="SyllabusDesigner-grid">
            {(['signerName', 'signerTitle'] as const).map((key) => (
              <div className="Form-group">
                <label for={'syl-' + key + (this.attrs.courseId || '')}>{t(key === 'signerName' ? 'signer_name' : 'signer_title')}</label>
                <input
                  id={'syl-' + key + (this.attrs.courseId || '')}
                  className="FormControl"
                  maxlength={100}
                  placeholder={inCourse ? extractText(t('forum_default')) : ''}
                  value={design[key]}
                  oninput={(e: InputEvent) => {
                    design[key] = (e.target as HTMLInputElement).value;
                    this.refresh(500);
                  }}
                />
              </div>
            ))}
          </div>

          {CERTIFICATE_IMAGES.map((kind) => this.imageRow(kind))}
          <p className="helpText">{t(inCourse ? 'help_course' : 'help_forum')}</p>
        </div>

        <figure className="SyllabusDesigner-preview">
          <div className="SyllabusDesigner-frame">
            {this.svg ? <CertificateSvg svg={this.svg} /> : <div className="SyllabusDesigner-blank" aria-label={extractText(t('preview_alt'))} />}
            {this.loading ? <LoadingIndicator size="small" /> : null}
          </div>
          <figcaption className="helpText">{t('preview_caption')}</figcaption>
        </figure>
      </div>
    );
  }

  imageRow(kind: (typeof CERTIFICATE_IMAGES)[number]): Mithril.Children {
    const design = this.attrs.design;
    const has = design[('has' + kind[0].toUpperCase() + kind.slice(1)) as 'hasLogo' | 'hasBackground' | 'hasSignature'];
    const id = `syl-${kind}-${this.attrs.courseId || 'forum'}`;

    return (
      <div className="SyllabusDesigner-image">
        <div className="SyllabusDesigner-imageText">
          <strong>{t(kind)}</strong>
          <span className="helpText">{has ? t('uploaded') : t(this.attrs.courseId ? 'using_default' : kind + '_none')}</span>
        </div>
        <label className={'Button' + (this.busy === kind ? ' disabled' : '')} for={id}>
          <Icon name="fas fa-upload" /> {t(has ? 'replace' : 'upload')}
        </label>
        <input
          id={id}
          className="sr-only"
          type="file"
          accept="image/png,image/jpeg,image/gif,image/webp"
          disabled={this.busy === kind}
          onchange={(e: Event) => {
            const input = e.target as HTMLInputElement;
            const file = input.files?.[0];
            input.value = '';
            if (file) this.save(kind, uploadDesignImage(kind, file, this.attrs.courseId));
          }}
        />
        {has ? (
          <Button
            className="Button Button--link"
            disabled={this.busy === kind}
            onclick={() => this.save(kind, removeDesignImage(kind, this.attrs.courseId))}
          >
            {t('remove')}
          </Button>
        ) : null}
      </div>
    );
  }

  save(kind: string, request: Promise<{ data: CertificateDesign }>) {
    this.busy = kind;
    request.then(
      ({ data }) => {
        this.busy = '';
        const design = this.attrs.design;
        design.hasLogo = data.hasLogo;
        design.hasBackground = data.hasBackground;
        design.hasSignature = data.hasSignature;
        this.refresh();
      },
      () => {
        // app.request has already shown the error.
        this.busy = '';
        m.redraw();
      }
    );
  }
}
