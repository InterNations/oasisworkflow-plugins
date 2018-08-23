function add_option_to_select(obj,dt,lbl,vl) {
	var sel = jQuery("#" + obj).find('option');
	sel.remove();
	var appendStr = "";
	if (numKeys(dt) > 1 && !jQuery("#" + obj).attr("size")) {
		appendStr = "<option></option>";
	}

	/*
	 If only user found then select it
	 */
	var selected_opt = '';
	if (numKeys(dt) == 1) {
		selected_opt = " selected=selected ";
	}


	if (typeof(dt) == "object" && numKeys(dt) > 0 && lbl) {
		for (var k in dt) {
			if (vl) {
				appendStr += "<option value='" + dt[k][vl] + "' " + selected_opt + " >" + dt[k][lbl] + "</option>";
			}
			else {
				appendStr += "<option value='" + k + "'>" + dt[k][lbl] + "</option>";
			}
		}
		jQuery('#' + obj).append(appendStr);

	}

	if (!lbl && !vl) {
		for (var k in dt) {
			appendStr += "<option value='" + k + "'>" + dt[k] + "</option>";
		}
		jQuery('#' + obj).append(appendStr);
	}

	if (numKeys(dt) == 1) {
		if (typeof jQuery("#assignee-set-point") != 'undefined') {
			jQuery("#assignee-set-point").click();
		}
	}
}

function numKeys(obj)
{
    var count = 0;
    for(var prop in obj)
    {
        count++;
    }
    return count;
}

/*
 * Opens a new window to show the revision compare
 * 
 * @since 3.2
 */
function revision_compare_popup( link_text, click_here_text, absolute_url ) {
    var revision_id = jQuery("#post_ID").val();
    var nonce = jQuery('#owf_compare_revision_nonce').val();
    // save the post, so that we can pass the changes to the revision compare page
    jQuery("#save-post").click();
        
    // open a popup window with 90% height and width. We will use this window to show the revision compare results.
    var h = (screen.height * 90)/100;
    var w = (screen.width * 90)/100;
    
    // link to the revision compare page
    var revision_link = absolute_url + "post.php?page=oasiswf-revision&revision=" + revision_id + "&_nonce=" + nonce;
    
    var compare_window = window.open("about:blank", "Revision_Compare", "height=" + h + ",width=" + w + ",scrollbars=yes");
    compare_window.document.write( link_text + " <a href='" + revision_link + "'>" + click_here_text + "</a>");
    var delay = 2000; //2 seconds, just to give the save-post a chance to save the post before it's used for compare
    setTimeout(function(){
    	compare_window.location.assign( revision_link );
    	return false;
    }, delay );
}

/*
 * function to escape special characters specially when dealing with json data
 */
String.prototype.escapeSpecialChars = function() {
    return this.replace(/\\n/g, "\\n")
               .replace(/\\'/g, "\\'")
               .replace(/\\"/g, '\\"')
               .replace(/\\&/g, "\\&")
               .replace(/\\r/g, "\\r")
               .replace(/\\t/g, "\\t")
               .replace(/\\b/g, "\\b")
               .replace(/\\f/g, "\\f");
};

function normalWorkFlowSubmit(submitFunction) {
	submitFunction();
}

var jQueryACFValidation = jQuery.noConflict();
(function(jQuery){
	owThirdPartyValidation = {
		run : function (fnParam) {
			if ( owf_workflow_util_vars.isACFEnabled === "yes" ) {
				workflowSubmitWithACF(fnParam);
			}
			else {
				normalWorkFlowSubmit(fnParam);
			}									
		}
	};
} (jQueryACFValidation));

/**
 * Unescape HTML entities in Javascript - http://stackoverflow.com/questions/1912501/unescape-html-entities-in-javascript
 *
 * @param input
 * @returns {string|JQuery}
 */
function html_decode(input){
	var doc = new DOMParser().parseFromString(input, "text/html");
	return doc.documentElement.textContent;
}

/*
 * function to get the query string parameter from the url
 */
function get_given_query_string_value_from_url(name, url) {
      url = url.toLowerCase(); // This is just to avoid case sensitiveness
      name = name.replace(/[\[\]]/g, "\\$&").toLowerCase();// This is just to avoid case sensitiveness for query parameter name
      var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
              results = regex.exec(url);
      if (!results)
         return null;
      if (!results[2])
         return '';
      return decodeURIComponent(results[2].replace(/\+/g, " "));
   }
