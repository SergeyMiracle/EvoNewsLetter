$dbname = $modx->db->config['dbase']; // Database name
$table_prefix = $modx->db->config['table_prefix']; // Database table prefix
$config_table = $table_prefix."easynewsletter_config";
$subscribers_table = $table_prefix."easynewsletter_subscribers";
$newsletter_table = $table_prefix."easynewsletter_newsletter";
$queue_table = $table_prefix."easynewsletter_queue";
$groups_table = $table_prefix."easynewsletter_groups";

$sql = "SHOW TABLES LIKE '$config_table'";
$rs = $modx->db->query($sql);
$count = $modx->db->getRecordCount($rs);

if($count < 1) {
  $sql = "CREATE TABLE IF NOT EXISTS ".$config_table." (
  `id` int(11) NOT NULL default '0',
  `mailmethod` varchar(20) NOT NULL default '',
  `port` int(11) NOT NULL default '0',
  `smtp` varchar(200) NOT NULL default '',
  `auth` varchar(5) NOT NULL default '',
  `authuser` varchar(100) NOT NULL default '',
  `authpassword` varchar(100) NOT NULL default '',
  `sendername` varchar(200) NOT NULL default '',
  `senderemail` varchar(200) NOT NULL default '',
  `lang_frontend` varchar(100) NOT NULL default '',
  `lang_backend` varchar(100) NOT NULL default '',
  PRIMARY KEY  (`id`)
)";
$modx->db->query($sql);
$sql = "INSERT INTO ".$config_table." VALUES (1, 'IsSMTP', 0, '', 'false', '', '', '', '', 'english', 'english')";
$modx->db->query($sql);
  $sql = "CREATE TABLE IF NOT EXISTS ".$newsletter_table." (
  `id` int(11) NOT NULL auto_increment,
  `date` date NOT NULL default '0000-00-00',
  `status` int(11) NOT NULL default '0',
  `sent` int(11) NOT NULL default '0',
  `header` longtext,
  `subject` text NOT NULL,
  `newsletter` longtext,
  `footer` longtext,
  PRIMARY KEY  (`id`)
)";
$modx->db->query($sql);
$sql = "CREATE TABLE IF NOT EXISTS ".$subscribers_table." (
  `id` int(11) NOT NULL auto_increment,
  `firstname` varchar(50) NOT NULL default '',
  `lastname` varchar(50) NOT NULL default '',
  `email` varchar(50) NOT NULL default '',
  `status` int(11) NOT NULL default '1',
  `blocked` int(11) NOT NULL default '0',
  `lastnewsletter` varchar(50) NOT NULL default '',
  `created` date NOT NULL default '0000-00-00',
  PRIMARY KEY  (`id`)
)";
$modx->db->query($sql);
  $sql = "
CREATE TABLE IF NOT EXISTS `".$queue_table."` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `recipients` text NOT NULL,
  `control` varchar(100) NOT NULL DEFAULT '',
  `message_id` text NOT NULL,
  `status` int(11) NOT NULL DEFAULT '0',
  `create_time` int(20) NOT NULL DEFAULT '0',
  `change_time` int(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
)";
$modx->db->query($sql);
  /*
$sql = "
CREATE TABLE IF NOT EXISTS `".$groups_table."` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `webgroup` int(10) NOT NULL DEFAULT '0',
  `subscriber` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ix_group_user` (`webgroup`,`subscriber`)
) DEFAULT CHARSET=utf8;";
  $modx->db->query($sql);*/
echo 'Easy Newsletter has now been installed. Please click <b>Easy Newsletter</b> in the navigation bar.';
} else {
$theme = $modx->config['manager_theme'];
$lang_backend = $modx->db->getValue($modx->db->select( 'lang_backend', $config_table, '`id` = 1' ));
include_once($path.'languages/'.$lang_backend.'.php');
if(!isset($_GET['p'])) { $_GET['p'] = ''; }
$selected1 = $selected2 = $selected0 = '';
switch($_GET['p']) {
  case "1":
    $selected1 = ' selected';
  break;
  case "2":
    $selected2 = ' selected';
  break;
  default:
  $selected0 = ' selected';
}
$notifier = (!$unsbscrbPage || (int)$unsbscrbPage<1) ? '<b>'.$lang_unsubscribenotifier.'</b>' : '';
echo '
<html>
<head>
	<title>MODx</title>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<link rel="stylesheet" type="text/css" href="media/style/'.$theme.'/style.css?" />
	<link rel="stylesheet" type="text/css" href="'.$path.'enstyle.css?" />
</head>
<body>
<br />
<div class="sectionHeader">Управление рассылкой</div><div class="sectionBody">
<div class="dynamic-tab-pane-control tab-pane">
'.$notifier.'
<div class="tab-row">
&nbsp;&nbsp;&nbsp;<a class="tab'.$selected0.'" href="index.php?a=112&id='.$modId.'&action=1"><span>'.$lang_links_subscribers.'</span></a><a class="tab'.$selected1.'" href="index.php?a=112&id='.$modId.'&p=1&action=1"><span>'.$lang_links_newsletter.'</span></a> <a class="tab'.$selected2.'" href="index.php?a=112&id='.$modId.'&p=2&action=1"><span>'.$lang_links_configuration.'</span></a>
</div></div><div class="sectContent"><br />';
include($path.'backend.php');
echo '
</div>
</div>
</body>
</html>
';
return;
}



/*Config

&modId=Module ID;int;5 &path=Path;text;../assets/modules/evonewsletter/ &unsbscrbPage=Unsubscribe page;int;40 &maillimit=Mails before re-connect;int;20 &display=Rows per page;int;30

