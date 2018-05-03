window.onerror = function(msg, url, lineNo, columnNo, error)
{
	/*console.log(url, msg, lineNo, columnNo);*/

	jQuery.ajax(
	{
		url: script_log.ajax_url,
		type: 'post',
		data: {
			action: 'send_js_debug',
			url: url,
			msg: msg,
			lineNo: lineNo,
			columnNo: columnNo,
		},
		dataType: 'json',
		success: function(data)
		{
			if(data.success)
			{
				
			}

			else if(data.error)
			{
				
			}
		}
	});

	return false;
};