<?php

/**
 * 휴면계정 정리 모듈
 * 
 * Copyright (c) 2015, Kijin Sung <kijin@kijinsung.com>
 * 
 * 이 프로그램은 자유 소프트웨어입니다. 소프트웨어의 피양도자는 자유 소프트웨어
 * 재단이 공표한 GNU 일반 공중 사용 허가서 2판 또는 그 이후 판을 임의로
 * 선택해서, 그 규정에 따라 프로그램을 개작하거나 재배포할 수 있습니다.
 *
 * 이 프로그램은 유용하게 사용될 수 있으리라는 희망에서 배포되고 있지만,
 * 특정한 목적에 맞는 적합성 여부나 판매용으로 사용할 수 있으리라는
 * 묵시적인 보증을 포함한 어떠한 형태의 보증도 제공하지 않습니다.
 * 보다 자세한 사항에 대해서는 GNU 일반 공중 사용 허가서를 참고하시기 바랍니다.
 *
 * GNU 일반 공중 사용 허가서는 이 프로그램과 함께 제공됩니다.
 * 만약, 이 문서가 누락되어 있다면 자유 소프트웨어 재단으로 문의하시기 바랍니다.
 */
class Member_ExpireAdminView extends Member_Expire
{
	/**
	 * 모듈 설정 화면을 표시하는 메소드.
	 */
	public function dispMember_ExpireAdminConfig()
	{
		// 현재 설정을 불러온다.
		Context::set('mex_config', $this->getConfig());
		
		// 템플릿을 지정한다.
		Context::setBrowserTitle('휴면계정 기본 설정 - XE Admin');
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('config');
	}
	
	/**
	 * 휴면계정 일괄 정리 화면을 표시하는 메소드.
	 */
	public function dispMember_ExpireAdminCleanup()
	{
		// 현재 설정을 불러온다.
		$config = $this->getConfig();
		Context::set('mex_config', $this->getConfig());
		
		// 휴면계정 수를 불러온다.
		$obj = new stdClass();
		$obj->threshold = date('YmdHis', time() - ($config->expire_threshold * 86400) + zgap());
		$expired_members_count = executeQuery('member_expire.countExpiredMembers', $obj);
		$expired_members_count = $expired_members_count->toBool() ? $expired_members_count->data->count : 0;
		Context::set('expire_threshold', $this->translateThreshold($config->expire_threshold));
		Context::set('expired_members_count', $expired_members_count);
		
		// 템플릿을 지정한다.
		Context::setBrowserTitle('휴면계정 일괄 정리 - XE Admin');
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('cleanup');
	}
	
	/**
	 * 안내메일 일괄 발송 화면을 표시하는 메소드.
	 */
	public function dispMember_ExpireAdminEmailSend()
	{
		// 현재 설정을 불러온다.
		$config = $this->getConfig();
		Context::set('mex_config', $this->getConfig());
		
		// 미리 알리기 위한 추가 날짜 설정을 불러온다.
		if (($extra_days = Context::get('extra_days')) && ctype_digit($extra_days))
		{
			$extra_days = intval($extra_days, 10);
		}
		else
		{
			$extra_days = $config->email_threshold;
		}
		Context::set('extra_days', $extra_days);
		
		// 휴면계정 수를 불러온다.
		$obj = new stdClass();
		$obj->threshold = date('YmdHis', time() - ($config->expire_threshold * 86400) + ($extra_days * 86400) + zgap());
		$expired_members_count = executeQuery('member_expire.countExpiredMembers', $obj);
		$expired_members_count = $expired_members_count->toBool() ? $expired_members_count->data->count : 0;
		Context::set('expired_members_count', $expired_members_count);
		
		// 아직 메일을 발송하지 않은 휴면계정 수를 불러온다.
		$obj = new stdClass();
		$obj->threshold = date('YmdHis', time() - ($config->expire_threshold * 86400) + ($extra_days * 86400) + zgap());
		$unnotified_members_count = executeQuery('member_expire.countUnnotifiedMembers', $obj);
		$unnotified_members_count = $unnotified_members_count->toBool() ? $unnotified_members_count->data->count : 0;
		Context::set('unnotified_members_count', $unnotified_members_count);
		
		// 템플릿을 지정한다.
		Context::setBrowserTitle('안내메일 일괄 발송 - XE Admin');
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('email_send');
	}
	
