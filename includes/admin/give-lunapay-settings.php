<?php

/**
 * Class Give_lunapay_Settings
 *
 * @since 3.0.2
 */
class Give_lunapay_Settings
{

    /**
     * @access private
     * @var Give_lunapay_Settings $instance
     */
    private static $instance;

    /**
     * @access private
     * @var string $section_id
     */
    private $section_id;

    /**
     * @access private
     *
     * @var string $section_label
     */
    private $section_label;

    /**
     * Give_lunapay_Settings constructor.
     */
    private function __construct()
    {

    }

    /**
     * get class object.
     *
     * @return Give_lunapay_Settings
     */
    public static function get_instance()
    {
        if (null === static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Setup hooks.
     */
    public function setup_hooks()
    {

        $this->section_id = 'lunapay';
        $this->section_label = __('Lunapay', 'give-lunapay');

        if (is_admin()) {
            // Add settings.
            add_filter('give_get_settings_gateways', array($this, 'add_settings'), 99);
            add_filter('give_get_sections_gateways', array($this, 'add_sections'), 99);
        }
    }

    /**
     * Add setting section.
     *
     * @param array $sections Array of section.
     *
     * @return array
     */
    public function add_sections($sections)
    {
        $sections[$this->section_id] = $this->section_label;

        return $sections;
    }

    /**
     * Add plugin settings.
     *
     * @param array $settings Array of setting fields.
     *
     * @return array
     */
    public function add_settings($settings)
    {
        $current_section = give_get_current_setting_section();

        if ($current_section != 'lunapay') {
            return $settings;
        }

        $give_lunapay_settings = array(
            array(
                'name' => __('lunapay Settings', 'give-lunapay'),
                'id' => 'give_title_gateway_lunapay',
                'type' => 'title',
            ),
         array(
            'name'        => __('Client ID', 'give-lunapay'),
            'desc'        => __('Enter your Client ID, found in your lunapay Account Settings.', 'give-lunapay'),
            'id'          => 'lunapay_client_id',
            'type'        => 'text',
            'row_classes' => 'give-lunapay-key',
      ),
          array(
            'name'        => __('LunaKey', 'give-lunapay'),
            'desc'        => __('Enter your LunaKey, found in your lunapay Account Settings.', 'give-lunapay'),
            'id'          => 'lunapay_lunakey',
            'type'        => 'text',
            'row_classes' => 'give-lunapay-key',
          ),
			
		  array(
            'name'        => __('Luna-signature Key', 'give-lunapay'),
            'desc'        => __('Enter your Luna-signature Key, found in your lunapay Account Settings.', 'give-lunapay'),
            'id'          => 'lunapay_luna_signature_key',
            'type'        => 'text',
            'row_classes' => 'give-lunapay-key',
          ),
			
		  array(
            'name'        => __('Group ID', 'give-lunapay'),
            'desc'        => __('Enter your Group ID, found in your lunapay Account Settings.', 'give-lunapay'),
            'id'          => 'lunapay_group_id',
            'type'        => 'text',
            'row_classes' => 'give-lunapay-key',
          ),
			
		array(
			'name' => __('Server End Point', 'give-lunapay'),
			'desc' => __('Enable Live Server, disable will set to default sandbox server', 'give-lunapay'),
			'id' => 'lunapay_server_end_point',
			'type' => 'radio_inline',
			'default' => 'disabled',
			'options' => array(
				'disabled' => __('Disabled', 'give-lunapay'),
				'enabled' => __('Enabled', 'give-lunapay'),
			),
			),
         
          
            array(
                'type' => 'sectionend',
                'id' => 'give_title_gateway_lunapay',
            ),
        );

        return array_merge($settings, $give_lunapay_settings);
    }
}

Give_lunapay_Settings::get_instance()->setup_hooks();
