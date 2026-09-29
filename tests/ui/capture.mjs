// UI-TEST-001 — screenshot + axe capture for before/after UI comparisons.
//
// Run (no package.json changes; dependencies come from npx):
//   UI_PASSWORD=... npx --yes -p playwright@1.48 -p @axe-core/playwright@4.10 node tests/ui/capture.mjs
//
// Environment:
//   UI_PASSWORD        required — password shared by the demo accounts (never commit it)
//   UI_BASE            default http://127.0.0.1:8000 (dev servers must already be running)
//   UI_TEAM            default php-360
//   UI_LEAD_EMAIL      default rafiq@kaz-software.com   (team_lead)
//   UI_MEMBER_EMAIL    default hasib@kaz-software.com   (member)
//   UI_ADMIN_EMAIL     default admin@example.com        (platform admin)
//   UI_LABEL           default baseline  → output in storage/ui-baseline/<label>/
//   UI_ONLY            comma-separated page ids to limit the run
//   UI_WIDTHS          default 375,768,1024,1440
//   UI_THEMES          default light,dark
//   UI_AXE_WIDTH       default 1440 (axe runs once per page/tab/theme at this width)

import { createRequire } from 'node:module';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '../..');

// npx -p puts <tmp>/node_modules/.bin on PATH; ES modules ignore NODE_PATH, so resolve from there.
function load(name) {
    const dirs = (process.env.PATH ?? '')
        .split(path.delimiter)
        .filter((p) => p.endsWith(path.join('node_modules', '.bin')))
        .map((p) => path.dirname(p));
    for (const dir of [...dirs, path.join(root, 'node_modules')]) {
        try {
            return createRequire(path.join(dir, 'noop.js'))(name);
        } catch {
            // try the next candidate
        }
    }
    throw new Error(
        `Cannot resolve "${name}". Run through: npx -p playwright@1.48 -p @axe-core/playwright@4.10 node tests/ui/capture.mjs`,
    );
}

const { chromium } = load('playwright');
const { default: AxeBuilder } = load('@axe-core/playwright');

const env = (key, fallback) => process.env[key] || fallback;
const password = process.env.UI_PASSWORD;
if (!password) {
    console.error('UI_PASSWORD is required.');
    process.exit(1);
}

const base = env('UI_BASE', 'http://127.0.0.1:8000');
const team = env('UI_TEAM', 'php-360');
const label = env('UI_LABEL', 'baseline');
const widths = env('UI_WIDTHS', '375,768,1024,1440').split(',').map(Number);
const themes = env('UI_THEMES', 'light,dark').split(',');
const axeWidth = Number(env('UI_AXE_WIDTH', '1440'));
const only = process.env.UI_ONLY
    ? new Set(process.env.UI_ONLY.split(','))
    : null;
const out = path.join(root, 'storage', 'ui-baseline', label);

const accounts = {
    lead: {
        email: env('UI_LEAD_EMAIL', 'rafiq@kaz-software.com'),
        loginPath: '/login',
    },
    member: {
        email: env('UI_MEMBER_EMAIL', 'hasib@kaz-software.com'),
        loginPath: '/login',
    },
    admin: {
        email: env('UI_ADMIN_EMAIL', 'admin@example.com'),
        loginPath: '/admin/login',
    },
};

const manifest = JSON.parse(
    readFileSync(path.join(here, 'pages.json'), 'utf8'),
);
const pages = manifest.pages.filter((p) => !only || only.has(p.id));

const browser = await chromium.launch({
    channel: 'chrome',
    args: ['--no-sandbox'],
});

async function login(role) {
    const account = accounts[role];
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto(base + account.loginPath);
    await page.locator('input[name=email]').fill(account.email);
    await page.locator('input[name=password]').fill(password);
    await Promise.all([
        page.waitForURL((url) => !url.pathname.endsWith('/login'), {
            timeout: 20000,
        }),
        page.locator('button[type=submit]').first().click(),
    ]);
    const state = await context.storageState();
    await context.close();
    return state;
}

const states = { guest: undefined };
for (const role of new Set(pages.map((p) => p.role))) {
    if (role !== 'guest') {
        states[role] = await login(role);
        console.log(`logged in as ${role} (${accounts[role].email})`);
    }
}

async function settle(page) {
    await page.waitForSelector('#app *', { timeout: 20000 });
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(400);
}

async function runAxe(page) {
    const result = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
        .analyze();
    return result.violations.map((v) => ({
        id: v.id,
        impact: v.impact,
        help: v.help,
        nodes: v.nodes.length,
        targets: v.nodes.slice(0, 5).map((n) => n.target.join(' ')),
    }));
}

const results = [];

