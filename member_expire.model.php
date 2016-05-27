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
class Member_ExpireModel extends Member_Expire
{
	/**
	 * member 컨트롤러와 DB 핸들을 캐싱해 둔다.
	 */
	protected $oDB;
	protected $oMemberController;
	protected $oSites;
	
	/**
	 * 처음 생성하면 DB 오브젝트와 member 컨트롤러를 로딩한다.
	 */
	public function __construct()
	{
		$this->oDB = DB::getInstance();
	}
	
	/**
	 * 휴면 안내메일을 발송하는 메소드.
	 */
	public function sendEmail($member_srl, $config = null, $resend = true, $use_transaction = true)
	{
		// 회원 오브젝트를 통째로 받은 경우 member_srl을 추출한다.
		if (is_object($member_srl) && isset($member_srl->member_srl))
		{
			$member = $member_srl;
			$member_srl = $member_srl->member_srl;
		}
		else
		{
			$args = new stdClass();
			$args->member_srl = $member_srl;
			$member_query = executeQuery('member.getMemberInfoByMemberSrl', $args);
			if (!$member_query->toBool() || !$member_query->data)
			{
				return -41;
			}
			$member = is_object($member_query->data) ? $member_query->data : reset($member_query->data);
			if (!$member)
			{
				return -42;
			}
			$member_srl = $member->member_srl;
		}
		
		// 모듈 설정이 로딩되지 않은 경우 지금 로딩한다.
		if (!$config)
		{
			$config = $this->getConfig();
		}
		
		// 이미 발송한 경우, $resend = true가 아니라면 재발송하지 않는다.
		$args = new stdClass();
		$args->member_srl = $member_srl;
		$output = executeQuery('member_expire.getNotifiedDates', $args);
		if (!$output->toBool())
		{
			return -43;
		}
		if (count($output->data) && !$resend)
		{
			return 2;
		}
		
		// 정리 예정일을 계산한다.
		$start_date = strtotime($config->auto_start) + zgap();
		$base_date = $member->last_login ? $member->last_login : $member->regdate;
		$base_date = $base_date ? ztime($base_date) : 0;
		$expire_date = $base_date + (86400 * $config->expire_threshold);
		if ($expire_date < $start_date) $expire_date = $start_date;
		if ($expire_date < time()) $expire_date = time();
		$member->expire_date = date('YmdHis', $expire_date);
		
		// 매크로를 변환한다.
		$site_title = Context::getSiteTitle();
		$macros = array(
			'{SITE_NAME}' => htmlspecialchars($site_title, ENT_QUOTES, 'UTF-8', false),
			'{USER_ID}' => htmlspecialchars($member->user_id, ENT_QUOTES, 'UTF-8', false),
			'{USER_NAME}' => htmlspecialchars($member->user_name, ENT_QUOTES, 'UTF-8', false),
			'{NICK_NAME}' => htmlspecialchars($member->nick_name, ENT_QUOTES, 'UTF-8', false),
			'{EMAIL}' => htmlspecialchars($member->email_address, ENT_QUOTES, 'UTF-8', false),
			'{LOGIN_DATE}' => $base_date ? date('Y년 n월 j일', $base_date) : '(로그인 기록 없음)',
			'{EXPIRE_DATE}' => date('Y년 n월 j일', $expire_date),
			'{TIME_LIMIT}' => $this->translateThreshold($config->expire_threshold),
			'{CLEAN_METHOD}' => $config->expire_method === 'delete' ? '삭제' : '별도의 저장공간으로 이동',
		);
		
		// 메일을 작성하여 발송한다.
		$subject = htmlspecialchars_decode(str_replace(array_keys($macros), array_values($macros), $config->email_subject));
		$content = str_replace(array_keys($macros), array_values($macros), $config->email_content);
		$recipient_name = $member->user_name ? $member->user_name : ($member->nick_name ? $member->nick_name : 'member');
		
		static $sender_name = null;
		static $sender_email = null;
		if ($sender_name === null)
		{
			$member_config = getModel('module')->getModuleConfig('member');
			$sender_name = $member_config->webmaster_name ? $member_config->webmaster_name : ($site_title ? $site_title : 'webmaster');
			$sender_email = $member_config->webmaster_email;
		}
		
		$oMail = new Mail();
		$oMail->setTitle($subject);
		$oMail->setContent($content);
		$oMail->setSender($sender_name, $sender_email);
		$oMail->setReceiptor($recipient_name, $member->email_address);
		$oMail->send();
		
		// 트랜잭션을 시작한다.
		if ($use_transaction)
		{
			$this->oDB->begin();
		}
		
		// 발송한 메일을 기록한다.
		$output = executeQuery('member_expire.deleteNotifiedDate', $member);
		if (!$output->toBool())
		{
			if ($use_transaction) $this->oDB->rollback();
			return -44;
		}
		$output = executeQuery('member_expire.insertNotifiedDate', $member);
		if (!$output->toBool())
		{
			if ($use_transaction) $this->oDB->rollback();
			return -45;
		}
		
		// 트랜잭션을 커밋한다.
		if ($use_transaction)
		{
			$this->oDB->commit();
		}
		return 1;
	}
	
