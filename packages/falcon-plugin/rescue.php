<?php
/** Operator CLI only. SHORTINIT skips normal plugin/theme bootstrap. */
if (PHP_SAPI!=='cli') { http_response_code(403);exit; }
$options=getopt('',['root:','inspect:','apply:','review:','confirm']);
try {
    $root=$options['root']??dirname(__DIR__,3);if (!is_string($root) || !is_file($root.'/wp-load.php')) { throw new RuntimeException('Gunakan --root=/folder/wordpress yang benar.'); }
    define('SHORTINIT',true);require $root.'/wp-load.php';require_once __DIR__.'/autoload.php';
    if (is_multisite()) { throw new RuntimeException('Multisite tidak didukung.'); }
    if (!defined('FWF_BACKUP_DIR')) { throw new RuntimeException('FWF_BACKUP_DIR belum dikonfigurasi.'); }
    $id=$options['apply']??$options['inspect']??null;if (!is_string($id) || isset($options['apply'],$options['inspect'])) { throw new RuntimeException('Pilih --inspect=ID atau --apply=ID --review=SHA256 --confirm.'); }
    $review=isset($options['apply'])?($options['review']??''):null;if ($review!==null && (!is_string($review) || !preg_match('/^[a-f0-9]{64}$/D',$review))) { throw new RuntimeException('Review SHA256 diperlukan.'); }
    $result=\FalconWF\Backup\Recovery::operate($id,$review,array_key_exists('confirm',$options));fwrite(STDOUT,json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n");
} catch (Throwable $error) { fwrite(STDERR,'FWF rescue: '.$error->getMessage().' ['.basename($error->getFile()).':'.$error->getLine().']'."\n");exit(1); }
