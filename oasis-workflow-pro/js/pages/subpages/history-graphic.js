var jQueryCgmp = jQuery.noConflict();
(function (jQuery) {
	jQuery(document).ready(function($) {
		var max_height = "" ;
		var min_top = 1000 ;
		jQuery("#graphic").removeClass("closed") ;
		window.workflowGraphic = {			
			init :function() {							
				jsPlumb.importDefaults({
					Endpoint : ["Dot", {radius:2}],				
					//overlays:[ ["PlainArrow", {location:1, width:20, length:12} ]],
					connectorStyle:{ strokeStyle:"blue", lineWidth:1 },
					//HoverPaintStyle : {strokeStyle:"#42a62c", lineWidth:8 },
					//paintStyle:{
					//	lineWidth:3,
					//	strokeStyle: "blue"
					//},
					anchor:"Continuous",
					ConnectionOverlays : [
						[ "Arrow", { 
							location:1,
							id:"arrow",
		                    length:0,
		                    foldback:0.8
						} ]					
					],
					maxConnections:-1
				});		
			}	
		};
		
		//-----------------------------
	    set_max_height = function(h){
	    	var h = h.replace("px","");
	    	if( max_height * 1 < h * 1 )max_height = h ;    		
	    }
	    
	    set_min_top = function(t){
	    	var t = t.replace("px","");
	    	if( min_top * 1 > t * 1 )min_top = t; 
	    }
	    
		var __createStep = function(objid) {   	
	    	jsPlumb.makeTarget(jsPlumb.getSelector("#"+objid), { anchor:"Continuous" } );    	
			jsPlumb.makeSource($('#ep-'+objid), {
				parent:$('#ep-'+objid).parent(),
				anchor:"Continuous",			
				//connectorStyle:{ strokeStyle:"blue", lineWidth:2 },
				maxConnections:-1			
			});		
	    }    
	    var createStep = function(param, act) {
	    	jQuery("#workflow-area").append(	"<div class='w' id='" + param["fc_addid"] + "'" +
						    				"' real='" + act + "'" +
						    				" db-id=" + param["fc_dbid"] + 
						    				" process-name='" + param["fc_process"] + "'>" +
						    				"<img alt='' src='" + wfPluginUrl + "img/" + param["fc_process"] +".gif' />" +
						    				"<label>" + param["fc_label"] + "</label>" + 
						    			"</div>" );
	    	
			jQuery("#" + param["fc_addid"]).append("<div class='ep' id='ep-" + param["fc_addid"] + "'></div>");
			jQuery("#" + param["fc_addid"]).css({'top': param["fc_position"][0], 'left': param["fc_position"][1]});
			__createStep(param["fc_addid"]);
			set_max_height(param["fc_position"][0]) ;
			set_min_top(param["fc_position"][0]) ;
	    };
	    
	    _graphic_make = function(param){    	
		   	var wfinfo = {};
		   	wfinfo = jQuery.parseJSON(param);
		   	
		   	if(typeof(wfinfo) != 'object'){
		   		alert("Graphic data is bad");
		   		return;
		   	}
		   	for( var w in wfinfo["steps"]){
		   		createStep(wfinfo["steps"][w],"old");
		   	}
		   	
		   	for( var k in wfinfo["conns"]){
		   		jsPlumb.connect({
						source:wfinfo["conns"][k]["sourceId"],
						target:wfinfo["conns"][k]["targetId"]
					}, wfinfo["conns"][k]["connset"]);
		   	}
	   }
	    jsPlumb.bind("ready", function() {   
	    	// chrome fix.
	    	document.onselectstart = function () { return false; };    	
	    	workflowGraphic.init() ;	
	    	
	    	_graphic_make(stepinfo) ;      	 
		   	 if(max_height){
			    	 max_height = max_height*1 + 70 ;
			    	 jQuery("#workflow-area").css("height", max_height + "px");
		   	 }
		   	 jQuery("#" + currentStepGpId).css("background-color", "#42a62c");
		   	 jQuery("#" + currentStepGpId).children("label").css("color", "white") ;
		   	 var ttop = min_top * 1 - 30 ;
		   	 if( ttop > 0 ){
		   		//jQuery("#workflow-area").css("margin-top","-" + ttop + "px");
		   	 }	   	 
	    });	   
	});
}(jQueryCgmp));