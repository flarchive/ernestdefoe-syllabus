import app from 'flarum/common/app';
import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';

export interface CertificateSvgAttrs extends ComponentAttrs {
  /** The certificate as SVG markup, from the server (see Certificates\SvgPainter). */
  svg: string;
  className?: string;
}

/**
 * Shows a certificate drawn as SVG, so the browser shapes every script in it.
 * The markup is the server's own (text XML-escaped there), parsed as SVG, not
 * HTML; anything that could run is stripped all the same.
 */
export default class CertificateSvg extends Component<CertificateSvgAttrs> {
  shown = '';

  view(): Mithril.Children {
    return <div className={'SyllabusCertificateSvg ' + (this.attrs.className || '')} />;
  }

  oncreate(vnode: Mithril.VnodeDOM<CertificateSvgAttrs, this>) {
    super.oncreate(vnode);
    this.place();
  }

  onupdate(vnode: Mithril.VnodeDOM<CertificateSvgAttrs, this>) {
    super.onupdate(vnode);
    this.place();
  }

  place() {
    if (this.attrs.svg === this.shown) return;
    this.shown = this.attrs.svg;
    const doc = new DOMParser().parseFromString(this.attrs.svg, 'image/svg+xml');
    const svg = doc.documentElement;
    if (svg.nodeName !== 'svg') return;
    svg.querySelectorAll('script, foreignObject').forEach((el) => el.remove());
    svg.querySelectorAll('*').forEach((el) => {
      for (const attr of Array.from(el.attributes)) if (/^on/i.test(attr.name)) el.removeAttribute(attr.name);
    });
    svg.removeAttribute('width');
    svg.removeAttribute('height');
    this.element.replaceChildren(document.importNode(svg, true));
  }
}

/** The certificate's SVG, as text. Failures are handled by the caller, which has a fallback for each. */
export function loadSvg(url: string): Promise<string> {
  // app.request parses every answer as JSON: wrap the SVG's text as a JSON string first.
  return app.request<string>({ method: 'GET', url, modifyText: (text: string) => JSON.stringify(text), errorHandler: () => {} });
}

/**
 * Downloads the certificate as a 2400 × 1697 JPEG drawn by the browser from
 * the same SVG the page shows. An SVG drawn as an image can't fetch fonts, so
 * its webfonts are inlined first; the visitor's own fonts (Chinese, Thai…)
 * work as they are.
 */
export async function downloadSvg(svg: string, filename: string): Promise<void> {
  const urls = Array.from(new Set(Array.from(svg.matchAll(/url\('([^']+)'\)/g), (m) => m[1])));
  for (const url of urls) {
    // A font is binary, which app.request (text and JSON) doesn't carry: plain fetch, same origin, cached for a year.
    const blob = await fetch(url.replace(/&amp;/g, '&')).then((r) => r.blob());
    const data = await new Promise<string>((resolve) => {
      const reader = new FileReader();
      reader.onload = () => resolve(String(reader.result));
      reader.readAsDataURL(blob);
    });
    svg = svg.split(url).join(data);
  }

  // Inside an SVG drawn as an image, fonts keep loading after decode(), and "block" would leave text
  // invisible until they do: swap, start them with a first draw, and give them a moment.
  svg = svg.replace(/font-display:block/g, 'font-display:swap');
  const image = new Image();
  const src = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml' }));
  try {
    image.src = src;
    await image.decode();
    document.createElement('canvas').getContext('2d')!.drawImage(image, 0, 0, 1, 1);
    await new Promise((resolve) => setTimeout(resolve, 800));
    const canvas = document.createElement('canvas');
    canvas.width = 2400;
    canvas.height = 1697;
    canvas.getContext('2d')!.drawImage(image, 0, 0, 2400, 1697);
    const jpeg = await new Promise<Blob>((resolve, reject) =>
      canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('canvas'))), 'image/jpeg', 0.92)
    );
    const link = document.createElement('a');
    link.href = URL.createObjectURL(jpeg);
    link.download = filename;
    link.click();
    setTimeout(() => URL.revokeObjectURL(link.href), 10000);
  } finally {
    URL.revokeObjectURL(src);
  }
}
