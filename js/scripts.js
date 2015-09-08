jQuery(document).ready(function () {

	if(jQuery('#tabs').size() != 0) {
		jQuery('#tabs').tabs({
				activate: function(event, ui) {
					window.location.hash = ui.newTab.context.hash;
				}
			}
		);
	}


	jQuery(".btn_delete").on("click", function() {

		if(window.confirm("delete realy?") == false) {
			return;
		}

		jQuery("#input_target_posttype").val(jQuery(this).attr("target"));

		var data = {
			action: 'meta_accelerator_remove',
			target: jQuery("#input_target_posttype").val()
		};

		jQuery.ajax({
			type: "post",
			url: ajaxurl,
			data: data,
			dataType: "json",
			success: function(response) {
				// append and call next step
				alert(response.msg);
				location.reload();
			},
			error: function(xhr, status, err) {
        // append error
				alert(xhr.responseText);
				location.reload(true);
				}
		});

	});

	jQuery(".btn_add").on("click", function() {
		if(window.confirm("create accelerator is ready?") == false) {
			return;
		}


		jQuery("#input_target_posttype").val(jQuery(this).attr("target"));

		var data = {
			action: 'meta_accelerator_add',
			target: jQuery("#input_target_posttype").val()
		};


		jQuery(".proceed_img" + data.target).css({"display": "inline-block"});
		jQuery(".proceed" + data.target).children().remove();
		jQuery(".proceed" + data.target).append(jQuery("<span />").text("creating"));

		jQuery.ajax({
			type: "post",
			url: ajaxurl,
			data: data,
			dataType: "json",
			success: function(response) {
				// append and call next step
				if(response.rsl) {
					if(response.next != "none") {
						// 次へ
						var str = "step: " + response.next + "/" + response.total;
						jQuery(".proceed" + response.target).children().remove();
						jQuery(".proceed" + response.target).append(jQuery("<span />").text(str));

						meta_accelerator_build(response.next, response.target);
					} else {
						alert("created");
						jQuery(".proceed_img").css({"display": "none"});
						location.reload();
					}

				} else {
					alert(response.msg);
					jQuery(".proceed_img").css({"display": "none"});

				}
			},
			error: function(xhr, status, err) {

	            // append error
				alert(xhr.responseText);
				jQuery(".proceed_img").css({"display": "none"});
				}
		});
	});


	jQuery('.btn_showdetail').on('click', function() {

		jQuery('.tbl_detail_' + jQuery(this).attr('target')).toggle();

		return false;
	})

});

var meta_accelerator_build = function(batch_paged, target) {
	var data = {
		action: 'meta_accelerator_add',
		target: target,
		batch_paged: batch_paged
	};


	jQuery.ajax({
		type: "post",
		url: ajaxurl,
		data: data,
		dataType: "json",
		success: function(response) {
			// append and call next step
			if(response.rsl) {
				if(response.next != "none") {
					// 次へ
					var str = "step: " + response.next + "/" + response.total;
					jQuery(".proceed" + response.target).children().remove();
					jQuery(".proceed" + response.target).append(jQuery("<span />").text(str));

					meta_accelerator_build(response.next, response.target);

				} else {
					alert("created");
					jQuery(".proceed_img").css({"display": "none"});
					location.reload();
				}

			} else {
				alert(response.msg);
				jQuery(".proceed_img").css({"display": "none"});

			}
		},
		error: function(xhr, status, err) {
            // append error
			alert(xhr.responseText);

			}
	});

}