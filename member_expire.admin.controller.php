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
		$new_config->auto_start = $request_vars->auto_start ? $request_vars->auto_start : date('Y-m-d', time() + zgap());
		$new_config->email_threshold = $request_vars->auto_notify ? $request_vars->auto_notify : 0;
		
		// 자동 정리 옵션을 선택한 경우, 현재 남아 있는 휴면계정 수를 구한다.
		if ($new_config->auto_expire === 'Y' || $new_config->email_threshold)
		{
			$obj = new stdClass();
			$obj->threshold = date('YmdHis', time() - ($new_config->expire_threshold * 86400) + zgap());
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
			$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispMember_expireAdminConfig'));
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
			$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispMember_expireAdminConfig'));
		}
	}
	
	/**
	 * 안내메일 발송 내역을 정리하는 메소드.
	 */
	public function procMember_ExpireAdminClearSentEmail()
	{
		// 정리 설정을 가져온다.
		$request_vars = Context::getRequestVars();
		$threshold = intval($request_vars->clear_threshold);
		
		// 정리한다.
		if ($threshold >= 0)
		{
			$args = new stdClass();
			$args->threshold = date('YmdHis', time() - ($threshold * 86400) + zgap());
			$output = executeQuery('member_expire.deleteNotifiedDate', $args);
		}
		
		// 목록 페이지로 돌려보낸다.
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispMember_expireAdminEmailList'));
		return;
	}
	
	/**
	 * 안내메일을 실제 발송하는 메소드.
	 */
	public function procMember_ExpireAdminDoSendEmail()
	{
		// 정리 설정을 가져온다.
		$config = $this->getConfig();
		$request_vars = Context::getRequestVars();
		$member_srl = $request_vars->member_srl ? $request_vars->member_srl : 0;
		$config->expire_threshold = $request_vars->threshold ? $request_vars->threshold : $config->expire_threshold;
		$config->expire_method = $request_vars->method ? $request_vars->method : $config->expire_method;
		$resend = $request_vars->resend === 'Y' ? true : false;
		$total_count = $request_vars->total_count ? $request_vars->total_count : 3;
		$batch_count = $request_vars->batch_count ? $request_vars->batch_count : 3;
		$done_count = 0;
		
		// 트랜잭션을 시작한다.
		$oDB = DB::getInstance();
		$oDB->begin();
		
		// 모델을 불러온다.
		$oModel = getModel('member_expire');
		
		// 발송 대상 회원정보 전체를 불러온다.
		if ($member_srl)
		{
			$args = new stdClass();
			$args->member_srl = $member_srl;
			$members_query = executeQuery('member.getMemberInfoByMemberSrl', $args);
			if (!$members_query->toBool())
			{
				$oDB->rollback(); $this->add('count', -3); return;
			}
			$members = $members_query->data ? $members_query->data : array();
			if (is_object($members)) $members = array($members);
		}
		else
		{
			$args = new stdClass();
			$args->threshold = date('YmdHis', time() - ($config->expire_threshold * 86400) + ($config->email_threshold * 86400) + zgap());
			$args->list_count = $batch_count;
			$args->page = 1;
			$args->orderby = 'asc';
			$members_query = executeQuery('member_expire.getUnnotifiedMembers', $args);
			if (!$members_query->toBool())
			{
				$oDB->rollback(); $this->add('count', -4); return;
			}
			$members = $members_query->data ? $members_query->data : array();
		}
		
		// 각 회원에게 메일을 발송한다.
		foreach ($members as $member)
		{
			$result = $oModel->sendEmail($member, $config, $resend, false);
			if ($result < 0)
			{
				$oDB->rollback(); $this->add('count', $result); return;
			}
			$done_count++;
		}
		
		// 트랜잭션을 커밋한다.
		$oDB->commit();
		
		// 발송된 결과 수를 반환한다.
		$this->add('count', $done_count);
		return true;
	}
	
	/**
	 * 개별 휴면계정 또는 주어진 갯수만큼의 휴면계정을 정리하는 메소드.
	 */
	public function procMember_ExpireAdminDoCleanup()
	{
		// 정리 설정을 가져온다.
		$config = $this->getConfig();
		$request_vars = Context::getRequestVars();
		$member_srl = $request_vars->member_srl ? $request_vars->member_srl : 0;
		$threshold = $request_vars->threshold ? $request_vars->threshold : $config->expire_threshold;
		$method = $request_vars->method ? $request_vars->method : $config->expire_method;
		$total_count = $request_vars->total_count ? $request_vars->total_count : 10;
		$batch_count = $request_vars->batch_count ? $request_vars->batch_count : 10;
		$done_count = 0;
		
		// 트랜잭션을 시작한다.
		$oDB = DB::getInstance();
		$oDB->begin();
		
		// 모델을 불러온다.
		$oModel = getModel('member_expire');
		
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
					$args->threshold = date('YmdHis', time() - ($threshold * 86400) + zgap());
					$args->list_count = $batch_count;
					$args->page = 1;
					$args->orderby = 'asc';
					$member_srls_query = executeQuery('member_expire.getExpiredMemberSrlOnly', $args);
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
					$result = $oModel->deleteMember($member_srl, true, false);
					if ($result < 0)
					{
						$oDB->rollback(); $this->add('count', $result); return;
					}
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
					$args->threshold = date('YmdHis', time() - ($threshold * 86400) + zgap());
					$args->list_count = $batch_count;
					$args->page = 1;
					$args->orderby = 'asc';
					$members_query = executeQuery('member_expire.getExpiredMembers', $args);
					if (!$members_query->toBool())
					{
						$oDB->rollback(); $this->add('count', -6); return;
					}
					$members = $members_query->data ? $members_query->data : array();
				}
				
				// 각 회원정보를 member_expired 테이블로 이동한다.
				foreach ($members as $member)
				{
					$result = $oModel->moveMember($member, true, false);
					if ($result < 0)
					{
						$oDB->rollback(); $this->add('count', $result); return;
					}
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
	public function procMember_ExpireAdminRestoreMember()
	{
		// 복원할 member_srl을 가져온다.
		$member_srl = Context::get('member_srl');
		if (!$member_srl)
		{
			$this->add('restored', -1);
			return;
		}
		
		// 복원한다.
		$oModel = getModel('member_expire');
		$result = $oModel->restoreMember($member_srl, true);
		if ($result < 0)
		{
			$this->add('restored', $result);
			return;
		}
		
		// 복원 완료 메시지를 반환한다.
		$this->add('restored', 1);
		return true;
	}
	
	/**
	 * 개별 휴면계정을 삭제하는 메소드.
	 */
	public function procMember_ExpireAdminDeleteMember()
	{
		// 복원할 member_srl을 가져온다.
		$member_srl = Context::get('member_srl');
		if (!$member_srl)
		{
			$this->add('deleted', -1);
			return;
		}
		
		// 트랜잭션을 시작한다.
		$oDB = DB::getInstance();
		$oDB->begin();
		
		// 삭제한다.
		$oModel = getModel('member_expire');
		$result = $oModel->restoreMember($member_srl, false);
		if ($result < 0)
		{
			$oDB->rollback(); $this->add('deleted', $result); return;
		}
		$result = $oModel->deleteMember($member_srl, true, false);
		if ($result < 0)
		{
			$oDB->rollback(); $this->add('deleted', $result); return;
		}
		
		// 트랜잭션을 커밋한다.
		$oDB->commit();
		
		// 복원 완료 메시지를 반환한다.
		$this->add('deleted', 1);
		return true;
	}
}
