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
class Member_Expire extends ModuleObject
{
	/**
	 * 모듈 설정을 불러오는 메소드.
	 */
	public function getConfig()
	{
		$config = getModel('module')->getModuleConfig('member_expire');
		if (!is_object($config))
		{
			$config = new stdClass();
		}
		if (!$config->expire_threshold) $config->expire_threshold = 365;
		if (!$config->expire_method) $config->expire_method = 'delete';
		if (!$config->auto_expire) $config->auto_expire = 'N';
		if (!$config->auto_restore) $config->auto_restore = 'N';
		if (!$config->auto_start) $config->auto_start = '2015-08-18';
		if (!$config->email_threshold) $config->email_threshold = 0;
		if (!$config->email_subject)
		{
			$config->email_subject = strval(Context::getSiteTitle());
			if ($config->email_subject !== '') $config->email_subject = '[' . $config->email_subject . ']';
			$config->email_subject .= ' 휴면계정 전환 안내';
		}
		if (!$config->email_content)
		{
			$config->email_content = file_get_contents($this->module_path.'tpl/email_default.html');
		}
		return $config;
	}
	
	/**
	 * 숫자로 지정된 기간을 사람이 이해하기 쉬운 표현으로 변경하는 메소드.
	 */
	protected function translateThreshold($days)
	{
		if ($days < 360)
		{
			return round($days / 30.25) . '개월';
		}
		else
		{
			return round($days / 365) . '년';
		}
	}
	
	/**
	 * 트리거가 정상적으로 등록되어 있는지 확인하는 메소드.
	 */
	public function checkTriggers()
	{
		$oModuleModel = getModel('module');
		if(!$oModuleModel->getTrigger('member.insertMember', 'member_expire', 'controller', 'triggerBlockDuplicates', 'before'))
		{
			return false;
		}
		if(!$oModuleModel->getTrigger('member.updateMember', 'member_expire', 'controller', 'triggerBlockDuplicates', 'before'))
		{
			return false;
		}
		if(!$oModuleModel->getTrigger('member.doLogin', 'member_expire', 'controller', 'triggerAutoExpire', 'after'))
		{
			return false;
		}
		if(!$oModuleModel->getTrigger('member.doLogout', 'member_expire', 'controller', 'triggerAutoExpire', 'after'))
		{
			return false;
		}
		if(!$oModuleModel->getTrigger('moduleObject.proc', 'member_expire', 'controller', 'triggerBeforeModuleProc', 'before'))
		{
			return false;
		}
		if(!$oModuleModel->getTrigger('moduleObject.proc', 'member_expire', 'controller', 'triggerAfterModuleProc', 'after'))
		{
			return false;
		}
		return true;
	}
	
	/**
	 * 모듈에서 사용하는 트리거를 등록하는 메소드.
	 */
	public function registerTriggers()
	{
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		if(!$oModuleModel->getTrigger('member.insertMember', 'member_expire', 'controller', 'triggerBlockDuplicates', 'before'))
		{
			$oModuleController->insertTrigger('member.insertMember', 'member_expire', 'controller', 'triggerBlockDuplicates', 'before');
		}
		if(!$oModuleModel->getTrigger('member.updateMember', 'member_expire', 'controller', 'triggerBlockDuplicates', 'before'))
		{
			$oModuleController->insertTrigger('member.updateMember', 'member_expire', 'controller', 'triggerBlockDuplicates', 'before');
		}
		if(!$oModuleModel->getTrigger('member.doLogin', 'member_expire', 'controller', 'triggerAutoExpire', 'after'))
		{
			$oModuleController->insertTrigger('member.doLogin', 'member_expire', 'controller', 'triggerAutoExpire', 'after');
		}
		if(!$oModuleModel->getTrigger('member.doLogout', 'member_expire', 'controller', 'triggerAutoExpire', 'after'))
		{
			$oModuleController->insertTrigger('member.doLogout', 'member_expire', 'controller', 'triggerAutoExpire', 'after');
		}
		if(!$oModuleModel->getTrigger('moduleObject.proc', 'member_expire', 'controller', 'triggerBeforeModuleProc', 'before'))
		{
			$oModuleController->insertTrigger('moduleObject.proc', 'member_expire', 'controller', 'triggerBeforeModuleProc', 'before');
		}
		if(!$oModuleModel->getTrigger('moduleObject.proc', 'member_expire', 'controller', 'triggerAfterModuleProc', 'after'))
		{
			$oModuleController->insertTrigger('moduleObject.proc', 'member_expire', 'controller', 'triggerAfterModuleProc', 'after');
		}
		return true;
	}
	
	/**
	 * 누락된 DB 인덱스가 있는지 확인하는 메소드.
	 */
	public function checkIndexes()
	{
		$oDB = DB::getInstance();
		if (!$oDB->isIndexExists('member_expired', 'idx_user_name'))
		{
			return false;
		}
		if (!$oDB->isIndexExists('member_expired_notified', 'idx_user_name'))
		{
			return false;
		}
		if (!$oDB->isIndexExists('member_expired_notified', 'idx_nick_name'))
		{
			return false;
		}
		return true;
	}
	
	/**
	 * 누락된 DB 인덱스를 생성하는 메소드.
	 */
	public function createIndexes()
	{
		$oDB = DB::getInstance();
		if (!$oDB->isIndexExists('member_expired', 'idx_user_name'))
		{
			$oDB->addIndex('member_expired', 'idx_user_name', 'user_name', false);
		}
		if (!$oDB->isIndexExists('member_expired_notified', 'idx_user_name'))
		{
			$oDB->addIndex('member_expired_notified', 'idx_user_name', 'user_name', false);
		}
		if (!$oDB->isIndexExists('member_expired_notified', 'idx_nick_name'))
		{
			$oDB->addIndex('member_expired_notified', 'idx_nick_name', 'nick_name', false);
		}
		return true;
	}
	
	/**
	 * 모듈 설치 메소드.
	 */
	public function moduleInstall()
	{
		$this->registerTriggers();
		$this->createIndexes();
		return new Object();
	}
	
	/**
	 * 모듈 업데이트 필요 여부 체크 메소드.
	 */
	public function checkUpdate()
	{
		return !$this->checkTriggers() || !$this->checkIndexes();
	}
	
	/**
	 * 모듈 업데이트 메소드.
	 */
	public function moduleUpdate()
	{
		$this->registerTriggers();
		$this->createIndexes();
		return new Object(0, 'success_updated');
	}
	
	/**
	 * 캐시파일 재생성 메소드.
	 */
	public function recompileCache()
	{
		// no-op
	}
}
