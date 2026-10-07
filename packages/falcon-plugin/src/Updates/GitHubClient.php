<?php
namespace FalconWF\Updates;
final class GitHubClient {
    public function __construct(private string $repo, private string $tokenConstant='FWF_GITHUB_TOKEN', private string $manifestName='release-manifest.json') {}
    private function credential(): string {
        if (!in_array($this->tokenConstant,['FWF_GITHUB_TOKEN','FWF_PROJECT_GITHUB_TOKEN'],true) || !in_array($this->manifestName,['release-manifest.json','project-manifest.json'],true) || !defined($this->tokenConstant)) { return ''; }
        $value=constant($this->tokenConstant);return is_string($value)?$value:'';
    }
    public static function validRepo(string $repo): bool { return (bool)preg_match('#^[a-zA-Z0-9_.-]+/[a-zA-Z0-9_.-]+$#',$repo) && !str_contains($repo,'..'); }
    public function asset(int $id, bool $stream=false): string|\WP_Error {
        if (!self::validRepo($this->repo) || $id<1 || $this->credential()==='') { return new \WP_Error('FWF_AUTH','Repo atau credential private release belum dikonfigurasi.'); }
        $url='https://api.github.com/repos/'.$this->repo.'/releases/assets/'.$id;
        $headers=['Authorization'=>'Bearer '.$this->credential(),'Accept'=>'application/octet-stream','X-GitHub-Api-Version'=>'2022-11-28','User-Agent'=>'Falcon-WF'];
        $initial=['headers'=>$headers,'timeout'=>30,'redirection'=>0,'limit_response_size'=>$stream?20*1024*1024:1024*1024];
        if ($stream) { $tmp=wp_tempnam('falcon-release.zip'); if (!$tmp) { return new \WP_Error('FWF_FILESYSTEM','File sementara tidak tersedia.'); } $initial['stream']=true;$initial['filename']=$tmp; }
        $response=wp_safe_remote_get($url,$initial);
        if (is_wp_error($response)) { if(isset($tmp)){wp_delete_file($tmp);} return new \WP_Error('FWF_UPSTREAM','GitHub asset tidak tersedia.'); }
        $status=wp_remote_retrieve_response_code($response);
        if ($status===302) {
            if(isset($tmp)){wp_delete_file($tmp);}
            $location=wp_remote_retrieve_header($response,'location');
            if (!is_string($location) || parse_url($location,PHP_URL_SCHEME)!=='https' || parse_url($location,PHP_URL_HOST)!=='release-assets.githubusercontent.com') { return new \WP_Error('FWF_PACKAGE','Redirect asset ke host yang tidak diizinkan.'); }
            // Never forward authorization to the signed download host.
            $args=['timeout'=>30,'redirection'=>0,'limit_response_size'=>$stream?20*1024*1024:1024*1024];
            if ($stream) { $tmp=wp_tempnam('falcon-release.zip'); if (!$tmp) { return new \WP_Error('FWF_FILESYSTEM','File sementara tidak tersedia.'); } $args['stream']=true;$args['filename']=$tmp; }
            $response=wp_safe_remote_get($location,$args);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response)!==200) { if(isset($tmp)){wp_delete_file($tmp);}return new \WP_Error('FWF_UPSTREAM','Download asset gagal.'); }
            return $stream?$tmp:wp_remote_retrieve_body($response);
        }
        if ($status!==200) { if(isset($tmp)){wp_delete_file($tmp);} return new \WP_Error('FWF_UPSTREAM','Asset API tidak menghasilkan alur download yang didukung.'); }
        return $stream?$tmp:wp_remote_retrieve_body($response);
    }
    public function release(string $tag=''): array|\WP_Error {
        $selection=UpdateManager::validateSelection($tag); if(is_wp_error($selection)){return $selection;}
        if (!self::validRepo($this->repo) || $this->credential()==='') { return new \WP_Error('FWF_AUTH','Repo owner/name dan credential server-side diperlukan.'); }
        $r=wp_safe_remote_get('https://api.github.com/repos/'.$this->repo.($tag===''?'/releases/latest':'/releases/tags/'.rawurlencode($tag)),[
            'timeout'=>20,'redirection'=>0,'limit_response_size'=>1024*1024,
            'headers'=>['Authorization'=>'Bearer '.$this->credential(),'Accept'=>'application/vnd.github+json','X-GitHub-Api-Version'=>'2022-11-28','User-Agent'=>'Falcon-WF']]);
        if (is_wp_error($r) || wp_remote_retrieve_response_code($r)!==200) { return new \WP_Error('FWF_UPSTREAM','Private release tidak tersedia atau akses ditolak.'); }
        $release=json_decode(wp_remote_retrieve_body($r),true);
        if (!is_array($release) || ($release['draft']??null)!==false || (($release['prerelease']??null)!==($tag!=='')) || ($tag!=='' && ($release['tag_name']??'')!==$tag) || !is_array($release['assets']??null)) { return new \WP_Error('FWF_PACKAGE','Release tidak cocok dengan jalur stable/prerelease yang dipilih.'); }
        $assets=[];
        foreach ($release['assets'] as $asset) {
            if (!is_string($asset['name']??null) || !is_int($asset['id']??null) || $asset['id']<1) { return new \WP_Error('FWF_PACKAGE','Metadata asset tidak valid.'); }
            if(isset($assets[$asset['name']])){return new \WP_Error('FWF_PACKAGE','Nama asset duplikat.');}
            $assets[$asset['name']]=$asset['id'];
        }
        if (!isset($assets[$this->manifestName])) { return new \WP_Error('FWF_PACKAGE','Release manifest belum tersedia.'); }
        $raw=$this->asset($assets[$this->manifestName]);if(is_wp_error($raw)){return $raw;}
        $manifest=json_decode($raw,true);
        if (!is_array($manifest)) { return new \WP_Error('FWF_PACKAGE','Manifest JSON tidak valid.'); }
        return ['manifest'=>$manifest,'assets'=>$assets,'tag'=>(string)($release['tag_name']??'')];
    }
}
