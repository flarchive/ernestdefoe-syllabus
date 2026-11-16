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
 * ready to print (or save as PDF from the print dialog); for anyone else with
 * the link, a short verification that it is genuine.
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

  /** The admin's wording, or the default, with its placeholders filled in — as text, never HTML. */
  text(cert: Certificate): string {
    const forum = app.forum.attribute<string>('title');
    if (!cert.text)
      return extractText(t('certificate.default_text', { forum, date: this.date(cert), name: cert.userName, course: cert.courseTitle }));
    return cert.text
      .replace(/\{name\}/g, cert.userName)
      .replace(/\{course\}/g, cert.courseTitle)
      .replace(/\{date\}/g, this.date(cert))
      .replace(/\{forum\}/g, forum);
  }

  certificate(cert: Certificate): Mithril.Children {
    return (
      <div className="SyllabusCertificateWrap">
        <div className="SyllabusCertificate-actions">
          <Button className="Button Button--primary" icon="fas fa-print" onclick={() => window.print()}>
            {t('certificate.print')}
          </Button>
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
        <article className="SyllabusCertificate">
          {cert.logoUrl ? <img className="SyllabusCertificate-logo" src={cert.logoUrl} alt={app.forum.attribute<string>('title')} /> : null}
          <div className="SyllabusCertificate-kicker">{t('certificate.heading')}</div>
          <div className="SyllabusCertificate-presented">{t('certificate.presented_to')}</div>
          <h1 className="SyllabusCertificate-name">{cert.userName}</h1>
          <div className="SyllabusCertificate-for">{t('certificate.for_completing')}</div>
          <h2 className="SyllabusCertificate-course">{cert.courseTitle}</h2>
          <p className="SyllabusCertificate-text">{this.text(cert)}</p>
          <div className="SyllabusCertificate-foot">
            <div>
              <div className="SyllabusCertificate-line">{this.date(cert)}</div>
              <small>{t('certificate.date')}</small>
            </div>
            <div>
              <div className="SyllabusCertificate-line">{cert.signature || app.forum.attribute<string>('title')}</div>
              <small>{t('certificate.issued_by')}</small>
            </div>
          </div>
          <div className="SyllabusCertificate-verify">
            {t('certificate.verify_at')} <span>{cert.verifyUrl}</span>
          </div>
        </article>
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
      </div>
    );
  }
}
