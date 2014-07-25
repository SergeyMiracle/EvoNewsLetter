<?php

if(!defined('MODX_BASE_PATH')){die();}

require_once (MODX_BASE_PATH.'assets/modules/evonewsletter/newsletter.class.inc.php');
$class = new newsletter();

$dbname = $modx->db->config['dbase']; // Database name
$table_prefix = $modx->db->config['table_prefix']; // Database table prefix
$config_table = $table_prefix."easynewsletter_config";
$subscribers_table = $table_prefix."easynewsletter_subscribers";
$newsletter_table = $table_prefix."easynewsletter_newsletter";
$queue_table = $table_prefix."easynewsletter_queue";
$display = isset($display) ? intval($display) : 30; // Number rows in table on subscribers page
$maillimit = isset($maillimit) ? intval($maillimit) : 30; // Number of messages sent to refresh manager page.
$unsbscrbPage = 37;
$sendTpl = '';
$_lang = '';

$lang_backend = $modx->db->getValue($modx->db->select( 'lang_backend', $config_table, '`id` = 1' ));
include_once($path.'languages/'.$lang_backend.'.php');
$site_url = $modx->config['site_url'];
$site_name = $modx->config['site_name'];

error_reporting(E_ALL ^ E_NOTICE);
if(!isset($_GET['p'])) { $_GET['p'] = ''; }
if(!isset($_GET['action'])) { $_GET['action'] = 1; }

if (!function_exists('subscribeAlert')) {
  function subscribeAlert($msg){
    global $modx;
    return "<script>window.setTimeout(\"alert('".addslashes($modx->db->escape($msg))."')\",10);</script>";
  }
}

