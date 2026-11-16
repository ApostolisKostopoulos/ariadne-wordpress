#!/usr/bin/env node
// Renders the wordpress.org icon and banner (.wordpress-org/*.png) from assets-src/*.svg with
// Chromium through Playwright (Apache-2.0). The banner's text is set in Commissioner (SIL OFL),
// whose variable latin woff2 file is passed in; nothing is downloaded by this script.
//
//   npm i --no-save playwright@1.64.1 && npx playwright install chromium   (once, anywhere)
//   node bin/render-assets.mjs <path to commissioner-latin-wght-normal.woff2>
//
// The font file ships with @fontsource-variable/commissioner on npm.
import { readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const fontPath = process.argv[2];
if (!fontPath) {
  console.error('usage: node bin/render-assets.mjs <commissioner-latin-wght-normal.woff2>');
  process.exit(1);
}
// Playwright from the current directory's node_modules (or NODE_PATH), not from this repo.
const require = createRequire(join(process.cwd(), 'noop.js'));
let chromium;
try {
  ({ chromium } = require('playwright'));
} catch {
  ({ chromium } = require('@playwright/test'));
}

const font = readFileSync(fontPath).toString('base64');
const jobs = [
  { svg: 'icon.svg', width: 256, height: 256, out: ['icon-256x256.png', 'icon-128x128.png'] },
  { svg: 'banner.svg', width: 1544, height: 500, out: ['banner-1544x500.png', 'banner-772x250.png'] },
];

const browser = await chromium.launch();
try {
  for (const job of jobs) {
    const svg = readFileSync(join(root, 'assets-src', job.svg), 'utf8');
    for (const name of job.out) {
      const scale = name.includes(`${job.width}x`) ? 1 : 0.5;
      const page = await browser.newPage({
        viewport: { width: job.width * scale, height: job.height * scale },
      });
      await page.setContent(
        `<!doctype html><style>@font-face{font-family:Commissioner;src:url(data:font/woff2;base64,${font}) format('woff2');font-weight:100 900}html,body{margin:0}svg{display:block;width:100vw;height:100vh}</style>${svg}`,
      );
      await page.evaluate(() => document.fonts.ready);
      writeFileSync(join(root, '.wordpress-org', name), await page.screenshot({ type: 'png' }));
      await page.close();
      console.log(`.wordpress-org/${name}`);
    }
  }
} finally {
  await browser.close();
}