	/**
	 * 회원 계정을 삭제하는 메소드.
	 */
	public function deleteMember($member_srl, $call_triggers = true, $use_transaction = true)
	{
		// 회원 오브젝트를 통째로 받은 경우 member_srl을 추출한다.
		if (is_object($member_srl) && isset($member_srl->member_srl))
		{
			$member = $member_srl;
			$member_srl = $member_srl->member_srl;
		}
		else
		{
			$member = null;
		}
		
		// 트랜잭션을 시작한다.
		if ($use_transaction)
		{
			$this->oDB->begin();
		}
		
		// 삭제에 필요한 $args를 작성한다.
		$args = new stdClass();
		$args->member_srl = $member_srl;
		
		// 삭제 전 트리거를 호출한다.
		if ($call_triggers)
		{
			$output = ModuleHandler::triggerCall('member.deleteMember', 'before', $args);
			if (!$output->toBool())
			{
				if ($use_transaction) $this->oDB->rollback();
				return -11;
			}
		}
		
		// 이 회원과 관련된 인증 메일을 삭제한다.
		$output = executeQuery('member.deleteAuthMail', $args);
		if (!$output->toBool())
		{
			if ($use_transaction) $this->oDB->rollback();
			return -12;
		}
		
		// 이 회원의 그룹 소속 정보를 삭제한다.
		$output = executeQuery('member.deleteMemberGroupMember', $args);
		if (!$output->toBool())
		{
			if ($use_transaction) $this->oDB->rollback();
			return -13;
		}
		
		// 회원 자체를 삭제한다.
		$output = executeQuery('member.deleteMember', $args);
		if (!$output->toBool())
		{
			if ($use_transaction) $this->oDB->rollback();
			return -14;
		}
		
		// 삭제 후 트리거를 호출한다.
		if ($call_triggers)
		{
			$output = ModuleHandler::triggerCall('member.deleteMember', 'after', $args);
			if (!$output->toBool())
			{
				if ($use_transaction) $this->oDB->rollback();
				return -15;
			}
		}
		
		// 회원과 관련된 나머지 정보를 삭제한다.
		if (!$this->oMemberController)
		{
			$this->oMemberController = getController('member');
		}
		$this->oMemberController->procMemberDeleteImageName($member_srl);
		$this->oMemberController->procMemberDeleteImageMark($member_srl);
		$this->oMemberController->procMemberDeleteProfileImage($member_srl);
		$this->oMemberController->delSignature($member_srl);
		$this->oMemberController->_clearMemberCache($member_srl);
		
		// 트랜잭션을 커밋한다.
		if ($use_transaction)
		{
			$this->oDB->commit();
		}
		
		// 회원정보 캐시를 비운다.
		$this->clearMemberCache($member_srl);
		return 1;
	}
	
	/**
	 * 회원 계정을 별도의 테이블로 이동하는 메소드. 소속 그룹 정보, 이미지 등은 그대로 유지한다.
	 */
	public function moveMember($member_srl, $clear_metadata = true, $use_transaction = true)
	{
		// 회원 오브젝트를 통째로 받은 경우 member_srl을 추출한다.
		if (is_object($member_srl) && isset($member_srl->member_srl))
		{
			$member = $member_srl;
			$member_srl = $member_srl->member_srl;
		}
		else
		{
			$args = new stdClass();
			$args->member_srl = $member_srl;
			$member_query = executeQuery('member.getMemberInfoByMemberSrl', $args);
			if (!$member_query->toBool() || !$member_query->data)
			{
				if ($use_transaction) $this->oDB->rollback();
				return -21;
			}
			$member = is_object($member_query->data) ? $member_query->data : reset($member_query->data);
			if (!$member)
			{
				if ($use_transaction) $this->oDB->rollback();
				return -22;
			}
			$member_srl = $member->member_srl;
		}
		
		// 트랜잭션을 시작한다.
		if ($use_transaction)
		{
			$this->oDB->begin();
		}
		
		// 회원정보를 member_expire 테이블로 복사한다.
		$output = executeQuery('member_expire.insertMovedMember', $member);
		if (!$output->toBool())
		{
			$output = executeQuery('member_expire.deleteMovedMember', $member);
			$output = executeQuery('member_expire.insertMovedMember', $member);
			if (!$output->toBool())
			{
				if ($use_transaction) $this->oDB->rollback();
				return -23;
			}
		}
		
		// 이 회원과 관련된 인증 메일을 삭제한다.
		if ($clear_metadata)
		{
			$output = executeQuery('member.deleteAuthMail', $member);
			if (!$output->toBool())
			{
				if ($use_transaction) $this->oDB->rollback();
				return -24;
			}
		}
		
		// member 테이블에서 회원정보를 삭제한다.
		$output = executeQuery('member.deleteMember', $member);
		if (!$output->toBool())
		{
			if ($use_transaction) $this->oDB->rollback();
			return -25;
		}
		
		// 트랜잭션을 커밋한다.
		if ($use_transaction)
		{
			$this->oDB->commit();
		}
		
		// 회원정보 캐시를 비운다.
		$this->clearMemberCache($member_srl);
		return 1;
	}
	
