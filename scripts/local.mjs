import {existsSync, mkdirSync, readFileSync, writeFileSync} from 'node:fs';
import {randomBytes} from 'node:crypto';
import {spawnSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import path from 'node:path';
const root = fileURLToPath(new URL('../', import.meta.url));
const envFile = path.join(root, 'local/.env');
const action = process.argv[2];
function run(args) {
  const r = spawnSync('docker', ['compose', '--env-file', envFile, '-f', path.join(root, 'local/compose.yml'), ...args], {stdio:'inherit', cwd:root});
  if (r.error) throw r.error;
  if (r.status !== 0) process.exit(r.status ?? 1);
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
    const {version} = JSON.parse(readFileSync(path.join(root,'release/components.json')));
    run(['run','--rm','cli','wp','plugin','install',`/artifacts/falcon-wf-${version}.zip`,'--activate','--force']);
  } else if (action === 'test') {
    run(['exec','-T','wordpress','php','/fwf-tests/lint.php']);
    run(['run','--rm','cli','wp','eval-file','/fwf-tests/integration.php']);
    run(['run','--rm','cli','wp','eval-file','/fwf-tests/content.php']);
    run(['run','--rm','cli','wp','eval-file','/fwf-tests/builder.php']);
    run(['run','--rm','cli','wp','eval-file','/fwf-tests/provider.php']);
    run(['run','--rm','cli','wp','eval-file','/fwf-tests/update.php']);
    run(['run','--rm','cli','wp','eval-file','/fwf-tests/immutable.php']);
    for (const test of ['tests/mcp.test.mjs','tests/admin.test.mjs','tests/frontend.test.mjs']) {
      const r=spawnSync(process.execPath,[test],{cwd:root,stdio:'inherit'}); if(r.status!==0) process.exit(r.status??1);
    }
  }
  else throw new Error('Unknown local command.');
}
