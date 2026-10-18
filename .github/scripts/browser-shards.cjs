'use strict';

const { readFileSync } = require('node:fs');
const { join } = require('node:path');

const timingsPath = join(__dirname, 'browser-timings.json');

function parseShard(spec) {
  const match = /^(\d+)\/(\d+)$/.exec(String(spec));
  const index = match ? Number(match[1]) : NaN;
  const total = match ? Number(match[2]) : NaN;
  if (!(total >= 1 && index >= 1 && index <= total)) throw new Error(`Invalid shard "${spec}"; expected N/TOTAL.`);
  return { index, total };
}

// Longest-first greedy split by measured seconds. Every shard computes the same
// plan from the same inputs, so each file runs exactly once across the matrix.
// Unknown files (new tests) weigh the median known time; stale entries are ignored.
function plan(files, timings, total) {
  if (!Number.isInteger(total) || total < 1) throw new Error('Shard total must be a positive integer.');
  if (files.length === 0) throw new Error('No test files to shard.');
  const unique = [...new Set(files)].sort();
  if (unique.length !== files.length) throw new Error('Duplicate test file.');
  const measured = (file) => (Object.hasOwn(timings, file) && Number.isFinite(timings[file]) && timings[file] > 0
    ? timings[file] : undefined);
  const known = unique.map(measured).filter((seconds) => seconds !== undefined).sort((a, b) => a - b);
  const fallback = known.length ? known[Math.floor(known.length / 2)] : 1;
  const weight = (file) => measured(file) ?? fallback;
  const shards = Array.from({ length: total }, () => ({ files: [], seconds: 0 }));
  for (const file of unique.sort((a, b) => weight(b) - weight(a) || (a < b ? -1 : 1))) {
    const lightest = shards.reduce((best, shard) => (shard.seconds < best.seconds ? shard : best));
    lightest.files.push(file);
    lightest.seconds += weight(file);
  }
  for (const shard of shards) shard.files.sort();
  return shards;
}

// Reads `node --test` TAP output, raw or from `gh run view --log`, and returns
// the mean seconds per top-level test file, rounded to 0.1 s.
function parseTimings(text) {
  const samples = new Map();
  let file;
  for (const raw of text.split(/\r?\n/)) {
    const line = raw.replace(/\x1b\[[0-9;]*m/g, '').replace(/^[^\t]*\t[^\t]*\t\S+Z /, '');
    const result = /^(?:not )?ok \d+ - (tests\/\S+)$/.exec(line);
    if (result) { file = result[1]; continue; }
    const duration = /^ {2}duration_ms: ([\d.]+)$/.exec(line);
    if (duration && file) {
      samples.set(file, [...(samples.get(file) ?? []), Number(duration[1]) / 1000]);
      file = undefined;
    } else if (/^\S/.test(line)) {
      file = undefined;
    }
  }
  const timings = {};
  for (const name of [...samples.keys()].sort()) {
    const values = samples.get(name);
    timings[name] = Math.max(0.1, Math.round((values.reduce((a, b) => a + b, 0) / values.length) * 10) / 10);
  }
  return timings;
}

if (require.main === module) {
  const [command, ...args] = process.argv.slice(2);
  if (command === 'shard') {
    const { index, total } = parseShard(args[0]);
    const shards = plan(args.slice(1), JSON.parse(readFileSync(timingsPath, 'utf8')), total);
    const mine = shards[index - 1];
    if (mine.files.length === 0) throw new Error(`Shard ${index}/${total} received no test files.`);
    console.error(`Shard ${index}/${total}: ${mine.files.length} files, ~${Math.round(mine.seconds)}s planned `
      + `(all shards: ${shards.map((shard) => `${Math.round(shard.seconds)}s`).join(', ')}).`);
    process.stdout.write(`${mine.files.join('\n')}\n`);
  } else if (command === 'timings') {
    const timings = parseTimings(readFileSync(0, 'utf8'));
    if (Object.keys(timings).length === 0) throw new Error('No test durations found in the input.');
    process.stdout.write(`${JSON.stringify(timings, null, 2)}\n`);
  } else {
    throw new Error('Expected shard or timings command.');
  }
}

module.exports = { parseShard, parseTimings, plan };
