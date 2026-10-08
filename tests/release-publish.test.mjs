import test from 'node:test';
import assert from 'node:assert/strict';
import {mkdtempSync, mkdirSync, writeFileSync, readFileSync, rmSync, statSync, symlinkSync, renameSync} from 'node:fs';
import {tmpdir} from 'node:os';
import path from 'node:path';
import {createHash} from 'node:crypto';
import {validateArtifacts, publishRelease} from '../scripts/publish-release.mjs';

const commit = 'a'.repeat(40);
const otherCommit = 'b'.repeat(40);
const repository = 'asukayalab/FalconWF';
const version = '0.1.0-alpha.16';
const tag = `v${version}`;
const hash = data => createHash('sha256').update(data).digest('hex');
const missing = () => Object.assign(new Error('Not found'), {status:404});

function fixture(t) {
  const root = mkdtempSync(path.join(tmpdir(), 'fwf-publish-test-'));
  t.after(() => rmSync(root, {recursive:true, force:true}));
  mkdirSync(path.join(root, 'release/notes'), {recursive:true});
  mkdirSync(path.join(root, 'dist'));
  const map = {
    product:'Falcon WF 0.1', version, min_wp:'6.7', min_php:'8.3',
    plugin:'falcon-wf', theme:'falcon-theme', release_status:'development',
    versions:{'falcon-wf':version, 'falcon-theme':'0.1.0-alpha.6'},
  };
  const packages = Object.entries(map.versions).map(([id, packageVersion]) => {
    const artifact = `${id}-${packageVersion}.zip`;
    const bytes = Buffer.from(`fixture package ${id} ${packageVersion}`);
    writeFileSync(path.join(root, 'dist', artifact), bytes);
    return {id, type:id === 'falcon-wf' ? 'plugin' : 'theme', version:packageVersion,
      artifact, sha256:hash(bytes), min_wp:map.min_wp, min_php:map.min_php};
  });
  const manifest = {schema:1, product:map.product, version, status:'development',
    source_commit:commit, dirty:false, source_digest:'c'.repeat(64),
    built_at:'2026-10-08T12:00:00.000Z', packages};
  const save = () => {
    writeFileSync(path.join(root, 'release/components.json'), JSON.stringify(map));
    writeFileSync(path.join(root, 'dist/release-manifest.json'), JSON.stringify(manifest));
  };
  save();
  writeFileSync(path.join(root, `release/notes/${version}.md`), 'Practical automatic update discovery.\n');
  return {root, map, manifest, save, validate:() => validateArtifacts({root, expectedSha:commit, repository})};
}

test('release artifacts bind independent component versions and hashes to tested commit', t => {
  const f = fixture(t);
  const release = f.validate();
  assert.equal(release.tag, tag);
  assert.equal(release.commit, commit);
  assert.deepEqual(release.files.map(file => path.basename(file)), [
    `falcon-wf-${version}.zip`, 'falcon-theme-0.1.0-alpha.6.zip', 'release-manifest.json',
  ]);
  assert.equal(release.repository, repository);
});

for (const [name, change] of [
  ['dirty checkout manifest', f => { f.manifest.dirty = true; }],
  ['different source commit', f => { f.manifest.source_commit = otherCommit; }],
  ['manifest product mismatch', f => { f.manifest.product = 'Other product'; }],
  ['manifest version mismatch', f => { f.manifest.version = '0.1.0-alpha.15'; }],
  ['component version mismatch', f => { f.manifest.packages[1].version = '0.1.0-alpha.7'; }],
  ['missing component', f => { f.manifest.packages.pop(); }],
  ['duplicate component', f => { f.manifest.packages[1] = {...f.manifest.packages[0]}; }],
  ['artifact path traversal', f => { f.manifest.packages[0].artifact = '../secret.zip'; }],
  ['package hash mismatch', f => { f.manifest.packages[0].sha256 = 'd'.repeat(64); }],
  ['unsupported stable promotion', f => { f.map.version = f.manifest.version = '0.1.0'; }],
  ['missing ZIP', f => { rmSync(path.join(f.root, 'dist', f.manifest.packages[0].artifact)); }],
  ['missing notes', f => { rmSync(path.join(f.root, `release/notes/${version}.md`)); }],
  ['empty notes', f => { writeFileSync(path.join(f.root, `release/notes/${version}.md`), ' \n'); }],
]) {
  test(`rejects ${name} before any GitHub operation`, t => {
    const f = fixture(t);
    change(f); f.save();
    assert.throws(f.validate);
  });
}