	/**
	 * 안내메일 내용 편집 화면을 표시하는 메소드.
	 */
	public function dispMember_ExpireAdminEmailTemplate()
	{
		// 현재 설정을 불러온다.
		$config = $this->getConfig();
		Context::set('mex_config', $this->getConfig());
		Context::set('expire_threshold', $this->translateThreshold($config->expire_threshold));
		
		// 에디터를 생성한다.
		$oEditorModel = getModel('editor');
		$oEditorConfig = getModel('module')->getModuleConfig('editor');
		$option = new stdClass();
		$option->skin = $oEditorConfig->editor_skin;
		$option->primary_key_name = 'temp_srl';
		$option->content_key_name = 'email_content';
		$option->allow_fileupload = false;
		$option->enable_autosave = false;
		$option->enable_default_component = true;
		$option->enable_component = true;
		$option->resizable = true;
		$option->height = 300;
		Context::set('editor', $oEditorModel->getEditor(0, $option));
		Context::set('editor_skin_list', $oEditorModel->getEditorSkinList());
		
		// 템플릿을 지정한다.
		Context::setBrowserTitle('안내메일 내용 편집 - XE Admin');
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('email_template');
	}
	
	/**
	 * 안내메일 발송 내역 화면을 표시하는 메소드.
	 */
	public function dispMember_ExpireAdminEmailList()
	{
		// 현재 설정을 불러온다.
		$config = $this->getConfig();
		Context::set('mex_config', $config);
		
		// 검색 조건을 불러온다.
		$search_target = Context::get('search_target');
		$search_keyword = Context::get('search_keyword');
		if (!in_array($search_target, array('email_address', 'user_id', 'user_name', 'nick_name')) || !$search_keyword)
		{
			Context::set('search_target', $search_target = null);
			Context::set('search_keyword', $search_keyword = null);
		}
		$valid_list_counts = array(10, 20, 30, 50, 100, 200, 300);
		$list_count = intval(Context::get('list_count'));
		if (!in_array($list_count, $valid_list_counts)) $list_count = 10;
		Context::set('list_count', $list_count);
		
		// 발송 내역을 불러온다.
		$obj = new stdClass();
		if ($search_target && $search_keyword) $obj->$search_target = trim($search_keyword);
		$sent_email_count = executeQuery('member_expire.countNotifiedDates', $obj);
		$sent_email_count = $sent_email_count->toBool() ? $sent_email_count->data->count : 0;
		$obj->list_count = $list_count;
		$obj->page = $page = Context::get('page') ? Context::get('page') : 1;
		$obj->orderby = 'desc';
		$sent_emails = executeQuery('member_expire.getNotifiedDates', $obj);
		$sent_emails = $sent_emails->toBool() ? $sent_emails->data : array();
		$member_srls = array();
		foreach ($sent_emails as $member)
		{
			$member_srls[] = $member->member_srl;
		}
		$sent_emails_groups = getModel('member')->getMembersGroups($member_srls);
		Context::set('sent_email_count', $sent_email_count);
		Context::set('sent_emails', $sent_emails);
		Context::set('sent_emails_groups', $sent_emails_groups);
		
		// 페이징을 처리한다.
		$paging = new Object();
		$paging->total_count = $sent_email_count;
		$paging->total_page = max(1, ceil($sent_email_count / $list_count));
		$paging->page = $page;
		$paging->page_navigation = new PageHandler($paging->total_count, $paging->total_page, $page, $list_count);
		Context::set('paging', $paging);
		Context::set('page', $page);
		
		// 템플릿을 지정한다.
		Context::setBrowserTitle('안내메일 발송 내역 - XE Admin');
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('email_list');
	}
	
