// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Verify historic GPX replay positions on the game-progress axis.
 *
 * @copyright 2026 Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @author Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {fileURLToPath} from 'node:url';

const source = await readFile(fileURLToPath(new URL('../amd/src/replay_progress.js', import.meta.url)), 'utf8');
const {progressAt, distanceAt} = await import('data:text/javascript;base64,' + Buffer.from(source).toString('base64'));
const points = [
    {time: 0, distance: 0},
    {time: 10, distance: 100},
    {time: 20, distance: 200},
    {time: 30, distance: 300},
    {time: 40, distance: 400},
];
const attempt = (time, stageid, success, penalty = false) => ({
    time, stageid, success, penalty, geometrysolved: !penalty,
});
const actor = {
    totalstages: 3,
    firstTrackTime: 0,
    lastTrackTime: 40,
    members: [{points}],
    attempts: [
        attempt(10, 1, true),
        attempt(20, 2, false, true),
        attempt(30, 2, true),
        attempt(40, 3, true),
    ],
};
assert.equal(distanceAt(points, 5), 50);
assert.equal(progressAt(actor, 5).fraction, 0);
assert.equal(progressAt(actor, 10).fraction, 0);
assert.equal(progressAt(actor, 15).fraction, 0.25 / 2);
assert.equal(progressAt(actor, 20).status, 'failed');
assert.equal(progressAt(actor, 20).fraction, 0.5 / 2);
assert.equal(progressAt(actor, 25).fraction, 0.75 / 2);
assert.ok(progressAt(actor, 25).fraction > progressAt(actor, 15).fraction);
assert.equal(progressAt(actor, 30).fraction, 1 / 2);
assert.equal(progressAt(actor, 40).fraction, 1);

const outOfSequence = {...actor, attempts: [attempt(10, 3, true), attempt(30, 1, true)]};
assert.equal(progressAt(outOfSequence, 10).completed, 1);
assert.equal(progressAt(outOfSequence, 30).completed, 2);
assert.equal(progressAt({...actor, attempts: [attempt(10, 1, true), attempt(20, 1, true)]}, 30).completed, 1);
assert.equal(progressAt({...actor, attempts: [
    {...attempt(20, 2, false, true), geometrysolved: true},
]}, 20).status, 'failed');
const team = {...actor, members: [
    {points: [{time: 0, distance: 0}, {time: 10, distance: 100}]},
    {points: [{time: 0, distance: 0}, {time: 10, distance: 300}]},
]};
assert.equal(progressAt({...team, attempts: [attempt(10, 1, true)]}, 5).fraction, 0);
assert.equal(progressAt({...actor, members: [{points: []}]}, 5).fraction, 0);

// Regression: E9 approaches stage 3 between 08:46:50 and the geometry arrival at 08:48:59.
// The question remains pending until 08:50:48, so the marker must wait at stage 3.
const stageEntry = {
    totalstages: 13,
    firstTrackTime: 1495520000,
    lastTrackTime: 1495522248,
    members: [{points: [
        {time: 1495520000, distance: 0},
        {time: 1495521436, distance: 100},
        {time: 1495522010, distance: 160},
        {time: 1495522139, distance: 180},
        {time: 1495522248, distance: 220},
    ]}],
    attempts: [
        attempt(1495520885, 297, true),
        attempt(1495521436, 299, true),
        attempt(1495522139, 298, false),
        {...attempt(1495522200, 298, false, true), geometrysolved: true},
        attempt(1495522248, 298, true),
    ],
};
assert.equal(progressAt(stageEntry, 1495522010).completed, 2);
assert.equal(progressAt(stageEntry, 1495522139).completed, 2);
assert.ok(progressAt(stageEntry, 1495522010).fraction > 1 / 12);
assert.ok(progressAt(stageEntry, 1495522010).fraction < 2 / 12);
assert.ok(progressAt(stageEntry, 1495522052).fraction > progressAt(stageEntry, 1495522010).fraction);
assert.ok(progressAt(stageEntry, 1495522092).fraction > progressAt(stageEntry, 1495522052).fraction);
assert.ok(progressAt(stageEntry, 1495522132).fraction > progressAt(stageEntry, 1495522092).fraction);
assert.equal(progressAt(stageEntry, 1495522139).fraction, 2 / 12);
assert.equal(progressAt(stageEntry, 1495522200).fraction, 2 / 12);
assert.equal(progressAt(stageEntry, 1495522248).fraction, 2 / 12);

process.stdout.write('Replay progress tests passed.\n');
