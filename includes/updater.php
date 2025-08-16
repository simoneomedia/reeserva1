
<?php
if (!defined('ABSPATH')) exit;
/**
 * Reeserva lightweight GitHub self-updater.
 */
class Reeserva_GitHub_Updater {
    private $file,$slug,$pluginData,$owner,$repo,$branch,$api_base,$channel,$token;
    public function __construct($file, array $cfg){
        $this->file=$file; $this->slug=plugin_basename($file);
        $this->owner=$cfg['owner']; $this->repo=$cfg['repo'];
        $this->branch=$cfg['branch'] ?? 'main';
        $this->channel=defined('REESERVA_UPDATE_CHANNEL') ? REESERVA_UPDATE_CHANNEL : ($cfg['channel'] ?? 'stable');
        $this->api_base='https://api.github.com/repos/'.$this->owner.'/'.$this->repo;
        $this->token=defined('REESERVA_GITHUB_TOKEN') ? REESERVA_GITHUB_TOKEN : ($cfg['token'] ?? '');

        add_filter('pre_set_site_transient_update_plugins', [$this,'check_for_update']);
        add_filter('plugins_api',[$this,'plugins_api'],10,3);
        add_filter('upgrader_source_selection',[$this,'fix_subdir_name'],10,4);
    }
    private function headers(){
        $h=['Accept'=>'application/vnd.github+json','User-Agent'=>'WP-Reeserva-Updater (+'.home_url().')'];
        if($this->token) $h['Authorization']='Bearer '.$this->token;
        return $h;
    }
    private function get($url,$ttl=900){
        $key='rsv_upd_'.md5($url.$this->channel);
        $cached=get_site_transient($key);
        if($cached) return $cached;
        $resp=wp_remote_get($url,['headers'=>$this->headers(),'timeout'=>15]);
        if(is_wp_error($resp)) return null;
        $code=wp_remote_retrieve_response_code($resp);
        $body=json_decode(wp_remote_retrieve_body($resp),true);
        if($code>=200 && $code<300 && is_array($body)){
            set_site_transient($key,$body,$ttl); return $body;
        }
        return null;
    }
    private function latest_release(){
        if($this->channel==='stable'){
            $rel=$this->get($this->api_base.'/releases/latest');
            if(!$rel) return null;
            if(!empty($rel['prerelease'])){
                $list=$this->get($this->api_base.'/releases?per_page=10'); if(!$list) return null;
                foreach($list as $r){ if(empty($r['prerelease'])) return $r; }
            }
            return $rel;
        } else {
            $list=$this->get($this->api_base.'/releases?per_page=10'); if(!$list) return null;
            foreach($list as $r){ if(!empty($r['prerelease'])) return $r; }
            foreach($list as $r){ if(empty($r['prerelease'])) return $r; }
            return $list[0] ?? null;
        }
    }
    private function version_from_tag($tag){
        $tag=trim($tag);
        if($tag && $tag[0]==='v') $tag=substr($tag,1);
        $tag=preg_replace('~[^0-9\.].*$~','',$tag);
        return $tag ?: '0.0.0';
    }
    public function check_for_update($transient){
        if(empty($transient->checked)) return $transient;
        if(!$this->pluginData) $this->pluginData=get_plugin_data($this->file,false,false);
        $current=$this->pluginData['Version'];
        $rel=$this->latest_release(); if(!$rel) return $transient;
        $tag=$rel['tag_name'] ?? ''; $new=$this->version_from_tag($tag);
        if(version_compare($new,$current,'<=')) return $transient;
        $package='';
        if(!empty($rel['assets'])){
            foreach($rel['assets'] as $a){
                if(!empty($a['browser_download_url']) && substr($a['name'],-4)==='.zip'){
                    $package=$a['browser_download_url']; break;
                }
            }
        }
        if(!$package) $package='https://codeload.github.com/'.$this->owner.'/'.$this->repo.'/zip/refs/tags/'.$rel['tag_name'];
        $obj=new stdClass();
        $obj->slug=dirname($this->slug);
        $obj->plugin=$this->slug;
        $obj->new_version=$new;
        $obj->url='https://github.com/'.$this->owner.'/'.$this->repo;
        $obj->package=$package;
        $transient->response[$this->slug]=$obj;
        return $transient;
    }
    public function plugins_api($res,$action,$args){
        if($action!=='plugin_information') return $res;
        if(empty($args->slug) || $args->slug!==dirname($this->slug)) return $res;
        if(!$this->pluginData) $this->pluginData=get_plugin_data($this->file,false,false);
        $rel=$this->latest_release(); $tag=$rel['tag_name'] ?? ''; $ver=$this->version_from_tag($tag);
        $obj=new stdClass();
        $obj->name=$this->pluginData['Name'];
        $obj->slug=dirname($this->slug);
        $obj->version=$ver ?: $this->pluginData['Version'];
        $obj->author=$this->pluginData['Author'];
        $obj->homepage='https://github.com/'.$this->owner.'/'.$this->repo;
        $obj->sections=['description'=>$this->pluginData['Description']];
        $obj->download_link='https://codeload.github.com/'.$this->owner.'/'.$this->repo.'/zip/refs/tags/'.($tag ?: 'main');
        return $obj;
    }
    public function fix_subdir_name($source,$remote_source,$upgrader,$hook_extra){
        if(empty($hook_extra['plugin']) || $hook_extra['plugin']!==$this->slug) return $source;
        $proper=trailingslashit($remote_source).dirname($this->slug).'/';
        if(trailingslashit($source)===trailingslashit($proper)) return $source;
        $paths=glob(trailingslashit($remote_source).'*', GLOB_ONLYDIR);
        if(!$paths) return $source;
        $top=trailingslashit($paths[0]);
        if(!@rename($top,$proper)) return $source;
        return $proper;
    }
}
