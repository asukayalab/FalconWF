<?php
namespace FalconWF\Packages;
final class Verifier {
    public static function bundle(string $root): array|\WP_Error {
        $manifest = json_decode((string)@file_get_contents($root . '/installer-manifest.json'), true);
        if (!is_array($manifest) || ($manifest['id'] ?? '') !== 'falcon-wf' || ($manifest['theme']['id'] ?? '') !== 'falcon-theme' || ($manifest['theme']['artifact'] ?? '') !== 'bundles/falcon-theme.zip' || !preg_match('/^[a-f0-9]{64}$/', $manifest['theme']['sha256'] ?? '')) {
            return new \WP_Error('FWF_PACKAGE', 'Manifest installer tidak valid. Gunakan ZIP hasil build.');
        }
        $zip = $root . '/bundles/falcon-theme.zip';
        if (!is_file($zip) || !hash_equals($manifest['theme']['sha256'], hash_file('sha256', $zip))) { return new \WP_Error('FWF_PACKAGE', 'Checksum bundle FT tidak cocok.'); }
        if (!class_exists('ZipArchive')) { return new \WP_Error('FWF_ENV', 'Extension PHP ZIP diperlukan untuk validasi paket.'); }
        $archive = new \ZipArchive();
        if ($archive->open($zip) !== true) { return new \WP_Error('FWF_PACKAGE', 'Bundle ZIP rusak.'); }
        $size = 0; $seen = []; $valid = true;
        for ($i=0; $i<$archive->numFiles; $i++) {
            $stat = $archive->statIndex($i); $name = $stat['name'];
            $size += $stat['size'];
            $opsys = 0; $attr = 0; $archive->getExternalAttributesIndex($i, $opsys, $attr);
            if (!str_starts_with($name, 'falcon-theme/') || str_contains($name, '..') || str_contains($name, '\\') || str_contains($name, "\0") || isset($seen[$name]) || (($attr >> 16) & 0170000) === 0120000) { $valid = false; }
            $seen[$name] = true;
        }
        $style = $archive->getFromName('falcon-theme/style.css');
        $archive->close();
        if (!$valid || $size > 20*1024*1024 || !isset($seen['falcon-theme/index.php']) || !is_string($style) || !preg_match('/^Version:\s*(.+)$/m', $style, $v) || trim($v[1]) !== $manifest['theme']['version']) { return new \WP_Error('FWF_PACKAGE', 'Isi/path/versi bundle FT tidak valid.'); }
        return $manifest['theme']+['expanded_bytes'=>$size];
    }
}
