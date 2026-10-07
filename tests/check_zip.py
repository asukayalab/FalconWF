import hashlib, json, sys
from io import BytesIO
from zipfile import ZipFile
from pathlib import Path
inventory=json.loads(Path('release/inventory.json').read_text())
manifest=json.loads(Path('dist/release-manifest.json').read_text())
components=json.loads(Path('release/components.json').read_text())
archives={}
for package in manifest['packages']:
    data=Path('dist',package['artifact']).read_bytes()
    assert hashlib.sha256(data).hexdigest()==package['sha256']
    archive=ZipFile(BytesIO(data))
    root=package['id']+'/'
    entries=archive.namelist()
    assert len(entries)==len(set(entries))
    assert all(name.startswith(root) and '..' not in name and '\\' not in name for name in entries)
    category=package['type']
    expected={root+i['path'] for i in inventory if i['package']==category}
    assets=json.loads(archive.read(root+'assets/manifest.json'))
    expected.add(root+'assets/manifest.json')
    expected.update(root+'assets/'+name for name in assets.values())
    if category=='plugin':
        expected.update([root+'installer-manifest.json',root+'bundles/falcon-theme.zip'])
        bundled=archive.read(root+'bundles/falcon-theme.zip')
        install=json.loads(archive.read(root+'installer-manifest.json'))
        assert hashlib.sha256(bundled).hexdigest()==install['theme']['sha256']
        assert install['version']==components['version']==install['theme']['version']
    assert set(entries)==expected, (set(entries)-expected,expected-set(entries))
    archives[category]=data
assert ZipFile(BytesIO(archives['plugin'])).read('falcon-wf/bundles/falcon-theme.zip')==archives['theme']
print('Package inventory, paths, asset manifests, version and nested FT checksums passed.')
