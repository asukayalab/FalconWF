import {copyFileSync, existsSync, lstatSync, mkdirSync, readFileSync, realpathSync, rmSync} from 'node:fs';
import {createHash} from 'node:crypto';
import {spawnSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import path from 'node:path';

export const RELEASE_REPOSITORY = 'asukayalab/FalconWF';
const prerelease = /^\d+\.\d+\.\d+-(?:alpha|beta|rc)\.\d+$/;
const sha = /^[a-f0-9]{40}$/;
const hash = /^[a-f0-9]{64}$/;
function requireCondition(condition, message) { if (!condition) throw new Error(message); }
function fileBytes(file, root) {
  requireCondition(existsSync(file) && lstatSync(file).isFile() && !lstatSync(file).isSymbolicLink(), `Missing or unsafe release file: ${file}`);
  if (root) {
    const relative = path.relative(realpathSync(root), realpathSync(file));
    requireCondition(relative !== '..' && !relative.startsWith(`..${path.sep}`) && !path.isAbsolute(relative), 'Release file escapes checkout.');
  }
  return readFileSync(file);
}

// components.json remains the only version owner. The checked build is reused, not rebuilt in the publishing job.
export function validateArtifacts({root, expectedSha, repository = RELEASE_REPOSITORY}) {
  requireCondition(repository === RELEASE_REPOSITORY, 'Unexpected release repository.');
  requireCondition(sha.test(expectedSha ?? ''), 'Expected source commit must be a full SHA.');
  const map = JSON.parse(fileBytes(path.join(root, 'release/components.json'), root));
  requireCondition(map.release_status === 'development' && prerelease.test(map.version), 'Stable automatic publication is blocked until release acceptance.');
  const manifestFile = path.join(root, 'dist/release-manifest.json');
  const manifest = JSON.parse(fileBytes(manifestFile, root));
  requireCondition(manifest.schema === 1 && manifest.product === map.product && manifest.version === map.version && manifest.status === map.release_status, 'Release manifest identity does not match component map.');
  requireCondition(manifest.dirty === false && manifest.source_commit === expectedSha && hash.test(manifest.source_digest ?? ''), 'Release provenance is dirty or belongs to a different source commit.');
  requireCondition(map.plugin === 'falcon-wf' && map.theme === 'falcon-theme' && Array.isArray(manifest.packages) && manifest.packages.length === 2, 'Release must contain exactly the two FWF components.');
  const files = [];
  for (const [id, type] of [['falcon-wf', 'plugin'], ['falcon-theme', 'theme']]) {
    const packages = manifest.packages.filter(p => p.id === id);
    const version = map.versions?.[id];
    requireCondition(packages.length === 1 && /^\d+\.\d+\.\d+(?:-(?:alpha|beta|rc)\.\d+)?$/.test(version ?? ''), 'Invalid or duplicate component version.');
    const pkg = packages[0];
    const artifact = `${id}-${version}.zip`;
    requireCondition(pkg.type === type && pkg.version === version && pkg.artifact === artifact && pkg.min_wp === map.min_wp && pkg.min_php === map.min_php && hash.test(pkg.sha256 ?? ''), 'Component metadata or artifact name mismatch.');
    const file = path.join(root, 'dist', artifact);
    const bytes = fileBytes(file, root);
    requireCondition(bytes.length > 0 && createHash('sha256').update(bytes).digest('hex') === pkg.sha256, 'Release asset checksum mismatch.');
    files.push(file);
  }
  const notes = path.join(root, 'release/notes', `${map.version}.md`);
  requireCondition(fileBytes(notes, root).toString('utf8').trim().length > 0, 'Release notes must not be empty.');
  files.push(manifestFile);
  return {root, repository, tag: `v${map.version}`, version: map.version, commit: expectedSha, manifest, files, notes};
}
async function optionalApi(gh, endpoint) {
  try { return await gh.api(endpoint); }
  catch (error) { if (error.status === 404) return null; throw error; }
}
async function findRelease(gh, base, tag) {
  let found = null;
  // Unlike the published-tag endpoint, this authenticated list also includes drafts for push access.
  // Scan the complete bounded list so an interrupted draft cannot be mistaken for an absent release.
  for (let page = 1; page <= 10; page++) {
    const rows = await gh.api(`${base}/releases?per_page=100&page=${page}`);
    requireCondition(Array.isArray(rows) && rows.length <= 100, 'Malformed GitHub release list.');
    for (const row of rows) {
      requireCondition(row && typeof row.tag_name === 'string' && typeof row.draft === 'boolean', 'Malformed GitHub release entry.');
      if (row.tag_name !== tag) continue;
      requireCondition(found === null, 'Multiple releases use the selected tag; operator review is required.');
      found = row;
    }
    if (rows.length < 100) return found;
  }
  throw new Error('GitHub release list exceeds the pagination limit; operator review is required.');
}
async function tagCommit(gh, base, tag) {
  const ref = await optionalApi(gh, `${base}/git/ref/tags/${encodeURIComponent(tag)}`);
  if (ref === null) return null;
  let object = ref.object;
  for (let depth = 0; depth < 8; depth++) {
    requireCondition(object && sha.test(object.sha ?? ''), 'Malformed GitHub tag object.');
    if (object.type === 'commit') return object.sha;
    requireCondition(object.type === 'tag', 'Release tag does not point to a commit.');
    object = (await gh.api(`${base}/git/tags/${object.sha}`)).object;
  }
  throw new Error('Annotated release tag exceeds the traversal limit.');
}

export async function publishRelease({release, gh}) {
  // Revalidate at the mutation boundary; a caller cannot authorize arbitrary upload paths through a constructed object.
  const checked = validateArtifacts({root: release.root, expectedSha: release.commit, repository: release.repository});
  requireCondition(checked.tag === release.tag, 'Release tag changed after validation.');
  release = checked;
  const base = `repos/${release.repository}`;
  const existing = await findRelease(gh, base, release.tag);
  if (existing !== null) {
    requireCondition(existing.tag_name === release.tag && typeof existing.draft === 'boolean', 'Malformed existing release response.');
    requireCondition(!existing.draft, 'Existing draft requires operator review; automatic retry cannot overwrite its assets.');
    return {status: 'skipped', tag: release.tag};
  }
  const commit = await tagCommit(gh, base, release.tag);
  requireCondition(commit === null || commit === release.commit, 'Release tag collision: tag belongs to a different commit.');
  if (commit === null) {
    await gh.run(['api', `${base}/git/refs`, '--method', 'POST', '-f', `ref=refs/tags/${release.tag}`, '-f', `sha=${release.commit}`]);
  }
  await gh.run(['release', 'create', release.tag, '--repo', release.repository, '--target', release.commit, '--title', `Falcon WF ${release.version}`, '--notes-file', release.notes, '--draft', '--prerelease', '--latest=false', '--verify-tag']);
  await gh.run(['release', 'upload', release.tag, ...release.files, '--repo', release.repository]);
  const draft = await findRelease(gh, base, release.tag);
  requireCondition(draft !== null, 'Uploaded draft is missing from authenticated release list.');
  requireCondition(draft.tag_name === release.tag && draft.draft === true && draft.prerelease === true && draft.target_commitish === release.commit, 'Uploaded draft release identity does not match checked source.');
  const expected = new Map(release.files.map(file => {
    const bytes = fileBytes(file, release.root);
    return [path.basename(file), {size: bytes.length, digest: `sha256:${createHash('sha256').update(bytes).digest('hex')}`}];
  }));
  requireCondition(Array.isArray(draft.assets) && draft.assets.length === expected.size, 'Draft release must have exactly three assets.');
  for (const asset of draft.assets) {
    requireCondition(expected.has(asset.name) && expected.get(asset.name).size === asset.size && expected.get(asset.name).digest === asset.digest && asset.state === 'uploaded', 'Draft release asset is missing, incomplete or unexpected.');
    expected.delete(asset.name);
  }
  requireCondition(expected.size === 0, 'Draft release is missing a required asset.');
  // A tag created by another actor between the earlier read and draft creation must not escape the commit check.
  requireCondition(await tagCommit(gh, base, release.tag) === release.commit, 'Release tag changed before publication.');
  await gh.run(['release', 'edit', release.tag, '--repo', release.repository, '--draft=false', '--prerelease', '--latest=false']);
  return {status: 'published', tag: release.tag};
}

function githubCli() {
  function run(args) {
    const result = spawnSync('gh', args, {encoding: 'utf8', maxBuffer: 2 * 1024 * 1024});
    if (result.error) throw result.error;
    if (result.status !== 0) {
      const error = new Error(`GitHub CLI failed: ${args[0]} (exit ${result.status}).`);
      // Only a confirmed HTTP 404 means absent. Authentication, rate limits and transport failures remain fatal.
      error.status = Number(`${result.stderr}\n${result.stdout}`.match(/\(HTTP (\d{3})\)/)?.[1]) || undefined;
      throw error;
    }
    return result.stdout;
  }
  return {api: async endpoint => JSON.parse(run(['api', endpoint])), run: async args => run(args)};
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const root = fileURLToPath(new URL('../', import.meta.url));
  const action = process.argv[2];
  const release = validateArtifacts({root, expectedSha: process.env.GITHUB_SHA, repository: process.env.GITHUB_REPOSITORY});
  if (action === 'prepare') {
    const destination = path.join(root, 'dist/release-upload');
    rmSync(destination, {recursive: true, force: true});
    mkdirSync(destination, {recursive: true});
    for (const file of release.files) copyFileSync(file, path.join(destination, path.basename(file)));
    console.log(`Prepared exactly three checked assets for ${release.tag}.`);
  } else if (action === 'publish') {
    requireCondition(process.env.GITHUB_ACTIONS === 'true' && process.env.GITHUB_REF === 'refs/heads/main' && process.env.GITHUB_EVENT_NAME === 'push', 'Publication is restricted to the main-push Actions workflow.');
    console.log(JSON.stringify(await publishRelease({release, gh: githubCli()})));
  } else throw new Error('Use prepare or publish.');
}
