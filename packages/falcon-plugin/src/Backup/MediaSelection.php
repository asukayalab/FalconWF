<?php
namespace FalconWF\Backup;
/** Native attachment records and their declared local file dependencies. */
final class MediaSelection {
    private const META=['_wp_attached_file','_wp_attachment_metadata','_wp_attachment_backup_sizes','_wp_attachment_image_alt','_thumbnail_id'];
    private static function fail(string $message): never { throw new \RuntimeException($message); }
    public static function ids(mixed $value): ?array {
        if ($value===null) { return null; }if (is_string($value)) { $value=explode(',',$value); }
        if (!is_array($value) || !$value) { self::fail('Pilih minimal satu media/report.'); }$ids=[];
        foreach ($value as $id) { if ((!is_int($id) && !is_string($id)) || !preg_match('/^[1-9][0-9]*$/D',(string)$id) || (string)(int)$id!==(string)$id || isset($ids[(int)$id])) { self::fail('ID media tidak valid atau berulang.'); }$ids[(int)$id]=(int)$id; }
        sort($ids,SORT_NUMERIC);return array_values($ids);
    }
    private static function decoded(string $value): mixed {
        $serialized=is_serialized($value);$raw=$value;$value=$serialized?@unserialize($value,['allowed_classes'=>false,'max_depth'=>64]):$value;if ($serialized && $value===false && $raw!=='b:0;') { self::fail('Metadata serialisasi tidak valid.'); }
        $check=static function($node,$depth=0) use (&$check) { if ($depth>64) { self::fail('Metadata terlalu dalam.'); }if (is_object($node) || is_resource($node)) { self::fail('Metadata objek tidak didukung.'); }if (is_array($node)) { foreach ($node as $child) { $check($child,$depth+1); } } };$check($value);return $value;
    }
    private static function relative(mixed $path,bool $basename=false): string {
        if (!is_string($path) || $path==='' || str_contains($path,'\\') || str_contains($path,"\0") || preg_match('~(^/|^[A-Za-z]:|(^|/)(\.|\.\.)(/|$)|//|(^|/)(\.env(?:\.[^/]*)?|wp-config\.php|debug\.log|\.git)(/|$))~',$path) || ($basename && basename($path)!==$path)) { self::fail('Path media tidak aman.'); }return $path;
    }
    /** Validate signed records without consulting possibly deleted live attachment rows. */
    public static function validate(array $records,array $roots,bool $live=false): array {
        global $wpdb;$columns=$wpdb->get_col("SHOW COLUMNS FROM `$wpdb->posts`",0);$ids=[];$paths=[];$edges=[];$parents=[];
        foreach ($records as $record) {
            if (!is_array($record) || array_keys($record)!==['post','meta'] || !is_array($record['post']) || array_keys($record['post'])!==$columns || !is_array($record['meta'])) { self::fail('Schema attachment tidak cocok.'); }
            $post=$record['post'];foreach ($post as $value) { if (!is_string($value) && $value!==null) { self::fail('Nilai row attachment tidak valid.'); } }$id=self::ids([$post['ID']])[0];if ($id<1 || isset($ids[$id]) || $post['post_type']!=='attachment') { self::fail('Record attachment tidak valid.'); }$ids[$id]=true;$meta=[];
            foreach ($record['meta'] as $row) { if (!is_array($row) || array_keys($row)!==['meta_key','meta_value'] || !in_array($row['meta_key'],self::META,true) || !is_string($row['meta_value']) || isset($meta[$row['meta_key']])) { self::fail('Metadata attachment tidak valid.'); }$meta[$row['meta_key']]=self::decoded($row['meta_value']); }
            $file=self::relative($meta['_wp_attached_file']??null);$paths[$file]=true;$folder=dirname($file);$folder=$folder==='.'?'':$folder.'/';
            $details=$meta['_wp_attachment_metadata']??[];$backups=$meta['_wp_attachment_backup_sizes']??[];
            if (!is_array($details) || !is_array($backups)) { self::fail('Metadata ukuran media tidak valid.'); }
            if (isset($details['file']) && $details['file']!==$file) { self::fail('File metadata tidak cocok dengan attachment.'); }
            if (isset($details['original_image'])) { $paths[$folder.self::relative($details['original_image'],true)]=true; }
            foreach ([$details['sizes']??[],$backups] as $sizes) { if (!is_array($sizes)) { self::fail('Ukuran media tidak valid.'); }foreach ($sizes as $size) { if (!is_array($size) || !isset($size['file'])) { self::fail('File ukuran media tidak valid.'); }$paths[$folder.self::relative($size['file'],true)]=true; } }
            $edge=$meta['_thumbnail_id']??'';if ($edge!=='' && (string)$edge!=='0') { $edges[$id]=self::ids([$edge])[0]; }
            if ($live) {
                $current=$wpdb->get_row($wpdb->prepare("SELECT post_type,guid FROM {$wpdb->posts} WHERE ID=%d",$id),ARRAY_A);
                if ($current && ($current['post_type']!=='attachment' || $current['guid']!==$post['guid'])) { self::fail('ID attachment dipakai objek lain.'); }
                if ((int)$post['post_parent']>0) { $parents[]=(int)$post['post_parent']; }
                if ((int)$post['post_author']>0 && !get_user_by('id',(int)$post['post_author'])) { self::fail('Pemilik attachment tidak tersedia.'); }
            }
        }
        if ($live) { foreach ($parents as $parent) { if (!isset($ids[$parent]) && !$wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID=%d",$parent))) { self::fail('Konten induk attachment belum tersedia.'); } } }
        $todo=$roots;$seen=[];while ($todo) { $id=array_pop($todo);if (isset($seen[$id])) { continue; }if (!isset($ids[$id])) { self::fail('Dependensi attachment tidak lengkap.'); }$seen[$id]=true;if (isset($edges[$id])) { $todo[]=$edges[$id]; } }
        if (count($seen)!==count($ids)) { self::fail('Attachment di luar pilihan.'); }$paths=array_keys($paths);sort($paths);if ($live) { foreach ($paths as $path) { $owners=$wpdb->get_col($wpdb->prepare("SELECT m.post_id FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID=m.post_id AND p.post_type='attachment' WHERE m.meta_key='_wp_attached_file' AND m.meta_value=%s",$path));foreach ($owners as $owner) { if (!isset($ids[(int)$owner])) { self::fail('File media dipakai attachment di luar pilihan.'); } } } }return $paths;
    }
    public static function snapshot(array $roots,bool $hash=true): array {
        global $wpdb;$records=[];$todo=$roots;$seen=[];
        while ($todo) {
            $id=array_pop($todo);if (isset($seen[$id])) { continue; }$seen[$id]=true;$post=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID=%d AND post_type='attachment'",$id),ARRAY_A);if (!$post) { self::fail('Media terpilih tidak ditemukan: '.$id); }
            $meta=$wpdb->get_results($wpdb->prepare("SELECT meta_key,meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key IN (".implode(',',array_fill(0,count(self::META),'%s')).") ORDER BY meta_key",$id,...self::META),ARRAY_A);if ($wpdb->last_error) { self::fail('Pembacaan metadata media gagal.'); }
            $records[$id]=['post'=>$post,'meta'=>$meta];foreach ($meta as $row) { if ($row['meta_key']==='_thumbnail_id' && $row['meta_value']!=='' && $row['meta_value']!=='0') { $todo[]=self::ids([$row['meta_value']])[0]; } }
        }
        ksort($records,SORT_NUMERIC);$records=array_values($records);$paths=self::validate($records,$roots,true);$root=wp_get_upload_dir()['basedir'];$real=realpath($root);if (!$real || is_link($root)) { self::fail('Uploads lokal tidak tersedia.'); }$files=[];$bytes=0;
        foreach ($paths as $relative) {
            $path=$root.'/'.$relative;$probe=$path;while ($probe!==$root) { if (is_link($probe)) { self::fail('Symlink media ditolak.'); }$probe=dirname($probe); }
            if (!is_file($path) || !is_readable($path) || !str_starts_with(realpath($path),$real.'/')) { self::fail('File/dependensi media tidak tersedia: '.$relative); }
            $size=filesize($path);$bytes+=$size;$files['media/'.$relative]=['path'=>$path,'sha256'=>$hash?hash_file('sha256',$path):null,'size'=>$size,'mode'=>fileperms($path)&0777];
        }
        $bytes+=strlen(wp_json_encode($records,JSON_THROW_ON_ERROR));return ['records'=>$records,'files'=>$files,'bytes'=>$bytes,'count'=>count($files)];
    }
    /** Caller owns the existing restore database transaction; no generic SQL from ZIP. */
    public static function restore(array $records,array $roots): void {
        global $wpdb;self::validate($records,$roots,true);
        foreach ($records as $record) {
            $post=$record['post'];foreach ($post as $value) { if (!is_string($value) && $value!==null) { self::fail('Nilai row attachment tidak valid.'); } }$id=self::ids([$post['ID']])[0];if ($wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID=%d",$id))) { unset($post['ID']);$written=$wpdb->update($wpdb->posts,$post,['ID'=>$id]); }else { $written=$wpdb->insert($wpdb->posts,$post); }
            if ($written===false) { self::fail('Pemulihan attachment gagal.'); }
            foreach (self::META as $key) { if ($wpdb->delete($wpdb->postmeta,['post_id'=>$id,'meta_key'=>$key])===false) { self::fail('Reset metadata attachment gagal.'); } }
            foreach ($record['meta'] as $row) { if ($wpdb->insert($wpdb->postmeta,['post_id'=>$id]+$row)===false) { self::fail('Pemulihan metadata attachment gagal.'); } }
        }
    }
}
