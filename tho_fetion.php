<?php
/** 
Plugin Name: 评论小秘书
Plugin URI: http://thobian.info/?p=786
Version: 1.3.2
Author: 晴天打雨伞
Description: <a href="http://thobian.info/?p=786">评论小秘书</a> 评论小秘书是一个基于中国移动飞信业务开发的一个插件。当博客收到新评论时，及时通过手机短信的方式提醒管理员收到新评论。
Author URI: http://thobian.info
*/

require_once(dirname(__FILE__).'/snoopy.php');

class fetion{
	var $uid	 = 0;
	var $phone	 = '';
	var $password= '';
	var $snoopy	 = null;
	var $result	 = array();
	var $url 	 = array('loginurl'=>'http://f.10086.cn/im5/login/loginHtml5.action',
						 'sendsms' =>'http://f.10086.cn/im5/chat/sendNewShortMsg.action',
						 'loginout'=>'http://f.10086.cn/im5/index/logoutsubmit.action',
						);

	function __construct($phonenum,$pwd){
		$this->phone 	= $phonenum;
		$this->password	= $pwd;
		$this->snoopy 	= new Snoopy();
	}

	function  login(){
		$data 	= array('m'=>$this->phone, 'pass'=>$this->password, 't'=>time());
		$this->snoopy->submit($this->url['loginurl'], $data);
		$result = array();
		if( $this->snoopy->results){
			$result 	= json_decode($this->snoopy->results, true);
			$this->uid 	= $result['idUser'];
			$this->snoopy->setcookies();
			
		}
		return $this->uid ? true:false;
	}

	function  sendToSelf( $msg ){
		$formvar['msg'] 	= $msg;
		$formvar['touserid']= $this->uid;
		
		$this->snoopy->referer 		= $this->url['loginurl'];
		$this->snoopy->agent   		= 'Mozilla/5.0 (Windows NT 5.1; rv:15.0) Gecko/20100101 Firefox/15.0';
		$this->snoopy->content_type = 'application/x-www-form-urlencoded; charset=UTF-8';
		$this->snoopy->submit($this->url['sendsms'], $formvar);
		$result = json_decode($this->snoopy->results, true);
		return isset($result['sendCode'])&&$result['sendCode']==true ? true : false;
	}

	function  logout(){		
		$this->snoopy->submit($this->url['loginout'].'?t='.time());
	}
}

//前台留言发短息
add_action('comment_post', 'send_message', 90 , 2);
function  send_message($comment_id){
	//判断博客当前时间
	$temp = split( '([-: ])', current_time('mysql') );
	$hour = $temp[3];
	$temp = explode(',', get_option("fetion_sendTime"));
	if( $hour<$temp[0]||$hour>$temp[1]){	return false;	}
	//评论的内容
	$comment = get_comment($comment_id);
	if(empty($comment)){
		return false;
	}
	//过滤垃圾评论
	if( $comment->comment_approved=='spam'){
		return false;
	}
	//管理员评论时不发送短信
	if( strtolower(get_bloginfo('admin_email'))==strtolower($comment->comment_author_email) ){
		return false;
	}
	//被评论的文章
	$article 	 = get_post( $comment->comment_post_ID );
	$message 	 = "您的博客有新评论。{$comment->comment_author} 在 {$article->post_title}（" .get_permalink($comment->comment_post_ID, false). "） 这篇文章上说：{$comment->comment_content}";
	$fetion_user = get_option('fetion_user');
	$fetion_pass = get_option('fetion_pass');
	unset($comment, $article);
	$fection=new fetion($fetion_user, $fetion_pass);
	if( $fection->login()&&$fection->sendToSelf($message) ){
		$fection->logout();
		return true;
	}else{
		return false;
	}
}

//添加后台设置菜单
add_action('admin_menu', 'tho_fetion_menu');
function tho_fetion_menu() {
	if( strtolower(get_bloginfo( 'charset' ))=='utf-8' ){
		add_options_page( '评论小秘书', '评论小秘书', 8, 'tho_fetion_setting', 'tho_fetion_options_subpanel');
	}else{
		add_options_page( 'Comments secretary', 'Comments secretary', 8, 'tho_fetion_setting', 'tho_fetion_options_subpanel_2');
	}
}

