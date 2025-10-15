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
