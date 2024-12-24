<?php
?>
<div class="row" data-equalizer>
	<div class="large-6 columns text-left widget" style="float: left;" data-equalizer-watch>
		<div class="dashboard-panel widget-title">
		
		<h6 class="heading bold">SERVER information</h6>
           <?php if(isset($data['info']['server'])): ?>
           <ul>
               <?php foreach($data['info']['server'] as $php_slug => $php_info): ?>                 
               <li> 
                   <div class="option_title"><pre><?php echo $php_slug; ?><?php if($php_info['value'] != '') : ?> : <?php  echo htmlentities($php_info['value']); ?></pre><?php endif; ?></div>
                   <div>Description: <?php echo $php_info['description'] ?></div>
               </li>
               <?php endforeach; ?>     
           </ul>
           <?php endif; ?>
			<h6 class="heading bold">PHP information</h6>
           <?php if(isset($data['info']['php_info'])): ?>
           <ul>
               <?php foreach($data['info']['php_info'] as $php_slug => $php_info): ?>                 
               <li> 
                   <div class="option_title"> <pre><?php echo $php_slug; ?><?php  if($php_info['value'] != '') : ?> :<?php  echo htmlentities($php_info['value']); ?></pre><?php endif; ?></div>
                    <div>Description: <?php echo $php_info['description'] ?></div>
               </li>
               <?php endforeach; ?>     
           </ul>
           <?php endif; ?>
		</div>
	</div>
	<div class="large-6 columns text-left widget" data-equalizer-watch>
		<div class="dashboard-panel widget-title">
			<?php include('help_centar.php'); ?>
			<h6 class="heading bold">WordPress Core Information</h6>
			<?php if(isset($data['info']['core_info'])): ?>
			<ul>
				<?php foreach($data['info']['core_info'] as $core_info): ?>                 
				<li> 
					<div class="option_title"><?php echo $core_info['name'] ?></div>
					<div>Version: <?php echo $core_info['version'] ?></div>
					<div class="description"><?php echo $core_info['description'] ?></div>                
				</li>

				<?php endforeach; ?>     
			</ul>
			<?php endif; ?> 
			<h6 class="heading bold">WordPress Themes Information</h6>
			<?php if(isset($data['info']['themes_versions'])): ?>
			<ul>
				<?php foreach($data['info']['themes_versions'] as $theme_slug => $theme_info): ?>                 
				<li> 
					<div class="option_title"><?php echo $theme_info['theme_name'] ?></div>
					<div>Version: <?php echo $theme_info['theme_version'] ?></div>
					<div>Slug: <?php echo $theme_slug ?></div>
				</li>
				<?php endforeach; ?>     
			</ul>
			<?php endif; ?>  
			<h6 class="heading bold">WordPress Plugins Information</h6>
			<?php if(isset($data['info']['plugins_info'])): ?>
			<ul>
				<?php foreach($data['info']['plugins_info'] as $plugin_path => $plugin_info): ?>                 
				<li> 
					<div data-tooltip aria-haspopup="true" class="has-tip" data-options="disable_for_touch:true"  title="<?php echo $plugin_info['info'] ?>"><?php echo $plugin_info['name'] ?></div>
					<div>Version: <?php echo $plugin_info['version']; ?></div>
					<div>Path: <?php echo $plugin_path ?></div>        
				</li>
				<?php endforeach; ?>     
			</ul>
			<?php endif; ?>    
		</div>
	</div>
	
</div>
                 
        
