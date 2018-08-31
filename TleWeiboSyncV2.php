<?php
/* 
Plugin Name: TleWeiboSyncV2
Plugin URI: https://github.com/muzishanshi/TleWeiboSyncV2
Description:  基于新浪微博API(OAuth2.0授权认证)，可以将在WordPress内发布的文章同步到指定的新浪微博账号。针对2017年6月26日微博API更新有所调整。
Version: 1.0.1
Author: 二呆
Author URI: http://www.tongleer.com
License: 
*/
include_once dirname(__FILE__) .'/saetv2.ex.class.php';
if (file_exists(dirname(__FILE__).'/sinav2_token_conf.php')) {
	include_once( dirname(__FILE__).'/sinav2_token_conf.php' );
}else{
	$weibosync_configs = get_settings('tle_weibo_sync');
	$sinav2_o = new SaeTOAuthV2( $weibosync_configs["weiboappkey"] , $weibosync_configs["weiboappsecret"] );
	if (isset($_REQUEST['code'])) {//callback
		$sinav2_keys = array();
		$sinav2_keys['code'] = $_REQUEST['code'];
		$sinav2_keys['redirect_uri'] = $weibosync_configs["weibocallback"];
		try {
			$sinav2_last_key = $sinav2_o->getAccessToken( 'code', $sinav2_keys ) ;
			if ($sinav2_last_key) {
				sinav2_save_access_token($sinav2_last_key['access_token'], $sinav2_last_key['remind_in'], $sinav2_last_key['expires_in'], $sinav2_last_key['uid']);
				include_once( dirname(__FILE__).'/sinav2_token_conf.php' );
			}else{
				echo('<a href="options-general.php?page=tle-weibo-sync&t=weiborelogin">授权失败，请重新授权。</a>');
			}
		} catch (OAuthException $e) {
			echo('发生意外，错误信息：'.$e);
		}
	} else {//get request token
		$sinav2_aurl = $sinav2_o->getAuthorizeURL( $weibosync_configs["weibocallback"]);
	}
}

//插件启用后自动跳转插件设置页面
register_activation_hook(__FILE__, 'tle_weibo_sync_activate');
add_action('admin_init', 'tle_weibo_sync_redirect');
function tle_weibo_sync_activate() {
    add_option('tle_weibo_sync_do_activation_redirect', true);
}
function tle_weibo_sync_redirect() {
    if (get_option('tle_weibo_sync_do_activation_redirect', false)) {
        delete_option('tle_weibo_sync_do_activation_redirect');
        wp_redirect(admin_url( 'options-general.php?page=tle-weibo-sync' ));
    }
}

if(isset($_GET['t'])){
    if($_GET['t'] == 'config'){
        update_option('tle_weibo_sync', array('weiboappkey' => $_REQUEST['weiboappkey'], 'weiboappsecret' => $_REQUEST['weiboappsecret'], 'weibocallback' => $_REQUEST['weibocallback']));
    }
	if($_GET['t'] == 'weibosynclogin'){
        
    }
	if($_GET['t'] == 'weiborelogin'){
        if (file_exists(dirname(__FILE__).'/sinav2_token_conf.php')) {
			if (!unlink(dirname(__FILE__).'/sinav2_token_conf.php')) {
				echo('操作失败，请确保插件目录(/wp-content/plugins/TleWeiboSyncV2/)可写');
			}else{
				echo "<script>location.href='';</script>";
			}
		}
    }
}

