
(function($) {
	$(function() {
		
		/**
		 * 설정 화면 날짜 선택.
		 */
		if ($("#auto_start").size()) {
			$("#auto_start_picker").datepicker({
				changeMonth: true,
				changeYear: true,
				gotoCurrent: false,
				yearRange: 'c:+10',
				dateFormat: 'yy-mm-dd',
				onSelect: function() {
					$("#auto_start").val($("#auto_start_picker").val());
				}
			});
			$(".dateRemover").click(function(e) {
				e.preventDefault();
				$(this).prevAll("input").val("");
			});
		}
		
		/**
		 * 휴면회원 일괄 정리.
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
			$("#cleanup_button_area").hide();
			$("#cleanup_progress_area").show();
			$("#cleanup_progress_bar").css("width", "0%");
			ajax_data = {
				"threshold": $("#cleanup_progress_area").data("threshold"),
				"method": $("#cleanup_progress_area").data("method"),
				"batch_count": $("#cleanup_progress_area").data("method") == "delete" ? 10 : 10,
				"total_count": total_count,
				"call_triggers": "Y"
			};
			ajax_callback = function() {
				$.exec_json(
					"member_expire.procMember_expireAdminDoCleanup", ajax_data,
					function(response) {
						if (response.count < 0) {
							alert("오류가 발생했습니다. (코드 " + response.count + ")");
							return;
						}
						ajax_count += response.count;
						if (response.count == 0 || ajax_count >= total_count) {
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
		 * 안내메일 일괄 발송.
		 */
		$("#start_email_send").click(function() {
			var ajax_callback;
			var ajax_data;
			var ajax_count = 0;
			var total_count = $("#cleanup_progress_area").data("count");
			var total_percentage = 0;
			var resend = $("#email_resend").is(":checked");
			if (!($("#notice_agree").is(':checked'))) {
				alert($("#notice_agree").data("nocheck"));
				return;
			}
			if ($("#email_only100").is(":checked")) {
				total_count = Math.min(100, total_count);
			}
			$("#extra_days").prop("disabled", "disabled");
			$("#cleanup_button_area").hide();
			$("#cleanup_progress_area").show();
			$("#cleanup_progress_bar").css("width", "0%");
			ajax_data = {
				"threshold": $("#cleanup_progress_area").data("threshold"),
				"method": $("#cleanup_progress_area").data("method"),
				"batch_count": 3,
				"total_count": total_count,
				"resend": resend ? "Y" : "N"
			};
			ajax_callback = function() {
				$.exec_json(
					"member_expire.procMember_expireAdminDoSendEmail", ajax_data,
					function(response) {
						if (response.count < 0) {
							alert("오류가 발생했습니다. (코드 " + response.count + ")");
							return;
						}
						ajax_count += response.count;
						if (response.count == 0 || ajax_count >= total_count) {
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
		 * 안내메일 일괄 발송 기준 날짜 변경.
		 */
		$("#extra_days").on("change", function() {
			window.location.href = window.location.href.setQuery("extra_days", $(this).val());
		});
		
		/**
		 * 안내메일 미리보기 작성.
		 */
		$("#email_preview").each(function() {
			var subject = $("#email_subject");
			var preview = $(this);
			var editor_sequence = preview.data("editor-sequence");
			if (!editor_sequence) return;
			if (!editorGetContent) return;
			var escapes = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			var macros = {};
			$("#cleanup_macro_table tr").each(function() {
				var key = $(this).find("th").text();
				var valuetd = $(this).find("td:last-child");
				var value = valuetd.data("actual-value") ? valuetd.data("actual-value") : valuetd.text().replace("예: ", "");
				macros[key] = value.replace(/[&<>"']/g, function(match) {
					return escapes[match];
				});
			});
			var replace_macros = function(content) {
				return content.replace(/\{[A-Z_]+\}/g, function(match) {
					return macros[match] ? macros[match] : match;
				});
			};
			setInterval(function() {
				var content = editorGetContent(editor_sequence);
				preview.html('<p class="email_subject">' + replace_macros(subject.val()) + '</p>' + "\n\n" + replace_macros(content));
			}, 1000);
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
					"call_triggers": "Y"
				},
				function(response) {
					if (response.count > 0) {
						container.find("a.do_expire_member").remove();
						container.append("정리완료");
						alert("정리되었습니다.");
					} else {
						alert("정리에 실패했습니다. (코드 " + response.count + ")");
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
					"call_triggers": "Y"
				},
				function(response) {
					if (response.restored > 0) {
						container.find("a.do_restore_member").remove();
						container.append("복원완료");
						alert("복원되었습니다.");
					} else {
						alert("복원에 실패했습니다. (코드 " + response.restored + ")");
					}
				},
				function(response) {
					alert("복원에 실패했습니다.");
				}
			);
		});
		
		/**
		 * 별도의 저장공간으로 이동된 개별 회원을 직접 삭제한다.
		 */
		$("a.do_delete_member").click(function(event) {
			event.preventDefault();
			var container = $(this).parent();
			var member_srl = $(this).data("member-srl");
			if (!member_srl) return;
			$.exec_json(
				"member_expire.procMember_expireAdminDeleteMember", {
					"member_srl": member_srl,
					"call_triggers": "Y"
				},
				function(response) {
					if (response.deleted > 0) {
						container.find("a.do_delete_member").remove();
						container.append("삭제완료");
						alert("삭제되었습니다.");
					} else {
						alert("삭제에 실패했습니다. (코드 " + response.deleted + ")");
					}
				},
				function(response) {
					alert("삭제에 실패했습니다.");
				}
			);
		});
		
		/**
		 * 별도의 저장공간으로 이동된 회원 중 현재 화면에 표시되는 화원들을 일괄 삭제한다.
		 */
		$("#delete_moved_on_this_page").click(function(event) {
			event.preventDefault();
			var member_srls = [];
			$("a.do_delete_member").each(function() {
				member_srls.push($(this).data("member-srl"));
			});
			if (!member_srls.length) return;
			$.exec_json(
				"member_expire.procMember_expireAdminDeleteMember", {
					"member_srls": member_srls,
					"call_triggers": "Y"
				},
				function(response) {
					window.location.reload();
				},
				function(response) {
					alert("일괄 삭제에 실패했습니다.");
				}
			);
		});
		
		/**
		 * 예외 회원을 해제한다.
		 */
		$("a.do_remove_exception").click(function(event) {
			event.preventDefault();
			var container = $(this).parent();
			var member_srl = $(this).data("member-srl");
			if (!member_srl) return;
			$.exec_json(
				"member_expire.procMember_expireAdminDeleteException", {
					"member_srl": member_srl,
					"call_triggers": "Y"
				},
				function(response) {
					if (response.removed > 0) {
						container.find("a.do_remove_exception").remove();
						container.append("해제됨");
						alert("예외 해제되었습니다.");
					} else {
						alert("예외 해제에 실패했습니다. (코드 " + response.removed + ")");
					}
				},
				function(response) {
					alert("예외 해제에 실패했습니다.");
				}
			);
		});
		
		/**
		 * 한 화면에 표시할 레코드 수를 조정한다.
		 */
		$('#list_count').change(function () {
			location.href = location.href.setQuery('list_count', $(this).val());
		});
	});
}(jQuery));
