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
class Member_ExpireAdminController extends Member_Expire
{
	/**
	 * 모듈 설정을 저장하는 메소드.
	 */
	public function procMember_ExpireAdminInsertConfig()
	{
		// 새로 저장하려는 설정을 가져온다.
		$request_vars = Context::getRequestVars();
		$new_config = $this->getConfig();
		$new_config->expire_threshold = $request_vars->expire_threshold;
		$new_config->expire_method = $request_vars->expire_method;
		$new_config->auto_expire = $request_vars->auto_expire === 'Y' ? 'Y' : 'N';
		$new_config->auto_restore = $request_vars->auto_restore === 'Y' ? 'Y' : 'N';
		$new_config->email_threshold = $request_vars->auto_notify ? $request_vars->auto_notify : 0;
		
		// 자동 정리 옵션을 선택한 경우, 현재 남아 있는 휴면계정 수를 구한다.
		if ($new_config->auto_expire === 'Y')
		{
			$obj = new stdClass();
			$obj->is_admin = 'N';
			$obj->threshold = date('YmdHis', time() - ($config->expire_threshold * 86400) + zgap());
			$expired_members_count = executeQuery('member_expire.countExpiredMembers', $obj);
			$expired_members_count = $expired_members_count->toBool() ? $expired_members_count->data->count : 0;
			if ($expired_members_count > 50)
			{
				return new Object(-1, 'msg_too_many_expired_members');
			}
		}
		
		// 새 모듈 설정을 저장한다.
		$output = getController('module')->insertModuleConfig('member_expire', $new_config);
		if ($output->toBool())
		{
			$this->setMessage('success_registed');
		}
		else
		{
			return $output;
		}
		
		// 반환 URL로 돌려보낸다.
		if (Context::get('success_return_url'))
		{
			$this->setRedirectUrl(Context::get('success_return_url'));
		}
		else
		{
			$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'member_expire', 'act', 'dispMember_expireAdminConfig'));
		}
	}
	
	/**
	 * 안내메일 내용 템플릿을 저장하는 메소드.
	 */
	public function procMember_ExpireAdminInsertEmailTemplate()
	{
		// 새로 저장하려는 설정을 가져온다.
		$request_vars = Context::getRequestVars();
		$new_config = $this->getConfig();
		$new_config->email_subject = $request_vars->email_subject;
		$new_config->email_content = $request_vars->email_content;
		
		// 새 모듈 설정을 저장한다.
		$output = getController('module')->insertModuleConfig('member_expire', $new_config);
		if ($output->toBool())
		{
			$this->setMessage('success_registed');
		}
		else
		{
			return $output;
		}
		
		// 반환 URL로 돌려보낸다.
		if (Context::get('success_return_url'))
		{
			$this->setRedirectUrl(Context::get('success_return_url'));
		}
		else
		{
			$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'member_expire', 'act', 'dispMember_expireAdminConfig'));
		}
	}
	
	/**
	 * 안내메일을 실제 발송하는 메소드.
	 */
	public function procMember_ExpireAdminDoSendEmail()
	{
		
	}
	
	/**
	 * 휴면계정을 실제 정리하는 메소드. member_srl만 넣을 경우 개별 회원만 정리할 수도 있다.
	 */
	public function procMember_ExpireAdminDoCleanup($member_srl = null, $call_triggers = null)
	{
		// 정리 설정을 가져온다.
		$config = $this->getConfig();
		$request_vars = Context::getRequestVars();
		if ($member_srl === null)
		{
			$member_srl = $request_vars->member_srl ? $request_vars->member_srl : 0;
		}
		$threshold = $request_vars->threshold ? $request_vars->threshold : $config->expire_threshold;
		$method = $request_vars->method ? $request_vars->method : $config->expire_method;
		$total_count = $request_vars->total_count ? $request_vars->total_count : 10;
		$batch_count = $request_vars->batch_count ? $request_vars->batch_count : 10;
		if ($call_triggers === null)
		{
			$call_triggers = $request_vars->call_triggers === 'Y' ? true : false;
		}
		$done_count = 0;
		
		// 트랜잭션을 시작한다.
		$oDB = &DB::getInstance();
		$oDB->begin();
		
		// member 컨트롤러를 불러온다.
		$oMemberController = getController('member');
		
		// 정리 방법에 따라 처리한다.
		switch ($method)
		{
			// 삭제.
			case 'delete':
				
				// 정리 대상 member_srl들을 불러온다.
				if ($member_srl)
				{
					$member_srls = array($member_srl);
				}
				else
				{
					$args = new stdClass();
					$obj->is_admin = 'N';
					$obj->threshold = date('YmdHis', time() - ($threshold * 86400) + zgap());
					$obj->list_count = $batch_count;
					$obj->page = 1;
					$obj->orderby = 'asc';
					$member_srls_query = executeQuery('member_expire.getExpiredMemberSrlOnly', $obj);
					if (!$member_srls_query->toBool())
					{
						$oDB->rollback(); $this->add('count', -1); return;
					}
					$member_srls = array();
					foreach ($member_srls_query->data as $member_srls_item)
					{
						$member_srls[] = $member_srls_item->member_srl;
					}
				}
				
				// 각각의 member_srl 및 관련정보를 삭제한다.
				foreach ($member_srls as $member_srl)
				{
					$args = new stdClass();
					$args->member_srl = $member_srl;
					if ($call_triggers)
					{
						$output = ModuleHandler::triggerCall('member.deleteMember', 'before', $args);
						if (!$output->toBool())
						{
							$oDB->rollback(); $this->add('count', -11); return;
						}
					}
					$output = executeQuery('member.deleteAuthMail', $args);
					if (!$output->toBool())
					{
						$oDB->rollback(); $this->add('count', -2); return;
					}
					$output = executeQuery('member.deleteMemberGroupMember', $args);
					if (!$output->toBool())
					{
						$oDB->rollback(); $this->add('count', -3); return;
					}
					$output = executeQuery('member.deleteMember', $args);
					if (!$output->toBool())
					{
						$oDB->rollback(); $this->add('count', -4); return;
					}
					if ($call_triggers)
					{
						$output = ModuleHandler::triggerCall('member.deleteMember', 'after', $args);
						if (!$output->toBool())
						{
							$oDB->rollback(); $this->add('count', -12); return;
						}
					}
					$oMemberController->procMemberDeleteImageName($member_srl);
					$oMemberController->procMemberDeleteImageMark($member_srl);
					$oMemberController->procMemberDeleteProfileImage($member_srl);
					$oMemberController->delSignature($member_srl);
					$oMemberController->_clearMemberCache($member_srl);
					$done_count++;
				}
				break;
			
			// 이동.
			case 'move':
				
				// 정리 대상 회원정보 전체를 불러온다.
				if ($member_srl)
				{
					$args = new stdClass();
					$args->member_srl = $member_srl;
					$members_query = executeQuery('member.getMemberInfoByMemberSrl', $args);
					if (!$members_query->toBool())
					{
						$oDB->rollback(); $this->add('count', -5); return;
					}
					$members = $members_query->data ? $members_query->data : array();
					if (is_object($members)) $members = array($members);
				}
				else
				{
					$args = new stdClass();
					$obj->is_admin = 'N';
					$obj->threshold = date('YmdHis', time() - ($threshold * 86400) + zgap());
					$obj->list_count = $batch_count;
					$obj->page = 1;
					$obj->orderby = 'asc';
					$members_query = executeQuery('member_expire.getExpiredMembers', $obj);
					if (!$members_query->toBool())
					{
						$oDB->rollback(); $this->add('count', -6); return;
					}
					$members = $members_query->data ? $members_query->data : array();
				}
				
				// 각 회원정보를 member_expired 테이블로 이동한다. 소속 그룹 정보, 이미지 등은 그대로 유지한다.
				foreach ($members as $member)
				{
					$output = executeQuery('member_expire.insertMovedMember', $member);
					if (!$output->toBool())
					{
						$output = executeQuery('member_expire.deleteMovedMember', $member);
						$output = executeQuery('member_expire.insertMovedMember', $member);
						if (!$output->toBool())
						{
							$oDB->rollback(); $this->add('count', -7); return;
						}
					}
					$output = executeQuery('member.deleteAuthMail', $member);
					if (!$output->toBool())
					{
						$oDB->rollback(); $this->add('count', -8); return;
					}
					$output = executeQuery('member.deleteMember', $member);
					if (!$output->toBool())
					{
						$oDB->rollback(); $this->add('count', -9); return;
					}
					$oMemberController->_clearMemberCache($member->member_srl);
					$done_count++;
				}
				break;
			
			// 기타.
			default:
				$done_count = -10;
		}
		
		// 트랜잭션을 커밋한다.
		$oDB->commit();
		
		// 정리된 결과 수를 반환한다.
		$this->add('count', $done_count);
		return true;
	}
	
	/**
	 * 개별 휴면계정을 복원하는 메소드.
	 */
	public function procMember_ExpireAdminRestoreMember($member_srl = null)
	{
		// 회원번호를 파악한다.
		if ($member_srl === null)
		{
			$member_srl = Context::get('member_srl');
			if (!$member_srl)
			{
				$this->add('restored', -1); return;
			}
		}
		
		// 이동되어 있는지 확인한다.
		$obj = new stdClass();
		$obj->member_srl = $member_srl;
		$member = executeQuery('member_expire.getMovedMembers', $obj);
		$member = $member->toBool() ? reset($member->data) : false;
		if (!$member)
		{
			$this->add('restored', -2); return;
		}
		
		// 트랜잭션을 시작한다.
		$oDB = DB::getInstance();
		$oDB->begin();
		
		// 회원정보를 member 테이블로 복사한다.
		$output = executeQuery('member_expire.insertRestoredMember', $member);
		if (!$output->toBool())
		{
			$output = executeQuery('member.deleteMember', $member);
			$output = executeQuery('member_expire.insertRestoredMember', $member);
			if (!$output->toBool())
			{
				$oDB->rollback(); $this->add('restored', -3); return;
			}
		}
		
		// member_expire 테이블에서 삭제한다.
		$output = executeQuery('member_expire.deleteMovedMember', $member);
		if (!$output->toBool())
		{
			$oDB->rollback(); $this->add('restored', -4); return;
		}
		
		// 트랜잭션을 커밋한다.
		$oDB->commit();
		
		// 복원 완료 메시지를 반환한다.
		$this->add('restored', 1);
		return true;
	}
}
