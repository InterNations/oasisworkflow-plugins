var n = 0;
	jQuery(".owfc-comment").each(function(index, element){
		var i = index++;
		var el = jQuery(element);
		var top = jQuery(el).offset().top + jQuery(el).height()+5;
		var left = jQuery('.entry-content').offset().left + jQuery('.entry-content').width()-250;
		var width_line = jQuery(el).position().left - jQuery('.entry-content').width();
		var data = el.data('comment');
		
		jQuery('body').append('<div class="comment-box" id="comment_'+i+'"></div>');
		jQuery(data.comments).each(function(index, el) {
			n++;
			if(index==0) var close ='<span class="comment-close">x<span>';
			else var close = '';
			jQuery('#comment_'+i).append('<div class="comment-header">'+el.title+close+'</div>');
			jQuery('#comment_'+i).append('<div class="owfc-comment-body"><span>Comment ['+n+']:</span>'+el.body+'</div>');
		});
		jQuery('#comment_'+i).offset({top: top, left: left});
	});

	jQuery(window).resize(function(){
		jQuery(".owfc-comment").each(function(index, element){
			var i = index++;
			var el = jQuery(element);
			var top = jQuery(el).offset().top + jQuery(el).height()+5;
			var left = jQuery('.entry-content').offset().left + jQuery('.entry-content').width()-250;
			jQuery('#comment_'+i).offset({top: top, left: left});
		});
	});

	jQuery(".owfc-comment").hover(function() {
		var i = jQuery(".owfc-comment").index(this);
		jQuery(this).addClass('show-comment');
		jQuery('#comment_'+i).addClass('show-comment-box');
      /* Stuff to do when the mouse enters the element */
		}, function() {
			var i = jQuery(".owfc-comment").index(this);
			jQuery(this).removeClass('show-comment');
			jQuery('#comment_'+i).removeClass('show-comment-box');
         /* Stuff to do when the mouse leaves the element */
	});

	jQuery(".comment-box").hover(function() {
		jQuery(this).css({
			'z-index': '500',
			'box-shadow': '0 0 10px rgba(0,0,0,0.8)'
		}); 
      /* Stuff to do when the mouse enters the element */      
	}, function() {
		jQuery(this).css({
			'z-index': '0',
			'box-shadow': 'none'
		});
      /* Stuff to do when the mouse leaves the element */
	});

	jQuery(".owfc-comment").click(function() {
		var i = jQuery(".owfc-comment").index(this);
		jQuery(this).toggleClass('long-show-comment');
		jQuery('#comment_'+i).toggleClass('long-show-box');
	});

	jQuery(".comment-close").click(function() {
		var i = jQuery(".comment-close").index(this);
		jQuery(this).parent().parent().toggleClass('long-show-box');
		jQuery('.owfc-comment:eq('+i+')').toggleClass('long-show-comment');
	});