'use strict';
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const fs = require('node:fs');
const zlib = require('node:zlib');
execFileSync(process.execPath, [path.join(__dirname, 'tools/build-navigation.cjs'), '--check'], { stdio: 'inherit' });
const lifecycle = fs.readFileSync(path.join(__dirname, '../assets/js/lunara-page-lifecycle.js'));
if (zlib.gzipSync(lifecycle).length > 2500) throw Error('The always-loaded lifecycle exceeds its 2.5 kB gzip budget.');
console.log('Native lifecycle delivery stays below 2.5 kB gzip; optional bundle and notices are reproducible.');
