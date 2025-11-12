<?php
/**
 * Application Constants
 * Centralized constants for magic strings and numbers
 */
class Constants {
    // Action prefixes
    const ACTION_PREFIX = 'action_';
    const SUBMIT_PREFIX = 'submit_';
    const VIEW_PREFIX = 'view_';
    
    // Default values
    const DEFAULT_PAGE = 'info';
    const DEFAULT_VIEW_URL = 'view/';
    
    // Demo mode keys
    const DEMO_MODE_KEY = 'demo';
    const DEMO_MODE_MESSAGE = 'quick actions disabled in demo mode';
    const DEMO_MODE_SAVE_MESSAGE = 'Saving settings and submission is disabled in demo mode';
    
    // Action types
    const ACTION_TYPE_AUTOLOAD = 'autoload';
    const ACTION_TYPE_ACTION = 'action';
    
    // Rate limiting
    const RATE_LIMIT_LOGIN_ATTEMPTS = 5;
    const RATE_LIMIT_LOGIN_WINDOW = 300; // 5 minutes
    
    // Redirect limits
    const REDIRECT_MAX_COUNT = 3;
    
    // Session keys
    const SESSION_NAMESPACE = 'wpsm';
    const SESSION_LOGIN_KEY = 'login';
    const SESSION_MESSAGE_KEY = 'sfmessage';
    const SESSION_REDIRECTING_KEY = 'redirecting';
    const SESSION_REDIRECT_COUNT_KEY = 'redirect_count';
    
    // Settings keys
    const SETTING_WP_DIR = 'wp_dir';
    const SETTING_SFSTORE = 'sfstore';
    const SETTING_VIEW_URL = 'view_url';
    const SETTING_SAFEMODE_DIR = 'safemode_dir';
    const SETTING_DEBUG = 'debug';
    
    // HTTP status codes
    const HTTP_OK = 200;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_FORBIDDEN = 403;
    const HTTP_NOT_FOUND = 404;
    const HTTP_TOO_MANY_REQUESTS = 429;
    const HTTP_INTERNAL_ERROR = 500;
    
    // File paths
    const WP_CONFIG_FILE = 'wp-config.php';
    const HTACCESS_FILE = '.htaccess';
    const WP_CONFIG_BACKUP_SUFFIX = '-safemode-backup.php';
    const HTACCESS_BACKUP_SUFFIX = '.safemode.backup';
}

