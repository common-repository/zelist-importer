jQuery(document).ready( function() {
	
	function refresh_freeglobes() {
		var step = jQuery('#freeglobes_step').val();
		jQuery('.freeglobes_step').hide();
		jQuery('a#back').hide();
		
		jQuery('.freeglobes_step').each(function(e){
			var div_id = jQuery(this).attr('id');
			var div_step = div_id.substr(16,1);
			if(div_step == step) jQuery(this).show();
		});
		if(step > 1) jQuery('a#back').show();
	}
	
	function switch_to_step(step) {
		jQuery('#freeglobes_step').val(step);
		refresh_freeglobes();
	}
	
	refresh_freeglobes();
	
	jQuery('.action').click(function(e) {
		jQuery(this).hide();
		if(!jQuery('#ajax_response').size()) jQuery('#zelist_import').append('<div id="ajax_response"></div>');
		jQuery('#ajax_response').removeClass('error');
		jQuery('#ajax_response').removeClass('success');
		jQuery('#ajax_response').html('<img src="images/loading.gif" />');
		var response_class  = '';
		
		var action = jQuery(this).attr('id');
		var nonce = jQuery('#import_nonce').val();
		var settings = jQuery('.' + action + '_settings').serialize();
		var settings2 = jQuery('.statics').serialize();

		jQuery.ajax({
			url: 'admin-ajax.php',
			type: 'POST',
			data: ({
				action : action,
				_ajax_nonce : nonce,
				settings : settings,
				static_settings : settings2
				}),
			dataType: 'json',
			timeout: 120000,
			error: function(error) {
			console.info(error);
				jQuery('a.action').show();
				jQuery('#ajax_response').addClass('error');
				jQuery('#ajax_response').html(error.responseText);
				},
			success: function(response) {
				console.info(response);
				jQuery('a.action').show();
				var response_class = '';
				if(response.error) response_class = 'error'; 
				else response_class = 'success';
				jQuery('#ajax_response').addClass(response_class);
				
				if(response.data) jQuery('#ajax_response').html(response.data);
				else jQuery('#ajax_response').html(response);
				
				if(response.step) switch_to_step(response.step);
				
				if(response.inputs) for(var key in response.inputs) {
					if(jQuery('#static_' + key).size() == 0) 
						jQuery('#freeglobes').append('<input type="hidden" class="statics" name="static_'+ key + '" id="static_' + key + '" value="" />');
					jQuery('#static_' + key).val(response.inputs[key]);
				}
				refresh_freeglobes();				
				}
		});
		return false;
	});

	jQuery('a#back').click(function(e) {
		jQuery('.action').show();
		
		var step = jQuery('#freeglobes_step').val();
		if(step > 1) step--;
		else return false;
		
		jQuery('#ajax_response').removeClass('error');
		jQuery('#ajax_response').removeClass('success');
		jQuery('#ajax_response').html('');
		switch_to_step(step);
		return false;
		
	});
});


