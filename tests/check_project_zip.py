from pathlib import Path
from zipfile import ZipFile
import hashlib,json
spec=json.loads(Path('examples/projects/falcon-reference/project.json').read_text())
manifest=json.loads(Path('dist/projects/project-manifest.json').read_text())
p=manifest['packages'][0]
raw=Path('dist/projects',p['artifact']).read_bytes()
assert hashlib.sha256(raw).hexdigest()==p['sha256']
with ZipFile(Path('dist/projects',p['artifact'])) as z:
    expected={spec['package']['id']+'/'+file for file in spec['runtime']}
    assert set(z.namelist())==expected and len(z.namelist())==len(expected)
    assert all('..' not in file and '\\' not in file for file in z.namelist())
    assert 'Template: falcon-theme' in z.read('falcon-reference/style.css').decode()
    assert 'Version: '+spec['version'] in z.read('falcon-reference/style.css').decode()
    assert all(b'@@PROJECT_' not in z.read(file) for file in z.namelist())
assert manifest['project_id']=='falcon-reference' and p['compatibility']==spec['compatibility']
print('Project ZIP inventory/hash/parent/version/compatibility and separation passed.')
