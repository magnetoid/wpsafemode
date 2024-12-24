<a href="#" data-reveal-id="myModal" class="help_centar"><i class="icon_info_alt icon"></i></a>  
<div class="widget widget-blue reveal-modal" id="myModal" data-reveal aria-labelledby="Help Center" aria-hidden="true" role="dialog">
	    <div class="widget-title">
	        <h6 class="heading white">Help Center</h6>
	    </div>
	    <ul>
	        <li style="border-bottom:0 !important;">
	           	<p class="white small"><?php echo HelpModel::get_page_help($page); ?></p>
	        </li>
	    </ul>
	    <a class="close-reveal-modal" aria-label="Close">&#215;</a>
</div>  