test('rejects a guessed repository or invalid source SHA', t => {
  const f = fixture(t);
  assert.throws(() => validateArtifacts({root:f.root, expectedSha:commit, repository:'someone/Other'}));
  assert.throws(() => validateArtifacts({root:f.root, expectedSha:'HEAD', repository}));
});

test('symlinked release ZIP cannot upload data outside declared files', t => {
  const f=fixture(t);
  const file=path.join(f.root, 'dist', f.manifest.packages[0].artifact);
  const outside=path.join(f.root, 'outside.zip');
  renameSync(file, outside);
  symlinkSync(outside, file);
  assert.throws(f.validate);
});

test('a symlink in the artifact directory cannot escape repository root', t => {
  const f=fixture(t);
  const outside=mkdtempSync(path.join(tmpdir(), 'fwf-outside-release-'));
  t.after(() => rmSync(outside, {recursive:true, force:true}));
  const dist=path.join(f.root, 'dist');
  const realDist=path.join(outside, 'dist');
  renameSync(dist, realDist);
  symlinkSync(realDist, dist);
  assert.throws(f.validate);
});

test('symlinked release notes cannot send an undeclared file to GitHub', t => {
  const f=fixture(t);
  const file=path.join(f.root, `release/notes/${version}.md`);
  const outside=path.join(f.root, 'secret-notes.md');
  renameSync(file, outside);
  symlinkSync(outside, file);
  assert.throws(f.validate);
});

test('publisher revalidates artifacts at mutation boundary', async t => {
  const f=fixture(t);
  const release=f.validate();
  writeFileSync(release.files[0], 'changed after review');
  const mock=github(release);
  await assert.rejects(publishRelease({release, gh:mock.gh}));
  assert.equal(mock.calls.length, 0);
});

function github(release, {existing=null, reference=null, annotations={}, listPages=null, listFailure=null, apiFailure=null, uploadFailure=false, refFailure=false, wrongAssets=false, mutateUploaded=null}={}) {
  const calls=[];
  let created=false;
  const uploaded = {
    id:17, tag_name:release.tag, draft:true, prerelease:true,
    target_commitish:release.commit,
    assets:release.files.map(file => ({name:path.basename(file), size:statSync(file).size, state:'uploaded', digest:`sha256:${hash(readFileSync(file))}`})),
  };
  if (wrongAssets) uploaded.assets[0].size++;
  mutateUploaded?.(uploaded);
  const gh = {
    async api(endpoint) {
      calls.push({api:endpoint});
      if (apiFailure) throw Object.assign(new Error('GitHub unavailable'), {status:apiFailure});
      if (endpoint.includes('/releases?')) {
        const page=Number(new URL(endpoint, 'https://api.github.com/').searchParams.get('page'));
        if (listFailure?.page === page) throw Object.assign(new Error('Release list unavailable'), {status:listFailure.status});
        if (created) return [uploaded];
        if (listPages) return listPages[page-1] ?? [];
        return existing ? [existing] : [];
      }
      if (endpoint.includes('/releases/tags/')) {
        // REST get-by-tag returns published releases only: it cannot find a draft.
        throw missing();
      }
      if (endpoint.includes('/git/ref/tags/')) {
        if (reference) return reference;
        throw missing();
      }
      if (endpoint.includes('/git/tags/')) {
        const annotation=annotations[endpoint.split('/').at(-1)];
        if (annotation) return annotation;
        throw missing();
      }
      throw new Error(`Unexpected GitHub API endpoint: ${endpoint}`);
    },
    async run(args) {
      calls.push({run:args});
      if (args[0] === 'api' && args[1].endsWith('/git/refs')) {
        if (refFailure) throw new Error('Tag creation denied');
        reference={object:{type:'commit', sha:release.commit}};
      }
      if (args[0] === 'release' && args[1] === 'create') created=true;
      if (args[0] === 'release' && args[1] === 'upload' && uploadFailure) throw new Error('Upload interrupted');
    },
  };
  return {gh, calls};
}