	/**
	 * 정리대상 회원 목록을 표시하는 메소드.
	 */
	public function dispMember_ExpireAdminListTargets()
	{
		// 현재 설정을 불러온다.
		$config = $this->getConfig();
		Context::set('mex_config', $config);
		
		// 검색 조건을 불러온다.
		$search_target = Context::get('search_target');
		$search_keyword = Context::get('search_keyword');
		if (!in_array($search_target, array('email_address', 'user_id', 'user_name', 'nick_name')) || !$search_keyword)
		{
			Context::set('search_target', $search_target = null);
			Context::set('search_keyword', $search_keyword = null);
		}
		$valid_list_counts = array(10, 20, 30, 50, 100, 200, 300);
		$list_count = intval(Context::get('list_count'));
		if (!in_array($list_count, $valid_list_counts)) $list_count = 10;
		Context::set('list_count', $list_count);
		
		// 휴면계정 목록을 불러온다.
		$obj = new stdClass();
		if ($search_target && $search_keyword) $obj->$search_target = trim($search_keyword);
		$obj->threshold = date('YmdHis', time() - ($config->expire_threshold * 86400) + zgap());
		$expired_members_count = executeQuery('member_expire.countExpiredMembers', $obj);
		$expired_members_count = $expired_members_count->toBool() ? $expired_members_count->data->count : 0;
		$obj->list_count = $list_count;
		$obj->page = $page = Context::get('page') ? Context::get('page') : 1;
		$obj->orderby = 'desc';
		$expired_members = executeQuery('member_expire.getExpiredMembers', $obj);
		$expired_members = $expired_members->toBool() ? $expired_members->data : array();
		$member_srls = array();
		foreach ($expired_members as $member)
		{
			$member_srls[] = $member->member_srl;
		}
		$expired_members_groups = getModel('member')->getMembersGroups($member_srls);
		Context::set('expire_threshold', $this->translateThreshold($config->expire_threshold));
		Context::set('expired_members_count', $expired_members_count);
		Context::set('expired_members', $expired_members);
		Context::set('expired_members_groups', $expired_members_groups);
		
		// 페이징을 처리한다.
		$paging = new Object();
		$paging->total_count = $expired_members_count;
		$paging->total_page = max(1, ceil($expired_members_count / $list_count));
		$paging->page = $page;
		$paging->page_navigation = new PageHandler($paging->total_count, $paging->total_page, $page, $list_count);
		Context::set('paging', $paging);
		Context::set('page', $page);
		
		// 템플릿을 지정한다.
		Context::setBrowserTitle('정리대상 회원 목록 - XE Admin');
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('list_targets');
	}
	
