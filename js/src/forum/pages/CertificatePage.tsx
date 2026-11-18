import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import { certificate, courseUrl } from '../../common/api';
import type { Certificate } from '../../common/api';
import { t } from '../components/util';

/**
 * /certificates/:code — for the member who earned it, the certificate itself,
 * ready to print (or save as PDF from the print dialog) or download; for
 * anyone else with the link, a short verification that it is genuine, with
 * the certificate under it. The certificate is one drawing from the server
 * (see Certificates\Renderer), so the page, the printout and the share image
 * are the same picture.
 */
export default class CertificatePage extends Page {
  cert: Certificate | null = null;
  missing = false;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    app.setTitle(extractText(t('certificate.title')));
    certificate(String(m.route.param('code') || '').toLowerCase()).then(
      ({ data }) => {
        this.cert = data;
        m.redraw();
      },
      () => {
        this.missing = true;
        m.redraw();
      }
    );
  }

  view(): Mithril.Children {
    const cert = this.cert;

    return (
      <PageStructure className="SyllabusCertificatePage" loading={!cert && !this.missing}>
        {this.missing ? (
          <div className="SyllabusVerify SyllabusVerify--invalid">
            <Icon name="fas fa-circle-xmark" />
            <h2>{t('certificate.invalid')}</h2>
          </div>
        ) : cert ? (
          cert.isOwner ? (
            this.certificate(cert)
          ) : (
            this.verify(cert)
          )
        ) : null}
      </PageStructure>
    );
  }

  date(cert: Certificate): string {
    return dayjs(cert.issuedAt).format('LL');
  }

  /** The drawing, linked to itself at full size: on a phone, a tap opens it to zoom. */
  image(cert: Certificate): Mithril.Children {
    return (
      <a className="SyllabusCertificate-link" href={cert.imageUrl} target="_blank" rel="noopener">
        <img
          className="SyllabusCertificate-image"
          src={cert.imageUrl}
          width="2400"
          height="1697"
          alt={extractText(t('certificate.share_title', { name: cert.userName, course: cert.courseTitle }))}
        />
      </a>
    );
  }

  certificate(cert: Certificate): Mithril.Children {
    return (
      <div className="SyllabusCertificateWrap">
        <div className="SyllabusCertificate-actions">
          <Button className="Button Button--primary" icon="fas fa-print" onclick={() => window.print()}>
            {t('certificate.print')}
          </Button>
          <a className="Button" href={cert.imageUrl + '&download=1'} download>
            <Icon name="fas fa-download" className="Button-icon" />
            <span className="Button-label">{t('certificate.download')}</span>
          </a>
          <Button
            className="Button"
            icon="fas fa-link"
            onclick={() => navigator.clipboard?.writeText(cert.verifyUrl).then(() => app.alerts.show({ type: 'success' }, t('certificate.copied')))}
          >
            {t('certificate.copy_link')}
          </Button>
          {cert.course ? (
            <Link className="Button Button--link" href={courseUrl(cert.course)}>
              {t('certificate.back')}
            </Link>
          ) : null}
        </div>
        <article className="SyllabusCertificate">{this.image(cert)}</article>
      </div>
    );
  }

  verify(cert: Certificate): Mithril.Children {
    return (
      <div className="SyllabusVerify">
        <Icon name="fas fa-circle-check" />
        <h2>{t('certificate.verified')}</h2>
        <p>{t('certificate.verified_text', { name: cert.userName, course: cert.courseTitle, date: this.date(cert) })}</p>
        <p className="helpText">{t('certificate.issued_by_forum', { forum: app.forum.attribute<string>('title') })}</p>
        {cert.course ? (
          <Link className="Button" href={courseUrl(cert.course)}>
            {t('certificate.see_course')}
          </Link>
        ) : null}
        <article className="SyllabusCertificate SyllabusCertificate--shared">{this.image(cert)}</article>
      </div>
    );
  }
}