for (const theme of themes) {
    for (const width of widths) {
        const contexts = {};
        for (const entry of pages) {
            if (!contexts[entry.role]) {
                const context = await browser.newContext({
                    storageState: states[entry.role],
                    viewport: { width, height: 900 },
                    reducedMotion: 'reduce',
                });
                const { hostname } = new URL(base);
                await context.addCookies([
                    {
                        name: 'appearance',
                        value: theme,
                        domain: hostname,
                        path: '/',
                    },
                ]);
                await context.addInitScript(
                    (t) => localStorage.setItem('appearance', t),
                    theme,
                );
                contexts[entry.role] = context;
            }

            const page = await contexts[entry.role].newPage();
            const consoleErrors = [];
            const httpErrors = [];
            page.on(
                'console',
                (m) =>
                    m.type() === 'error' &&
                    consoleErrors.push(m.text().slice(0, 300)),
            );
            page.on('pageerror', (e) =>
                consoleErrors.push(String(e).slice(0, 300)),
            );
            page.on(
                'response',
                (r) =>
                    r.status() >= 400 &&
                    !r.url().endsWith('favicon.ico') &&
                    httpErrors.push(`${r.status()} ${r.url()}`),
            );

            const url = base + entry.path.replaceAll('{team}', team);
            const record = {
                id: entry.id,
                role: entry.role,
                theme,
                width,
                url,
                shots: [],
                axe: {},
            };
            try {
                const response = await page.goto(url, {
                    waitUntil: 'domcontentloaded',
                });
                record.status = response?.status();
                await settle(page);
                record.finalUrl = page.url();

                const views = [{ name: null }, ...(entry.tabs ?? [])];
                for (const view of views) {
                    if (view.selector) {
                        // Evaluate-click: tabs may be clipped off-screen at narrow widths (that is a finding, not a script failure).
                        await page
                            .locator(view.selector)
                            .evaluate((el) => el.click());
                        await page.waitForTimeout(400);
                    }
                    const key = view.name
                        ? `${entry.id}--${view.name}`
                        : entry.id;
                    const file = path.join(
                        out,
                        'shots',
                        key,
                        `${theme}-${width}.png`,
                    );
                    mkdirSync(path.dirname(file), { recursive: true });
                    await page.screenshot({ path: file, fullPage: true });
                    record.shots.push(path.relative(out, file));
                    if (width === axeWidth) {
                        record.axe[key] = await runAxe(page);
                    }
                }
                record.horizontalOverflow = await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth >
                        window.innerWidth,
                );
            } catch (error) {
                record.error = String(error).split('\n')[0];
            }
            record.consoleErrors = consoleErrors;
            record.httpErrors = httpErrors;
            results.push(record);
            await page.close();
            process.stdout.write(
                `${record.error ? '✗' : '✓'} ${theme} ${width} ${entry.id}\n`,
            );
        }
        for (const context of Object.values(contexts)) {
            await context.close();
        }
    }
}

await browser.close();

mkdirSync(out, { recursive: true });
writeFileSync(
    path.join(out, 'results.json'),
    JSON.stringify(
        {
            label,
            base,
            team,
            widths,
            themes,
            results,
            skipped: manifest.skipped,
        },
        null,
        2,
    ),
);

// ---- Markdown report ----
const impacts = ['critical', 'serious', 'moderate', 'minor'];
const lines = [
    `# UI capture — ${label}`,
    '',
    `Base: ${base} · Team: ${team} · Widths: ${widths.join(', ')} · Themes: ${themes.join(', ')} · Axe at ${axeWidth}px`,
    '',
];

lines.push(
    '## Pages',
    '',
    '| Page | Role | Status | Redirected to | Overflow widths | Console errors | HTTP ≥400 | Error |',
    '|---|---|---|---|---|---|---|---|',
);
for (const entry of pages) {
    const recs = results.filter((r) => r.id === entry.id);
    const first = recs[0] ?? {};
    const expected = base + entry.path.replaceAll('{team}', team);
    const redirected =
        first.finalUrl && first.finalUrl.split('?')[0] !== expected
            ? first.finalUrl.replace(base, '')
            : '';
    const overflow = [
        ...new Set(
            recs.filter((r) => r.horizontalOverflow).map((r) => r.width),
        ),
    ].join(', ');
    const consoleCount = recs.reduce((n, r) => n + r.consoleErrors.length, 0);
    const httpCount = recs.reduce((n, r) => n + r.httpErrors.length, 0);
    const errors = [...new Set(recs.map((r) => r.error).filter(Boolean))].join(
        '; ',
    );
    lines.push(
        `| ${entry.id} | ${entry.role} | ${first.status ?? '—'} | ${redirected} | ${overflow} | ${consoleCount} | ${httpCount} | ${errors} |`,
    );
}

lines.push(
    '',
    '## Accessibility (axe, WCAG 2.2 AA tags)',
    '',
    `| View | Theme | ${impacts.join(' | ')} | Rules |`,
    `|---|---|${impacts.map(() => '---').join('|')}|---|`,
);
const totals = Object.fromEntries(impacts.map((i) => [i, 0]));
for (const r of results.filter((x) => x.width === axeWidth)) {
    for (const [view, violations] of Object.entries(r.axe)) {
        const count = (impact) =>
            violations
                .filter((v) => v.impact === impact)
                .reduce((n, v) => n + v.nodes, 0);
        impacts.forEach((i) => (totals[i] += count(i)));
        lines.push(
            `| ${view} | ${r.theme} | ${impacts.map(count).join(' | ')} | ${violations.map((v) => v.id).join(', ')} |`,
        );
    }
}
lines.push(
    '',
    `**Totals (nodes):** ${impacts.map((i) => `${i} ${totals[i]}`).join(' · ')}`,
);

lines.push(
    '',
    '## Not captured',
    '',
    ...manifest.skipped.map((s) => `- \`${s.page}\` — ${s.reason}`),
    '',
);
writeFileSync(path.join(out, 'report.md'), lines.join('\n'));
console.log(`\nReport: ${path.relative(root, path.join(out, 'report.md'))}`);
