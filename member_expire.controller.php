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
class Member_ExpireController extends Member_Expire
{
	/**
	 * 임시 복원된 회원정보를 기억하는 변수.
	 */
	protected static $_temp_member_srl = array();
	
	/**
	 * 임시 복원 처리가 필요한 act 목록.
	 */
	protected static $_acts_to_intercept = array(
		'procMemberLogin',
		'procMemberCheckValue',
		'procMemberFindAccount',
		'procMemberFindAccountByQuestion',
		'procMemberResendAuthMail',
		'procMemberAuthAccount',
	);
	
	/**
	 * 회원 추가 및 수정 전 트리거.
	 * 별도의 저장공간으로 이동된 회원과 같은 아이디 등을 사용하여 가입하거나
	 * 중복되는 내용으로 회원정보를 수정하는 것을 금지한다.
	 */
	public function triggerBlockDuplicates($args)
	{
		// 별도 저장된 휴면회원과 같은 아이디로 가입하는 것을 금지한다.
		if ($args->user_id)
		{
			$obj = new stdClass();
			$obj->user_id = $args->user_id;
			$output = executeQuery('member_expire.getMovedMembers', $obj);
			if ($output->toBool() && count($output->data))
			{
				return new Object(-1,'msg_exists_user_id');
			}
		}
		
		// 별도 저장된 휴면회원과 같은 메일 주소로 가입하는 것을 금지한다.
		if ($args->email_address)
		{
			$obj = new stdClass();
			$obj->email_address = $args->email_address;
			$output = executeQuery('member_expire.getMovedMembers', $obj);
			if ($output->toBool() && count($output->data))
			{
				$config = $this->getConfig();
				if ($config->auto_restore === 'Y')
				{
					return new Object(-1, 'msg_exists_expired_email_address_auto_restore');
				}
				else
				{
					return new Object(-1,'msg_exists_expired_email_address');
				}
			}
		}
		
		// 별도 저장된 휴면회원과 같은 닉네임으로 가입하는 것을 금지한다.
		if ($args->nick_name)
		{
			$obj = new stdClass();
			$obj->nick_name = $args->nick_name;
			$output = executeQuery('member_expire.getMovedMembers', $obj);
			if ($output->toBool() && count($output->data))
			{
				return new Object(-1,'msg_exists_nick_name');
			}
		}
	}
	
	/**
	 * 회원 로그인 및 로그아웃 트리거.
	 * 실제 로그인 및 로그아웃과는 무관하고, 적당한 간격으로 자동 정리를 실행하는 데 쓰인다.
	 * 이 트리거의 호출 빈도가 실제 회원수에 비례할 가능성이 높기 때문이다.
	 */
	public function triggerAutoExpire()
	{
		// 자동으로 처리할 일이 없다면 종료한다.
		$config = $this->getConfig();
		$tasks = 0;
		if ($config->auto_expire !== 'Y' && $config->email_threshold <= 0)
		{
			return;
		}
		
		// 이번에 처리할 일을 결정한다.
		$expire_enabled = $config->auto_expire === 'Y' && (time() > (strtotime($config->auto_start) + zgap()));
		if ($expire_enabled && $config->email_threshold <= 0)
		{
			$task = 'expire';
		}
		elseif (!$expire_enabled && $config->email_threshold > 0)
		{
			$task = 'notify';
		}
		else
		{
			$task = mt_rand() % 2 ? 'expire' : 'notify';
		}
		
		// 휴면계정을 자동 정리한다.
		if ($task === 'expire')
		{
			// 정리할 휴면계정이 있는지 확인한다.
			$obj = new stdClass();
			$obj->threshold = date('YmdHis', time() - ($config->expire_threshold * 86400) + zgap());
			$obj->list_count = $obj->page_count = $obj->page = 1;
			$obj->orderby = 'asc';
			$members_query = executeQuery('member_expire.getExpiredMembers', $obj);
			
			// 정리할 휴면계정이 있다면 지금 정리한다.
			if ($members_query->toBool() && count($members_query->data))
			{
				$oDB = DB::getInstance();
				$oDB->begin();
				$oModel = getModel('member_expire');
				
				foreach ($members_query->data as $member)
				{
					if ($config->expire_method === 'delete')
					{
						$oModel->deleteMember($member, true, false);
					}
					else
					{
						$oModel->moveMember($member, true, false);
					}
				}
				
				$oDB->commit();
			}
		}
		
		// 휴면 안내메일을 자동 발송한다.
		if ($task === 'notify')
		{
			// 안내할 회원이 있는지 확인한다.
			$obj = new stdClass();
			$obj->threshold = date('YmdHis', time() - ($config->expire_threshold * 86400) + ($config->email_threshold * 86400) + zgap());
			$obj->list_count = $obj->page_count = $obj->page = 1;
			$obj->orderby = 'asc';
			$members_query = executeQuery('member_expire.getUnnotifiedMembers', $obj);
			
			// 안내할 회원이 있다면 지금 안내메일을 발송한다.
			if ($members_query->toBool() && count($members_query->data))
			{
				$oDB = DB::getInstance();
				$oDB->begin();
				$oModel = getModel('member_expire');
				
				foreach ($members_query->data as $member)
				{
					$oModel->sendEmail($member, $config, false, false);
				}
				
				$oDB->commit();
			}
		}
	}
	
