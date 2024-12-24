<?php


?>
<div class="row" data-equalizer>    
	<div class="large-4 columns text-left widget" data-equalizer-watch>
       	<div class="widget-title dashboard-panel ">
       		<?php include('help_centar.php'); ?>
			<h6 class="heading bold">Global Settings</h6>
			<form action="" method="post">
				<ul>
		
			
				<li><label>Username</label><input type="text" name="username" id="username" placeholder="Set your login username" value="<?php echo $data['global_settings']['login']['username']; ?>"/></li>
				<li><label>Email</label><input type="text" name="email" id="email" placeholder="Set your email" value="<?php echo $data['global_settings']['login']['email']; ?>"/></li>
				<li><label>Password</label><input type="password" name="password" id="password" placeholder="Set your password" value=""/></li>
				<li><label>Repeat Password</label><input type="password" name="repeat_password" id="repeat_password" placeholder="Repeat your password" value=""/></li>
				<li><label>Api Key</label><input type="text" name="api_key" id="api_key" placeholder="Set your api key" value="<?php echo $data['global_settings']['api_key_value']['api_key']; ?>"/></li>
				

				</ul>
				<input type='submit' class='btn btn-blue' name="submit_global_settings" value="Save Settings"> 
			</form>		
		</div>
	</div>
</div>