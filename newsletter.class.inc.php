<?php
/**
 * 
 * 
 * 
 * 
 * @date:    02.12.2012
**/

class newsletter {

	const WAIT_MINUTE = 1;
	const HIDEMENU = 0;

	protected $_prefix;
	protected $_waitMinute;
	protected $_hidemenu;
	protected $_tableNewdocs;
	protected $_tableNewsletter;
	protected $_tableSubscribers;
	protected $_tableQueue;
	protected $_groupsTable;
	
	public  $message;
	
	function __construct(){
		global $modx;

		$this->_waitMinute = self::WAIT_MINUTE;
		$this->_hidemenu = self::HIDEMENU;
		$this->_prefix = $modx->db->config['table_prefix'];
		$this->_tableNewdocs = $this->_prefix.'easynewsletter_newdocs';
		$this->_tableNewsletter = $this->_prefix.'easynewsletter_newsletter';
		$this->_tableSubscribers = $this->_prefix.'easynewsletter_subscribers';
		$this->_tableQueue = $this->_prefix.'easynewsletter_queue';
		$this->_groupsTable = $this->_prefix."easynewsletter_groups";
	}

	/**
	*
	* Постановка в очередь письма для всех подписчиков
	*
	**/
	public function send_to_queue($nid, $return=false) {
		global $modx;

		$result = $modx->db->query("SELECT `email`, `created` FROM `".$this->_tableSubscribers."` ");

		$countIns = 0;
		while ($row = $modx->db->getRow($result)) {
			if (trim($row['email']) != '') {
				// Created code for unsubscribe link.
       	$control = '';
        $email = $row['email'];
        $created = $row['created'];
        $control = md5($email.$created);

				$fields = array(
          'recipients'	=> trim($row['email']), 
          'message_id'	=> (int)$nid, 
          'create_time'	=> time(),
          'control'			=> $control,
        ); 
        $modx->db->insert($fields, $this->_tableQueue);
        $countIns++;
			}
		}
		if ($return) {
			return $countIns;
		}
	}
	
	/**
	*
	* Получение новых документов на сайте
	*
	**/
	protected function get_new_doc(){
		global $modx;

		$sql = "SELECT `doc_id` FROM `".$this->_tableNewdocs."` WHERE `status` = 0 AND `time` < DATE_SUB(NOW(), INTERVAL ".$this->_waitMinute." MINUTE) GROUP BY doc_id"; 
		$rs = $modx->db->query($sql);
		
		$created_ids = array();
		while ($row=$modx->db->getRow($rs)){
			$created_ids[$row['doc_id']] = $row['doc_id'];
		}
		
		return empty($created_ids) ? false : $created_ids;
	}

	/**
	*
	* Подготовка письма из таблицы новых документов и отправка в очередь рассылки
	*
	**/
	public function cron($debug = false, $send = true){
		global $modx;		
		
		$created_ids = $this->get_new_doc();		

		if ($debug) $result  = '<p><strong>site_mailer cron debug:</strong></p>';
		if ($debug) $result .= '<p>minute: '.$this->_waitMinute.'</p>';
		if ($debug) $result .= '<p>created_ids: '.(@implode(',', $created_ids)).'</p>';

		//Нет данных
		if ($created_ids===false) {
			echo $result;
			return;
		}

		//Массивы отправленных документов 
		$sendind_created = array();
		
		//Формируем основную часть письма
		$body='';
		
		$where = "`id` IN (".implode(",",$created_ids).") AND `published`='1'";
		if ($this->_hidemenu == '0'){
			$where .= " AND `hidemenu` = '0'";
		}
			
		$created = $modx->db->select("id, pagetitle, createdon", $modx->getFullTableName('site_content'), $where, 'createdon');
		if ($modx->db->getRecordCount($created)){
			$body = '<p>Новые записи на сайте &quot;'.$modx->config['site_name'].'&quot; ('.date("m.d.Y").')</p><ul>';

			while($row = $modx->db->getRow($created) ) { 
				$body .= '<li><a href="'.$modx->config['site_url'].$modx->makeUrl($row['id']).'">'.$row['pagetitle'].'</a></li>';
				$sendind_created[]=$row['id'];
			}
			$body.='</ul>';
		}

		if (empty($body)) return;
		
		$body = str_replace($modx->config['site_url']."/",$modx->config['site_url'],$body);
		
		if ($debug) $result .= '<p>body: <pre>'.$body.'</pre></p>';
		
		$del_ids = $sendind_created;
    if (($send)&&(!empty($del_ids))) {
    	$modx->db->query("DELETE FROM ".$this->_tableNewdocs." WHERE doc_id IN (".implode(",", $del_ids).")");
    	if ($insert = $modx->db->query("INSERT INTO `".$this->_tableNewsletter."` VALUES('', now(), '', '', '', 'Изменения на сайте ".$modx->config['site_name']." (".date("m.d.Y").")', '".$modx->db->escape($body)."', '') ")) {
  		$lastId = $modx->db->getInsertId();
    	$this->send_to_queue($lastId);
  		}
  	}

    return $result;
	}

	/**
	*
	*	Get MODX groups for subscriber
	*
	**/
	public function get_user_groups($userId){
		global $modx;

		$userId = (int)$userId;
		$groupsarray = array();
		$result = $modx->db->select('*', $this->_groupsTable, "`subscriber` = '".$userId."'");
		$limit = $modx->db->getRecordCount($result);
		while($row = $modx->db->getRow($result)) { 
			$groupsarray[] = $row['webgroup'];
		}
		unset($result, $row);

		$result = $modx->db->select('name, id', $this->_prefix.'webgroup_names', '', 'name');
		while($row = $modx->db->getRow($result)) { 
			$output .= '<input type="checkbox" name="user_groups[]" value="'.$row['id'].'"'.(in_array($row['id'], $groupsarray) ? ' checked="checked"' : '').' />'.$row['name'].'<br>';
		}
		return $output;
	}

	/**
	*
	*	Set MODX groups for subscriber
	*
	**/
	public function set_user_groups($userId, $userGroups){
		global $modx;

		$userId = (int)$userId;
		$modx->db->delete($this->_groupsTable, "`subscriber` = '".$userId."'");

		if (count($userGroups) > 0) {
			for ($i = 0; $i < count($userGroups); $i++) {
				$insert = $modx->db->insert(array('webgroup' => (int)$userGroups[$i], 'subscriber' => $userId), $this->_groupsTable);
				if (!$insert) {
					$output = 'An error occurred while attempting to add the user to a user_group.<br />';
					return $output;
				}
			}
		}

	}

}