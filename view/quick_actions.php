<?php  ?>

    
      <div class="row widget_error quick-links"> 
       <div class="large-4 columns text-left">
       <div class="widget-title dashboard-panel ">   
       <?php include('help_centar.php'); ?>  
       	<h6 class="heading bold">  Quick Actions</h6>
      </div>
      </div>
       </div>
     <div class="row widget_error quick-links">
       <div class="large-4 columns text-left">
     <?php if(isset($data['quick_actions']['links'])): ?>
     <div class="row"> <div class="columns large-12">
     <ul>
     <?php  foreach($data['quick_actions']['links'] as $key => $quick_action_link): ?>
	   <li><a class="button" href="<?php echo $quick_action_link['link']; ?>"><?php echo $quick_action_link['text']; ?></a></li>	 
	 <?php endforeach; ?>
	 </ul>
	 </div> </div>
	 <?php endif; ?>
	 <?php
	  if(isset($data['quick_actions']['data']['siteurl']) && isset($data['quick_actions']['data']['homeurl'])):
	  ?>
	  </div>
	 
	 <div class="large-4 columns text-left">
	   <form action="" method="post">
	   <fieldset>
	   <legend>Quick Change Site and Home URL</legend>
	    <label for="site_url">Site URL
	     <input type="text" name="site_url" id="site_url" value="<?php echo $data['quick_actions']['data']['siteurl']['option_value']; ?>"/>
        </label>
	    <label for="home_url">Home URL
	    <input type="text" name="home_url" id="home_url" value="<?php echo $data['quick_actions']['data']['homeurl']['option_value']; ?>"/>
        </label>
        <input type="submit" name="submit_site_url" class="button"/>
        </fieldset>
       </form>
      
	 </div>
    <?php endif; ?>
   </div>
   



