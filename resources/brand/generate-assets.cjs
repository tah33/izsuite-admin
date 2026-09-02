/**
 * izSuite brand asset generator.
 *
 * Emits every logo, icon, favicon and social image under public/ from a single
 * palette. The wordmark is outlined from Manrope ExtraBold (already vendored in
 * public/fonts, and the face the PDF invoices use), so the SVGs are pure paths
 * with no font dependency at render time.
 *
 * The two dependencies are intentionally NOT in package.json — this runs by hand
 * when the brand changes, not on every install:
 *
 *   npm install --no-save opentype.js sharp
 *   node resources/brand/generate-assets.cjs
 *
 * Must run from the project root. Keep BLUE/INK in step with config/brand.php
 * and the --brand-* tokens in resources/css/app.css.
 */
const opentype = require('opentype.js');
const fs = require('fs');
const path = require('path');

const ROOT = process.cwd();
const OUT  = path.join(ROOT, 'public/images/brand');

const BLUE  = '#2563EB';
const INK   = '#0F172A';
const WHITE = '#FFFFFF';

const load = (f) => opentype.parse(fs.readFileSync(path.join(ROOT, 'public/fonts', f)).buffer);
const XB = load('Manrope-ExtraBold.ttf');
const BD = load('Manrope-Bold.ttf');

const r = (n) => Math.round(n * 1000) / 1000;

/* ---------- wordmark: "iz" + "Suite" as two colour runs, correctly kerned ---------- */
function wordmark(size) {
  const fullAdv  = XB.getAdvanceWidth('izSuite', size);
  const suiteAdv = XB.getAdvanceWidth('Suite', size);
  const xSuite   = fullAdv - suiteAdv;               // keeps the z->S kern pair

  const iz    = XB.getPath('iz', 0, 0, size);
  const suite = XB.getPath('Suite', xSuite, 0, size);

  const all = new opentype.Path();
  all.extend(iz); all.extend(suite);
  const bb = all.getBoundingBox();

  return { iz, suite, bb, width: bb.x2 - bb.x1, capH: -bb.y1, x1: bb.x1 };
}

/* ---------- letter-spaced text, laid out glyph by glyph ---------- */
function tracked(font, text, size, tracking) {
  let x = 0;
  const p = new opentype.Path();
  for (const ch of text) {
    const g = font.charToGlyph(ch);
    p.extend(g.getPath(x, 0, size));
    x += (g.advanceWidth / font.unitsPerEm) * size + tracking;
  }
  const bb = p.getBoundingBox();
  return { path: p, width: x - tracking, capH: -bb.y1 };
}

/* ---------- full lockup: wordmark + rule/tagline/rule scaled to wordmark width ---------- */
const TAGLINE = 'ALL-IN-ONE BUSINESS SOFTWARE';

function lockup(opts) {
  const size = (opts && opts.size) || 200;
  const pad  = (opts && opts.pad)  || 0;
  const wm   = wordmark(size);

  // Solve the tagline size so dash+gap+text+gap+dash spans exactly the wordmark width.
  const probe  = 100;
  const t0     = tracked(BD, TAGLINE, probe, probe * 0.2);
  const unit   = (w) => ({ dash: w * 1.55, thick: w * 0.1, gap: w * 0.62 });
  const u0     = unit(probe);
  const total0 = u0.dash * 2 + u0.gap * 2 + t0.width;
  const tSize  = (wm.width / total0) * probe;

  const tl = tracked(BD, TAGLINE, tSize, tSize * 0.2);
  const u  = unit(tSize);

  // vertical: tagline caps start just under the wordmark baseline
  const tlTop  = wm.capH * 0.17;
  const tlBase = tlTop + tl.capH;

  const dashY  = tlTop + tl.capH / 2 - u.thick / 2;
  const leftX  = wm.x1;
  const textX  = leftX + u.dash + u.gap;
  const rightX = leftX + wm.width - u.dash;

  const h = wm.capH + tlBase;

  const body = (izFill, suiteFill, tagFill) =>
    '  <g transform="translate(' + r(pad - wm.x1) + ' ' + r(pad + wm.capH) + ')">\n' +
    '    <path fill="' + izFill + '" d="' + wm.iz.toPathData(3) + '"/>\n' +
    '    <path fill="' + suiteFill + '" d="' + wm.suite.toPathData(3) + '"/>\n' +
    '    <g fill="' + tagFill + '">\n' +
    '      <rect x="' + r(leftX) + '" y="' + r(dashY) + '" width="' + r(u.dash) + '" height="' + r(u.thick) + '" rx="' + r(u.thick / 2) + '"/>\n' +
    '      <path transform="translate(' + r(textX) + ' ' + r(tlBase) + ')" d="' + tl.path.toPathData(3) + '"/>\n' +
    '      <rect x="' + r(rightX) + '" y="' + r(dashY) + '" width="' + r(u.dash) + '" height="' + r(u.thick) + '" rx="' + r(u.thick / 2) + '"/>\n' +
    '    </g>\n' +
    '  </g>';

  return { wm, tl, u, body, width: wm.width + pad * 2, height: h + pad * 2 };
}

