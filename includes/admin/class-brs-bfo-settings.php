<?php
if (!defined('ABSPATH')) exit;

class BRS_BFO_Settings {
    const OPTION_DELETE_ON_UNINSTALL = 'brs_bfo_delete_data_on_uninstall';

    public function register() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('plugin_action_links_' . plugin_basename(BRS_BFO_FILE), [$this, 'plugin_action_links']);
    }

    public function menu() {
        add_submenu_page(
            'woocommerce',
            __('BRS Fake Orders Settings', 'brs-block-fake-orders'),
            __('BRS Fake Orders Settings', 'brs-block-fake-orders'),
            'manage_woocommerce',
            'brs-fake-orders-settings',
            [$this, 'render_page']
        );
    }

    public function register_settings() {
        register_setting(
            'brs_bfo_settings',
            self::OPTION_DELETE_ON_UNINSTALL,
            ['type' => 'boolean', 'sanitize_callback' => [$this, 'sanitize_bool'], 'default' => false]
        );

        add_settings_section(
            'brs_bfo_cleanup',
            __('Data & Uninstall', 'brs-block-fake-orders'),
            function(){ echo '<p>'.esc_html__('Control what happens to plugin data on uninstall.', 'brs-block-fake-orders').'</p>'; },
            'brs_bfo_settings'
        );

        add_settings_field(
            'delete_on_uninstall',
            __('Delete data on uninstall', 'brs-block-fake-orders'),
            [$this, 'field_delete_on_uninstall'],
            'brs_bfo_settings',
            'brs_bfo_cleanup'
        );

        // --- Validation Settings Section ---
        add_settings_section(
            'brs_bfo_section_validation',
            __('Validation Settings', 'brs-block-fake-orders'),
            '__return_false',
            'brs_bfo_settings'
        );

        // Register toggles
        add_settings_field(
            'brs_bfo_check_token',
            __('Enable Token Validation', 'brs-block-fake-orders'),
            [$this, 'render_checkbox_field'],
            'brs_bfo_settings',
            'brs_bfo_section_validation',
            ['option_name' => 'brs_bfo_check_token']
        );
        register_setting('brs_bfo_settings', 'brs_bfo_check_token', ['type' => 'boolean', 'default' => true]);

        add_settings_field(
            'brs_bfo_check_origin',
            __('Enable Origin Validation', 'brs-block-fake-orders'),
            [$this, 'render_checkbox_field'],
            'brs_bfo_settings',
            'brs_bfo_section_validation',
            ['option_name' => 'brs_bfo_check_origin']
        );
        register_setting('brs_bfo_settings', 'brs_bfo_check_origin', ['type' => 'boolean', 'default' => true]);

        add_settings_field(
            'brs_bfo_check_referer',
            __('Enable Referer Validation', 'brs-block-fake-orders'),
            [$this, 'render_checkbox_field'],
            'brs_bfo_settings',
            'brs_bfo_section_validation',
            ['option_name' => 'brs_bfo_check_referer']
        );
        register_setting('brs_bfo_settings', 'brs_bfo_check_referer', ['type' => 'boolean', 'default' => true]);

        add_settings_field(
            'brs_bfo_check_session',
            __('Enable Session Validation', 'brs-block-fake-orders'),
            [$this, 'render_checkbox_field'],
            'brs_bfo_settings',
            'brs_bfo_section_validation',
            ['option_name' => 'brs_bfo_check_session']
        );
        register_setting('brs_bfo_settings', 'brs_bfo_check_session', ['type' => 'boolean', 'default' => true]);

        // Allowed domain
        $default_domain = parse_url(get_site_url(), PHP_URL_HOST);
        add_settings_field(
            'brs_bfo_allowed_domain',
            __('Allowed Domain', 'brs-block-fake-orders'),
            [$this, 'render_text_field'],
            'brs_bfo_settings',
            'brs_bfo_section_validation',
            ['option_name' => 'brs_bfo_allowed_domain', 'placeholder' => $default_domain]
        );
        register_setting('brs_bfo_settings', 'brs_bfo_allowed_domain', ['type' => 'string', 'default' => $default_domain]);

    }
    
    public function render_checkbox_field($args) {
        $value = get_option($args['option_name'], false);
        echo '<input type="checkbox" name="' . esc_attr($args['option_name']) . '" value="1" ' . checked($value, true, false) . ' />';
    }

    public function render_text_field($args) {
        $value = get_option($args['option_name'], $args['placeholder']);
        echo '<input type="text" class="regular-text" name="' . esc_attr($args['option_name']) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($args['placeholder']) . '" />';
    }


    public function sanitize_bool($val) {
        return (bool) $val;
    }

    public function field_delete_on_uninstall() {
        $value = (bool) get_option(self::OPTION_DELETE_ON_UNINSTALL, false);
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_DELETE_ON_UNINSTALL); ?>" value="1" <?php checked($value, true); ?> />
            <?php esc_html_e('If enabled, the plugin will drop its log table and remove related options when you uninstall it from Plugins.', 'brs-block-fake-orders'); ?>
        </label>
        <?php
    }

    public function render_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('BRS Block Fake Orders – Settings', 'brs-block-fake-orders'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('brs_bfo_settings');
                do_settings_sections('brs_bfo_settings');
                submit_button();
                ?>
            </form>
            <hr />
            <p><strong><?php esc_html_e('Heads up:', 'brs-block-fake-orders'); ?></strong>
            <?php esc_html_e('Uninstall is different from deactivation. Data is only removed on uninstall, and only if the above checkbox is enabled.', 'brs-block-fake-orders'); ?></p>
        </div>
        <?php
    }

    public function plugin_action_links($links) {
        $url = admin_url('admin.php?page=brs-fake-orders-settings');
        array_unshift($links, '<a href="'.esc_url($url).'">'.esc_html__('Settings', 'brs-block-fake-orders').'</a>');
        return $links;
    }
}