	/**
	 * 별도저장 회원 목록을 표시하는 메소드.
	 */
	public function dispMember_ExpireAdminListMoved()
	{
		// 현재 설정을 불러온다.
		$config = $this->getConfig();
		Context::set('mex_config', $config);
		
		// 검색 조건을 불러온다.
		$search_target = Context::get('search_target');
		$search_keyword = Context::get('search_keyword');
		if (!in_array($search_target, array('email_address', 'user_id', 'user_name', 'nick_name')) || !$search_keyword)
		{
			Context::set('search_target', $search_target = null);
			Context::set('search_keyword', $search_keyword = null);
		}
		$valid_list_counts = array(10, 20, 30, 50, 100, 200, 300);
		$list_count = intval(Context::get('list_count'));
		if (!in_array($list_count, $valid_list_counts)) $list_count = 10;
		Context::set('list_count', $list_count);
		
		// 휴면계정 목록을 불러온다.
		$obj = new stdClass();
		if ($search_target && $search_keyword) $obj->$search_target = trim($search_keyword);
		$moved_members_count = executeQuery('member_expire.countMovedMembers', $obj);
		$moved_members_count = $moved_members_count->toBool() ? $moved_members_count->data->count : 0;
		$obj->list_count = $list_count;
		$obj->page = $page = Context::get('page') ? Context::get('page') : 1;
		$obj->orderby = 'desc';
		$moved_members = executeQuery('member_expire.getMovedMembers', $obj);
		$moved_members = $moved_members->toBool() ? $moved_members->data : array();
		$member_srls = array();
		foreach ($moved_members as $member)
		{
			$member_srls[] = $member->member_srl;
		}
		$moved_members_groups = getModel('member')->getMembersGroups($member_srls);
		Context::set('expire_threshold', $this->translateThreshold($config->expire_threshold));
		Context::set('moved_members_count', $moved_members_count);
		Context::set('moved_members', $moved_members);
		Context::set('moved_members_groups', $moved_members_groups);
		
		// 페이징을 처리한다.
		$paging = new Object();
		$paging->total_count = $moved_members_count;
		$paging->total_page = max(1, ceil($moved_members_count / $list_count));
		$paging->page = $page;
		$paging->page_navigation = new PageHandler($paging->total_count, $paging->total_page, $page, $list_count);
		Context::set('paging', $paging);
		Context::set('page', $page);
		
		// 템플릿을 지정한다.
		Context::setBrowserTitle('별도저장 회원 목록 - XE Admin');
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('list_moved');
	}
	
	/**
	 * 예외 목록 화면을 표시하는 메소드.
	 */
	public function dispMember_ExpireAdminListExceptions()
	{
		// 현재 설정을 불러온다.
		$config = $this->getConfig();
		Context::set('mex_config', $config);
		
		// 검색 조건을 불러온다.
		$search_target = Context::get('search_target');
		$search_keyword = Context::get('search_keyword');
		if (!in_array($search_target, array('email_address', 'user_id', 'user_name', 'nick_name')) || !$search_keyword)
		{
			Context::set('search_target', $search_target = null);
			Context::set('search_keyword', $search_keyword = null);
		}
		$valid_list_counts = array(10, 20, 30, 50, 100, 200, 300);
		$list_count = intval(Context::get('list_count'));
		if (!in_array($list_count, $valid_list_counts)) $list_count = 10;
		Context::set('list_count', $list_count);
		
		// 발송 내역을 불러온다.
		$obj = new stdClass();
		if ($search_target && $search_keyword) $obj->$search_target = trim($search_keyword);
		$exception_count = executeQuery('member_expire.countExceptions', $obj);
		$exception_count = $exception_count->toBool() ? $exception_count->data->count : 0;
		$obj->list_count = $list_count;
		$obj->page = $page = Context::get('page') ? Context::get('page') : 1;
		$obj->orderby = 'desc';
		$exceptions = executeQuery('member_expire.getExceptions', $obj);
		$exceptions = $exceptions->toBool() ? $exceptions->data : array();
		$member_srls = array();
		foreach ($exceptions as $member)
		{
			$member_srls[] = $member->member_srl;
		}
		$exceptions_groups = getModel('member')->getMembersGroups($member_srls);
		Context::set('exception_count', $exception_count);
		Context::set('exceptions', $exceptions);
		Context::set('exceptions_groups', $exceptions_groups);
		
		// 페이징을 처리한다.
		$paging = new Object();
		$paging->total_count = $exception_count;
		$paging->total_page = max(1, ceil($exception_count / $list_count));
		$paging->page = $page;
		$paging->page_navigation = new PageHandler($paging->total_count, $paging->total_page, $page, $list_count);
		Context::set('paging', $paging);
		Context::set('page', $page);
		
		// 템플릿을 지정한다.
		Context::setBrowserTitle('예외 목록 - XE Admin');
		$this->setTemplatePath($this->module_path.'tpl');
		$this->setTemplateFile('exceptions');
	}
}
