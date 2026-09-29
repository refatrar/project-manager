// UI-TEST-001 — production bundle size report (raw + gzip) for before/after comparisons.
//
// Run:  node tests/ui/bundle-report.mjs            (UI_LABEL defaults to "baseline")
//
// Builds into storage/ui-baseline/<label>/build via `vp build --outDir`, so the committed
// public/build is never touched. laravel-vite-plugin honours a CLI outDir (userConfig.build.outDir).

import { spawnSync } from 'node:child_process';
import { gzipSync } from 'node:zlib';
import {
    mkdirSync,
    readdirSync,
    readFileSync,
    rmSync,
    statSync,
    writeFileSync,
} from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(
    path.dirname(fileURLToPath(import.meta.url)),
    '../..',
);
const label = process.env.UI_LABEL || 'baseline';
const out = path.join(root, 'storage', 'ui-baseline', label);
const buildDir = path.join(out, 'build');

rmSync(buildDir, { recursive: true, force: true });
mkdirSync(out, { recursive: true });

const build = spawnSync('npx', ['vp', 'build', '--outDir', buildDir], {
    cwd: root,
    stdio: 'inherit',
});
if (build.status !== 0) {
    console.error('Build failed.');
    process.exit(build.status ?? 1);
}

const manifest = JSON.parse(
    readFileSync(path.join(buildDir, 'manifest.json'), 'utf8'),
);
const entryFiles = new Set(
    Object.values(manifest)
        .filter((c) => c.isEntry)
        .map((c) => c.file),
);
const pageOf = Object.fromEntries(
    Object.entries(manifest)
        .filter(([src]) => src.startsWith('resources/js/pages/'))
        .map(([src, chunk]) => [
            chunk.file,
            src.replace('resources/js/pages/', '').replace(/\.tsx$/, ''),
        ]),
);

const assets = readdirSync(path.join(buildDir, 'assets'))
    .map((name) => {
        const file = path.join(buildDir, 'assets', name);
        const bytes = readFileSync(file);
        const rel = `assets/${name}`;
        return {
            file: rel,
            type: name.endsWith('.css')
                ? 'css'
                : name.endsWith('.js')
                  ? 'js'
                  : 'other',
            entry: entryFiles.has(rel),
            page: pageOf[rel] ?? null,
            raw: statSync(file).size,
            gzip: /\.(js|css|svg)$/.test(name) ? gzipSync(bytes).length : null,
        };
    })
    .sort((a, b) => b.raw - a.raw);

const sum = (list, key) => list.reduce((n, a) => n + (a[key] ?? 0), 0);
const kb = (n) => (n / 1024).toFixed(1);
const js = assets.filter((a) => a.type === 'js');
const css = assets.filter((a) => a.type === 'css');

const summary = {
    label,
    jsFiles: js.length,
    jsRaw: sum(js, 'raw'),
    jsGzip: sum(js, 'gzip'),
    cssRaw: sum(css, 'raw'),
    cssGzip: sum(css, 'gzip'),
    entries: assets
        .filter((a) => a.entry)
        .map(({ file, raw, gzip }) => ({ file, raw, gzip })),
};
writeFileSync(
    path.join(out, 'bundle.json'),
    JSON.stringify({ summary, assets }, null, 2),
);

const lines = [
    `# Bundle report — ${label}`,
    '',
    `JS: ${summary.jsFiles} files · ${kb(summary.jsRaw)} KB raw · ${kb(summary.jsGzip)} KB gzip`,
    `CSS: ${kb(summary.cssRaw)} KB raw · ${kb(summary.cssGzip)} KB gzip`,
    '',
    '| File | Kind | Raw KB | Gzip KB |',
    '|---|---|---:|---:|',
    ...assets
        .filter((a) => a.type !== 'other')
        .slice(0, 30)
        .map(
            (a) =>
                `| ${a.file} | ${a.entry ? 'entry' : a.page ? `page: ${a.page}` : a.type} | ${kb(a.raw)} | ${a.gzip == null ? '—' : kb(a.gzip)} |`,
        ),
    '',
];
writeFileSync(path.join(out, 'bundle.md'), lines.join('\n'));
console.log(lines.slice(0, 5).join('\n'));
console.log(`Report: ${path.relative(root, path.join(out, 'bundle.md'))}`);
