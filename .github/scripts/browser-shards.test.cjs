'use strict';

const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { test } = require('node:test');
const { parseShard, parseTimings, plan } = require('./browser-shards.cjs');

const script = join(__dirname, 'browser-shards.cjs');
const run = (args, input) => spawnSync(process.execPath, [script, ...args], { encoding: 'utf8', input });
const names = (count) => Array.from({ length: count }, (_, i) => `tests/t${String(i).padStart(2, '0')}.js`);

test('every file runs exactly once across the shards, including new and renamed tests', () => {
  const files = names(23);
  const timings = { 'tests/t03.js': 60, 'tests/t04.js': 35, 'tests/t10.js': 12, 'tests/deleted.js': 99 };
  for (const total of [1, 2, 3, 4, 6]) {
    const assigned = plan(files, timings, total).flatMap((shard) => shard.files);
    assert.deepEqual([...assigned].sort(), files, `total ${total}`);
  }
});

test('the slowest files are separated instead of following name order', () => {
  // Name order with --test-shard put t00 and t04 together; seconds decide here.
  const timings = { 'tests/t00.js': 66, 'tests/t01.js': 5, 'tests/t02.js': 5, 'tests/t03.js': 5,
    'tests/t04.js': 35, 'tests/t05.js': 5, 'tests/t06.js': 5, 'tests/t07.js': 5 };
  const shards = plan(names(8), timings, 4);
  const slowest = shards.find((shard) => shard.files.includes('tests/t00.js'));
  assert.equal(slowest.files.includes('tests/t04.js'), false);
  assert.deepEqual(plan(names(6), { 'tests/t00.js': 6, 'tests/t01.js': 5, 'tests/t02.js': 4,
    'tests/t03.js': 3, 'tests/t04.js': 2, 'tests/t05.js': 2 }, 2).map((shard) => shard.seconds), [11, 11]);
});

test('every shard computes the same plan from any input order', () => {
  const files = names(15);
  const timings = Object.fromEntries(files.map((file, i) => [file, (i * 7) % 11 + 1]));
  const expected = plan(files, timings, 4);
  assert.deepEqual(plan([...files].reverse(), timings, 4), expected);
});

test('unknown or invalid timings weigh the median measured time', () => {
  const timings = { 'tests/t00.js': 10, 'tests/t01.js': 20, 'tests/t02.js': 30, 'tests/t03.js': -1 };
  const shards = plan(names(4), timings, 1);
  assert.equal(shards[0].seconds, 10 + 20 + 30 + 20);
  assert.equal(plan(names(3), {}, 1)[0].seconds, 3);
});

test('invalid shard requests and test lists are rejected', () => {
  assert.deepEqual(parseShard('2/4'), { index: 2, total: 4 });
  for (const spec of ['0/4', '5/4', '1/0', '1', 'a/4', '', undefined]) assert.throws(() => parseShard(spec), String(spec));
  assert.throws(() => plan([], {}, 4));
  assert.throws(() => plan(['tests/a.js', 'tests/a.js'], {}, 2));
  assert.throws(() => plan(['tests/a.js'], {}, 0));
});

test('CLI prints this shard and fails instead of running nothing', () => {
  const files = names(9);
  const printed = [1, 2, 3, 4].flatMap((index) => {
    const result = run(['shard', `${index}/4`, ...files]);
    assert.equal(result.status, 0, result.stderr);
    assert.match(result.stderr, new RegExp(`Shard ${index}/4: \\d+ files`));
    return result.stdout.trim().split('\n');
  });
  assert.deepEqual(printed.sort(), files);
  assert.notEqual(run(['shard', '3/4', 'tests/a.js', 'tests/b.js']).status, 0);
  assert.notEqual(run(['shard', '5/4', 'tests/a.js']).status, 0);
  assert.notEqual(run(['unknown']).status, 0);
});

test('timings come from top-level TAP results in raw or GitHub log form', () => {
  const log = [
    'Browser tests (1/4)\tUNKNOWN STEP\t2026-10-18T13:48:32.9034034Z # Subtest: tests/slow.js',
    'Browser tests (1/4)\tUNKNOWN STEP\t2026-10-18T13:48:32.9034034Z     ok 1 - nested case',
    'Browser tests (1/4)\tUNKNOWN STEP\t2026-10-18T13:48:32.9034034Z       duration_ms: 99999',
    'Browser tests (1/4)\tUNKNOWN STEP\t2026-10-18T13:48:32.9041370Z ok 1 - tests/slow.js',
    'Browser tests (1/4)\tUNKNOWN STEP\t2026-10-18T13:48:32.9042160Z   ---',
    'Browser tests (1/4)\tUNKNOWN STEP\t2026-10-18T13:48:32.9042624Z   duration_ms: 60000.4',
    'not ok 2 - tests/failed.cjs',
    '  ---',
    '  duration_ms: 1234.5',
    'ok 3 - tests/slow.js',
    '  duration_ms: 61999.6',
    'ok 4 - a test outside a file wrapper',
    '  duration_ms: 5000',
    'ok 5 - tests/instant.js',
    '  duration_ms: 3',
  ].join('\r\n');
  assert.deepEqual(parseTimings(log), { 'tests/failed.cjs': 1.2, 'tests/instant.js': 0.1, 'tests/slow.js': 61 });
  const result = run(['timings'], log);
  assert.equal(result.status, 0, result.stderr);
  assert.deepEqual(JSON.parse(result.stdout), parseTimings(log));
  assert.notEqual(run(['timings'], 'no results').status, 0);
});

test('the committed timing table holds positive seconds for test files', () => {
  const timings = JSON.parse(readFileSync(join(__dirname, 'browser-timings.json'), 'utf8'));
  assert.ok(Object.keys(timings).length > 0);
  for (const [file, seconds] of Object.entries(timings)) {
    assert.match(file, /^tests\/[^/]+\.c?js$/);
    assert.ok(Number.isFinite(seconds) && seconds > 0, file);
  }
});