test('already published version never writes or overwrites even after another commit', async t => {
  const release=fixture(t).validate();
  const mock=github(release, {existing:{draft:false, tag_name:tag, target_commitish:otherCommit}});
  await publishRelease({release, gh:mock.gh});
  assert.equal(mock.calls.filter(call => call.run).length, 0);
  assert.equal(mock.calls.length, 1);
});

test('interrupted existing draft requires review and is never overwritten', async t => {
  const release=fixture(t).validate();
  const mock=github(release, {existing:{draft:true, tag_name:tag, target_commitish:commit}});
  await assert.rejects(publishRelease({release, gh:mock.gh}));
  assert.equal(mock.calls.filter(call => call.run).length, 0);
  assert.ok(mock.calls.every(call => !call.api?.includes('/releases/tags/')));
});

function fullReleasePage(page) {
  return Array.from({length:100}, (_, index) => ({tag_name:`v0.0.${page*100+index}`, draft:false}));
}

test('draft on a later authenticated release-list page blocks duplicate creation', async t => {
  const release=fixture(t).validate();
  const mock=github(release, {listPages:[fullReleasePage(1), [{tag_name:tag, draft:true}]]});
  await assert.rejects(publishRelease({release, gh:mock.gh}));
  assert.equal(mock.calls.filter(call => call.run).length, 0);
  assert.equal(mock.calls.filter(call => call.api?.includes('/releases?')).length, 2);
});

test('duplicate target tags across release-list pages fail closed', async t => {
  const release=fixture(t).validate();
  const firstPage=fullReleasePage(1);
  firstPage[0]={tag_name:tag, draft:false};
  const mock=github(release, {listPages:[firstPage, [{tag_name:tag, draft:true}]]});
  await assert.rejects(publishRelease({release, gh:mock.gh}));
  assert.equal(mock.calls.filter(call => call.run).length, 0);
});

test('full release list at pagination limit never assumes version is absent', async t => {
  const release=fixture(t).validate();
  const mock=github(release, {listPages:Array.from({length:10}, (_, index) => fullReleasePage(index+1))});
  await assert.rejects(publishRelease({release, gh:mock.gh}));
  assert.equal(mock.calls.filter(call => call.run).length, 0);
  assert.equal(mock.calls.filter(call => call.api?.includes('/releases?')).length, 10);
});

test('later release-list request failure cannot authorize a new release', async t => {
  const release=fixture(t).validate();
  const mock=github(release, {listPages:[fullReleasePage(1)], listFailure:{page:2, status:500}});
  await assert.rejects(publishRelease({release, gh:mock.gh}));
  assert.equal(mock.calls.filter(call => call.run).length, 0);
});

for (const [name, listPages] of [
  ['non-array response', [{message:'unexpected response'}]],
  ['row missing draft state', [[{tag_name:tag}]]],
  ['row missing tag', [[{draft:false}]]],
  ['null row', [[null]]],
]) {
  test(`malformed release list ${name} cannot authorize publication`, async t => {
    const release=fixture(t).validate();
    const mock=github(release, {listPages});
    await assert.rejects(publishRelease({release, gh:mock.gh}));
    assert.equal(mock.calls.filter(call => call.run).length, 0);
  });
}

test('an existing tag at a different commit blocks release creation', async t => {
  const release=fixture(t).validate();
  const mock=github(release, {reference:{object:{type:'commit', sha:otherCommit}}});
  await assert.rejects(publishRelease({release, gh:mock.gh}));
  assert.equal(mock.calls.filter(call => call.run).length, 0);
});

for (const status of [401, 403, 404, 429, 500]) {
  test(`GitHub HTTP ${status} is not mistaken for an absent release`, async t => {
    const release=fixture(t).validate();
    const mock=github(release, {apiFailure:status});
    await assert.rejects(publishRelease({release, gh:mock.gh}));
    assert.equal(mock.calls.filter(call => call.run).length, 0);
  });
}

test('failed upload leaves draft unpublished with no delete or overwrite attempt', async t => {
  const release=fixture(t).validate();
  const mock=github(release, {uploadFailure:true});
  await assert.rejects(publishRelease({release, gh:mock.gh}));
  const commands=mock.calls.filter(call => call.run?.[0] === 'release').map(call => call.run);
  assert.deepEqual(commands.map(args => args[1]), ['create', 'upload']);
  assert.ok(commands[0].includes('--draft'));
  assert.ok(!commands.some(args => args.includes('--clobber') || args[1] === 'delete'));
});