	/**
	 * 회원 계정을 원래의 위치로 복원하는 메소드.
	 */
	public function restoreMember($member_srl, $use_transaction = true)
	{
		// 회원 오브젝트를 통째로 받은 경우 member_srl을 추출한다.
		if (is_object($member_srl) && isset($member_srl->member_srl))
		{
			$member = $member_srl;
			$member_srl = $member_srl->member_srl;
		}
		else
		{
			$member = null;
		}
		
		// 현재 별도의 테이블로 이동되어 있는지 확인한다.
		if (!$member)
		{
			$args = new stdClass();
			$args->member_srl = $member_srl;
			$member = executeQuery('member_expire.getMovedMembers', $args);
			$member = $member->toBool() ? reset($member->data) : false;
			if (!$member)
			{
				return -31;
			}
		}
		
		// 트랜잭션을 시작한다.
		if ($use_transaction)
		{
			$this->oDB->begin();
		}
		
		// 회원정보를 member 테이블로 복사한다.
		$output = executeQuery('member_expire.insertRestoredMember', $member);
		if (!$output->toBool())
		{
			$output = executeQuery('member.deleteMember', $member);
			$output = executeQuery('member_expire.insertRestoredMember', $member);
			if (!$output->toBool())
			{
				if ($use_transaction) $this->oDB->rollback();
				return -32;
			}
		}
		
		// member_expire 테이블에서 삭제한다.
		$output = executeQuery('member_expire.deleteMovedMember', $member);
		if (!$output->toBool())
		{
			if ($use_transaction) $this->oDB->rollback();
			return -33;
		}
		
		// 트랜잭션을 커밋한다.
		if ($use_transaction)
		{
			$this->oDB->commit();
		}
		
		// 회원정보 캐시를 비운다.
		$this->clearMemberCache($member_srl);
		return 1;
	}
	
	/**
	 * 회원 계정과 관련된 캐시를 모두 비운다.
	 */
	protected function clearMemberCache($member_srl)
	{
		// 가상 사이트 목록을 구한다.
		if ($this->oSites === null)
		{
			$this->oSites = array();
			$sites_query = executeQuery('member_expire.getVirtualSiteSrlOnly', new stdClass());
			if ($sites_query->toBool())
			{
				foreach ($sites_query->data as $site_info)
				{
					$this->oSites[] = $site_info->site_srl;
				}
			}
			if (!count($this->oSites))
			{
				$this->oSites = array(0);
			}
		}
		if (!in_array(0, $this->oSites))
		{
			$this->oSites[] = 0;
		}
		
		// 회원정보 캐시를 비운다.
		$cache_path = getNumberingPath($member_srl) . $member_srl;
		$oCacheHandler = CacheHandler::getInstance('object');
		if($oCacheHandler->isSupport())
		{
			$cache_key = $oCacheHandler->getGroupKey('member', 'member_info:' . $cache_path);
			$oCacheHandler->delete($cache_key);
		}
		
		// 가상 사이트별 회원그룹 캐시를 비운다.
		$oCacheHandler = CacheHandler::getInstance('object', null, true);
		if($oCacheHandler->isSupport())
		{
			foreach ($this->oSites as $site_srl)
			{
				$cache_key = $oCacheHandler->getGroupKey('member', 'member_groups:' . $cache_path . '_' . $site_srl);
				$oCacheHandler->delete($cache_key);
				$cache_key = $oCacheHandler->getGroupKey('member', 'member_groups:' . $cache_path . ':site:' . $site_srl);
				$oCacheHandler->delete($cache_key);
			}
		}
	}
}