add_action('admin_menu', 'tle_weibo_sync_menu');
function tle_weibo_sync_menu(){
    add_options_page('微博同步', '微博同步', 'manage_options', 'tle-weibo-sync', 'tle_weibo_sync_options');
}
function tle_weibo_sync_options(){
	global $sinav2_aurl;
    $weibosync_configs = get_settings('tle_weibo_sync');
	?>
	<div class="wrap">
		<h2>微博同步设置:</h2>
		作者：<a href="http://www.tongleer.com" target="_blank" title="">二呆</a><br />
		<?php
		$version=file_get_contents('http://api.tongleer.com/interface/TleWeiboSyncV2.php?action=update&version=1');
		echo $version;
		?>
		<form method="get" action="">
			<p>
				<input type="text" name="weiboappkey" value="<?=$weibosync_configs["weiboappkey"];?>" required placeholder="App Key" size="50" />
			</p>
			<p>
				<input type="text" name="weiboappsecret" value="<?=$weibosync_configs["weiboappsecret"];?>" required placeholder="App Secret" size="50" />
			</p>
			<p>
				<input type="text" name="weibocallback" value="<?=$weibosync_configs["weibocallback"];?>" required placeholder="回调地址" size="50" />
			</p>
			<p>
				<input type="hidden" name="t" value="config" />
				<input type="hidden" name="page" value="tle-weibo-sync" />
				<input type="submit" value="第一步：修改配置" />
			</p>
			<p>
				<input type="button" value="第二步：登录微博账号" disabled />
			</p>
		</form>
		<ul>
		<?php 
		if (!isset($_GET['oauth_token']) && !defined('SINAV2_ACCESS_TOKEN')){ ?>
			<li><a href="<?php echo $sinav2_aurl ?>"><img src="<?=plugins_url('t-login.png', __FILE__);?>"></a></li>
		<?php
		}else{
			$c = new SaeTClientV2( $weibosync_configs["weiboappkey"] , $weibosync_configs["weiboappsecret"] , SINAV2_ACCESS_TOKEN );
			$ms  = $c->show_user_by_id(SINAV2_UID);
			if(isset($ms['error_code'])){
			?>
			<li>获取用户信息失败,错误代码:<?php echo $ms['error_code']?>,错误信息：<?php echo $ms['error']?></li>
			<li><a href="options-general.php?page=tle-weibo-sync&t=weiborelogin">更换账号</a>,(更换账户
			时请先退出当前浏览器中新浪微博(weibo.com)的登录状态).</li>
			<?php
			}else{
			$ti = $c->get_token_info();
			?>
			<li><img src="<?php echo $ms['profile_image_url']?>" style="border:2px #CCCCCC solid;"/></li>
			<li>当前新浪微博账号<b><?php echo $ms['name']?></b>，<a href="options-general.php?page=tle-weibo-sync&t=weiborelogin">更换账号</a>(更换账户
			时请先退出当前浏览器中新浪微博(weibo.com)的登录状态).</li>
			<li>离授权过期还有：<?php echo sinav2_expire_in($ti['expire_in']); ?>，授权开始时间：<?php echo gmdate('Y-n-j G:i l', $ti['create_at']); ?>，授权过期时间：<?php echo gmdate('Y-n-j G:i l', 0+$ti['create_at']+$ti['expire_in']); ?></li>
			<?php 
			}
		}
		?>
		</ul>
	</div>
	<?php
}
function sinav2_save_access_token($token, $remind_in, $expires_in, $uid){
    $profile = dirname(__FILE__).'/sinav2_token_conf.php';

	$sinav2_new_profile = "<?php\ndefine('SINAV2_ACCESS_TOKEN','$token');\ndefine('SINAV2_REMIND_IN','$remind_in');\ndefine('SINAV2_EXPIRES_IN','$expires_in');\ndefine('SINAV2_UID','$uid');\n";

	$fp = @fopen($profile,'wb');
	if(!$fp) {
	    echo('操作失败，请确保插件目录(/wp-content/plugins/TleWeiboSyncV2/)可写');
	}
	fwrite($fp,$sinav2_new_profile);
	fclose($fp);
}

function sinav2_expire_in($timestamp){
    $d = floor($timestamp/86400);
    $h = floor(($timestamp%86400)/3600);
    $i = floor((($timestamp%86400)%3600)/60);
    $s = floor((($timestamp%86400)%3600)%60);
    return "{$d}天{$h}小时{$i}分{$s}秒";
}

