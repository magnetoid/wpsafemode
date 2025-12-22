<?php

class WpConfigService
{
    private $dashboard_model;
    private $wpconfig_clean_comments_source;

    public function __construct(DashboardModel $dashboard_model)
    {
        $this->dashboard_model = $dashboard_model;
    }

    /**
     * Updates option (constant or variable) from wp-config.php with new assigned value set from wp configuration section 
     * 
     * @param array $wpconfig_option set of wp config option data 
     * @param array $wpconfig_array list from wp-config.php data split into array 
     * 
     * @return array $wp_config_array updated with new value for given wp config option 
     */
    public function update_wp_config_value($wpconfig_option, $wpconfig_array): array
    {
        if ($wpconfig_option['value_type'] == 'boolean') {
            if (!empty($wpconfig_option['new_value'])) {
                $wpconfig_option['new_value'] = 'true';
            } else {
                $wpconfig_option['new_value'] = 'false';
            }
            if ($wpconfig_option['value'] == '1' || $wpconfig_option['value'] == 'on') {
                $wpconfig_option['value'] = 'true';
            } else {
                $wpconfig_option['value'] = 'false';
            }
        }

        if ($wpconfig_option['new_value'] == $wpconfig_option['value']) {
            return $wpconfig_array;
        }

        $new_line = '';
        if ($wpconfig_option['type'] == 'constant') {
            if ($wpconfig_option['value_type'] == 'boolean') {
                $new_line = "define('" . $wpconfig_option['name'] . "', " . $wpconfig_option['new_value'] . ");";
            } else {
                $new_line = "define('" . $wpconfig_option['name'] . "', '" . $wpconfig_option['new_value'] . "');";
            }
        }
        if ($wpconfig_option['type'] == 'variable') {
            $new_line = "$" . $wpconfig_option['name'] . " = '" . $wpconfig_option['new_value'] . "';";
        }
        $new_line .= "\n\n";
        $found_abspath = false;

        foreach ($wpconfig_array as $key => $wpconfig_line) {

            if (strstr($wpconfig_line, 'ABSPATH')) {
                $found_abspath = true;
            }
            if (empty($wpconfig_line) && $found_abspath == false) {
                $editing_end = $key;
            }
            if (strstr($wpconfig_line, "Happy blogging")) {
                $editing_end = $key;
            }

            if (strstr($wpconfig_line, $wpconfig_option['name']) && (($wpconfig_option['type'] == 'constant' && strstr($wpconfig_line, 'define(')) || ($wpconfig_option['type'] == 'variable' && strstr($wpconfig_line, '$' . $wpconfig_option['name']))) && (strstr($wpconfig_line, $wpconfig_option['value']) || empty($wpconfig_option['value'])) && strstr($this->wpconfig_clean_comments_source, $wpconfig_line)) {
                $wpconfig_array[$key] = $new_line;
                return $wpconfig_array;
            }
        }

        $insert = array($new_line);
        array_splice($wpconfig_array, ($editing_end - 1), 0, $insert);
        return $wpconfig_array;
    }

    public function handle_advanced_submission()
    {
        // CSRF validation should remain in controller or be passed here. 
        // Assuming controller calls this after validation.

        $wpconfig_options = $this->dashboard_model->get_wp_config_options();
        $wpconfig_array = $this->dashboard_model->get_wp_config_array();

        $this->wpconfig_clean_comments_source = $this->dashboard_model->get_wp_config(true);
        $wpconfig_array_temp = $wpconfig_array; // Default in case loop doesn't run properly

        foreach ($wpconfig_options as $key => $wpconfig_option) {
            $wpconfig_post_value = filter_input(INPUT_POST, $wpconfig_option['input_key']);
            $wpconfig_option['new_value'] = trim($wpconfig_post_value);

            if ($wpconfig_option['new_value'] == 'on') {
                $wpconfig_option['new_value'] = 1;
            }

            if ($wpconfig_option['new_value'] != $wpconfig_option['value']) {
                $wpconfig_array = $this->update_wp_config_value($wpconfig_option, $wpconfig_array);
            }

            if (isset($wpconfig_array[0])) {
                $wpconfig_array_temp = $wpconfig_array;
            }
        }

        $wpconfig_source = '';
        foreach ($wpconfig_array_temp as $wpconfig_option_new) {
            $wpconfig_source .= $wpconfig_option_new;
        }

        $this->dashboard_model->save_wpconfig($wpconfig_source);
    }

