'use strict';

const { execFileSync } = require('node:child_process');
const { appendFileSync, readFileSync } = require('node:fs');

const rootDocs = new Set(['AGENTS.md', 'ARCHITECTURE.md', 'CLAUDE.md', 'DESIGN.md', 'README.md']);
const commitId = /^(?:[0-9a-f]{40}|[0-9a-f]{64})$/;

function isDocumentation(path) {
  return rootDocs.has(path) || (path.startsWith('docs/') && path.endsWith('.md'));
}

function classify(eventName, event, cwd = process.cwd()) {
  const full = (reason) => ({ docsOnly: false, reason });
  const git = (...args) => execFileSync('git', args, {
    cwd, encoding: 'utf8', maxBuffer: 16 * 1024 * 1024,
    stdio: ['ignore', 'pipe', 'pipe'],
  }).trimEnd();

  let base;
  let head;
  if (eventName === 'pull_request') {
    base = event?.pull_request?.base?.sha;
    head = event?.pull_request?.head?.sha;
  } else if (eventName === 'push') {
    base = event?.before;
    head = event?.after;
  } else {
    return full('Unrecognized event; run the full suite.');
  }

  if (![base, head].every((sha) => typeof sha === 'string' && commitId.test(sha) && !/^0+$/.test(sha))) {
    return full('Missing or new-branch commit range; run the full suite.');
  }

  try {
    // PRs cover the complete branch; pushes cover every commit in the push.
    if (eventName === 'pull_request') base = git('merge-base', base, head);
    // Disabling rename detection includes BOTH paths, so moving code into docs
    // cannot hide a source deletion. NUL delimiters preserve unusual filenames.
    const changed = git('diff', '--name-only', '--no-renames', '-z', base, head, '--');
    const paths = changed.split('\0').filter(Boolean);
    if (paths.length === 0) return full('Empty comparison; run the full suite.');
    const docsOnly = paths.every(isDocumentation);
    return {
      docsOnly,
      reason: `${paths.length} changed path(s): ${docsOnly ? 'documentation only' : 'full checks required'}.`,
    };
  } catch {
    return full('Git comparison unavailable; run the full suite.');
  }
}

function requireSuccess(needs) {
  const scope = needs?.scope;
  if (scope?.result !== 'success') throw new Error('CI scope validation did not succeed.');
  const docsOnly = scope.outputs?.docs_only;
  if (docsOnly !== 'true' && docsOnly !== 'false') throw new Error('CI scope output is missing or invalid.');
  const expected = docsOnly === 'true' ? 'skipped' : 'success';
  for (const job of ['checks', 'browser']) {
    if (needs[job]?.result !== expected) throw new Error(`${job} must be ${expected} for this CI scope.`);
  }
  return docsOnly === 'true'
    ? 'Documentation-only CI passed; runtime checks intentionally skipped.'
    : 'Full CI passed, including every browser shard.';
}

if (require.main === module) {
  if (process.argv[2] === 'scope') {
    let result;
    try {
      result = classify(process.env.GITHUB_EVENT_NAME, JSON.parse(readFileSync(process.env.GITHUB_EVENT_PATH, 'utf8')));
    } catch {
      result = { docsOnly: false, reason: 'Event payload unavailable; run the full suite.' };
    }
    appendFileSync(process.env.GITHUB_OUTPUT, `docs_only=${result.docsOnly}\n`);
    console.log(result.reason);
    if (process.env.GITHUB_STEP_SUMMARY) appendFileSync(process.env.GITHUB_STEP_SUMMARY, `${result.reason}\n`);
  } else if (process.argv[2] === 'gate') {
    console.log(requireSuccess(JSON.parse(process.env.CI_NEEDS)));
  } else {
    throw new Error('Expected scope or gate command.');
  }
}

module.exports = { classify, isDocumentation, requireSuccess };
