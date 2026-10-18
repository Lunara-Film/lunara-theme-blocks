'use strict';

const assert = require('node:assert/strict');
const { execFileSync, spawnSync } = require('node:child_process');
const { mkdtempSync, mkdirSync, writeFileSync, readFileSync, realpathSync, rmSync } = require('node:fs');
const { tmpdir } = require('node:os');
const { dirname, join } = require('node:path');
const { test } = require('node:test');
const { classify, isDocumentation, requireSuccess } = require('./ci-policy.cjs');

function repository(t) {
  const tempRoot = realpathSync(tmpdir());
  const cwd = mkdtempSync(join(tempRoot, 'lunara-ci-policy-'));
  t.after(() => {
    assert.equal(dirname(realpathSync(cwd)), tempRoot);
    rmSync(cwd, { recursive: true, force: true });
  });
  const git = (...args) => execFileSync('git', args, { cwd, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
  const write = (path, content) => {
    mkdirSync(dirname(join(cwd, path)), { recursive: true });
    writeFileSync(join(cwd, path), content);
  };
  const commit = (message) => {
    git('add', '--all');
    git('commit', '-qm', message);
    return git('rev-parse', 'HEAD');
  };
  git('init', '-q', '-b', 'main');
  git('config', 'user.email', 'ci-test@example.invalid');
  git('config', 'user.name', 'CI test');
  git('config', 'commit.gpgsign', 'false');
  git('config', 'core.autocrlf', 'false');
  write('README.md', '# Theme\n');
  write('docs/SESSION-LOG.md', '# Log\n');
  write('inc/example.php', '<?php echo "original";\n');
  return { cwd, git, write, commit, base: commit('Initial fixture') };
}

const push = (before, after) => ({ before, after });
const pr = (base, head) => ({ pull_request: { base: { sha: base }, head: { sha: head } } });
const results = (docsOnly, checks, browser, scope = 'success') => ({
  scope: { result: scope, outputs: { docs_only: docsOnly } },
  checks: { result: checks }, browser: { result: browser },
});

test('only explicit documentation locations qualify', () => {
  for (const path of ['AGENTS.md', 'ARCHITECTURE.md', 'CLAUDE.md', 'DESIGN.md', 'README.md', 'docs/SESSION-LOG.md', 'docs/hand offs/évidence.md']) {
    assert.equal(isDocumentation(path), true, path);
  }
  for (const path of ['style.css', 'functions.php', 'theme.json', 'assets/main.js', 'package.json', 'package-lock.json',
    '.github/workflows/ci.yml', '.github/scripts/ci-policy.cjs', 'tests/ci-skip.txt', 'tests/fixture.md',
    'docs/probe.php', 'docs/example.html', 'docs/data.json', '_oscar-facts-source.md', 'readme.md', 'unknown.txt']) {
    assert.equal(isDocumentation(path), false, path);
  }
});

test('documentation PRs and pushes qualify, but a mixed multi-commit push runs full CI', (t) => {
  const r = repository(t);
  r.write('docs/SESSION-LOG.md', '# Log\n\nReleased.\n');
  const docs = r.commit('Release receipt');
  assert.equal(classify('pull_request', pr(r.base, docs), r.cwd).docsOnly, true);
  assert.equal(classify('push', push(r.base, docs), r.cwd).docsOnly, true);
  r.write('inc/example.php', '<?php echo "changed";\n');
  r.commit('Change runtime');
  r.write('README.md', '# Updated documentation\n');
  const head = r.commit('Document runtime change');
  assert.equal(classify('push', push(docs, head), r.cwd).docsOnly, false);
  assert.equal(classify('pull_request', pr(r.base, head), r.cwd).docsOnly, false);
});

test('PR scope uses the merge base, without counting unrelated changes on main', (t) => {
  const r = repository(t);
  r.git('switch', '-qc', 'docs-branch');
  r.write('README.md', '# Branch documentation\n');
  const head = r.commit('Docs branch');
  r.git('switch', '-q', 'main');
  r.write('inc/example.php', '<?php echo "main changed";\n');
  const base = r.commit('Main change');
  assert.equal(classify('pull_request', pr(base, head), r.cwd).docsOnly, true);
  assert.equal(classify('push', push(base, head), r.cwd).docsOnly, false);
});

test('documentation renames and deletions qualify; a runtime rename into docs does not', (t) => {
  const r = repository(t);
  r.git('mv', 'docs/SESSION-LOG.md', 'docs/Releases with spaces.md');
  r.git('rm', 'README.md');
  const docs = r.commit('Move and remove documentation');
  assert.equal(classify('push', push(r.base, docs), r.cwd).docsOnly, true);
  r.git('mv', 'inc/example.php', 'docs/retired-source.md');
  const movedCode = r.commit('Move code into docs');
  assert.equal(classify('push', push(docs, movedCode), r.cwd).docsOnly, false);
});

test('file lists are complete beyond 300 files and preserve unusual paths', (t) => {
  const r = repository(t);
  for (let i = 0; i < 305; i++) r.write(`docs/receipt-${i}.md`, '# Receipt\n');
  r.write('docs/évidence with spaces.md', '# Receipt\n');
  if (process.platform !== 'win32') r.write('docs/line\nbreak.md', '# Receipt\n');
  const docs = r.commit('Many docs');
  assert.equal(classify('push', push(r.base, docs), r.cwd).docsOnly, true);
  r.write('zz-runtime.js', 'console.log("must run");\n');
  const mixed = r.commit('Runtime at end of large diff');
  assert.equal(classify('push', push(r.base, mixed), r.cwd).docsOnly, false);
});

test('empty, missing, invalid, unknown and unavailable comparisons default to full CI', (t) => {
  const r = repository(t);
  for (const [name, event] of [
    ['push', push(r.base, r.base)], ['push', push('0'.repeat(40), r.base)],
    ['push', push('f'.repeat(40), r.base)], ['push', push('--output=bad', r.base)],
    ['push', {}], ['pull_request', {}], ['workflow_dispatch', {}],
  ]) assert.equal(classify(name, event, r.cwd).docsOnly, false, JSON.stringify(event));
});

test('required gate accepts only verified docs skips or complete full-suite success', () => {
  assert.doesNotThrow(() => requireSuccess(results('true', 'skipped', 'skipped')));
  assert.doesNotThrow(() => requireSuccess(results('false', 'success', 'success')));
  for (const docs of ['true', 'false', '', undefined, 'invalid']) {
    for (const checks of ['success', 'failure', 'cancelled', 'skipped', undefined]) {
      for (const browser of ['success', 'failure', 'cancelled', 'skipped', undefined]) {
        const valid = (docs === 'true' && checks === 'skipped' && browser === 'skipped')
          || (docs === 'false' && checks === 'success' && browser === 'success');
        if (!valid) assert.throws(() => requireSuccess(results(docs, checks, browser)));
      }
    }
  }
  for (const scope of ['failure', 'cancelled', 'skipped', undefined]) {
    const needs = results('true', 'skipped', 'skipped');
    needs.scope.result = scope;
    assert.throws(() => requireSuccess(needs));
  }
  assert.throws(() => requireSuccess({}));
});

test('CLI writes Actions output and refuses failed jobs', (t) => {
  const r = repository(t);
  r.write('README.md', '# Updated\n');
  const head = r.commit('Docs update');
  const eventPath = join(r.cwd, 'event.json');
  const output = join(r.cwd, 'output.txt');
  const summary = join(r.cwd, 'summary.md');
  writeFileSync(eventPath, JSON.stringify(push(r.base, head)));
  const env = { ...process.env, GITHUB_EVENT_NAME: 'push', GITHUB_EVENT_PATH: eventPath, GITHUB_OUTPUT: output, GITHUB_STEP_SUMMARY: summary };
  const run = (command, extra = {}) => spawnSync(process.execPath, [join(__dirname, 'ci-policy.cjs'), command], {
    cwd: r.cwd, env: { ...env, ...extra }, encoding: 'utf8',
  });
  assert.equal(run('scope').status, 0);
  assert.equal(readFileSync(output, 'utf8'), 'docs_only=true\n');
  assert.match(readFileSync(summary, 'utf8'), /documentation only/);
  writeFileSync(output, '');
  writeFileSync(eventPath, 'invalid JSON');
  assert.equal(run('scope').status, 0);
  assert.equal(readFileSync(output, 'utf8'), 'docs_only=false\n');
  assert.equal(run('gate', { CI_NEEDS: JSON.stringify(results('true', 'skipped', 'skipped')) }).status, 0);
  assert.equal(run('gate', { CI_NEEDS: JSON.stringify(results('false', 'success', 'failure')) }).status, 1);
});
