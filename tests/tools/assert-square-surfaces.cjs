'use strict';
const assert = require('node:assert/strict');

// Inspect actual cascade output, including pseudo-elements and responsive/late
// styles. Only the brand seal and relationship-diagram nodes have curved frames.
module.exports = async function assertSquareSurfaces(page, label) {
    const failures = await page.evaluate(() => {
        const exceptions = '.lunara-debrief-seal,.lunara-debrief-seal-inner,.lunara-debrief-orbit-plate-mark,.lunara-pair-thread-origin,.lunara-pair-thread-node,.splide__spinner';
        const corners = ['borderTopLeftRadius','borderTopRightRadius','borderBottomLeftRadius','borderBottomRightRadius'];
        const failures = [];
        for (const el of document.body.querySelectorAll('*')) {
            if (!el.getBoundingClientRect().width || el.matches(exceptions) || el.closest('svg')) continue;
            for (const pseudo of [null, '::before', '::after']) {
                const css = getComputedStyle(el, pseudo);
                if (css.display === 'none' || css.visibility === 'hidden' || (pseudo && ['none','normal'].includes(css.content))) continue;
                if (corners.some(corner => parseFloat(css[corner]) > 0)) {
                    failures.push(`${el.tagName}.${el.className}${pseudo || ''}: ${corners.map(c => css[c]).join('/')}`);
                }
            }
        }
        return failures;
    });
    assert.deepEqual(failures, [], `${label}: public frames and controls have square corners`);
};