    public function handle_basic_submission()
    {
        $saveconfig = filter_input(INPUT_POST, 'saveconfig');
        $wpdebug = filter_input(INPUT_POST, 'wpdebug');
        $automatic_updater = filter_input(INPUT_POST, 'automatic_updater');
        $automatic_updater_core = filter_input(INPUT_POST, 'automatic_updater_core');

        if (!empty($saveconfig)) {
            $fileStr = $this->dashboard_model->get_wp_config();
            $ini_array = $this->dashboard_model->get_wp_config_array();
            //wp debug on/off
            $found_line = false;
            foreach ($ini_array as $key => $value) {
                if (!empty($value)) {
                    if (stristr($value, "WP_DEBUG")) {
                        if (!empty($wpdebug) && $wpdebug == 'on') {
                            $new_value = str_replace("false", "true", $value);
                        } else {
                            $new_value = str_replace("true", "false", $value);
                        }
                        $fileStr = str_replace($value, $new_value, $fileStr);
                        $found_line = true;
                    }
                }
            }
            if ($found_line == false) {
                if (!empty($wpdebug) && $wpdebug == 'on') {
                    $add_line = "\n\n" . "define('WP_DEBUG', true);\n\n";
                    $fileStr = str_replace("/* That's all, stop editing! Happy blogging. */", $add_line . "/* That's all, stop editing! Happy blogging. */", $fileStr);
                }
            }
            //automatic updater off/on
            $found_line = false;
            foreach ($ini_array as $key => $value) {
                if (!empty($value)) {
                    if (stristr($value, "AUTOMATIC_UPDATER_DISABLED")) {
                        $new_value = $value;
                        if (!empty($automatic_updater) || $automatic_updater == 'on') {
                            $new_value = str_replace("false", "true", $value);

                        } else {
                            $new_value = str_replace("true", "false", $value);
                        }
                        $fileStr = str_replace($value, $new_value, $fileStr);
                        $found_line = true;
                    }
                }
            }
            if ($found_line == false) {
                $add_line = '';
                if (!empty($automatic_updater) || $automatic_updater == 'on') {
                    $add_line = "\n\n" . "define('AUTOMATIC_UPDATER_DISABLED', true);\n";
                    $fileStr = str_replace("/* That's all, stop editing! Happy blogging. */", $add_line . "/* That's all, stop editing! Happy blogging. */", $fileStr);
                }
            }
            //wp autoupdate core on/off
            $found_line = false;
            foreach ($ini_array as $key => $value) {
                if (!empty($value)) {
                    $new_value = $value;
                    if (stristr($value, "WP_AUTO_UPDATE_CORE")) {
                        if (!empty($automatic_updater_core) && $automatic_updater_core == 'on') {
                            $new_value = str_replace("false", "true", $value);
                        } else {
                            $new_value = str_replace("true", "false", $value);
                        }
                        $fileStr = str_replace($value, $new_value, $fileStr);
                        $found_line = true;
                    }
                }
            }
            if ($found_line == false) {
                $add_line = '';
                if (!empty($automatic_updater_core) || $automatic_updater_core == 'on') {
                    $add_line = "\n\n" . "define('WP_AUTO_UPDATE_CORE', true);\n";
                    $fileStr = str_replace("/* That's all, stop editing! Happy blogging. */", $add_line . "/* That's all, stop editing! Happy blogging. */", $fileStr);
                }
            }

            $pos = strpos($fileStr, ' ?>');
            if ($pos !== false) {
                $fileStr = substr($fileStr, 0, $pos) . "\r\n" . substr($fileStr, $pos);
            }
            $this->dashboard_model->save_wpconfig($fileStr);
        }
    }
}
