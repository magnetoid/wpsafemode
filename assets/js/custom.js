  jQuery(document).ready(function($){
           $('.submit-plugins-action').click(function(){

               if($(this).val() == 'enable_all'){
                   console.log($(this).val());
                   $('.plugins-checkbox').each(function(){
                       $(this).prop('checked', true);
                   });
               }
               if($(this).val() == 'disable_all'){
                   $('.plugins-checkbox').each(function(){
                       $(this).prop('checked', false);
                   });
               }
           });
           if($('.tables-list').length > 0){
		   	$('.tables-list').hide();
		   	
		   	$('.partial_backup').click(function(){
		   		$('.tables-list').toggle(200);
		   	});
		   	
            $('.backup-type, .search-criteria-db').click(function(){
            	if($(this).val() == 'full'){
					$('.tables-list').hide(100);
					setTimeout(function(){
					  	$(document).foundation('equalizer', 'reflow');
					},200);
				}
				if($(this).val() == 'partial'){
					$('.tables-list').show(100);
					setTimeout(function(){
					  	$(document).foundation('equalizer', 'reflow');
					},200);
				}
            });
		   }
           if($('.backup-files-submit').length > 0){
		   	  $('.backup-files-submit').click(function(){
		   	   $('.backup-files-submit').hide();
		   	  	$(this).after('<span class="error">Site files backup is in process, please don\'t close this page or refresh...</span>' );
		   	  });
		   }
		   //set advanced toggle trigger event 
		   if($('.advanced-panel').length > 0){
		   	$('.advanced-panel').hide(200);
		  // 	
		  setTimeout(function(){
		  	$(document).foundation('equalizer', 'reflow');
		  },200);
		   }
		   if($('.advanced-toggle').length > 0 ){
		   	$('.advanced-toggle').click(function(){
		   		var target = $(this).attr('rel');
		   		if(target!=undefined && target!=''){
					$('#' + target ).toggle(200).foundation('equalizer', 'reflow');
				setTimeout(function(){
		        	$(document).foundation('equalizer', 'reflow');
		             },200);
				}
		   	});
		   }
       });