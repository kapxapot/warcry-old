$(document).ready(function() {
	// colorbox
	$('.colorbox').colorbox({rel:'colorbox', transition:'none', maxWidth: '90%', scalePhotos: 'true'});
	
	$('.embed-responsive').parent().removeClass('center');
});

$(window).on("load", function() {
	//$("div.center").each(function() {
	/*$("#main").each(function() {
		var imgs = $(this).find("img.img-responsive");
		//if (imgs.length > 1) {
			var narrow = imgs.filter(function() {
    			return $(this).width() < 400;
			});
			
			//narrow.removeClass("img-responsive");
		//}
	});*/

	// evening block heights
	// deprecated -> use flexbox
	var evenHeights = function(style) {
	    var heights = $(style).map(function() {
	        return $(this).height();
	    }).get();
	
	    maxHeight = Math.max.apply(null, heights);
	
	    $(style).height(maxHeight);
	};
	
	evenHeights(".stream-panel");
});

function Search(curobj) {
	curobj.q.value="site:warcry.ru " + curobj.qfront.value;
}