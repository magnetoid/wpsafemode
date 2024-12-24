<?php
	if(defined('WP_DEBUG') && WP_DEBUG == true){
	    $wp_debug_checked = 'checked="checked"';
	}else{
	    $wp_debug_checked = '';
	}
	if(defined('AUTOMATIC_UPDATER_DISABLED') && AUTOMATIC_UPDATER_DISABLED == true){
	    $automatic_updater = 'checked="checked"';
	}else{
	    $automatic_updater = '';
	}
	if(defined('WP_AUTO_UPDATE_CORE') && WP_AUTO_UPDATE_CORE == true){
	    $automatic_updater_core = 'checked="checked"';
	}else{
	    $automatic_updater_core = '';
	}
?>
<div class="row" data-equalizer="foo">
	<div class="large-4 columns text-left widget" data-equalizer-watch="foo">
    <div class="dashboard-panel widget-title">
      <h6 class="heading bold">WP Configuration</h6>
		<form action="<?php echo $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']; ?>" method="post">
		    <fieldset class="switch medium round" tabindex="0">
		   <ul>
			    <li>
			        <div class="row">
			            <div class="small-6 columns">

			                <div class="title">WP Debug</div>

			            </div>

			            <div class="small-6 columns text-right">

			                <input id="checkbox-wpconfig-4" type="checkbox" value="on" name="wpdebug" <?php echo $wp_debug_checked; ?>>

						    <label for="checkbox-wpconfig-4"></label>

			            </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				        	<div class="title" aria-haspopup="true" title="Enable/Disable Automatic updates for plugins and themes">Automatic Updater</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input id="checkbox-wpconfig-5" type="checkbox" value="on" name="automatic_updater" <?php echo $automatic_updater; ?>>

					        <label for="checkbox-wpconfig-5"></label>

				        </div>

			        </div>



			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="Enable/Disable Automatic update for WordPress Core"> Core Automatic Updater</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input id="checkbox-wpconfig-6" type="checkbox" value="on" name="automatic_updater_core" <?php echo $automatic_updater_core; ?>>

					        <label for="checkbox-wpconfig-6"></label>

				        </div>

			        </div>

			    </li>

			    </ul>

			    </fieldset>

			    



		    <!-- ---------------- SUBMIT SAVE CONFIG ------------------------------->

		    <input type=submit value="Save Config" name="saveconfig" class="btn btn-blue"/>

			     <fieldset class="switch medium round" tabindex="0" style="margin-top: 10%;">

			     <ul>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="Change DB name - Coming soon">Change DB name</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input type="text" disabled="disabled" name="change-db-name" placeholder="Coming soon">

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="Change DB host and port - Coming soon">Change DB host and port</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input type="text" disabled="disabled" name="change-host" placeholder="Coming soon">

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="Change DB username - Coming soon">Change DB username</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input type="text" disabled="disabled" name="change-username" placeholder="Coming soon">

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="Change DB password - Coming soon">Change DB password</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input type="password" disabled="disabled" name="change-password" placeholder="Coming soon">

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="Define DB charset set - Coming soon">Define DB charset set</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input type="text" disabled="disabled" name="change-charset" placeholder="Coming soon">

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="Define db collate - Coming soon">Define DB collate</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input type="text" disabled="disabled" name="change-collate" placeholder="Coming soon">

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-5 item">

				       		<div class="title" aria-haspopup="true" title="Define new security keys - Coming soon">Define new security keys</div>

				        </div>

				        <div class="columns small-7 item text-right">

					        <textarea disabled="disabled" name="change-security-keys" placeholder="Coming soon" rows="4"></textarea>

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="Only numbers, letters, and underscores please!">Define new table prefix</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input type="text" disabled="disabled" name="change-table-prefix" placeholder="Coming soon">

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="This is only for backuped table prefix">Backup to bck_ table prefix</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input type="text" disabled="disabled" name="change-bck_ table-prefix" placeholder="Coming soon">

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="Define site url - Coming soon">Define site url</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input type="text" disabled="disabled" name="change-site-url" placeholder="Coming soon">

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="Define dynamicly site url - Coming soon">Define dynamicly site url</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input type="text" disabled="disabled" name="change-dynamicly-site-url" placeholder="Coming soon">

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="Define home url - Coming soon">Define home url</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input type="text" disabled="disabled" name="change-home-url" placeholder="Coming soon">

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="Define Content url - Coming soon">Define Content url</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input type="text" disabled="disabled" name="change-content-url" placeholder="Coming soon">

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="Define Plugin url - Coming soon">Define Plugin url</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input type="text" disabled="disabled" name="change-plugin-url" placeholder="Coming soon">

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="Define and register new theme dir - Coming soon">Define and register new theme dir</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input type="text" disabled="disabled" name="change-theme-dir" placeholder="Coming soon">

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="Define uploads folder - Coming soon">Define uploads folder</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input type="text" disabled="disabled" name="change-uploads-folder" placeholder="Coming soon">

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="Define autosave interval in secounds- Coming soon">Define autosave interval</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input type="number" disabled="disabled" name="change-autosave-interval" placeholder="Coming soon">

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				        	<div class="title" aria-haspopup="true" title="Disable/Enable post revision">Disable/Enable post revision</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input id="checkbox-wpconfig-7" disabled="disabled" type="checkbox" value="off" name="post-revision">

					        <label for="checkbox-wpconfig-7"></label>

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="Define cookie domain- Coming soon">Define cookie domain</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input type="text" disabled="disabled" name="cookie-domain" placeholder="Coming soon">

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				        	<div class="title" aria-haspopup="true" title="Disable/Enable">Disable/Enable multisite</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input id="checkbox-wpconfig-8" disabled="disabled" type="checkbox" value="off" name="multisite">

					        <label for="checkbox-wpconfig-8"></label>

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="Define no blog redirect- Coming soon">Define no blog redirect</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input type="text" disabled="disabled" name="no-blog-redirect" placeholder="Coming soon">

				        </div>

			        </div>

			    </li>
			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				        	<div class="title" aria-haspopup="true" title="Enable Debug logging to the /wp-content/debug.log file">Enable Debug logging in log file</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input id="checkbox-wpconfig-13" disabled="disabled" type="checkbox" value="off" name="debug-log">

					        <label for="checkbox-wpconfig-13"></label>

				        </div>

			        </div>

			    </li>
			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				        	<div class="title" aria-haspopup="true" title="Disable display of errors and warnings">Disable display of errors and warnings</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input id="checkbox-wpconfig-14" disabled="disabled" type="checkbox" value="on" name="display-errors">

					        <label for="checkbox-wpconfig-14"></label>

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				        	<div class="title" aria-haspopup="true" title="Enable/Disable script debug">Enable/Disable script debug</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input id="checkbox-wpconfig-10" disabled="disabled" type="checkbox" value="off" name="script-debug">

					        <label for="checkbox-wpconfig-10"></label>

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				        	<div class="title" aria-haspopup="true" title="Enable/Disable JS Concatenation">Enable/Disable JS Concatenation</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input id="checkbox-wpconfig-11" disabled="disabled" type="checkbox" value="off" name="js-concatenation">

					        <label for="checkbox-wpconfig-11"></label>

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="Define WP Memory Limit- Coming soon">Define WP Memory Limit</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input type="number" disabled="disabled" name="memory-limit" placeholder="Coming soon">

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="Define WP Max Memory limit- Coming soon">Define WP Max Memory limit</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input type="number" disabled="disabled" name="max-memory-limit" placeholder="Coming soon">

				        </div>

			        </div>

			    </li> 

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				        	<div class="title" aria-haspopup="true" title="Enable Disable wp cache">Enable Disable wp cache</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input id="checkbox-wpconfig-12" disabled="disabled" type="checkbox" value="off" name="wp-cache">

					        <label for="checkbox-wpconfig-12"></label>

				        </div>

			        </div>

			    </li>

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="Define custom user table- Coming soon">Define custom user table</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input type="text" disabled="disabled" name="custom-user-table" placeholder="Coming soon">

				        </div>

			        </div>

			    </li> 

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="Define wp lang - Coming soon">Define wp lang</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input type="text" disabled="disabled" name="wp-lang" placeholder="Coming soon">

				        </div>

			        </div>

			    </li> 

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="Define wp lang dir - Coming soon">Define wp lang dir</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input type="text" disabled="disabled" name="wp-lang-dir" placeholder="Coming soon">

				        </div>

				        

				        

			        </div>

			    </li> 

			    <li>

			        <div class="row">

				        <div class="columns small-6 item">

				       		<div class="title" aria-haspopup="true" title="Generate wpconfig file - Coming soon">Generate wpconfig file</div>

				        </div>

				        <div class="columns small-6 item text-right">

					        <input type="text" disabled="disabled" name="generate-wpconfig-file" placeholder="Coming soon">

				        </div>

			        </div>

			    </li> 

			</ul>

		    </fieldset>

		</form>

	 </div>

	</div>
</div>