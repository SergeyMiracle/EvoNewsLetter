<?php
/**
* Evo Newsletter 0.3
* snippet
*/

if(!defined('MODX_MANAGER_PATH')) die();

$table_prefix = $modx->db->config['table_prefix'];
$config_table = $table_prefix."easynewsletter_config";
$subscribers_table = $table_prefix."easynewsletter_subscribers";
$lang = $modx->db->getValue($modx->db->select('lang_frontend', $config_table, '`id` = 1'));

include_once('assets/modules/evonewsletter/languages/'.$lang.'.php');

$subscribeTpl = isset($subscribeTpl) ? $subscribeTpl : '';
$tplFields = array();

$list = '
	<script type="text/javascript">
	function validate_email(field,alerttxt)	{	
    with (field){
      apos=value.indexOf("@")
      dotpos=value.lastIndexOf(".")
      if (apos<1||dotpos-apos<2) 
        {alert(alerttxt);return false}
      else {return true}
    }
	}
	function validate_form(thisform){
    with (thisform)	{
      if (validate_email(email,"'.$lang_notvalidemail.'")==false)
        {email.focus();return false}
    }
	}
	</script>';
$list .= '<h3 class="title">'.$lang_mailinglist.'</h3>
				<form onsubmit="return validate_form(this);" action="/[~[*id*]~]" method="post" class="subscribe">
				
          <div>
					<label>'.$lang_firstname.'</label> 
          <input type="text" name="firstname" maxlength="50" value="'.$firstname.'">
          </div>
          <div>
					<label>'.$lang_email.'<sup>*</sup></label> 
          <input type="text" name="email" value="'.$email.'">
          </div>
          <div class="options">
          <input type="radio" name="option" value="subscribe" id="optsbscrb" checked><label for="optsbscrb">Подписаться</label> &nbsp;&nbsp;
          <input type="radio" name="option" value="unsubscribe" id="optunsbscrb"><label for="optunsbscrb">Отписаться</label>
          </div>

          <input class="button" type="submit" value="'.$lang_submit.'">
				
			</form>';

if (!function_exists('subscribeAlert')) {
  function subscribeAlert($msg){
    global $modx;
    return "<script>window.setTimeout(\"alert('".addslashes($modx->db->escape($msg))."')\",10);</script>";
  }
}
      
$action = $_REQUEST['option'];

switch($action) {

case "subscribe":
  $firstname = isset($_POST['firstname']) ? $modx->db->escape($modx->stripTags($_POST['firstname'])) : '';
  $lastname = isset($_POST['lastname']) ? $modx->db->escape($modx->stripTags($_POST['lastname'])) : '';
  $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) ? $modx->db->escape($modx->stripTags($_POST['email'])) : '';
  $date = date("Y-m-d");
  
  $tplFields['firstname'] = $firstname;
  $tplFields['lastname'] = $lastname;
  $tplFields['email'] = $email;
  
  if ($email) {
    $num = $modx->db->getRecordCount($modx->db->select('id', $subscribers_table, "email = '$email'"));
    if ($num < 1) {
      $fields = array(
        'firstname'	=> $firstname, 
        'lastname'	=> $lastname, 
        'email'	=> $email,
        'created' => $date
			); 
      $modx->db->insert($fields, $subscribers_table);
      unset ($firstname, $lastname, $email);
      $msg = $lang_subscribesuccess;
    } else {
        $msg = $lang_alreadysubscribed;
      }
  } else {
      $msg = $lang_notvalidemail;
    }
	break;
	
case "unsubscribe":
  $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) ? $modx->db->escape($modx->stripTags($_POST['email'])) : '';

  $tplFields['firstname'] = $firstname;
  $tplFields['lastname'] = $lastname;
  $tplFields['email'] = $email;
  
	if ($email) {
    $num = $modx->db->getRecordCount($modx->db->select('id', $subscribers_table, "email = '$email'"));
    if ($num < 1) {
      echo subscribeAlert($lang_notsubscribed);
    } else {
        $modx->db->delete($subscribers_table, "email = '$email'");
        echo '<span class="msg">'.$lang_unsubscribesuccess.'</span>';
        unset ($firstname, $lastname, $email);
      }
  } else {
      echo subscribeAlert($lang_notvalidemail);
    }
    
	break;
  case "unsbscrb":
  if ( isset($_REQUEST['ctr']) && trim($_REQUEST['ctr']) != '' && isset($_REQUEST['ml'])){
    $email = filter_var(urldecode($_REQUEST['ml']), FILTER_VALIDATE_EMAIL) ? $modx->db->escape(urldecode($_REQUEST['ml'])) : '';
    $control = $modx->db->escape(trim($_REQUEST['ctr']));
    $result = $modx->db->select('*', $subscribers_table, 'email="'.$email.'"');
    $cnt = $modx->db->getRecordCount($result);
    if ($cnt != 1) {
      $err = $lang_unsubscribeerror1;
    } else {
        $row = $modx->db->getRow($result);
        $created = $row['created'];
        $thisControl = md5($email.$created);
        if ($thisControl != $control) {
          $err = $lang_unsubscribeerror2;
        } else {
            $modx->db->delete($subscribers_table, "email = '$email'");
            $msg = $lang_unsbscrb1 . $email . $lang_unsbscrb2;
          }
      }
  }
  
  break;
}

if ($subscribeTpl) {
  echo $modx->parseChunk($subscribeTpl, $tplFields, '[+', '+]');
} else echo $list;
if ($msg) echo subscribeAlert($msg);
?>