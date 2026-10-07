import {existsSync, mkdirSync, readFileSync, writeFileSync} from 'node:fs';
import {randomBytes, randomUUID} from 'node:crypto';
import {spawnSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import path from 'node:path';
const root = fileURLToPath(new URL('../', import.meta.url));
const envFile = path.join(root, 'local/.env');
const action = process.argv[2];
function run(args) {
  const r = spawnSync('docker', ['compose', '--env-file', envFile, '-f', path.join(root, 'local/compose.yml'), ...args], {stdio:'inherit', cwd:root});
  if (r.error) throw r.error;
  if (r.status !== 0) throw new Error(`Docker command failed (exit ${r.status ?? 1}).`);
}
function withSiteState(task) {
  const owner = randomUUID();
  run(['run','--rm','cli','wp','eval-file','/fwf-tests/site-state.php','capture',owner]);
  try { task(); }
  finally {
    // A failed child must throw instead of process.exit so this restoration still runs.
    run(['run','--rm','cli','wp','eval-file','/fwf-tests/site-state.php','restore',owner]);
  }
}
if (action === 'setup') {
  mkdirSync(path.join(root,'dist'), {recursive:true});
  if (!existsSync(envFile)) {
    const secret = () => randomBytes(24).toString('hex');
    writeFileSync(envFile, `FWF_DB_PASSWORD=${secret()}\nFWF_DB_ROOT_PASSWORD=${secret()}\nFWF_ADMIN_PASSWORD=${secret()}\n`, {mode:0o600});
  }
  console.log('Local credentials prepared in ignored local/.env (not printed).');
} else {
  if (!existsSync(envFile)) throw new Error('Run npm run local:setup first.');
  if (action === 'up') run(['up','-d','wordpress']);
  else if (action === 'seed') run(['run','--rm','cli','wp','eval-file','/fwf-examples/content/seed.php']);
  else if (action === 'down') run(['stop']);
  else if (action === 'install') {
    const env = Object.fromEntries(readFileSync(envFile,'utf8').trim().split('\n').map(l=>l.split('=')));
    const probe = spawnSync('docker',['compose','--env-file',envFile,'-f',path.join(root,'local/compose.yml'),'run','--rm','cli','wp','core','is-installed'],{cwd:root,stdio:'ignore'});
    if (probe.status !== 0) run(['run','--rm','cli','wp','core','install','--url=http://localhost:8091','--title=Falcon WF Local','--admin_user=fwf-admin',`--admin_password=${env.FWF_ADMIN_PASSWORD}`,'--admin_email=local@example.invalid','--skip-email']);
    const {versions} = JSON.parse(readFileSync(path.join(root,'release/components.json')));
    const version=versions['falcon-wf'];
    run(['run','--rm','cli','wp','plugin','install',`/artifacts/falcon-wf-${version}.zip`,'--activate','--force']);
  } else if (action === 'test-state-recovery') {
    withSiteState(()=>run(['run','--rm','cli','wp','eval-file','/fwf-tests/state-failure.php']));
  } else if (action === 'test') {
    const guardCheck=spawnSync(process.execPath,['tests/harness.test.mjs'],{cwd:root,stdio:'inherit'});
    if (guardCheck.status!==0) throw new Error('State recovery regression failed.');
    withSiteState(()=>{
      run(['run','--rm','cli','wp','eval-file','/fwf-tests/runtime-package.php']);
      run(['exec','-T','wordpress','php','/fwf-tests/lint.php']);
      run(['exec','-T','wordpress','php','/fwf-tests/update-policy.php']);
      for (const file of ['integration.php','content.php','builder.php','native-save.php','design.php','seo.php','provider.php','update.php','immutable.php']) {
        run(['run','--rm','cli','wp','eval-file',`/fwf-tests/${file}`]);
      }
      for (const test of ['tests/project-update.test.mjs','tests/mcp.test.mjs','tests/admin.test.mjs','tests/frontend.test.mjs','tests/project.test.mjs']) {
        const r=spawnSync(process.execPath,[test],{cwd:root,stdio:'inherit'});
        if(r.status!==0) throw new Error(`Integration check failed: ${test} (exit ${r.status ?? 1}).`);
      }
      run(['run','--rm','cli','wp','eval-file','/fwf-tests/runtime-package.php']);
    });
  }
  else throw new Error('Unknown local command.');
}
