window.onerror = function(msg, url, lineNo, columnNo, error)
{
	jQuery.ajax(
	{
		url: script_log.ajax_url,
		type: 'post',
		dataType: 'json',
		data: {
			action: 'api_log_js_debug',
			url: url,
			msg: msg,
			lineNo: lineNo,
			columnNo: columnNo,
		},
		success: function(data)
		{
			if(data.success){}
			else if(data.error){}
		}
	});

	return false;
};