/* ---------- wordmark only ---------- */
function wordmarkOnly(opts) {
  const size = (opts && opts.size) || 200;
  const pad  = (opts && opts.pad)  || 0;
  const wm   = wordmark(size);

  const body = (izFill, suiteFill) =>
    '  <g transform="translate(' + r(pad - wm.x1) + ' ' + r(pad + wm.capH) + ')">\n' +
    '    <path fill="' + izFill + '" d="' + wm.iz.toPathData(3) + '"/>\n' +
    '    <path fill="' + suiteFill + '" d="' + wm.suite.toPathData(3) + '"/>\n' +
    '  </g>';

  return { body, width: wm.width + pad * 2, height: wm.capH + pad * 2 };
}

/* ---------- "iz" mark only ---------- */
function markOnly(opts) {
  const size = (opts && opts.size) || 200;
  const pad  = (opts && opts.pad)  || 0;
  const p    = XB.getPath('iz', 0, 0, size);
  const bb   = p.getBoundingBox();

  const body = (fill) =>
    '  <g transform="translate(' + r(pad - bb.x1) + ' ' + r(pad - bb.y1) + ')">\n' +
    '    <path fill="' + fill + '" d="' + p.toPathData(3) + '"/>\n' +
    '  </g>';

  return { body, width: bb.x2 - bb.x1 + pad * 2, height: bb.y2 - bb.y1 + pad * 2 };
}

/* ---------- app icon tile: rounded square + centred "iz" ---------- */
function icon(o) {
  o = o || {};
  const box        = o.box        || 512;
  const radius     = o.radius     === undefined ? 0.2234 : o.radius;
  const glyphRatio = o.glyphRatio === undefined ? 0.56   : o.glyphRatio;
  const bg         = o.bg;
  const fg         = o.fg;
  const bgStroke   = o.bgStroke || null;

  const size = box * 2;                       // oversized, then measured and fitted
  const p    = XB.getPath('iz', 0, 0, size);
  const bb   = p.getBoundingBox();
  const gw   = bb.x2 - bb.x1;
  const gh   = bb.y2 - bb.y1;

  const scale = (box * glyphRatio) / gw;
  const dx    = (box - gw * scale) / 2 - bb.x1 * scale;
  const dy    = (box - gh * scale) / 2 - bb.y1 * scale;
  const rad   = box * radius;

  const plate = bg === 'none'
    ? ''
    : '  <rect width="' + box + '" height="' + box + '" rx="' + r(rad) + '" fill="' + bg + '"' +
      (bgStroke ? ' stroke="' + bgStroke + '" stroke-width="' + r(box * 0.016) + '"' : '') + '/>\n';

  const body = plate +
    '  <g transform="translate(' + r(dx) + ' ' + r(dy) + ') scale(' + r(scale) + ')">' +
    '<path fill="' + fg + '" d="' + p.toPathData(3) + '"/></g>';

  return { width: box, height: box, body: body };
}

function svg(o) {
  return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + r(o.width) + ' ' + r(o.height) +
    '" width="' + r(o.width) + '" height="' + r(o.height) + '" role="img" aria-label="' + o.title + '">\n' +
    '  <title>' + o.title + '</title>\n' + o.body + '\n</svg>\n';
}

const write = (file, content) => {
  const dest = path.join(OUT, file);
  fs.writeFileSync(dest, content);
  console.log('  ' + path.relative(ROOT, dest).replace(/\\/g, '/') + '  (' + content.length + 'b)');
};

fs.mkdirSync(OUT, { recursive: true });
console.log('writing brand assets:');

const LOCKUP_TITLE = 'izSuite - All-in-One Business Software';