add_action('publish_post', 'sinav2_post_article', 0);
function sinav2_post_article($post_ID) {
	if (!defined('SINAV2_ACCESS_TOKEN'))return;
	/* 此处修改为通过文章自定义栏目来判断是否同步 */
	if(get_post_meta($post_ID,'weibo_sync',true) == 1) return;
	$weibosync_configs = get_settings('tle_weibo_sync');
	
	$get_post_info = get_post($post_ID);
	$get_post_centent = get_post($post_ID)->post_content;
	$get_post_title = get_post($post_ID)->post_title;
	if ($get_post_info->post_status == 'publish' && $_POST['original_post_status'] != 'publish') {
		$keywords = ""; 
		/* 获取文章标签关键词 */
		$tags = wp_get_post_tags($post_ID);
		foreach ($tags as $tag ) {
		$keywords = $keywords.'#'.$tag->name."#";
		}

		/* 修改了下风格，并添加文章关键词作为微博话题，提高与其他相关微博的关联率 */
		$string1 = '【文章发布】' . strip_tags( $get_post_title ).'：';
		$string2 = $keywords.' 查看全文：'.get_permalink($post_ID);

		/* 微博字数控制，避免超标同步失败 */
		$wb_num = (138 - WeiboLength($string1.$string2))*2;
		$postData = $string1.mb_strimwidth(strip_tags( apply_filters('the_content', $get_post_centent)),0, $wb_num,'...').$string2;

		/* 获取特色图片，如果没设置就抓取文章第一张图片，需要主题函数支持 */
		$img=false;
		if (has_post_thumbnail()) {
			$timthumb_src = wp_get_attachment_image_src( get_post_thumbnail_id($post_ID), 'full' ); 
			$img = $timthumb_src[0];
		} else if(function_exists('catch_first_image')) {
			$img = catch_first_image(); 
		}
		/*
		preg_match_all("/\<img.*?src\=\"(.*?)\"[^>]*>/i", stripslashes($get_post_centent), $matchpic);
		$imgurl=@$matchpic[1][0];
		$filename = dirname(__FILE__).'/sinatempimg.png';
		$img=false;
		if(!empty($imgurl)){
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $imgurl);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
			$file = curl_exec($ch);
			curl_close($ch);
			//$filename = pathinfo($imgurl, PATHINFO_BASENAME);
			$resource = fopen($filename, 'a');
			fwrite($resource, $file);
			fclose($resource);
			$filesize=abs(filesize($filename));
			if($filesize<5120000){
				$img = $filename;
			}
		}
		
		/* 同步微博 */
		$c = new SaeTClientV2( $weibosync_configs["weiboappkey"] , $weibosync_configs["weiboappsecret"] , SINAV2_ACCESS_TOKEN );
		$res=$c->share($postData,$img);
		/*
		if(file_exists($filename)){
			@unlink($filename);
		}
		*/
		/* 若同步成功，则给新增自定义栏目weibo_sync，避免以后更新文章重复同步 */
		add_post_meta($post_ID, 'weibo_sync', 1, true);
		//var_dump($res);die();
	}
}
/*获取微博字符长度函数*/
function WeiboLength($str){
    $arr = arr_split_zh($str);   //先将字符串分割到数组中
    foreach ($arr as $v){
        $temp = ord($v);        //转换为ASCII码
        if ($temp > 0 && $temp < 127) {
            $len = $len+0.5;
        }else{
            $len ++;
        }
    }
    return ceil($len);        //加一取整
}
/*拆分字符串函数,只支持 gb2312编码*/
function arr_split_zh($tempaddtext){
    $tempaddtext = iconv("UTF-8", "GBK//IGNORE", $tempaddtext);
    $cind = 0;
    $arr_cont=array();
    for($i=0;$i<strlen($tempaddtext);$i++){
        if(strlen(substr($tempaddtext,$cind,1)) > 0){
            if(ord(substr($tempaddtext,$cind,1)) < 0xA1 ){ //如果为英文则取1个字节
                array_push($arr_cont,substr($tempaddtext,$cind,1));
                $cind++;
            }else{
                array_push($arr_cont,substr($tempaddtext,$cind,2));
                $cind+=2;
            }
        }
    }
    foreach ($arr_cont as &$row){
        $row=iconv("gb2312","UTF-8",$row);
    }
    return $arr_cont;
}
/* 抓取文章第一张图片作为特色图片（已加上是否已存在判断，可放心添加到functions.php） */
if(!function_exists('catch_first_image')){
	function catch_first_image() {
		global $post, $posts;
		$first_img = '';
		ob_start();
		ob_end_clean();
		$output = preg_match_all('/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $get_post_centent,$matches);
		$first_img = $matches [1] [0];
		return $first_img;
	} 
}
?>