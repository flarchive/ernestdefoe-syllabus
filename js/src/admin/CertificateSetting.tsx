import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import CertificateDesigner from '../common/components/CertificateDesigner';
import { forumDesign, saveForumDesign } from '../common/api';
import type { CertificateDesign } from '../common/api';

const t = (key: string) => app.translator.trans(`ernestdefoe-syllabus.admin.certificate.${key}`);

/** The forum's default certificate design: courses use it for every part they leave empty. */
export default class CertificateSetting extends Component {
  design: CertificateDesign | null = null;
  saving = false;
  dirty = false;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    forumDesign().then(({ data }) => {
      this.design = data;
      m.redraw();
    });
  }

  view(): Mithril.Children {
    return (
      <div className="Form-group SyllabusCertificateSetting">
        <label>{t('heading')}</label>
        <div className="helpText">{t('help')}</div>
        {this.design ? (
          <>
            <CertificateDesigner design={this.design} onchange={() => (this.dirty = true)} />
            <Button className="Button Button--primary" loading={this.saving} disabled={!this.dirty} onclick={() => this.save()}>
              {t('save')}
            </Button>
          </>
        ) : (
          <LoadingIndicator />
        )}
      </div>
    );
  }

  save() {
    const design = this.design!;
    this.saving = true;
    saveForumDesign({ template: design.template, signerName: design.signerName, signerTitle: design.signerTitle }).then(
      ({ data }) => {
        this.saving = false;
        this.dirty = false;
        Object.assign(design, data);
        app.alerts.show({ type: 'success' }, t('saved'));
        m.redraw();
      },
      () => {
        this.saving = false;
        m.redraw();
      }
    );
  }
}
