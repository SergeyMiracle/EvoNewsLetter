//<?
/* 
* sendToNewsletter
* Плагин для добавления css и js на страницу и отправки документа в рассылку.
* 
* @properties  &templ=Template;text;
*/
global $content, $pagetitle, $default_template;

$templ = isset($templ) ? explode(',',$templ) : false; // Шаблоны
$cur_templ = isset($_POST['template']) ? $_POST['template'] : (isset($content['template']) ? $content['template'] : $default_template);
$newsletter_table = $modx->getFullTableName('easynewsletter_newsletter');
$moduleId = isset($moduleId) ? $moduleId : '';

if ($templ && !in_array($cur_templ,$templ)) return;

$e = &$modx->Event;

if ($e->name == 'OnDocFormRender') {
  $outputTpl = <<< OUT
    <!-- addElement -->
    <style type="text/css">
    .fake_tv {margin:10px 0;}
  	.fake_tv label {display:inline-block; width:155px;font-weight:bold;}
 		</style>
    <script type="text/javascript">
      /* <![CDATA[ */
      jQuery(function() {
        jQuery("#tv_body").append('<div class="split"></div><div class="fake_tv"><label for="notify">Отправить в рассылку</label><input type="checkbox" id="notify" name="notify" ></div>');
      });
  /* ]]> */
  	</script>
    <!-- /addElement -->
OUT;
  
  $output = $outputTpl;
  $e->output($output);
}

if ($e->name == 'OnDocFormSave') {
  
  	if (!isset($_POST['notify']) || $_POST['notify'] != 'on'){
    	return;
  	} 

 	$nid = $modx->event->params['id'];
  
	$docLink = $modx->makeUrl($nid, '', '', 'full');
 	$newsBody = '<h3>'.$pagetitle.'</h3><p>Эта новость на сайте: '.$docLink.'</p>';
	
	$fields = array(
		'date' => date("Y-m-d"),
		'subject' => $pagetitle,
		'newsletter' => $newsBody,
	);
	$modx->db->insert($fields, $newsletter_table);  
}


/*Config

&templ=Template;text;9 &moduleId=Module Id;text;5
OnDocFormRender
OnDocFormSave
*/