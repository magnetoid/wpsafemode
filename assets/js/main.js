jQuery(function($) {
	
	"use strict";


	$('a.checkbox').click(function(event){
		event.preventDefault();
		$(this).toggleClass('checked');
	});

	$(window).load(function(){
		$('.header-overlay').height($(window).height());

		$('.widget').height($(window).height() - $('nav').outerHeight() - 120);
	});


	$('.contact-form input').focus(function(){
		$(this).prev('label').addClass('active');
	}).blur(function(){
		if($(this).val() == '')
			$(this).prev('label').removeClass('active');
	});

	//added argument slow - Daliborka
	$('a.collapse-dashboard').click(function(event){
		event.preventDefault();
		$('.dashboard').toggleClass('collapsed', 'slow');
		$('section.main').toggleClass('collapsed', 'slow');
		$('.dashboard ul li a span').toggle('slow');
		$(this).toggleClass('active', 'slow');
	});

	$('.widget ul li a.checkbox').click(function(){
		$(this).toggleClass('active', 'slow');
	});	
	
	$(document).ready(function(){
		if($("#bad_ips").length > 0){
			$("#bad_ips").hide(100);
			setTimeout(function(){
			  	$(document).foundation('equalizer', 'reflow');
			  },200);
		}
		if($("#allow_wpadmin_ip").length > 0){
			$("#allow_wpadmin_ip").hide(100);
			setTimeout(function(){
			  	$(document).foundation('equalizer', 'reflow');
			  },200);
		}
		if($("#error_div").length > 0){
			$("#error_div").hide(100);
			setTimeout(function(){
			  	$(document).foundation('equalizer', 'reflow');
			  },200);
		}
		if($("#new_domain").length > 0){			
			$("#new_domain").hide(100);
			setTimeout(function(){
			  	$(document).foundation('equalizer', 'reflow');
			  },200);
		}
		if($("#domain_name").length > 0){
			$("#domain_name").hide(100);
			setTimeout(function(){
			  	$(document).foundation('equalizer', 'reflow');
			  },200);			
		}
		if($("#charset_value").length > 0){
			$("#charset_value").hide(100);
			setTimeout(function(){
			  	$(document).foundation('equalizer', 'reflow');
			  },200);
		}
		if($("#language_value").length > 0){
			$("#language_value").hide(100);
			setTimeout(function(){
			  	$(document).foundation('equalizer', 'reflow');
			  },200);
		}
		if($("#single_file_name").length > 0){
			$("#single_file_name").hide(100);
			setTimeout(function(){
			  	$(document).foundation('equalizer', 'reflow');
			  },200);
		}
		if($("#redirect_url").length > 0){
			$("#redirect_url").hide(100);
			setTimeout(function(){
			  	$(document).foundation('equalizer', 'reflow');
			  },200);
		}
		if($("#default_file_name").length > 0){
			$("#default_file_name").hide(100);
			setTimeout(function(){
			  	$(document).foundation('equalizer', 'reflow');
			  },200);
		}
		if($('#referrer').length > 0){			
			$('#referrer').hide(100);
			setTimeout(function(){
			  	$(document).foundation('equalizer', 'reflow');
			  },200);
		}
		if($('#sitemap_urls').length > 0){			
			$('#sitemap_urls').hide(100);
			setTimeout(function(){
			  	$(document).foundation('equalizer', 'reflow');
			  },200);
		}
		
		$('#block_ips').click(function() {
		    $("#bad_ips").toggle(100).foundation('equalizer', 'reflow');
		    setTimeout(function(){
			  	$(document).foundation('equalizer', 'reflow');
			  },200);
		});
		$('#allow_wpadmin').click(function() {
		    $("#allow_wpadmin_ip").toggle(100).foundation('equalizer', 'reflow');
		     setTimeout(function(){
			  	$(document).foundation('equalizer', 'reflow');
			  },200);
		});
		$('#error_page').click(function() {
		    $("#error_div").toggle(100).foundation('equalizer', 'reflow');
		     setTimeout(function(){
			  	$(document).foundation('equalizer', 'reflow');
			  },200);
		});
		$('#old_domain').click(function() {
		    $("#new_domain").toggle(100).foundation('equalizer', 'reflow');
		     setTimeout(function(){
			  	$(document).foundation('equalizer', 'reflow');
			  },200);
		});
		$('#redirect_www').click(function() {
		    $("#domain_name").toggle(100).foundation('equalizer', 'reflow');
		     setTimeout(function(){
			  	$(document).foundation('equalizer', 'reflow');
			  },200);
		});
		$('#set_charset').click(function(){
			$("#charset_value").toggle(100).foundation('equalizer', 'reflow');	
			 setTimeout(function(){
			  	$(document).foundation('equalizer', 'reflow');
			  },200);
		});
		$('#set_language').click(function(){
			$("#language_value").toggle(100).foundation('equalizer', 'reflow');	
			 setTimeout(function(){
			  	$(document).foundation('equalizer', 'reflow');
			  },200);
		});
		$("#pass_single_file").click(function(){
			$("#single_file_name").toggle(100).foundation('equalizer', 'reflow');
			 setTimeout(function(){
			  	$(document).foundation('equalizer', 'reflow');
			  },200);
		});
		$("#development_redirect").click(function(){
			$("#redirect_url").toggle(100).foundation('equalizer', 'reflow');
			 setTimeout(function(){
			  	$(document).foundation('equalizer', 'reflow');
			  },200);
		});
		$("#default_page").click(function(){
			$("#default_file_name").toggle(100).foundation('equalizer', 'reflow');
			 setTimeout(function(){
			  	$(document).foundation('equalizer', 'reflow');
			  },200);
		});
		$("#deny_referrer").click(function(){
			$("#referrer").toggle(100).foundation('equalizer', 'reflow');
			 setTimeout(function(){
			  	$(document).foundation('equalizer', 'reflow');
			  },200);
		});
		$("#sitemap").click(function(){
			$("#sitemap_urls").toggle(100).foundation('equalizer', 'reflow');
			 setTimeout(function(){
			  	$(document).foundation('equalizer', 'reflow');
			  },200);
		});
	});
	
	  
	/*
	$(document).ready(function(event){
		$('.edit_htaccess_div').css('display', 'none');
		$('#edit_htaccess_button').click(function(event){
			event.preventDefault();
			$('.edit_htaccess_div').show().css('height', 'auto');
		});
	});
	*/

	
	//equalizer doesn't  work without this function
	$(document).foundation({
	equalizer : {
	// Specify if Equalizer should make elements equal height once they become stacked.
	equalize_on_stack: true
	}
	});
		
});

