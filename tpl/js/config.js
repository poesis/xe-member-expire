
(function($) {
	$(function() {
		
		/**
		 * 휴면회원 정리 루틴.
		 */
		$("#start_cleanup").click(function() {
			var ajax_callback;
			var ajax_data;
			var ajax_count = 0;
			var total_count = $("#cleanup_progress_area").data("count");
			var total_percentage = 0;
			if (!($("#notice_agree").is(':checked'))) {
				alert($("#notice_agree").data("nocheck"));
				return;
			}
			$("#start_cleanup").hide();
			$("#cleanup_progress_area").show();
			$("#cleanup_progress_bar").css("width", "0%");
			ajax_data = {
				"threshold": $("#cleanup_progress_area").data("threshold"),
				"method": $("#cleanup_progress_area").data("method"),
				"batch_count": $("#cleanup_progress_area").data("method") == "delete" ? 10 : 10,
				"total_count": total_count
			};
			ajax_callback = function() {
				$.exec_json(
					"member_expire.procMember_expireAdminDoCleanup", ajax_data,
					function(response) {
						ajax_count += response.count;
						if (response.count < 1 || ajax_count >= total_count) {
							$("#cleanup_progress_bar").css("width", "100%");
							$("#cleanup_progress_number_area").hide();
							$("#cleanup_progress_finish_area").show();
						} else {
							total_percentage = ((ajax_count / total_count) * 100).toFixed(1);
							$("#cleanup_progress_bar").css("width", total_percentage + "%");
							$("#cleanup_progress_number").text(total_percentage);
							setTimeout(ajax_callback, 20);
						}
					},
					function(response) {
						alert("오류가 발생했습니다.");
					}
				);
			};
			ajax_callback();
		});
		
		/**
		 * 정리대상 개별 회원을 직접 정리한다.
		 */
		$("a.do_expire_member").click(function(event) {
			event.preventDefault();
			var container = $(this).parent();
			var member_srl = $(this).data("member-srl");
			if (!member_srl) return;
			$.exec_json(
				"member_expire.procMember_expireAdminDoCleanup", {
					"member_srl": member_srl,
				},
				function(response) {
					if (response.count > 0) {
						container.find("a.do_expire_member").remove();
						container.append("정리완료");
						alert("정리되었습니다.");
					} else {
						alert("정리에 실패했습니다.");
					}
				},
				function(response) {
					alert("정리에 실패했습니다.");
				}
			);
		});
		
		/**
		 * 별도의 저장공간으로 이동된 개별 회원을 직접 복원한다.
		 */
		$("a.do_restore_member").click(function(event) {
			event.preventDefault();
			var container = $(this).parent();
			var member_srl = $(this).data("member-srl");
			if (!member_srl) return;
			$.exec_json(
				"member_expire.procMember_expireAdminRestoreMember", {
					"member_srl": member_srl,
				},
				function(response) {
					if (response.restored > 0) {
						container.find("a.do_restore_member").remove();
						container.append("복원완료");
						alert("복원되었습니다.");
					} else {
						alert("복원에 실패했습니다.");
					}
				},
				function(response) {
					alert("복원에 실패했습니다.");
				}
			);
		});
	});
}(jQuery));