	/**
	 * 모듈 실행 전 트리거.
	 * 로그인, 아이디/비번찾기 등 휴면계정을 다시 활성화시키기 위해 꼭 필요한 작업을 할 때
	 * 코어에서 회원정보에 접근할 수 있도록 임시로 member 테이블에 레코드를 옮겨 준다.
	 * 필요없게 되면 모듈 실행 후 트리거에서 원위치시킨다.
	 */
	public function triggerBeforeModuleProc($oModule)
	{
		// 처리가 필요하지 않은 act인 경우 즉시 실행을 종료한다.
		if (!in_array($oModule->act, self::$_acts_to_intercept)) return;
		
		// 이미 로그인했다면 실행을 종료한다.
		if ($_SESSION['member_srl']) return;
		
		// 로그인 및 인증을 위해 입력된 아이디, 메일 주소, 닉네임 또는 member_srl을 파악한다.
		$user_id = Context::get('user_id');
		$email_address = Context::get('email_address');
		$nick_name = Context::get('name') === 'nick_name' ? Context::get('value') : null;
		$member_srl = (!$user_id && !$email_address && !$nick_name && Context::get('auth_key')) ? Context::get('member_srl') : null;
		if (strpos($user_id, '@') !== false)
		{
			$email_address = $user_id;
			$user_id = null;
		}
		if (!$user_id && !$email_address && !$nick_name && !$member_srl)
		{
			return;
		}
		
		// 주어진 정보와 일치하는 회원이 있는지 확인한다.
		$obj = new stdClass();
		if ($user_id)
		{
			$obj->user_id = $user_id;
			$output = executeQuery('member.getMemberSrl', $obj);
		}
		elseif ($email_address)
		{
			$obj->email_address = $email_address;
			$output = executeQuery('member.getMemberSrl', $obj);
		}
		elseif ($nick_name)
		{
			$obj->nick_name = $nick_name;
			$output = executeQuery('member.getMemberSrl', $obj);
		}
		else
		{
			$obj->member_srl = $member_srl;
			$output = executeQuery('member.getMemberInfoByMemberSrl', $obj);
		}
		if ($output->toBool() && count($output->data))
		{
			return;
		}
		
		// 별도의 저장공간으로 이동된 휴면회원 중 주어진 정보와 일치하는 경우가 있는지 확인한다.
		$output = executeQuery('member_expire.getMovedMembers', $obj);
		if (!$output->toBool() || !count($output->data))
		{
			return;
		}
		
		// 자동 복원 기능을 사용하지 않는 경우, 휴면 처리되었다는 메시지를 출력한다.
		$config = $this->getConfig();
		if ($config->auto_restore !== 'Y')
		{
			return new Object(-1, 'msg_your_membership_has_expired');
		}
		
		// 회원정보를 member 테이블로 복사한다.
		$member = reset($output->data);
		$output = getModel('member_expire')->restoreMember($member, true);
		if (!$output)
		{
			return;
		}
		
		// 다시 정리되지 않도록 예외 처리한다.
		$obj = new stdClass();
		$obj->member_srl = $member->member_srl;
		executeQuery('member_expire.insertException', $obj);
		
		// 임시로 복원해 놓았음을 표시하여, 인증 실패시 되돌릴 수 있도록 한다.
		self::$_temp_member_srl = $member->member_srl;
		return;
	}
	
	/**
	 * 모듈 실행 후 트리거.
	 * 임시로 member 테이블에 옮겨놓은 레코드를 원위치시킨다.
	 */
	public function triggerAfterModuleProc($oModule)
	{
		// 실행 전 트리거에서 임시로 복원해 둔 회원이 없다면 여기서도 할 일이 없다.
		if (!self::$_temp_member_srl) return;
		
		// 로그인에 성공했다면 원래대로 돌려놓을 필요가 없다.
		if (!$_SESSION['member_srl'])
		{
			getModel('member_expire')->moveMember(self::$_temp_member_srl, false, true);
		}
		
		// 임시로 예외 등록을 해두었다면 해제한다.
		$obj = new stdClass();
		$obj->member_srl = self::$_temp_member_srl;
		executeQuery('member_expire.deleteException', $obj);
		
		// 로그인 후 전달할 페이지가 지정되어 있다면 redirect URL을 변경한다.
		$config = $this->getConfig();
		if ($oModule->act === 'procMemberLogin' && $_SESSION['member_srl'] && $config->url_after_restore)
		{
			$oModule->setRedirectUrl($config->url_after_restore);
		}
	}
}
