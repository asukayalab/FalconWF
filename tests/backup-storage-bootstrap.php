<?php
$owner=getenv('FWF_STORAGE_TEST_OWNER');
if (!is_string($owner) || !preg_match('/^[a-f0-9-]{36}$/D',$owner)) { throw new RuntimeException('Owner required.'); }
$root='/fwf-backups/storage-test-'.$owner;
if (file_exists($root) || !mkdir($root,0700) || !mkdir($root.'/public',0700)) { throw new RuntimeException('Owned fixture collision.'); }
$_SERVER['DOCUMENT_ROOT']=$root.'/public';
$extra=getenv('WORDPRESS_CONFIG_EXTRA');$needle="define('FWF_BACKUP_DIR', '/fwf-backups');";
if (substr_count($extra,$needle)!==1) { throw new RuntimeException('Local config mismatch.'); }
putenv('WORDPRESS_CONFIG_EXTRA='.str_replace($needle,'// Storage fixture without server constant.',$extra));