/* full lockup */
const lk = lockup({ size: 200, pad: 6 });
write('izsuite-logo.svg',       svg({ width: lk.width, height: lk.height, body: lk.body(BLUE, INK, INK),      title: LOCKUP_TITLE }));
write('izsuite-logo-light.svg', svg({ width: lk.width, height: lk.height, body: lk.body(WHITE, WHITE, WHITE), title: LOCKUP_TITLE }));

/* wordmark */
const wo = wordmarkOnly({ size: 200, pad: 6 });
write('izsuite-wordmark.svg',       svg({ width: wo.width, height: wo.height, body: wo.body(BLUE, INK),    title: 'izSuite' }));
write('izsuite-wordmark-light.svg', svg({ width: wo.width, height: wo.height, body: wo.body(WHITE, WHITE), title: 'izSuite' }));

/* bare mark */
const mk = markOnly({ size: 200, pad: 4 });
write('izsuite-mark.svg',       svg({ width: mk.width, height: mk.height, body: mk.body(BLUE),  title: 'izSuite' }));
write('izsuite-mark-light.svg', svg({ width: mk.width, height: mk.height, body: mk.body(WHITE), title: 'izSuite' }));

/* app icons */
write('izsuite-icon.svg',      svg(Object.assign(icon({ bg: WHITE, fg: BLUE, bgStroke: '#E5E7EB' }), { title: 'izSuite' })));
write('izsuite-icon-blue.svg', svg(Object.assign(icon({ bg: BLUE,  fg: WHITE }),                     { title: 'izSuite' })));
write('izsuite-icon-dark.svg', svg(Object.assign(icon({ bg: INK,   fg: WHITE }),                     { title: 'izSuite' })));

/* maskable android icon: same plate, glyph pulled into the safe zone */
write('izsuite-icon-maskable.svg', svg(Object.assign(icon({ bg: BLUE, fg: WHITE, radius: 0, glyphRatio: 0.40 }), { title: 'izSuite' })));

/* favicon - blue plate reads on both light and dark browser chrome */
fs.writeFileSync(path.join(ROOT, 'public/favicon.svg'), svg(Object.assign(icon({ box: 64, bg: BLUE, fg: WHITE }), { title: 'izSuite' })));
console.log('  public/favicon.svg');

/* ---------- social / OG card: navy field, reversed lockup, centred ---------- */
const OG_W = 1200, OG_H = 630;
const ogLk    = lockup({ size: 200, pad: 0 });
const ogScale = (OG_W * 0.54) / ogLk.width;
const ogCard  = svg({
  width: OG_W,
  height: OG_H,
  title: LOCKUP_TITLE,
  body:
    '  <rect width="' + OG_W + '" height="' + OG_H + '" fill="' + INK + '"/>\n' +
    '  <circle cx="' + OG_W + '" cy="0" r="' + r(OG_H * 0.72) + '" fill="' + BLUE + '" opacity="0.16"/>\n' +
    '  <circle cx="0" cy="' + OG_H + '" r="' + r(OG_H * 0.5) + '" fill="' + BLUE + '" opacity="0.1"/>\n' +
    '  <g transform="translate(' + r((OG_W - ogLk.width * ogScale) / 2) + ' ' +
      r((OG_H - ogLk.height * ogScale) / 2) + ') scale(' + r(ogScale) + ')">\n' +
    ogLk.body(WHITE, WHITE, WHITE) + '\n  </g>',
});
write('og-image.svg', ogCard);

/* ================= rasters ================= */
const sharp = require('sharp');

const png = async (svgStr, w, h, dest) => {
  const buf = await sharp(Buffer.from(svgStr), { density: 384 })
    .resize(w, h, { fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } })
    .png({ compressionLevel: 9 })
    .toBuffer();
  fs.writeFileSync(dest, buf);
  console.log('  ' + path.relative(ROOT, dest).replace(/\\/g, '/') + '  ' + w + 'x' + h + '  (' + buf.length + 'b)');
  return buf;
};

/* PNG-payload ICO container */
function buildIco(entries) {
  const head = Buffer.alloc(6);
  head.writeUInt16LE(0, 0);              // reserved
  head.writeUInt16LE(1, 2);              // type: icon
  head.writeUInt16LE(entries.length, 4); // image count

  const dir = Buffer.alloc(16 * entries.length);
  let offset = head.length + dir.length;

  entries.forEach((e, i) => {
    const b = i * 16;
    dir.writeUInt8(e.size >= 256 ? 0 : e.size, b + 0); // width
    dir.writeUInt8(e.size >= 256 ? 0 : e.size, b + 1); // height
    dir.writeUInt8(0, b + 2);                          // palette count
    dir.writeUInt8(0, b + 3);                          // reserved
    dir.writeUInt16LE(1, b + 4);                       // colour planes
    dir.writeUInt16LE(32, b + 6);                      // bits per pixel
    dir.writeUInt32LE(e.data.length, b + 8);
    dir.writeUInt32LE(offset, b + 12);
    offset += e.data.length;
  });

  return Buffer.concat([head, dir, ...entries.map((e) => e.data)]);
}

