// Films the Afrit 6 help videos as still frames (one per step), in a demo copy of the portal with made-up data.
// Usage: node tools/afrit6-help/film.cjs <plan.json> <out-dir> <base-url> [topic]
// Writes <out-dir>/<topic>/00-title.png, 01.png … NN.png, 99-end.png and <out-dir>/manifest.json.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const [planFile, outDir, base, only] = process.argv.slice(2);
const plan = JSON.parse(fs.readFileSync(planFile, 'utf8'));
const W = 1280, H = 720;
const logo = 'data:image/png;base64,' + fs.readFileSync(path.join(__dirname, '../../public/images/afrit6-logo.png')).toString('base64');

function nextWeekday() {
  const d = new Date(); d.setDate(d.getDate() + 1);
  while (d.getDay() === 0 || d.getDay() === 6) d.setDate(d.getDate() + 1);
  return d.toISOString().slice(0, 10);
}

async function card(page, file, big, small) {
  await page.setContent(`<html><body style="margin:0;width:${W}px;height:${H}px;background:radial-gradient(circle at 30% 20%,#1d3a22,#0b100c 70%);display:flex;flex-direction:column;justify-content:center;padding:0 110px;box-sizing:border-box;font-family:Inter,Arial,sans-serif;color:#fff">
    <img src="${logo}" style="height:64px;width:auto;align-self:flex-start;margin-bottom:46px">
    <div style="font-size:54px;font-weight:800;line-height:1.1;letter-spacing:-.5px">${big}</div>
    ${small ? `<div style="font-size:26px;color:#cfd8cc;margin-top:18px;line-height:1.35">${small}</div>` : ''}
    <div style="position:absolute;left:110px;bottom:60px;width:120px;height:6px;border-radius:3px;background:#f5d60a"></div></body></html>`);
  await page.screenshot({ path: file });
}

async function overlay(page, sel, nth, caption, n, total) {
  const loc = page.locator(sel).nth(nth);
  await loc.scrollIntoViewIfNeeded({ timeout: 5000 });
  const box = await loc.boundingBox();
  if (box) {
    // Keep the element in the middle of the picture, above the caption bar (tall ones: their top part).
    const sy = await page.evaluate(() => window.scrollY);
    const room = H - 150;
    const abs = box.y + sy;
    let y = box.height > room - 40 ? abs - 90 : abs + box.height / 2 - room / 2;
    const max = await page.evaluate(() => document.documentElement.scrollHeight - innerHeight);
    await page.evaluate((v) => window.scrollTo(0, v), Math.max(0, Math.min(y, max)));
  }
  const b = await loc.boundingBox();
  await page.evaluate(({ b, caption, n, total }) => {
    document.querySelectorAll('.zz-help').forEach((e) => e.remove());
    const add = (css, html = '') => { const d = document.createElement('div'); d.className = 'zz-help'; d.style.cssText = css; d.innerHTML = html; document.body.appendChild(d); return d; };
    if (b) {
      const p = 8;
      add(`position:fixed;left:${b.x - p}px;top:${b.y - p}px;width:${b.width + 2 * p}px;height:${b.height + 2 * p}px;border:4px solid #f5d60a;border-radius:12px;box-shadow:0 0 0 9999px rgba(8,12,9,.38);z-index:99998;pointer-events:none`);
      add(`position:fixed;left:${b.x + Math.min(b.width - 6, Math.max(14, b.width * 0.62))}px;top:${b.y + Math.min(b.height - 4, Math.max(12, b.height * 0.6))}px;width:28px;height:28px;z-index:99999;pointer-events:none`,
        '<svg viewBox="0 0 24 24" width="28" height="28"><path d="M4 2l15 9.5-6.4 1.3 3.7 7.3-2.9 1.4-3.6-7.3L4 19z" fill="#fff" stroke="#111" stroke-width="1.6" stroke-linejoin="round"/></svg>');
    }
    add(`position:fixed;left:50%;transform:translateX(-50%);bottom:26px;max-width:1100px;width:calc(100% - 120px);background:rgba(10,14,11,.94);color:#fff;border-radius:16px;padding:18px 26px;font:600 24px/1.35 Inter,Arial,sans-serif;display:flex;gap:18px;align-items:center;z-index:100000;box-shadow:0 10px 30px rgba(0,0,0,.35)`,
      `<span style="flex:none;background:#f5d60a;color:#111;border-radius:10px;padding:4px 11px;font-size:19px;font-weight:800">${n}/${total}</span><span>${caption}</span>`);
  }, { b, caption, n, total });
}

async function act(page, d) {
  if (d.fill_date) await page.fill(d.fill_date, nextWeekday());
  if (d.select) await page.selectOption(d.select[0], d.select[1]);
  if (d.select_first) {
    const v = await page.$eval(d.select_first, (s) => [...s.options].find((o) => o.value !== '')?.value);
    if (v !== undefined) await page.selectOption(d.select_first, v);
  }
  if (d.fill) await page.fill(d.fill[0], d.fill[1]);
  if (d.type) await page.fill(d.type[0], d.type[1]);
  if (d.click) {
    await Promise.all([page.waitForLoadState('load'), page.locator(d.click).first().click()]);
    await page.waitForTimeout(600);
  }
  if (d.goto) await page.goto(base + d.goto);
}

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: W, height: H }, deviceScaleFactor: 1, locale: plan.lang === 'nl' ? 'nl-BE' : 'en-GB' });
  await page.goto(base + '/afrit6');
  await page.fill('input[name=email]', 'lotte@studio-lumen.example');
  await page.fill('input[name=password]', 'Demo-Afrit6-2026!');
  await Promise.all([page.waitForNavigation(), page.press('input[name=password]', 'Enter')]);
  const manifest = {};
  for (const t of plan.topics) {
    if (only && t.key !== only) continue;
    const dir = path.join(outDir, t.key);
    fs.mkdirSync(dir, { recursive: true });
    await card(page, path.join(dir, '00-title.png'), t.title, t.intro);
    await page.goto(base + t.page);
    await page.waitForTimeout(400);
    const frames = [];
    for (let i = 0; i < t.steps.length; i++) {
      const s = t.steps[i];
      try {
        await overlay(page, s.el, s.nth || 0, s.text, i + 1, t.steps.length);
      } catch (e) {
        console.error(`${t.key} step ${i + 1}: ${e.message.split('\n')[0]}`);
        await overlay(page, 'body', 0, s.text, i + 1, t.steps.length);
      }
      const f = path.join(dir, String(i + 1).padStart(2, '0') + '.png');
      await page.screenshot({ path: f });
      frames.push(f);
      await page.evaluate(() => document.querySelectorAll('.zz-help').forEach((e) => e.remove()));
      try { await act(page, s.do || {}); } catch (e) { console.error(`${t.key} step ${i + 1} action: ${e.message.split('\n')[0]}`); }
    }
    await card(page, path.join(dir, '99-end.png'), plan.outro.split('!')[0] + '!', plan.outro.split('!').slice(1).join('!').trim());
    manifest[t.key] = { title: t.title, intro: t.intro, steps: t.steps.map((s) => s.text), outro: plan.outro };
    console.log('filmed', t.key, frames.length);
  }
  fs.writeFileSync(path.join(outDir, 'manifest.json'), JSON.stringify(manifest, null, 1));
  await browser.close();
})();
