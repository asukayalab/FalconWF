import sys
from pathlib import Path
from zipfile import ZipFile, ZipInfo, ZIP_DEFLATED
source, output, root = sys.argv[1:]
with ZipFile(output, 'w', compression=ZIP_DEFLATED) as archive:
    for path in sorted(Path(source).rglob('*')):
        if not path.is_file():
            continue
        if path.is_symlink():
            raise ValueError('Symlinks are not allowed in releases')
        info = ZipInfo(root + '/' + path.relative_to(source).as_posix(), (2026, 1, 1, 0, 0, 0))
        info.compress_type = ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        archive.writestr(info, path.read_bytes())
