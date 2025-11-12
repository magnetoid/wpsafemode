<?php 
//TODO add method in dashboard model that returns array of menu items data in manner = view, name, icon 
//TODO iterate through array of menu items data and build menu items programmatically, instead of adding them now like straight html + php 

if($data['current_page']=='login'){
	return;
}
 ?>	
 	<div class="dashboard">
	    <a href="#" class="collapse-dashboard"><i class="arrow_carrot-left_alt2"></i></a>
	    <ul class="widget-content menu">
<?php  if(isset($data['menu_items'])):  ?>	
	<?php foreach($data['menu_items'] as $menu_item): ?>
		<?php if(isset($menu_item['disabled']) && $menu_item['disabled'] == true): ?>
			<li class="inactive">
			<a href="#" data-tooltip aria-haspopup="true" class="has-tip" data-options="disable_for_touch:true" title="Coming Soon"><i class="<?php echo isset($menu_item['icon'])?$menu_item['icon']:'';  ?> icon"></i>
			<span><?php echo $menu_item['name'];  ?></span>
			</a>
			</li>
		<?php else:  ?>
			<li class="<?php echo (isset($data['current_page']) && $data['current_page'] == $menu_item['slug'])?'active':''; ?>">
				<a href="<?php echo (isset($menu_item['link']))?$menu_item['link']:'#';  ?>" data-view="<?php echo $menu_item['slug']; ?>" data-tooltip aria-haspopup="true" class="has-tip" data-options="disable_for_touch:true" title="<?php echo $menu_item['name'];  ?>"><i class="<?php echo isset($menu_item['icon'])?$menu_item['icon']:'';  ?> icon"></i> 
					<span><?php echo $menu_item['name'];  ?></span>
				</a>
			</li>	
		<?php endif;  ?>
	<?php endforeach;  ?>
<?php endif;  ?>
	    </ul>
	</div>