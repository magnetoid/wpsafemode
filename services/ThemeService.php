<?php
/**
 * Theme Service
 * Handles theme-related operations
 */
class ThemeService
{

    private $model;
    private $cache;

    /**
     * Constructor
     * 
     * @param DashboardModel $model Dashboard model instance
     * @param Cache|null $cache Cache instance
     */
    public function __construct(DashboardModel $model, ?Cache $cache = null)
    {
        $this->model = $model;
        $this->cache = $cache ?? Cache::getInstance();
    }

    /**
     * Get active theme
     * 
     * @return array
     */
    public function getActiveTheme(): array
    {
        return $this->model->get_active_themes();
    }

    /**
     * Get all themes
     * 
     * @param string $wp_dir WordPress directory
     * @return array
     */
    public function getAllThemes(string $wp_dir): array
    {
        return $this->cache->remember('all_themes', function () use ($wp_dir) {
            return $this->model->get_all_themes($wp_dir);
        }, 300); // Cache for 5 minutes
    }

    /**
     * Set active theme
     * 
     * @param array $theme Theme data
     * @return bool
     */
    public function setActiveTheme(array $theme): bool
    {
        $result = $this->model->set_active_theme($theme);
        if ($result) {
            $this->cache->delete('active_theme');
        }
        return $result;
    }

    /**
     * Download Safe Mode theme
     * 
     * @return bool
     */
    public function downloadSafeModeTheme(): bool
    {
        return $this->model->safemode_download_theme();
    }
}
