"""Create a source-only handoff ZIP. Runtime installers remain in dist/."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED

root = Path(__file__).resolve().parent.parent
folders = ('packages', 'scripts', 'tests', 'release', 'engineering', 'examples', 'guides')
files = ['.gitignore', 'AGENTS.md', 'README.md', 'package.json', 'package-lock.json', 'local/compose.yml']
for folder in folders:
    for path in sorted((root / folder).rglob('*')):
        if path.is_symlink():
            raise RuntimeError('Source export refuses symlinks')
        if path.is_file() and not any(part in ('.DS_Store', '__pycache__', '.env', 'node_modules', 'vendor') for part in path.relative_to(root).parts) and path.suffix != '.pyc':
            files.append(path.relative_to(root).as_posix())
files = sorted(set(files))
# Check local secrets without printing their values or including their file.
secrets = []
env = root / 'local/.env'
if env.exists():
    for line in env.read_text().splitlines():
        if '=' in line and not line.lstrip().startswith('#'):
            value = line.split('=', 1)[1].strip().strip('\"\'')
            if len(value) >= 12:
                secrets.append(value.encode())
for name in files:
    content = (root / name).read_bytes()
    if any(secret in content for secret in secrets):
        raise RuntimeError('Source export refused: local credential found in selected source')
output = root / 'local/artifacts/github-upload'
output.mkdir(parents=True, exist_ok=True)
archive_path = output / 'FalconWF-source-alpha.2.zip'
with ZipFile(archive_path, 'w', ZIP_DEFLATED) as archive:
    for name in files:
        archive.write(root / name, 'FalconWF/' + name)
with ZipFile(archive_path) as archive:
    assert archive.testzip() is None
    assert sorted(archive.namelist()) == ['FalconWF/' + name for name in files]
    assert all(not any(part in ('.git', 'docs', 'session-notes', 'dist', 'build', 'node_modules', '.env', 'artifacts') for part in Path(name).parts) for name in archive.namelist())
(output / 'ISI-SOURCE.txt').write_text('\n'.join(files) + '\n')
print(f'Created source handoff ZIP: {archive_path.relative_to(root)} ({len(files)} files)')
print('Archive integrity, exact source inventory, excluded folders, and local credential checks passed.')
