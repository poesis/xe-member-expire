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
	
	/**
	 * 처음 생성하면 DB 오브젝트와 member 컨트롤러를 로딩한다.
	 */
	public function __construct()
	{
		$this->oDB = DB::getInstance();
		$this->oMemberController = getController('member');
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
				if ($use_transaction) $this->oDB->rollback();
				return -41;
			}
			$member = is_object($member_query->data) ? $member_query->data : reset($member_query->data);
			if (!$member)
			{
				if ($use_transaction) $this->oDB->rollback();
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
		$output = executeQuery('member_expire.getNotifiedDate', $args);
		if (!$output->toBool())
		{
			return -43;
		}
		if (count($output->data))
		{
			return 2;
		}
		
		// 트랜잭션을 시작한다.
		if ($use_transaction)
		{
			$this->oDB->begin();
		}
		
		// 발송한 메일을 기록한다.
		$args = new stdClass();
		$args->member_srl = $member_srl;
		$output = executeQuery('member_expire.deleteNotifiedDate', $args);
		if (!$output->toBool())
		{
			return -44;
		}
		$output = executeQuery('member_expire.insertNotifiedDate', $args);
		if (!$output->toBool())
		{
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
		return true;
	}
	
	/**
	 * 회원 계정을 별도의 테이블로 이동하는 메소드. 소속 그룹 정보, 이미지 등은 그대로 유지한다.
	 */
	public function moveMember($member_srl, $use_transaction = true)
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
		$output = executeQuery('member.deleteAuthMail', $member);
		if (!$output->toBool())
		{
			if ($use_transaction) $this->oDB->rollback();
			return -24;
		}
		
		// member 테이블에서 회원정보를 삭제한다.
		$output = executeQuery('member.deleteMember', $member);
		if (!$output->toBool())
		{
			if ($use_transaction) $this->oDB->rollback();
			return -25;
		}
		
		// 회원정보 캐시를 비운다.
		$this->oMemberController->_clearMemberCache($member->member_srl);
		
		// 트랜잭션을 커밋한다.
		if ($use_transaction)
		{
			$this->oDB->commit();
		}
		return true;
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
			$obj = new stdClass();
			$obj->member_srl = $member_srl;
			$member = executeQuery('member_expire.getMovedMembers', $obj);
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
		return 1;
	}
}
