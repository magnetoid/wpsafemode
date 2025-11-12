<?php
/**
 * Plugin Service
 * Handles plugin-related operations
 */
class PluginService {
    
    private $model;
    private $cache;
    
    /**
     * Constructor
     * 
     * @param DashboardModel $model Dashboard model instance
     * @param Cache|null $cache Cache instance
     */
    public function __construct(DashboardModel $model, ?Cache $cache = null) {
        $this->model = $model;
        $this->cache = $cache ?? Cache::getInstance();
    }
    
    /**
     * Get active plugins
     * 
     * @return array
     */
    public function getActivePlugins(): array {
        return $this->cache->remember('active_plugins', function() {
            return $this->model->get_active_plugins();
        }, 300); // Cache for 5 minutes
    }
    
    /**
     * Save plugins configuration
     * 
     * @param array $plugins Plugins array
     * @return bool
     */
    public function savePlugins(array $plugins): bool {
        $result = $this->model->save_plugins($plugins);
        if ($result) {
            $this->cache->delete('active_plugins');
        }
        return $result;
    }
    
    /**
     * Enable all plugins
     * 
     * @return bool
     */
    public function enableAll(): bool {
        $plugins = $this->getActivePlugins();
        foreach ($plugins as $key => $plugin) {
            $plugins[$key]['active'] = true;
        }
        return $this->savePlugins($plugins);
    }
    
    /**
     * Disable all plugins
     * 
     * @return bool
     */
    public function disableAll(): bool {
        $plugins = $this->getActivePlugins();
        foreach ($plugins as $key => $plugin) {
            $plugins[$key]['active'] = false;
        }
        return $this->savePlugins($plugins);
    }
}