function tho_fetion_options_subpanel() {
	//保存用户账号信息
	$message = "由于飞信没有开放官方API，可能出现不能收到短信的时候，请谅解。";
	if( isset($_POST["tho_fetion_submit"]) ){
		$message 	 = "账号信息保存成功。";
		$fetion_user = $_POST["fetion_user"];
		$fetion_pass = $_POST["fetion_pass"];
		$send_Time_1 = $_POST['fetion_send_time_1'];
		$send_Time_2 = $_POST['fetion_send_time_2'];
		$send_time   = $send_Time_1.','.$send_Time_2;
		$fetion		 = new fetion($fetion_user, $fetion_pass);
		if( $fetion->login() ){
			$fetion_user_saved = get_option("fetion_user");
			$fetion_pass_saved = get_option("fetion_pass");
			$fetion_send_saved = get_option("fetion_sendTime");
			if( $fetion_user_saved!=$fetion_user ){
				if( update_option("fetion_user", $fetion_user) ){
					if($fetion_pass_saved!=$fetion_pass ){
						if(!update_option("fetion_pass", $fetion_pass)){	$message = "更新失败。";		}
					}
				}else{
					$message = "更新失败。";
				}
			}
			if( $fetion_send_saved!=$send_time ){
				update_option("fetion_sendTime", $send_time);
			}
		} else {
			$message ='登陆信息验证失败，无法设置该插件。';
		}
		$fetion->logout();
	//测试短息	
	}else if( isset($_POST["tho_fetion_test"]) ){
		$fetion_user = $_POST["fetion_user"];
		$fetion_pass = $_POST["fetion_pass"];
		$fetion_msg  = empty($_POST["message"]) ? '您正在使用由 晴天打雨伞（http://thobbian.info）制作的 评论提醒插件，这是一条测试信息': $_POST["message"];
		$fetion=new fetion($fetion_user, $fetion_pass);
		if( $fetion->login()&&$fetion->sendToSelf($fetion_msg) ){
			$message = "恭喜您，测试短息发送成功，请注意查收。";
		}else{
			$message = "很遗憾，测试短息发送失败。";
		}
		$fetion->logout();
	}
	$fetion_user 	 = get_option("fetion_user");	
	$fetion_pass 	 = get_option("fetion_pass");
	$fetion_sendTime = get_option("fetion_sendTime");
	if( $fetion_sendTime ){
		 $temp = explode(',', $fetion_sendTime);
		 $beginTime = $temp[0];
		 $endTime = $temp[1];
	}else{
		 $beginTime = 8;
		 $endTime = 22;
	}
	if( empty($fetion_user) ){	$fetion_user = '手机号';	}
?>
    <div class="wrap">
        <h2 id="write-post">评论小秘书</h2>
		<?php if($message){?>
		<div id="message" class="updated fade"><p><?php echo $message; ?></p></div>
		<?php } ?>
		<h3>插件简介</h3>
		<p>评论小秘书是一个基于中国移动飞信业务开发的一个插件。当博客收到新评论时，及时通过手机短信的方式提醒管理员收到新评论</p>
	    <h3>特别提醒</h3>
		<p class="warning">由于插件是基于飞信业务开发，请确保您已经开通飞信业务！且绑定了手机号码（注：目前好像只有移动用户可以绑定手机号码）。[<a href="http://feition.10086.cn" target="_blank">开通飞信</a>]</p>
		<h3>账号设置</h3>
        <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=tho_fetion_setting">
        <table class="form-table">
          <tr>
            <td width="110px">手机号码</td>
            <td>
              <input type="text" name="fetion_user" id="fetion_user" value="<?php echo $fetion_user; ?>"  autocomplete="off" />
            </td>
          </tr>
		  <tr>
            <td>飞信密码</td>
            <td>
              <input type="password" name="fetion_pass" id="fetion_pass" value="<?php echo $fetion_pass; ?>"  autocomplete="off" />
            </td>
          </tr>
		  <tr>
            <td>发送时间</td>
            <td>
              <select name="fetion_send_time_1" id="fetion_send_time_1">
			  	<?php
					$i = 0;
					while($i<=24){
						if( $beginTime==$i ){
							echo "<option value='{$i}' selected='selected'>{$i}</option>\n";
						}else{
							echo "<option value='{$i}'>{$i}</option>\n";
						}
						$i++;
					}
				?>
			  </select>
			  <select name="fetion_send_time_2" id="fetion_send_time_2">
			  	<?php
					$i = $beginTime;
					while($i<=24){
						if( $endTime==$i ){
							echo "<option value='{$i}' selected='selected'>{$i}</option>\n";
						}else{
							echo "<option value='{$i}'>{$i}</option>\n";
						}
						$i++;
					}
				?>
			  </select>请正确设置你所在的时区【<a href="options-general.php#gmt_offset">设置时区</a>】。您博客现在的时间为：<?php  echo current_time('mysql');?>
            </td>
          </tr>
        </table>
        <p class="submit" style="color:#999999">
			<input type="submit" value="保存设置" name="tho_fetion_submit" />
			<?php if( !empty($fetion_pass) ){?>
			&nbsp;&nbsp;&nbsp;&nbsp;<input type="submit" value="测试短息" name="tho_fetion_test" />
			<?php }?>
		</p>
        </form>
      </div>

	 <script type="text/javascript">
		var fetion_user = document.getElementById('fetion_user');
		fetion_user.style.color = '#999999';
		fetion_user.onfocus = function(){	
			fetion_user.style.color = '#000000';
			if( fetion_user.value=='手机号' ){
				fetion_user.value='';
			}
		}
		fetion_user.onblur = function(){
			if( fetion_user.value==''||fetion_user.value=='手机号' ){
				fetion_user.style.color = '#999999';
				fetion_user.value='手机号';
			}
		}
		var send_time = document.getElementById('fetion_send_time_1');
		send_time.onchange = function(){
			var str = '';
			var beginTime = this.value;
			var send_time_2 = document.getElementById('fetion_send_time_2');
			//清空已有的选项
			send_time_2.options.length = 0;
			//添加选项
			for(var i=beginTime; 24>=i; i++){
				var temp = new Option(i, i); 
				send_time_2.options[i-beginTime] = temp;
			}
		}
	</script>
<?php 
}
function  tho_fetion_options_subpanel_2(){
    echo '<div class="wrap">
        <h2 id="write-post">Comments secretary</h2>
		<div id="message" class="updated fade"><p>Dear user,I\'m sorry to tell you, I can only work on the blog his charset is \'utf-8\'.</p></div>
      </div>';
}
?>