(async () => {
  console.log('\nwriting rasters:');

  const iconRounded  = svg(Object.assign(icon({ box: 512, bg: BLUE, fg: WHITE }), { title: 'izSuite' }));
  const iconFull     = svg(Object.assign(icon({ box: 512, bg: BLUE, fg: WHITE, radius: 0, glyphRatio: 0.52 }), { title: 'izSuite' }));
  const iconMaskable = svg(Object.assign(icon({ box: 512, bg: BLUE, fg: WHITE, radius: 0, glyphRatio: 0.40 }), { title: 'izSuite' }));

  // favicon.ico — small sizes get the full-bleed plate so the glyph stays readable at 16px
  const ico = [];
  for (const s of [16, 32, 48]) {
    ico.push({ size: s, data: await png(iconFull, s, s, path.join(OUT, 'favicon-' + s + '.png')) });
  }
  const icoBuf = buildIco(ico);
  fs.writeFileSync(path.join(ROOT, 'public/favicon.ico'), icoBuf);
  console.log('  public/favicon.ico  16+32+48  (' + icoBuf.length + 'b)');

  // iOS home screen — opaque, no rounding (iOS applies its own mask)
  await png(iconFull, 180, 180, path.join(ROOT, 'public/apple-touch-icon.png'));

  // PWA / Android
  await png(iconRounded,  192, 192, path.join(OUT, 'icon-192.png'));
  await png(iconRounded,  512, 512, path.join(OUT, 'icon-512.png'));
  await png(iconMaskable, 512, 512, path.join(OUT, 'icon-512-maskable.png'));

  // PNG logos — email clients and PDF renderers do not handle SVG reliably
  const lkSvg      = fs.readFileSync(path.join(OUT, 'izsuite-logo.svg'), 'utf8');
  const lkLightSvg = fs.readFileSync(path.join(OUT, 'izsuite-logo-light.svg'), 'utf8');
  const woSvg      = fs.readFileSync(path.join(OUT, 'izsuite-wordmark.svg'), 'utf8');
  const woLightSvg = fs.readFileSync(path.join(OUT, 'izsuite-wordmark-light.svg'), 'utf8');
  const mkSvg      = fs.readFileSync(path.join(OUT, 'izsuite-mark.svg'), 'utf8');
  const mkLightSvg = fs.readFileSync(path.join(OUT, 'izsuite-mark-light.svg'), 'utf8');

  const at = (w, srcW, srcH) => [w, Math.round((w / srcW) * srcH)];

  await png(lkSvg,      ...at(720, lk.width, lk.height), path.join(OUT, 'izsuite-logo.png'));
  await png(lkLightSvg, ...at(720, lk.width, lk.height), path.join(OUT, 'izsuite-logo-light.png'));
  await png(woSvg,      ...at(720, wo.width, wo.height), path.join(OUT, 'izsuite-wordmark.png'));
  await png(woLightSvg, ...at(720, wo.width, wo.height), path.join(OUT, 'izsuite-wordmark-light.png'));
  await png(mkSvg,      ...at(256, mk.width, mk.height), path.join(OUT, 'izsuite-mark.png'));
  await png(mkLightSvg, ...at(256, mk.width, mk.height), path.join(OUT, 'izsuite-mark-light.png'));

  // social card
  await png(ogCard, OG_W, OG_H, path.join(OUT, 'og-image.png'));

  console.log('\nmetrics:');
  console.log('  lockup   ' + r(lk.width) + ' x ' + r(lk.height) + '  (ratio ' + r(lk.width / lk.height) + ')');
  console.log('  wordmark ' + r(wo.width) + ' x ' + r(wo.height) + '  (ratio ' + r(wo.width / wo.height) + ')');
  console.log('  mark     ' + r(mk.width) + ' x ' + r(mk.height) + '  (ratio ' + r(mk.width / mk.height) + ')');
  console.log('  tagline caps ' + r(lk.tl.capH) + ' vs wordmark caps ' + r(lk.wm.capH));
})().catch((e) => { console.error(e); process.exit(1); });