switch($_GET['p']) {

	// List newsletters
	case "1":
		if ($_GET['action'] == 1) {

			if (!isset($_GET['sortorder'])) {
				$sortorder = 'date';
			} else {
				$sortorder = $modx->db->escape($_GET['sortorder']);
			}
			$sql = "SELECT * FROM `$newsletter_table` ORDER BY `".$sortorder."` ASC";
			$result = $modx->db->query($sql);
			$num = mysql_num_rows($result);
			if ($num > 0) {
				$list = '<script type="text/javascript">
				<!--
				function delete_newsletter(a,b)	{
					answer = confirm("'.$lang_newsletter_delete_alert.'\n"+b)
					if (answer !=0)	{
						location = "index.php?a=112&id='.$modId.'&p=1&action=6&nid="+a
					}
				}
				function send_newsletter(a,b) {
					answer = confirm("'.$lang_newsletter_send_alert1.'\n"+b+"\n\n'.$lang_newsletter_send_alert2.'")
					if (answer !=0)	{
						location = "index.php?a=112&id='.$modId.'&p=1&action=2&nid="+a
					}
				} 
				//-->
				</script>';
        $list .= '<ul class="actionButtons">
        						<li><a href="index.php?a=112&id='.$modId.'&p=1&action=3">'.$lang_newsletter_create.'</a></li>
        					</ul>
        					<table class="table table-striped table-bordered table-condensed">
        					<thead><tr>
        						<th><a href="index.php?a=112&id='.$modId.'&p=1&action=1&sortorder=date"><strong>'.$lang_newsletter_date.'</strong></a></th>
        						<th width="50%"><a href="index.php?a=112&id='.$modId.'&p=1&action=1&sortorder=subject"><strong>'.$lang_newsletter_subject.'</strong></a></th>
        						<td><a href="index.php?a=112&id='.$modId.'&p=1&action=1&sortorder=status"><strong>'.$lang_newsletter_status.'</strong></a></td>
        						<td><a href="index.php?a=112&id='.$modId.'&p=1&action=1&sortorder=sent"><strong>'.$lang_newsletter_sent.'</strong></a></td>
        						<th><strong>'.$lang_newsletter_action.'</strong></th>
        					</tr></thead>';
				$i=0;	
				while($i < $num){		
					$countQueue = $modx->db->getValue($modx->db->select("count(*)", $queue_table, "`status` = 0 AND message_id = '".(int)mysql_result($result,$i,"id")."'"));
					$newsStatus = ($countQueue > 0) ? 'in queue '.$countQueue : '&nbsp;-';
					$row = $modx->db->getRow($result);	
					$list .='<tr>
									<td>'.mysql_result($result,$i,"date").'</td>
									<td>'.mysql_result($result,$i,"subject").'</td>
									<td>'.$newsStatus.'</td>
									<td>'.mysql_result($result,$i,"sent").'</td>
									<td>
										<a href="index.php?a=112&id='.$modId.'&p=1&action=3&nid='.mysql_result($result,$i,"id").'">'.$lang_newsletter_edit.'</a> | 
										<a href="index.php?a=112&id='.$modId.'&p=1&action=6&nid='.mysql_result($result,$i,"id").'" onclick=" delete_newsletter(\''.mysql_result($result,$i,"id").'\',\''.mysql_result($result,$i,"subject").'\'); return false;">'.$lang_newsletter_delete.'</a> | 
										<a href="index.php?a=112&id='.$modId.'&p=1&action=7&nid='.mysql_result($result,$i,"id").'">'.$lang_newsletter_testmail.'</a> | 
										<a href="index.php?a=112&id='.$modId.'&p=1&action=2&nid='.mysql_result($result,$i,"id").'" onclick=" send_newsletter(\''.mysql_result($result,$i,"id").'\',\''.mysql_result($result,$i,"subject").'\'); return false;">'.$lang_newsletter_send.'</a>
									</td>
									</tr>';
					$i++;
				}
				$list .= '</table>';
				echo $list;
				
				// echo ($countQueue = $modx->db->getValue($modx->db->query('SELECT count(`recipients`) FROM '.$queue_table.' WHERE status = 0'))) ? '<br>In queue: ' . $countQueue : '<br>Queue is empty';
				
			} else {
				echo $lang_newsletter_noposts.'<ul class="actionButtons"><li><a href="index.php?a=112&id='.$modId.'&p=1&action=3">'.$lang_newsletter_create.'</a></li></ul>';
			}
		} elseif ($_GET['action'] == 2) {
			// Send newsletter 

			$sql = "SELECT * FROM `".$config_table."` WHERE `id` = 1";
			$result = $modx->db->query($sql);
      		$row = $modx->db->getRow($result);
      		$mailmethod = $row['mailmethod']; 
			$smtp = $row['smtp'];
			$fromname = ($row['sendername']!='') ? $row['sendername'] : $modx->config['site_name'];
			$from = ($row['senderemail']!='') ? $row['senderemail'] : $modx->config['emailsender'];;
			$auth = $row['auth'];
			$authuser = $row['authuser'];
			$authpassword = $row['authpassword'];
      		unset($result, $row);

			$nid = (int)$_GET['nid'];
			$sql = "SELECT * FROM `".$newsletter_table."` WHERE `id` = '".$nid."'";
			$result = $modx->db->query($sql);
      		$row = $modx->db->getRow($result);
     		$newsletter_subject = $row['subject'];
      		$newsletter_newsletter = $row['newsletter'];
      		unset($result, $row);

			//include_once "../manager/includes/controls/class.phpmailer.php";
			
			$sql = "SELECT * FROM `".$subscribers_table."` WHERE `blocked`=0";
			$result = $modx->db->query($sql);
			$num = $modx->db->getRecordCount($result);
      
			$i = (isset($_GET['starti'])) ? (int)$_GET['starti'] : 0;
			$sentsuccess=0;
			echo $lang_newsletter_sending;
			while($i < $num){
        // Created unsubscribe link.
        	$unsbscrb = '';
	        if ($unsbscrbPage && (int)$unsbscrbPage>0) {
	          $email = mysql_result($result,$i,"email");
	          $created = mysql_result($result,$i,"created");
	          $control = md5($email.$created);
	          $unsbscrbUrl = $modx->makeUrl($unsbscrbPage,'','&option=unsbscrb&ctr='.$control.'&ml='.urlencode($email), 'full');
	          $unsbscrb = '<p style="font-size: 10px; color: #cccccc;"> Что бы отказаться от получения писем перейдите по <a href="'.$unsbscrbUrl.'" style="font-size: 10px; color: #cccccc;">этой ссылке</a></p>';
	        }
        
				/*
        		$mail->IsHTML(1);
				$mail->From		= $from;
				$mail->FromName	= $fromname;
				$mail->Subject	= $newsletter_subject;
				$mail->Body		= $modx->parseChunk('mail', array( 'body' => $newsletter_newsletter.$unsbscrb, 'subscribe_url'=> $modx->makeUrl(40, '', '', 'full'), 'site_url'=>$modx->getConfig('site_url')), '[+', '+]' );
				$mail->AddAddress(mysql_result($result,$i,"email"));
				if(!$mail->send()) {
					echo $lang_newsletter_sending_done4;
					return 'Main mail: ' . $_lang['ef_mail_error'] . $mail->ErrorInfo;
				} else {
					$sentsuccess++;
				}*/
				$message = $modx->parseChunk('mail', array( 'body' => $newsletter_newsletter.$unsbscrb, 'subscribe_url'=> $modx->makeUrl(37, '', '', 'full'), 'site_url'=>$modx->getConfig('site_url')), '[+', '+]' );;
	            $param = array();
	            $param['from']     = $from;
	            $param['fromname'] = $fromname;
	            $param['subject']  = $newsletter_subject;
	            $param['body']     = $message;
	            $param['to']       = mysql_result($result,$i,"email");
	            if(!$modx->sendmail($param))
	            {
	            	echo $lang_newsletter_sending_done4;
					return 'Main mail: ' . $_lang['ef_mail_error'] . $modx->mail->ErrorInfo;;
	            }else{
	            	$sentsuccess++;
	            }
        		unset ($mail);
				$i++;
        		if ($sentsuccess == $maillimit) {
        			echo $lang_newsletter_sending_done1 . $i . $lang_newsletter_sending_done2 . $num . $lang_newsletter_sending_done3;
          			echo '<meta http-equiv="refresh" content="0; url='.$site_url.'manager/index.php?a=112&id='.$modId.'&p=1&action=2&nid='.$nid.'&starti='.$i.'">';
          			exit;
        		}
			}
			if ($i > 0) {
				$upd = $modx->db->query("UPDATE `".$newsletter_table."` SET `sent` = `sent` + '".$i."' WHERE `id`='".$nid."'");
			}

			echo $lang_newsletter_sending_done1 . $i . $lang_newsletter_sending_done2 . $num . $lang_newsletter_sending_done3;
			
		} elseif ($_GET['action'] == 3) {
			// Newsletter Rich Text Editor
			$action = 4;
			$nid = $newsletter = $subject = '';
			
			$nid=(isset($_GET['nid']) && (int)$_GET['nid']>0)?(int)$_GET['nid']:0;
			if ($nid > 0) {
				$sql = "SELECT * FROM `$newsletter_table` WHERE `id` = $nid";
				$result = $modx->db->query($sql);
        $row = $modx->db->getRow($result);
        $subject = $row['subject'];
        $newsletter = $row['newsletter'];
				$action = 5;
			}
			
			echo '<div class="content_">
					<h3>'.$lang_newsletter_edit_header.'</h3>
					<form action="index.php?a=112&id='.$modId.'&p=1&action='.$action.'" method="post"><b>
					'.$lang_newsletter_edit_subject.'</b><br /><input type="hidden" name="xid" value="'.$nid.'"><input type="text" size="50" maxlength="50" name="subject" value="'.$subject.'"></input><br /><br /><b>'.$lang_newsletter_edit_content.'</b>';
			
			// Get access to template variable function (to output the RTE)
			include_once($modx->config['base_path'].'manager/includes/tmplvars.inc.php');
		  
			$event_output = $modx->invokeEvent("OnRichTextEditorInit", array('editor'=>$modx->config['which_editor'], 'elements'=>array('tvmailMessage')));
		
			if(is_array($event_output)) {
				$editor_html = implode("",$event_output);
			}
			// Get HTML for the textarea, last parameters are default_value, elements, value
			$rte_html = renderFormElement('richtext', 'mailMessage', '', '', $newsletter);
			
			echo $rte_html;
			
			echo $editor_html;
			echo  '<br />
			      <ul class="actionButtons">
			          <li><input type="submit" value="'.$lang_newsletter_edit_save.'" class="inputbutton"></input></li>
			          <li><a href="index.php?a=112&id='.$modId.'&p=1&action=1">Отмена</a></li>
			          </ul>
						</div>';
	} elseif ($_GET['action'] == 4) {
		// insert correct path for images
		$testo = str_replace('src="assets/images/','src="'.$site_url.'assets/images/',$_POST['tvmailMessage']);
    $testo = $modx->db->escape($testo);
		// Insert newsletter into database
		$sql = "INSERT INTO $newsletter_table VALUES('', now(), '','', '', '".$modx->db->escape($_POST['subject'])."', '".$testo."', '') ";
		$result = $modx->db->query($sql);
		echo '<h3>'.$lang_newsletter_edit_create.'<h3>';
    echo '<ul class="actionButtons">
          <li><a href="index.php?a=112&id='.$modId.'&p=1&action=1">К списку новостей</a></li>
          </ul>';
	} elseif ($_GET['action'] == 5) {
		// Update existing newsletter
				// insert correct path for images
		$testo = str_replace('src="assets/images/','src="'.$site_url.'assets/images/',$_POST['tvmailMessage']);
    $testo = $modx->db->escape($testo);
		$sql = "UPDATE $newsletter_table SET subject='".$modx->db->escape($_POST['subject'])."', newsletter='".$testo."' WHERE id='".(int)$_POST['xid']."'";
		$result = $modx->db->query($sql);
		echo '<h3>'.$lang_newsletter_edit_update.'<h3>';
    echo '<ul class="actionButtons">
          <li><a href="index.php?a=112&id='.$modId.'&p=1&action=1">&nbsp; К списку новостей</a></li>
          </ul>';
	} elseif ($_GET['action'] == 6) {
		// Delete newsletter
		$sql = "DELETE FROM $newsletter_table WHERE id='".(int)$_GET['nid']."'";
		$result = $modx->db->query($sql);
		$modx->db->delete($queue_table, 'message_id = "'.(int)$_GET['nid'].'"');
		echo '<h3>'.$lang_newsletter_edit_delete.'<h3>';
    echo '<ul class="actionButtons">
          <li><a href="index.php?a=112&id='.$modId.'&p=1&action=1">&nbsp;К списку новостей</a></li>
          </ul>';
    echo '<meta http-equiv="refresh" content="2; url='.$site_url.'manager/index.php?a=112&id='.$modId.'&p=1&action=1">';
		} elseif ($_GET['action'] == 7) {
			// Send test newsletter
			$nid = (int)$_GET['nid'];
			$sql = "SELECT * FROM `$newsletter_table` WHERE `id` = $nid";
			$result = $modx->db->query($sql);
      $row = $modx->db->getRow($result);
      $newsletter_header = $row['header'];
      $newsletter_subject = $row['subject'];
      $newsletter_newsletter = $row['newsletter'];
      $newsletter_footer = $row['footer'];
			
			$sql = "SELECT * FROM `$config_table` WHERE `id` = 1";
			$result = $modx->db->query($sql);
      $row = $modx->db->getRow($result);

      $mailmethod = $row['mailmethod']; 
			$smtp = $row['smtp'];
			$fromname = $row['sendername'];
			$from = $row['senderemail'];
			$auth = $row['auth'];
			$authuser = $row['authuser'];
			$authpassword = $row['authpassword'];
			
			include_once "../manager/includes/controls/class.phpmailer.php";
			$sql = "SELECT * FROM `$subscribers_table`";
			$result = $modx->db->query($sql);
			$num = mysql_num_rows($result);

			$mail = new PHPMailer();
			if ($mailmethod == 'IsMail') {$mail->IsMail();}
			if ($mailmethod == 'IsSMTP') {
				$mail->IsSMTP();
				$mail->Host = $smtp;
				if ($auth == 'true') {
					$mail->SMTPAuth = true;
					$mail->Username = $authuser;
					$mail->Password = $authpassword;
				} else {
					$mail->SMTPAuth = false;
				}
			}
			if ($mailmethod == 'IsSMTP') {$mail->Host = $smtp;}
			if ($mailmethod == 'IsSendmail') {$mail->IsSendmail();}
			if ($mailmethod == 'IsQmail') {$mail->IsQmail();}
			$mail->CharSet = $modx->config['modx_charset'];
      $mail->IsHTML(1);
			$mail->From		= $from;
			$mail->FromName	= $fromname;
			$mail->Subject	= $newsletter_subject;
			$mail->Body		= $modx->parseChunk('mail', array( 'body' => $newsletter_newsletter, 'subscribe_url'=> $modx->makeUrl(40, '', '', 'full'), 'site_url'=>$modx->getConfig('site_url')), '[+', '+]' );
			$mail->AddAddress($from);
			if(!$mail->send()) {
				echo $lang_newsletter_sending_done4;
				return 'Main mail: ' . $_lang['ef_mail_error'] . $mail->ErrorInfo;
			} else echo $lang_newsletter_sending_done5;
      echo '<ul class="actionButtons">
          <li><a href="index.php?a=112&id='.$modId.'&p=1&action=1">&nbsp; К списку новостей</a></li>
          </ul>';
		} elseif ($_GET['action'] == 8) {
			// Send news to queue

			$nid = (int)$_GET['nid'];
			$result = $class->send_to_queue($nid, true);

			echo '<p>Поставлено в очередь '.$result.' писем.</p>';
			echo '<ul class="actionButtons">
          <li><a href="index.php?a=112&id='.$modId.'&p=1&action=1">К списку новостей</a></li>
          </ul>';

			
		} 
		elseif ($_GET['action'] == 9) {
			// Send group newsletter in queue
			$nid = (int)$_GET['nid'];
			$sql = "SELECT * FROM `$newsletter_table` WHERE `id` = $nid";
			$result = $modx->db->query($sql);
      $row = $modx->db->getRow($result);
			$newsletter_header = $row['header'];
			$newsletter_subject = $row['subject'];
			$newsletter_newsletter = $row['newsletter'];
			$newsletter_footer = $row['footer'];

			$group = $_GET['group'];

			if ($group == 'all') {
				$result = $modx->db->query("SELECT `email`, `created` FROM `".$subscribers_table."` ");
			} elseif ((int)$group > 0) {
				$result = $modx->db->query(
					"SELECT `user`.`email`, `user`.`created` 
					 FROM `".$subscribers_table."` `user`, `".$groups_table."` `group` 
					 WHERE `user`.`id`=`group`.`subscriber`
					 AND `group`.`webgroup`='".(int)$group."'");
				if ($modx->db->getRecordCount($result) < 1) {
					echo 'No subscribers in the selected group. Messages will not be sent.';
					return;
				}
			} else {
				echo 'A group name is not valid. Messages will not be sent.';
				return;
			}
			// $result = $modx->db->query('SELECT `email`, `created` FROM `'.$subscribers_table.'` ');
			$countIns = 0;
			while ($row = $modx->db->getRow($result)) {
				if (trim($row['email']) != '') {
					// Created unsubscribe link.
        	$control = '';
	        if ($unsbscrbPage && (int)$unsbscrbPage>0) {
	          $email = $row['email'];
	          $created = $row['created'];
	          $control = md5($email.$created);
	        }
					$fields = array(
              'recipients'	=> trim($row['email']), 
              'message_id'	=> $nid, 
              'create_time'	=> time(),
              'control'			=> $control
            ); 
          $modx->db->insert($fields, $queue_table);
          $countIns++;
				}
			}
			echo '<p>Поставлено в очередь '.$countIns.' писем "'.$newsletter_subject.'".</p>';
			echo '<ul class="actionButtons">
          <li><a href="index.php?a=112&id='.$modId.'&p=1&action=1">К списку новостей</a></li>
          </ul>';
		}

	break;
	case "2":
		if ($_GET['action'] == 1) {
			// Show Configuration
			$sql = "SELECT *  FROM `$config_table` WHERE `id` = 1";
			$result = $modx->db->query($sql);
      $i=0;
			$mailmethod = mysql_result($result,$i,"mailmethod");
			$auth = mysql_result($result,$i,"auth");
			$list = '<div class="content_">
					<h3>'.$lang_config_header.'</h3>
					<form action="index.php?a=112&id='.$modId.'&p=2&action=2" method="post"><b>';
			$list .= '<table class="table table-striped table-bordered table-condensed">';
			
			$list .= '<tr><td><strong>'.$lang_config_sendername.'</strong>:</td><td> <input type="text" size="100" maxlength="100" name="sendername" value="'.stripslashes(mysql_result($result,$i,"sendername")).'"></input></td></tr>';
			$list .= '<tr><td>&nbsp;</td><td>&nbsp;&nbsp;'.$lang_config_sendername_description.'</td></tr>';
			$list .= '<tr><td><strong>'.$lang_config_senderemail.'</strong>:</td><td> <input type="text" size="100" maxlength="100" name="senderemail" value="'.mysql_result($result,$i,"senderemail").'"></input></td></tr>';
			$list .= '<tr><td>&nbsp;</td><td>&nbsp;&nbsp;'.$lang_config_senderemail_description.'</td></tr>';
			
			$list .= '<tr><td><strong>'.$lang_config_mail.'</strong>:</td><td> <select name="mailmethod">';

			if($mailmethod == 'IsMail'){$dropdown = ' selected="selected"';} else {$dropdown = '';}
			$list .= '<option value="IsMail"'.$dropdown.'>PHP mail</option>';

			if($mailmethod == 'IsSMTP'){$dropdown = ' selected="selected"';} else {$dropdown = '';}
			$list .= '<option value="IsSMTP"'.$dropdown.'>SMTP</option>';

			if($mailmethod == 'IsSendmail'){$dropdown = ' selected="selected"';} else {$dropdown = '';}
			$list .= '<option value="IsSendmail"'.$dropdown.'>Sendmail</option>';

			if($mailmethod == 'IsQmail'){$dropdown = ' selected="selected"';} else {$dropdown = '';}
			$list .= '<option value="IsQmail"'.$dropdown.'>Qmail MTA</option>';
	
			$list .= '</select></td></tr>';
			$list .= '<tr><td>&nbsp;</td><td>&nbsp;&nbsp;'.$lang_config_mail_description.'</td></tr>';

			$list .= '<tr><td><strong>'.$lang_config_auth.'</strong>:</td><td> <select name="auth">';

			if($auth == 'true'){$dropdown3 = ' selected="selected"';} else {$dropdown3 = '';}
			$list .= '<option value="true"'.$dropdown3.'>'.$lang_config_true.'</option>';

			if($auth == 'false'){$dropdown3 = ' selected="selected"';} else {$dropdown3 = '';}
			$list .= '<option value="false"'.$dropdown3.'>'.$lang_config_false.'</option>';
			
			$list .= '</select></td></tr>';
			$list .= '<tr><td>&nbsp;</td><td>&nbsp;&nbsp;'.$lang_config_auth_description.'</td></tr>';

			$list .= '<tr><td><strong>'.$lang_config_smtp.'</strong>:</td><td> <input type="text" size="100" maxlength="100" name="smtp" value="'.mysql_result($result,$i,"smtp").'"></input></td></tr>';
			$list .= '<tr><td>&nbsp;</td><td>&nbsp;&nbsp;'.$lang_config_smtp_description.'</td></tr>';
			$list .= '<tr><td><strong>'.$lang_config_authuser.'</strong>:</td><td> <input type="text" size="100" maxlength="100" name="authuser" value="'.mysql_result($result,$i,"authuser").'"></input></td></tr>';
			$list .= '<tr><td>&nbsp;</td><td>&nbsp;&nbsp;'.$lang_config_authuser_description.'</td></tr>';
			$list .= '<tr><td><strong>'.$lang_config_authpassword.'</strong>:</td><td> <input type="password" size="100" maxlength="100" name="authpassword" value="'.mysql_result($result,$i,"authpassword").'"></input></td></tr>';
			$list .= '<tr><td>&nbsp;</td><td>&nbsp;&nbsp;'.$lang_config_authpassword_description.'</td></tr>';
// -------------------------------------------------		
			$list .= '<tr><td><strong>'.$lang_config_lang_website.'</strong>:</td><td> <select name="lang_frontend">';
			if(mysql_result($result,$i,"lang_frontend") == 'english'){$dropdown2 = ' selected="selected"';} else {$dropdown2 = '';}
			$list .= '<option value="english"'.$dropdown2.'>English</option>';
			if(mysql_result($result,$i,"lang_frontend") == 'russian'){$dropdown2 = ' selected="selected"';} else {$dropdown2 = '';}
			$list .= '<option value="russian"'.$dropdown2.'>Русский</option>';
			if(mysql_result($result,$i,"lang_frontend") == 'danish'){$dropdown2 = ' selected="selected"';} else {$dropdown2 = '';}
			$list .= '<option value="danish"'.$dropdown2.'>Dansk</option>';
			if(mysql_result($result,$i,"lang_frontend") == 'italian'){$dropdown2 = ' selected="selected"';} else {$dropdown2 = '';}
			$list .= '<option value="italian"'.$dropdown2.'>Italiano</option>';
			if(mysql_result($result,$i,"lang_frontend") == 'german'){$dropdown2 = ' selected="selected"';} else {$dropdown2 = '';}
			$list .= '<option value="german"'.$dropdown2.'>Deutsch</option>';
			$list .= '</select></td></tr>';
			$list .= '<tr><td>&nbsp;</td><td>&nbsp;&nbsp;'.$lang_config_lang_website_description.'</td></tr>';

			$list .= '<tr><td><strong>'.$lang_config_lang_manager.'</strong>:</td><td> <select name="lang_backend">';			
			if(mysql_result($result,$i,"lang_backend") == 'english'){$dropdown2 = ' selected="selected"';} else {$dropdown2 = '';}
			$list .= '<option value="english"'.$dropdown2.'>English</option>';
			if(mysql_result($result,$i,"lang_backend") == 'russian'){$dropdown2 = ' selected="selected"';} else {$dropdown2 = '';}
			$list .= '<option value="russian"'.$dropdown2.'>Русский</option>';
			if(mysql_result($result,$i,"lang_backend") == 'danish'){$dropdown2 = ' selected="selected"';} else {$dropdown2 = '';}
			$list .= '<option value="danish"'.$dropdown2.'>Dansk</option>';
			if(mysql_result($result,$i,"lang_backend") == 'italian'){$dropdown2 = ' selected="selected"';} else {$dropdown2 = '';}
			$list .= '<option value="italian"'.$dropdown2.'>Italiano</option>';
			if(mysql_result($result,$i,"lang_backend") == 'german'){$dropdown2 = ' selected="selected"';} else {$dropdown2 = '';}
			$list .= '<option value="german"'.$dropdown2.'>Deutsch</option>';
			$list .= '</select></td></tr>';
			$list .= '<tr><td>&nbsp;</td><td>&nbsp;&nbsp;'.$lang_config_lang_manager_description.'</td></tr>';
// -------------------------------------------------
			$list .= '</table>';
			$list .= '<ul class="actionButtons"><li><input type="submit" value="'.$lang_config_save.'" class="inputbutton"></input></li></ul>';
			echo $list;
		} elseif ($_GET['action'] == 2) {
			// Update configuration
			$sql = "UPDATE $config_table 
							SET mailmethod='".$modx->db->escape($_POST['mailmethod'])."', 
									smtp='".$modx->db->escape($_POST['smtp'])."', 
									auth='".$modx->db->escape($_POST['auth'])."', 
									authuser='".$modx->db->escape($_POST['authuser'])."', 
									authpassword='".$_POST['authpassword']."', 
									sendername='".addslashes($_POST['sendername'])."', 
									senderemail='".$modx->db->escape($_POST['senderemail'])."', 
									lang_frontend='".$modx->db->escape($_POST['lang_frontend'])."', 
									lang_backend='".$modx->db->escape($_POST['lang_backend'])."' 
							WHERE id='1'";	
			$result = $modx->db->query($sql);
			echo $lang_config_update;	
		}
	break;	
	
	default:
		if ($_GET['action'] == 1) {
			// List subscribers
      
      if (!isset($_GET['sortorder'])) {
				$sortorder = 'id';
			} else {
				$sortorder = $_GET['sortorder'];
			}
      
      // Pagination
      $page = ( isset($_GET['page'])  && (int)$_GET['page'] > 0 ) ? (int)$_GET['page'] : 1;
      $rescount = $modx->db->getValue( $modx->db->select( 'count(*)', $subscribers_table ) );
      $totalPages = ceil($rescount / $display);
      if ($totalPages < 1) $totalPages = 1;
      if ($page > $totalPages) $page = $totalPages;
      $start = $page * $display - $display;
      
      if ($totalPages > 1) {
        $pagestext = $lang_subscriber_pages1 . $page . $lang_subscriber_pages2 . $totalPages;
        $pages = '<p>'.$pagestext.'</p>';
        $pages .= '<ul class="actionButtons">';
        if ($page == 1) {
          $pages .= '
          <li><a href="index.php?a=112&id='.$modId.'&action=1&sortorder='.$sortorder.'&page='.($page+1).'" class="next">Далее &gt;</a></li>
          ';
        }	else if ($page == $totalPages) {
          $pages .= '
          <li><a href="index.php?a=112&id='.$modId.'&action=1&sortorder='.$sortorder.'&page='.($page-1).'" rel="nofollow">&lt; Назад</a></li>
          ';
          } 	else {
            $pages .= '
                  <li><a href="index.php?a=112&id='.$modId.'&action=1&sortorder='.$sortorder.'&page='.($page-1).'"  rel="nofollow">&lt; Назад</a></li>
                  <li><a href="index.php?a=112&id='.$modId.'&action=1&sortorder='.$sortorder.'&page='.($page+1).'" class="next">Далее &gt;</a></li>
                ';
            }
        $pages .= '</ul>';
      } else $pages = '';
      
      $result = $modx->db->select('*', $subscribers_table, '', $sortorder. ' ASC', $start.','.$display);
      $num = $modx->db->getRecordCount($result);

			if ($num > 0) {
			$list = '<script type="text/javascript">
			<!--
			function delete_subscriber(a,b,c,d)
			{
			answer = confirm("'.$lang_subscriber_delete_alert.'\n"+b+" "+c+" - "+d)
			if (answer !=0)
				{
				location = "index.php?a=112&id='.$modId.'&action=4&nid="+a
				}
				}
				//-->
				</script>';
      $list .= '<ul class="actionButtons"><li><a href="index.php?a=112&id='.$modId.'&action=5">'.$lang_subscriber_new_add.'</a></li></ul>
       					<table class="table table-striped table-bordered table-condensed">
       					<thead><tr>
       					<th><a href="index.php?a=112&id='.$modId.'&action=1&sortorder=id"> # </a></th>
       					<th><a href="index.php?a=112&id='.$modId.'&action=1&sortorder=firstname">'.$lang_subscriber_firstname.'</a></th>
       					<th><a href="index.php?a=112&id='.$modId.'&action=1&sortorder=lastname">'.$lang_subscriber_lastname.'</a></th>
       					<th><a href="index.php?a=112&id='.$modId.'&action=1&sortorder=email">'.$lang_subscriber_email.'</a></th>
       					<th></th>
       					<th><a href="index.php?a=112&id='.$modId.'&action=1&sortorder=created">'.$lang_subscriber_created.'</a></th>
       					<th>'.$lang_subscriber_action.'</td>
       					</tr></thead>';
				$i=0;	
				while($i < $num){		
					//$row = $modx->db->getRow($result);
					$blockedIcon = (mysql_result($result,$i,"blocked")==1) ? '<img src="media/style/MODxCarbon/images/icons/delete.png" title="Заблокирован">' : '<img src="media/style/MODxCarbon/images/icons/save.png" title="Активен">';
					$list .=	'<tbody><tr>
										<td>'.mysql_result($result,$i,"id").'</td>
										<td>'.mysql_result($result,$i,"firstname").'</td>
										<td>'.mysql_result($result,$i,"lastname").'</td>
										<td>'.mysql_result($result,$i,"email").'</td>
										<td align="center">'.$blockedIcon.'</td>
										<td>'.mysql_result($result,$i,"created").'</td>
										<td><a href="index.php?a=112&id='.$modId.'&action=2&nid='.mysql_result($result,$i,"id").'">'.$lang_newsletter_edit.'</a> | 
												<a href="index.php?a=112&id='.$modId.'&action=4&nid='.mysql_result($result,$i,"id").'" onclick=" delete_subscriber(\''.mysql_result($result,$i,"id").'\',\''.mysql_result($result,$i,"firstname").'\',\''.mysql_result($result,$i,"lastname").'\',\''.mysql_result($result,$i,"email").'\'); return false;">'.$lang_newsletter_delete.'</a></td>';
					$list .= '</tr>';
					$i++;
				}
				$list .= '</tbody></table>';
				echo $list;
        echo '<p>' . $lang_subscriber_all . $rescount . '</p>';
        echo $pages;
			} else {
				echo $lang_subscriber_noposts;
        echo '<ul class="actionButtons"><li><a href="index.php?a=112&id='.$modId.'&action=5">'.$lang_subscriber_new_add.'</a></li></ul>';
			}
		} elseif ($_GET['action'] == 2) {
			// Update existing subscriber form
			$sql = "SELECT * FROM `$subscribers_table` WHERE id = '".$_GET['nid']."'";
			$result = $modx->db->query($sql);
			$blocked = mysql_result($result,$i,"blocked")==1 ? ' checked="checked"' : '';
      $i = 0;
			echo '<div class="content_">
					<h3>'.$lang_subscriber_edit_header.'</h3>
					<form action="index.php?a=112&id='.$modId.'&action=3&nid='.$_GET['nid'].'" method="post">
					<b>'.$lang_subscriber_firstname.'</b><br /><input type="text" size="50" maxlength="50" name="firstname" value="'.mysql_result($result,$i,"firstname").'"><br />
					<b>'.$lang_subscriber_lastname.'</b><br /><input type="text" size="50" maxlength="50" name="lastname" value="'.mysql_result($result,$i,"lastname").'"><br />
					<b>'.$lang_subscriber_email.'</b><br /><input type="text" size="50" maxlength="50" name="email" value="'.mysql_result($result,$i,"email").'"><br /><br />
					<div>
					<b>'.$lang_subscriber_blocked.'</b> <input type="checkbox" name="blocked" '.$blocked.'><br /><br />
					</div>
					<ul class="actionButtons">
          <li><input type="submit" value="'.$lang_subscriber_edit_save.'" class="inputbutton"></input></li>
          <li><a href="index.php?a=112&id='.$modId.'&action=1">Назад</a></li>
          </ul>
          </div>';
          
		} elseif ($_GET['action'] == 3) {
			// Update existing subscriber
			$blocked = ($_POST['blocked'] == 'on') ? 1 : 0;
			$sql = "UPDATE $subscribers_table SET 
							firstname='".$modx->db->escape($_POST['firstname'])."', 
							lastname='".$modx->db->escape($_POST['lastname'])."', 
							email='".$modx->db->escape($_POST['email'])."',
							blocked='".$blocked."' 
							WHERE id='".(int)$_GET['nid']."'";
			$result = $modx->db->query($sql);
			//$class->set_user_groups($_GET['nid'], $_POST['user_groups']);
			echo '<h3>'.$lang_subscriber_edit_update.'<h3>';
      echo '<ul class="actionButtons">
          <li><a href="index.php?a=112&id='.$modId.'&action=1">К списку подписчиков</a></li>
          </ul>';
		} elseif ($_GET['action'] == 4) {
			// Delete subscriber
			$sql = "DELETE FROM $subscribers_table WHERE id='".(int)$_GET['nid']."'";
			$result = $modx->db->query($sql);

			/*$sql = "DELETE FROM `".$groups_table."` WHERE `subscriber`=".(int)$_GET['nid'].";";
			$rs = $modx->db->query($sql);
			if(!$rs) {
				echo "Something went wrong while trying to delete the web user's access permissions...";
				return;
			}*/
			echo '<h3>'.$lang_subscriber_edit_delete.'<h3>';
      echo '<ul class="actionButtons">
          <li><a href="index.php?a=112&id='.$modId.'&action=1">К списку подписчиков</a></li>
          </ul>';
		} elseif ($_GET['action'] == 5) {
      // Add subscriber
      $msg = $email = $firstname = $lastname = '';    
       
      if (isset($_POST['subscribe'])){
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) ? $modx->db->escape($_POST['email']) : '';
        $firstname = isset($_POST['firstname']) ? $modx->db->escape($_POST['firstname']) : '';
        $lastname = isset($_POST['lastname']) ? $modx->db->escape($_POST['lastname']) : '';
        $date = date("Y-m-d"); 
        
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
            $msg = $lang_subscriber_edit_update;
          } else {
              $msg = $lang_alreadysubscribed;
            }
        } else {
            $msg = $lang_notvalidemail;
          }
      }
      
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
      $list .=  '<div class="content_">
					<h3>'.$lang_subscriber_new_header.'</h3>
					<form action="index.php?a=112&id='.$modId.'&action=5" method="post">
					<b>'.$lang_subscriber_firstname.'</b><br /><input type="text" size="50" maxlength="50" name="firstname" value="'.$firstname.'"><br />
					<b>'.$lang_subscriber_lastname.'</b><br /><input type="text" size="50" maxlength="50" name="lastname" value="'.$lastname.'"><br />
					<b>'.$lang_subscriber_email.'</b><br /><input type="text" size="50" maxlength="50" name="email" value="'.$email.'"><br /><br />
					<ul class="actionButtons">
          <li><input type="submit" value="'.$lang_subscriber_edit_save.'" class="inputbutton" name="subscribe"></li>
          <li><a href="index.php?a=112&id='.$modId.'&action=1">Назад</a></li>
          </ul>
          </div>';
          
      echo $list;
      if ($msg) echo subscribeAlert($msg);
    }
}
?>
