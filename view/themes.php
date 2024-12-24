<?php
//echo '<pre>'.print_r($data,true).'</pre>';
foreach($data['themes']['active_theme'] as $active_theme){
   if($active_theme['option_name'] == 'stylesheet'){
       $current_theme = $active_theme['option_value'];
   }
    if($active_theme['option_name'] == 'template'){
        $theme_template = $active_theme['option_value'];
    }

}

?>
<div class="row" data-equalizer>
    <!------------------------------- grid half screen ------------------------------------------------------------------->
	<div class="large-4 columns widget" data-equalizer-watch> 
	    <div class="dashboard-panel widget-title">
	    	<?php include('help_centar.php'); ?>
			<h6 class="heading bold">Set Current Theme</h6>	    
			 
	        <form method="post" action="">  
	        	<ul>
	                <?php
	                //print_r($data['themes']['all_themes']);
	                foreach($data['themes']['all_themes'] as $key => $value):
	                    $checked = ($key == $current_theme)?'checked':'';
	                    $current = ($key == $current_theme)?' (current theme)':'';
	                    echo '<li><input type="radio" name="active_theme" value="'.$key.'" '.$checked .'/> '.$value['theme_name']. $current . '</li>'; //close your tags!!
	                endforeach;
	                echo '<li><input type="radio" name="active_theme" value="downloadsafe"/> Download Twenty Fifteen (this will download and activate clean theme from wordpress.org)</li>'; //close your tags!!
	                ?>
	            </ul>
	         <input type="submit" name="submit_themes" class="btn btn-blue" value="Save Current Theme"/>
	        </form>
	    </div>
	</div>
</div>














