<?php
$owner=getenv('FWF_RETENTION_TEST_OWNER');
if (!is_string($owner) || !preg_match('/^[a-f0-9-]{36}$/D',$owner)) { throw new RuntimeException('Retention fixture owner required.'); }
$path='/fwf-backups/retention-test-'.$owner;
if (file_exists($path) || !mkdir($path,0700)) { throw new RuntimeException('Private fixture collision.'); }
$extra=getenv('WORDPRESS_CONFIG_EXTRA');$needle="define('FWF_BACKUP_DIR', '/fwf-backups');";
if (!is_string($extra) || substr_count($extra,$needle)!==1) { rmdir($path);throw new RuntimeException('Local backup config mismatch.'); }
putenv('WORDPRESS_CONFIG_EXTRA='.str_replace($needle,"define('FWF_BACKUP_DIR', '$path');",$extra));
