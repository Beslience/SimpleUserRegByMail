<?php
/**
 * Created by PhpStorm.
 * User: zhengjiayuan
 * Date: 2018/7/15
 * Time: 13:19
 */

header('content-type:text/html;charset=utf-8');
// 1.包含所需文件
require_once 'swiftmailer-master/lib/swift_required.php';
require_once 'PdoMySQL.class.php';
require_once 'config.php';
require_once 'pwd.php';
// 2.接收信息
$act=$_GET['act'];
$username = @addslashes($_POST['username']);
$password = @md5($_POST['password']);
$email = @$_POST['email'];
$table='user';
// 3.得到连接对象
$PdoMySQL = new PdoMySQL();
if ($act==='reg'){
    // 完成注册的功能
    $regtime = time();
    $token = md5($username.$password.$regtime);
    $token_exptime = $regtime + 24 * 3600; // 过期时间
    // compact() 函数创建包含变量名和它们的值的数组。
    $data = compact('username','password','email','token','token_exptime','regtime');
    $res = $PdoMySQL->add($data,$table);
    $lastInsertId = $PdoMySQL->getLastInsertId();
    if ($res){
        // 发送邮件,以QQ邮箱为例
        // 配置邮件服务器，得到传输对象
        $transport = Swift_SmtpTransport::newInstance('smtp.qq.com',25);
        // 设置登录账号和密码
        $transport->setUsername('2693587758@qq.com');
        $transport->setPassword($emailPassword);
        // 得到发送邮件对象Swift_Mailer对象
        $mailer = Swift_Mailer::newInstance($transport);
        // 得到邮件信息对象
        $message = Swift_Message::newInstance();
        // 设置管理员的信息
        $message->setFrom(array('2693587758@qq.com'=>'King'));
        // 将邮件发给谁
        $message->setTo(array($email=>'imooc'));
        // 设置邮件主题
        $message->setSubject('激活邮件');
        $url = "http://".$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']."?act=active&token={$token}";
        $urlencode=urldecode($url);
        $str=<<<EOF
        亲爱的{$username}你好~！感谢您注册我们的网站<br/> 
        请点击此链接激活账号即可登录! <br/>
        <a href="{$url}">{$urlencode}</a>
        <br/>
        如果点此链接无反应，可以将其复制到浏览器中来执行，链接的有效时间为24小时。 
EOF;
        $message->setBody("{$str}",'text/html','utf-8');
        try{
            if ($mailer->send($message)){
                echo "恭喜您{$username}注册成功，请到邮箱激活之后登录<br/>";
                echo "3秒后跳转到登录页面";
                echo '<meta http-equiv="refresh" content="3;url=index.php#tologin" />';
            }else{
                $PdoMySQL->delete($table,'id='.$lastInsertId);
                echo "3秒后跳转到注册页面";
                echo '<meta http-equiv="refresh" content="3;url=index.php#toregister" />';
            }
        }catch(Exception $e){
            echo '邮件发送错误'.$e->getMessage();
        }
    }else {
        echo '用户注册失败，3秒钟后跳转到注册页面';
        exit();
        echo '<meta http-equiv="refresh" content="3;url=index.php#toregister" />';
    }

}elseif($act==='login'){
    // 登录账号
    $row = $PdoMySQL->find($table,"username='{$username}' and password='{$password}'",'status');
    if (@$row['status']==0){
        echo "请先激活再登录";
        echo '<meta http-equiv="refresh" content="3;url=index.php#tologin" />';
    }else{
        echo "登录成功，3秒钟跳转到首页";
        echo '<meta http-equiv="refresh" content="3;url=index.php#http://www.imooc.com" />';
    }
}elseif($act==='active'){
    // 激活账号

    $token = addslashes($_GET['token']);
    $row = $PdoMySQL->find($table,"token='{$token}' AND status = 0",array('id','token_exptime'));
    $now = time();
    if ($now > $row['token_exptime']){
        echo '激活时间过期,请重新注册';
    }else{
        $res = $PdoMySQL->update(array('status'=>1),$table,'id='.$row['id']);
        if ($res){
            echo "激活成功，3秒钟后跳转到登录页面";
            echo '<meta http-equiv="refresh" content="3;url=index.php#tologin" />';
        }else{
            echo '激活失败,请重新激活';
            echo '<meta http-equiv="refresh" content="3;url=index.php#toregister" />';
        }
    }
}