test('wrong uploaded asset metadata blocks publication', async t => {
  const release=fixture(t).validate();
  const mock=github(release, {wrongAssets:true});
  await assert.rejects(publishRelease({release, gh:mock.gh}));
  assert.ok(!mock.calls.some(call => call.run?.[1] === 'edit'));
});

for (const [name, mutateUploaded] of [
  ['different target commit', data => { data.target_commitish=otherCommit; }],
  ['wrong release tag', data => { data.tag_name='v0.1.0-alpha.15'; }],
  ['unexpected non-draft', data => { data.draft=false; }],
  ['stable release flag', data => { data.prerelease=false; }],
  ['missing asset', data => { data.assets.pop(); }],
  ['extra asset', data => { data.assets.push({name:'unexpected.zip', size:1, state:'uploaded'}); }],
  ['unfinished asset', data => { data.assets[0].state='new'; }],
  ['same-size asset with wrong digest', data => { data.assets[0].digest=`sha256:${'f'.repeat(64)}`; }],
  ['asset missing checksum evidence', data => { delete data.assets[0].digest; }],
]) {
  test(`uploaded draft with ${name} cannot be published`, async t => {
    const release=fixture(t).validate();
    const mock=github(release, {mutateUploaded});
    await assert.rejects(publishRelease({release, gh:mock.gh}));
    assert.ok(!mock.calls.some(call => call.run?.[1] === 'edit'));
  });
}

test('new tested version creates draft, uploads exact three assets, then publishes prerelease', async t => {
  const release=fixture(t).validate();
  const mock=github(release);
  await publishRelease({release, gh:mock.gh});
  const commands=mock.calls.filter(call => call.run?.[0] === 'release').map(call => call.run);
  assert.deepEqual(commands.map(args => args[1]), ['create', 'upload', 'edit']);
  assert.ok(commands[0].includes('--draft'));
  assert.ok(commands[0].includes('--verify-tag'));
  assert.equal(commands[0][commands[0].indexOf('--target')+1], commit);
  assert.equal(commands[0][commands[0].indexOf('--notes-file')+1], release.notes);
  for (const file of release.files) assert.ok(commands[1].includes(file));
  assert.ok(!commands[1].includes('--clobber'));
  assert.ok(commands[2].includes('--draft=false'));
  assert.ok(commands[2].includes('--prerelease'));
  assert.ok(commands[2].includes('--latest=false'));
});

test('failed creation of exact-commit tag never creates or publishes draft', async t => {
  const release=fixture(t).validate();
  const mock=github(release, {refFailure:true});
  await assert.rejects(publishRelease({release, gh:mock.gh}));
  assert.ok(!mock.calls.some(call => call.run?.[0] === 'release'));
});

test('existing lightweight tag at the tested commit may be published without retargeting', async t => {
  const release=fixture(t).validate();
  const mock=github(release, {reference:{object:{type:'commit', sha:commit}}});
  await publishRelease({release, gh:mock.gh});
  assert.equal(mock.calls.filter(call => call.run?.[1] === 'edit').length, 1);
});

test('annotated tag is peeled to checked commit without creating a replacement ref', async t => {
  const release=fixture(t).validate();
  const annotation='d'.repeat(40);
  const mock=github(release, {reference:{object:{type:'tag', sha:annotation}}, annotations:{[annotation]:{object:{type:'commit', sha:commit}}}});
  await publishRelease({release, gh:mock.gh});
  assert.ok(!mock.calls.some(call => call.run?.[0] === 'api'));
  assert.equal(mock.calls.filter(call => call.run?.[1] === 'edit').length, 1);
});

test('cyclic annotated tag fails closed within bounded API reads', async t => {
  const release=fixture(t).validate();
  const annotation='d'.repeat(40);
  const object={type:'tag', sha:annotation};
  const mock=github(release, {reference:{object}, annotations:{[annotation]:{object}}});
  await assert.rejects(publishRelease({release, gh:mock.gh}));
  assert.ok(mock.calls.length <= 11);
  assert.equal(mock.calls.filter(call => call.run).length, 0);
});
