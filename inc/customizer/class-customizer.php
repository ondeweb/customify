<?php
/**
 * Customify Theme Customizer
 *
 * @package customify
 */

class  Customify_Customizer
{
    static $config;
    static $_instance;
    static $has_icon = false;
    static $has_font = false;
    public $devices = array('desktop', 'tablet', 'mobile');
    private $selective_settings = array();

    function __construct()
    {

    }

    /**
     * Main initial
     */
    function init()
    {

        require_once get_template_directory() . '/inc/customizer/class-customizer-sanitize.php';
        require_once get_template_directory() . '/inc/customizer/class-customizer-auto-css.php';

        if (is_admin() || is_customize_preview()) {
            add_action( 'customize_register', array($this, 'register'), 666 );
            add_action('customize_preview_init', array($this, 'preview_js'));
            add_action('wp_ajax_customify/customizer/ajax/get_icons', array($this, 'get_icons'));

            require_once get_template_directory() . '/inc/customizer/class-customizer-fonts.php';
            require_once get_template_directory() . '/inc/customizer/class-customizer.php';
        }

        add_action('wp_ajax_customify__reset_section', array('Customify_Customizer', 'reset_customize_section'));
    }

    static function get_instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Reset Customize section
     */
    static function reset_customize_section()
    {
        if (!current_user_can('customize')) {
            wp_send_json_error();
        }

        $settings = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : array();

        foreach ($settings as $k) {
            $k = sanitize_text_field($k);
            remove_theme_mod($k);
        }

        wp_send_json_success();
    }

    /**
     * Reset Customize section
     */
    function get_icons()
    {
        if (!current_user_can('customize')) {
            wp_send_json_error();
        }

        require_once get_template_directory() . '/inc/customizer/class-customizer-icons.php';
        wp_send_json_success(Customify_Font_Icons::get_instance()->get_icons());
        die();
    }

    /**
     * Binds JS handlers to make Theme Customizer preview reload changes asynchronously.
     */
    function preview_js()
    {
        if (is_customize_preview()) {
            $suffix = Customify()->get_asset_suffix();

            wp_enqueue_script('customify-customizer-auto-css', esc_url(get_template_directory_uri()) . '/assets/js/customizer/auto-css' . $suffix . '.js', array('customize-preview'), '20151215', true);
            wp_enqueue_script('customify-customizer', esc_url( get_template_directory_uri() ) . '/assets/js/customizer/customizer' . $suffix . '.js', array('customize-preview', 'customize-selective-refresh'), '20151215', true);
            wp_localize_script('customify-customizer-auto-css', 'Customify_Preview_Config', array(
                'fields'         => $this->get_config(),
                'devices'        => $this->devices,
                'typo_fields'    => $this->get_typo_fields(),
                'styling_config' => $this->get_styling_config(),
            ));
        }
    }

    /**
     * Get all customizer settings/control that added via `customify/customizer/config` hook
     *
     *  Ensure you call this method after all config files loaded
     *
     * @param null $wp_customize
     * @return array
     */
    static function get_config($wp_customize = null)
    {
        if ( is_null( self::$config ) ) {

            $_config = apply_filters('customify/customizer/config', array(), $wp_customize);
            $config = array();
            foreach ($_config as $f) {

                $f = wp_parse_args($f, array(

                    'priority'    => null,
                    'title'       => null,
                    'label'       => null,
                    'name'        => null,
                    'type'        => null,
                    'description' => null,
                    'capability'  => null,
                    'mod'         => null, // theme_mod || option default theme_mod
                    'settings'    => null,

                    'active_callback'      => null, // For control

                    // For settings
                    'sanitize_callback'    => array('Customify_Sanitize_Input', 'sanitize_customizer_input'),
                    'sanitize_js_callback' => null,
                    'theme_supports'       => null,
                    //'transport'          => 'postMessage', // or refresh
                    'default'              => null,

                    // for selective refresh
                    'selector'             => null,
                    'render_callback'      => null,
                    'css_format'           => null,

                    'device'          => null,
                    'device_settings' => null,

                    'field_class' => null, // Custom class for control
                ));

                if (!isset($f['type'])) {
                    $f['type'] = null;
                }

                switch ($f['type']) {
                    case 'panel':
                        $config['panel|' . $f['name']] = $f;
                        break;
                    case 'section':
                        $config['section|' . $f['name']] = $f;
                        break;
                    default:
                        if ($f['type'] == 'icon') {
                            self::$has_icon = true;
                        }

                        if ($f['type'] == 'font') {
                            self::$has_font = true;
                        }

                        if (isset($f['fields'])) {
                            if (!in_array($f['type'], array('typography', 'styling', 'modal'))) {
                                $types = wp_list_pluck($f['fields'], 'type');
                                if (in_array('icon', $types)) {
                                    self::$has_icon = true;
                                }

                                if (in_array('font', $types)) {
                                    self::$has_font = true;
                                }
                            }
                        }
                        $config['setting|' . $f['name']] = $f;

                }
            }
            self::$config = $config;
        }
        return self::$config;
    }

    /**
     * Check if has icon field;
     *
     * @return bool
     */
    function has_icon()
    {
        return self::$has_icon;
    }

    /**
     * Check if has font field;
     *
     * @return bool
     */
    function has_font()
    {
        return self::$has_icon;
    }

    /**
     *  Get Customizer setting.
     *
     * @param $name
     * @param string $device
     * @param bool $key
     * @return array|bool|mixed|null|string|void
     */
    function get_setting($name, $device = 'desktop', $key = false)
    {
        $config = self::get_config();
        $get_value = null;
        if (isset($config['setting|' . $name])) {
            $default = isset($config['setting|' . $name]['default']) ? $config['setting|' . $name]['default'] : false;
            $default = apply_filters('customify/customize/settings-default', $default, $name);
            if ('option' == $config['setting|' . $name]['mod']) {
                $value = get_option($name, $default);
            } else {
                $value = get_theme_mod($name, $default);
            }

            if (!$config['setting|' . $name]['device_settings']) {
                return $value;
            }

        } else {
            $value = get_theme_mod($name, null);
        }

        if (!$key) {
            if ($device != 'all') {
                if (is_array($value) && isset($value[$device])) {
                    $get_value = $value[$device];
                } else {
                    $get_value = $value;
                }
            } else {
                $get_value = $value;
            }
        } else {
            $value_by_key = isset($value[$key]) ? $value[$key] : false;
            if ($device != 'all' && is_array($value_by_key)) {
                if (is_array($value_by_key) && isset($value_by_key[$device])) {
                    $get_value = $value_by_key[$device];
                } else {
                    $get_value = $value_by_key;
                }
            } else {
                $get_value = $value_by_key;
            }
        }

        return $get_value;
    }

    /**
     * Get customizer setting data when the field type is `modal`
     *
     * @param string $name Setting name
     * @param string $tab  String tab name
     *
     * @return array|bool
     */
    function get_setting_tab($name, $tab = null)
    {
        $values = $this->get_setting($name, 'all');
        if (!$tab) {
            return $values;
        }
        if (is_array($values) && isset($values[$tab])) {
            return $values[$tab];
        }
        return false;
    }

    /**
     * Get typography fields
     *
     * @return array
     */
    function get_typo_fields()
    {
        $typo_fields = array(
            array(
                'name'    => 'font',
                'type'    => 'select',
                'label'   => __('Font Family', 'customify'),
                'choices' => array()
            ),
            array(
                'name'    => 'font_weight',
                'type'    => 'select',
                'label'   => __('Font Weight', 'customify'),
                'choices' => array()
            ),
            array(
                'name'  => 'languages',
                'type'  => 'checkboxes',
                'label' => __('Font Languages', 'customify'),
            ),
            array(
                'name'            => 'font_size',
                'type'            => 'slider',
                'label'           => __('Font Size', 'customify'),
                'device_settings' => true,
                'min'             => 9,
                'max'             => 80,
                'step'            => 1
            ),
            array(
                'name'            => 'line_height',
                'type'            => 'slider',
                'label'           => __('Line Height', 'customify'),
                'device_settings' => true,
                'min'             => 9,
                'max'             => 80,
                'step'            => 1
            ),
            array(
                'name'  => 'letter_spacing',
                'type'  => 'slider',
                'label' => __('Letter Spacing', 'customify'),
                'min'   => -10,
                'max'   => 10,
                'step'  => 0.1
            ),
            array(
                'name'    => 'style',
                'type'    => 'select',
                'label'   => __('Font Style', 'customify'),
                'choices' => array(
                    ''        => __('Default', 'customify'),
                    'normal'  => __('Normal', 'customify'),
                    'italic'  => __('Italic', 'customify'),
                    'oblique' => __('Oblique', 'customify'),
                )
            ),
            array(
                'name'    => 'text_decoration',
                'type'    => 'select',
                'label'   => __('Text Decoration', 'customify'),
                'choices' => array(
                    ''             => __('Default', 'customify'),
                    'underline'    => __('Underline', 'customify'),
                    'overline'     => __('Overline', 'customify'),
                    'line-through' => __('Line through', 'customify'),
                    'none'         => __('None', 'customify'),
                )
            ),
            array(
                'name'    => 'text_transform',
                'type'    => 'select',
                'label'   => __('Text Transform', 'customify'),
                'choices' => array(
                    ''           => __('Default', 'customify'),
                    'uppercase'  => __('Uppercase', 'customify'),
                    'lowercase'  => __('Lowercase', 'customify'),
                    'capitalize' => __('Capitalize', 'customify'),
                    'none'       => __('None', 'customify'),
                )
            )
        );

        return $typo_fields;
    }

    /**
     * Get styling field
     *
     * @return array
     */
    function get_styling_config()
    {
        $fields = array(
            'tabs'          => array(
                'normal' => __('Normal', 'customify'),  // null or false to disable
                'hover'  => __('Hover', 'customify'), // null or false to disable
            ),
            'normal_fields' => array(
                array(
                    'name'       => 'text_color',
                    'type'       => 'color',
                    'label'      => __('Color', 'customify'),
                    'css_format' => 'color: {{value}}; text-decoration-color: {{value}};'
                ),
                array(
                    'name'       => 'link_color',
                    'type'       => 'color',
                    'label'      => __('Link Color', 'customify'),
                    'css_format' => 'color: {{value}}; text-decoration-color: {{value}};'
                ),

                array(
                    'name'            => 'margin',
                    'type'            => 'css_ruler',
                    'device_settings' => true,
                    'css_format'      => array(
                        'top'    => 'margin-top: {{value}};',
                        'right'  => 'margin-right: {{value}};',
                        'bottom' => 'margin-bottom: {{value}};',
                        'left'   => 'margin-left: {{value}};',
                    ),
                    'label'           => __('Margin', 'customify'),
                ),

                array(
                    'name'            => 'padding',
                    'type'            => 'css_ruler',
                    'device_settings' => true,
                    'css_format'      => array(
                        'top'    => 'padding-top: {{value}};',
                        'right'  => 'padding-right: {{value}};',
                        'bottom' => 'padding-bottom: {{value}};',
                        'left'   => 'padding-left: {{value}};',
                    ),
                    'label'           => __('Padding', 'customify'),
                ),

                array(
                    'name'  => 'bg_heading',
                    'type'  => 'heading',
                    'label' => __('Background', 'customify'),
                ),

                array(
                    'name'       => 'bg_color',
                    'type'       => 'color',
                    'label'      => __('Background Color', 'customify'),
                    'css_format' => 'background-color: {{value}};'
                ),
                array(
                    'name'       => 'bg_image',
                    'type'       => 'image',
                    'label'      => __('Background Image', 'customify'),
                    'css_format' => 'background-image: url("{{value}}");'
                ),
                array(
                    'name'       => 'bg_cover',
                    'type'       => 'select',
                    'choices'    => array(
                        ''        => __('Default', 'customify'),
                        'auto'    => __('Auto', 'customify'),
                        'cover'   => __('Cover', 'customify'),
                        'contain' => __('Contain', 'customify'),
                    ),
                    'required'   => array('bg_image', 'not_empty', ''),
                    'label'      => __('Size', 'customify'),
                    'class'      => 'field-half-left',
                    'css_format' => '-webkit-background-size: {{value}}; -moz-background-size: {{value}}; -o-background-size: {{value}}; background-size: {{value}};'
                ),
                array(
                    'name'       => 'bg_position',
                    'type'       => 'select',
                    'label'      => __('Position', 'customify'),
                    'required'   => array('bg_image', 'not_empty', ''),
                    'class'      => 'field-half-right',
                    'choices'    => array(
                        ''              => __('Default', 'customify'),
                        'center'        => __('Center', 'customify'),
                        'top left'      => __('Top Left', 'customify'),
                        'top right'     => __('Top Right', 'customify'),
                        'top center'    => __('Top Center', 'customify'),
                        'bottom left'   => __('Bottom Left', 'customify'),
                        'bottom center' => __('Bottom Center', 'customify'),
                        'bottom right'  => __('Bottom Right', 'customify'),
                    ),
                    'css_format' => 'background-position: {{value}};'
                ),
                array(
                    'name'       => 'bg_repeat',
                    'type'       => 'select',
                    'label'      => __('Repeat', 'customify'),
                    'class'      => 'field-half-left',
                    'required'   => array(
                        array('bg_image', 'not_empty', ''),
                    ),
                    'choices'    => array(
                        'repeat'    => __('Default', 'customify'),
                        'no-repeat' => __('No repeat', 'customify'),
                        'repeat-x'  => __('Repeat horizontal', 'customify'),
                        'repeat-y'  => __('Repeat vertical', 'customify'),
                    ),
                    'css_format' => 'background-repeat: {{value}};'
                ),

                array(
                    'name'       => 'bg_attachment',
                    'type'       => 'select',
                    'label'      => __('Attachment', 'customify'),
                    'class'      => 'field-half-right',
                    'required'   => array(
                        array('bg_image', 'not_empty', '')
                    ),
                    'choices'    => array(
                        ''       => __('Default', 'customify'),
                        'scroll' => __('Scroll', 'customify'),
                        'fixed'  => __('Fixed', 'customify')
                    ),
                    'css_format' => 'background-attachment: {{value}};'
                ),

                array(
                    'name'  => 'border_heading',
                    'type'  => 'heading',
                    'label' => __('Border', 'customify'),
                ),

                array(
                    'name'       => 'border_style',
                    'type'       => 'select',
                    'class'      => 'clear',
                    'label'      => __('Border Style', 'customify'),
                    'default'    => '',
                    'choices'    => array(
                        ''       => __('Default', 'customify'),
                        'none'   => __('None', 'customify'),
                        'solid'  => __('Solid', 'customify'),
                        'dotted' => __('Dotted', 'customify'),
                        'dashed' => __('Dashed', 'customify'),
                        'double' => __('Double', 'customify'),
                        'ridge'  => __('Ridge', 'customify'),
                        'inset'  => __('Inset', 'customify'),
                        'outset' => __('Outset', 'customify'),
                    ),
                    'css_format' => 'border-style: {{value}};',
                ),

                array(
                    'name'       => 'border_width',
                    'type'       => 'css_ruler',
                    'label'      => __('Border Width', 'customify'),
                    'required'   => array(
                        array( 'border_style', '!=', 'none' ),
                        array( 'border_style', '!=', '' )
                    ),
                    'css_format' => array(
                        'top'    => 'border-top-width: {{value}};',
                        'right'  => 'border-right-width: {{value}};',
                        'bottom' => 'border-bottom-width: {{value}};',
                        'left'   => 'border-left-width: {{value}};'
                    ),
                ),
                array(
                    'name'       => 'border_color',
                    'type'       => 'color',
                    'label'      => __('Border Color', 'customify'),
                    'required'   => array(
                        array( 'border_style', '!=', 'none' ),
                        array( 'border_style', '!=', '' )
                    ),
                    'css_format' => 'border-color: {{value}};',
                ),

                array(
                    'name'       => 'border_radius',
                    'type'       => 'css_ruler',
                    'label'      => __('Border Radius', 'customify'),
                    'css_format' => array(
                        'top'    => 'border-top-left-radius: {{value}};',
                        'right'  => 'border-top-right-radius: {{value}};',
                        'bottom' => 'border-bottom-right-radius: {{value}};',
                        'left'   => 'border-bottom-left-radius: {{value}};'
                    ),
                ),

                array(
                    'name'       => 'box_shadow',
                    'type'       => 'shadow',
                    'label'      => __('Box Shadow', 'customify'),
                    'css_format' => 'box-shadow: {{value}};',
                ),

            ),

            'hover_fields' => array(
                array(
                    'name'       => 'text_color',
                    'type'       => 'color',
                    'label'      => __('Color', 'customify'),
                    'css_format' => 'color: {{value}}; text-decoration-color: {{value}};'
                ),
                array(
                    'name'       => 'link_color',
                    'type'       => 'color',
                    'label'      => __('Link Color', 'customify'),
                    'css_format' => 'color: {{value}}; text-decoration-color: {{value}};'
                ),
                array(
                    'name'  => 'bg_heading',
                    'type'  => 'heading',
                    'label' => __('Background', 'customify'),
                ),
                array(
                    'name'       => 'bg_color',
                    'type'       => 'color',
                    'label'      => __('Background Color', 'customify'),
                    'css_format' => 'background-color: {{value}};'
                ),
                array(
                    'name'  => 'border_heading',
                    'type'  => 'heading',
                    'label' => __('Border', 'customify'),
                ),
                array(
                    'name'       => 'border_style',
                    'type'       => 'select',
                    'label'      => __('Border Style', 'customify'),
                    'default'    => '',
                    'choices'    => array(
                        ''       => __('Default', 'customify'),
                        'none'   => __('None', 'customify'),
                        'solid'  => __('Solid', 'customify'),
                        'dotted' => __('Dotted', 'customify'),
                        'dashed' => __('Dashed', 'customify'),
                        'double' => __('Double', 'customify'),
                        'ridge'  => __('Ridge', 'customify'),
                        'inset'  => __('Inset', 'customify'),
                        'outset' => __('Outset', 'customify'),
                    ),
                    'css_format' => 'border-style: {{value}};',
                ),
                array(
                    'name'       => 'border_width',
                    'type'       => 'css_ruler',
                    'label'      => __('Border Width', 'customify'),
                    'required'   => array('border_style', '!=', 'none'),
                    'css_format' => array(
                        'top'    => 'border-top-width: {{value}};',
                        'right'  => 'border-right-width: {{value}};',
                        'bottom' => 'border-bottom-width: {{value}};',
                        'left'   => 'border-left-width: {{value}};'
                    ),
                ),
                array(
                    'name'       => 'border_color',
                    'type'       => 'color',
                    'label'      => __('Border Color', 'customify'),
                    'required'   => array('border_style', '!=', 'none'),
                    'css_format' => 'border-color: {{value}};',
                ),
                array(
                    'name'       => 'border_radius',
                    'type'       => 'css_ruler',
                    'label'      => __('Border Radius', 'customify'),
                    'css_format' => array(
                        'top'    => 'border-top-left-radius: {{value}};',
                        'right'  => 'border-top-right-radius: {{value}};',
                        'bottom' => 'border-bottom-right-radius: {{value}};',
                        'left'   => 'border-bottom-left-radius: {{value}};'
                    ),
                ),
                array(
                    'name'       => 'box_shadow',
                    'type'       => 'shadow',
                    'label'      => __('Box Shadow', 'customify'),
                    'css_format' => 'box-shadow: {{value}};',
                ),

            ),


        );

        return apply_filters( 'customify/get_styling_config', $fields );
    }

    /**
     * Setup icon args
     *
     * @param $icon
     * @return array
     */
    function setup_icon($icon)
    {
        if (!is_array($icon)) {
            $icon = array();
        }
        return wp_parse_args($icon, array('type' => '', 'icon' => ''));
    }

    /**
     * Get customize setting data
     *
     * @param string $key Setting name
     * @return bool|mixed
     */
    function get_field_setting($key)
    {
        $config = self::get_config();
        if (isset($config['setting|' . $key])) {
            return $config['setting|' . $key];
        }
        return false;
    }

    /**
     * Get Media url from data
     *
     * @param string/array $value   media data
     * @param null $size            WordPress image size name
     * @return array|false          Media url or empty
     */
    function get_media($value, $size = null)
    {

        if (empty($value)) {
            return false;
        }

        if (!$size) {
            $size = 'full';
        }
        // attachment_url_to_postid
        if (is_numeric($value)) {
            $image_attributes = wp_get_attachment_image_src($value, $size);
            if ($image_attributes) {
                return $image_attributes[0];
            } else {
                return false;
            }
        } elseif (is_string($value)) {
            $img_id = attachment_url_to_postid($value);
            if ($img_id) {
                $image_attributes = wp_get_attachment_image_src($img_id, $size);
                if ($image_attributes) {
                    return $image_attributes[0];
                } else {
                    return false;
                }
            }
            return $value;
        } elseif (is_array($value)) {
            $value = wp_parse_args($value, array(
                'id'   => '',
                'url'  => '',
                'mime' => ''
            ));

            if (empty($value['id']) && empty($value['url'])) {
                return false;
            }

            $url = '';

            if (strpos($value['mime'], 'image/') !== false) {
                $image_attributes = wp_get_attachment_image_src($value['id'], $size);
                if ($image_attributes) {
                    $url = $image_attributes[0];
                }
            } else {
                $url = wp_get_attachment_url($value['id']);
            }

            if (!$url) {
                $url = $value['url'];
                if ($url) {
                    $img_id = attachment_url_to_postid($url);
                    if ($img_id) {
                        return wp_get_attachment_url($img_id);
                    }
                }
            }

            return $url;
        }

        return false;
    }

    /**
     * Load support controls
     */
    function load_controls()
    {
        $fields = array(
            'base',
            'select',
            'font',
            'font_style',
            'text_align',
            'text_align_no_justify',
            'checkbox',
            'css_ruler',
            'shadow',
            'icon',
            'slider',
            'color',
            'textarea',
            'radio',

            'media',
            'image',
            'video',

            'text',
            'hidden',
            'heading',
            'typography',
            'modal',
            'styling',
            'hr',

            'repeater'
            // custom_key => full_file_path
        );

        $fields = apply_filters('customify/customize/register-controls', $fields);

        foreach ($fields as $k => $f ) {
            // $field_type
            if ( is_numeric( $k ) ) {
                $field_type = $f;
                $file = get_template_directory() . '/inc/customizer/controls/class-control-' . str_replace('_', '-', $field_type) . '.php';
            } else {
                $field_type = $k;
                $file = $f;
            }

            if (file_exists($file)) {

                $control_class_name = 'Customify_Customizer_Control_';
                $tpl_type = str_replace('_', ' ', $field_type);
                $tpl_type = str_replace(' ', '_', ucfirst($tpl_type));
                $control_class_name .= $tpl_type;
                require_once $file;

                if ($control_class_name) {
                    if (method_exists($control_class_name, 'field_template')) {
                        add_action('customize_controls_print_footer_scripts', array($control_class_name, 'field_template'));
                    }
                }
            }
        }

    }


    /**
     * Register Customize Settings
     *
     * @param $wp_customize
     */
    function register($wp_customize)
    {

        //Custom panel
        require_once get_template_directory() . '/inc/customizer/class-customify-wp-customize-panel.php';
        // Load custom section
        require_once get_template_directory() . '/inc/customizer/class-customify-wp-customize-section.php';

        // Register new panel and section type
        $wp_customize->register_panel_type('Customify_WP_Customize_Panel');
        $wp_customize->register_section_type('Customify_WP_Customize_Section');

        $this->load_controls();

        foreach (self::get_config($wp_customize) as $args) {
            switch ($args['type']) {
                case  'panel':
                    $name = $args['name'];
                    unset($args['name']);
                    if (!$args['title']) {
                        $args['title'] = $args['label'];
                    }
                    if (!$name) {
                        $name = $args['title'];
                    }
                    unset($args['name']);
                    unset($args['type']);
                    $panel = new Customify_WP_Customize_Panel($wp_customize, $name, $args);
                    $wp_customize->add_panel($panel);
                    break;
                case 'section':
                    $name = $args['name'];
                    unset($args['name']);
                    if (!$args['title']) {
                        $args['title'] = $args['label'];
                    }
                    if (!$name) {
                        $name = $args['title'];
                    }
                    unset($args['name']);
                    unset($args['type']);
                    $wp_customize->add_section(new Customify_WP_Customize_Section($wp_customize, $name, $args));
                    break;
                default:

                    switch ($args['type']) {
                        case  'image_select':
                            $args['setting_type'] = 'radio';
                            $args['field_class'] = 'custom-control-image_select' . ($args['field_class'] ? ' ' . $args['field_class'] : '');
                            break;
                        case  'radio_group':
                            $args['setting_type'] = 'radio';
                            $args['field_class'] = 'custom-control-radio_group' . ($args['field_class'] ? ' ' . $args['field_class'] : '');
                            break;
                        default:
                            $args['setting_type'] = $args['type'];
                    }


                    $args['defaultValue'] = $args['default'];
                    $settings_args = array(
                        'sanitize_callback'    => $args['sanitize_callback'],
                        'sanitize_js_callback' => $args['sanitize_js_callback'],
                        'theme_supports'       => $args['theme_supports'],
                        'type'                 => $args['mod'],
                    );
                    $settings_args['default'] = apply_filters('customify/customize/settings-default', $args['default'], $args['name']);

                    $settings_args['transport'] = 'refresh';
                    if (!$settings_args['sanitize_callback']) {
                        $settings_args['sanitize_callback'] = array('Customify_Sanitize_Input', 'sanitize_customizer_input');
                    }

                    foreach ($settings_args as $k => $v) {
                        unset($args[$k]);
                    }

                    unset($args['mod']);
                    $name = $args['name'];
                    unset($args['name']);

                    unset($args['type']);
                    if (!$args['label']) {
                        $args['label'] = $args['title'];
                    }

                    $selective_refresh = null;
                    if ($args['selector'] && ($args['render_callback'] || $args['css_format'])) {
                        $selective_refresh = array(
                            'selector'        => $args['selector'],
                            'render_callback' => $args['render_callback'],
                        );

                        if ($args['css_format']) {
                            //$selective_refresh['selector'] = '#customify-style-inline-css';
                            // $selective_refresh['render_callback'] = 'Customify_Customizer_Auto_CSS';
                            $settings_args['transport'] = 'postMessage';
                            $selective_refresh = null;
                        } else {
                            $settings_args['transport'] = 'postMessage';
                        }


                    }
                    unset($args['default']);

                    $wp_customize->add_setting($name, array_merge(array('sanitize_callback' => array('Customify_Sanitize_Input', 'sanitize_customizer_input')), $settings_args));

                    $control_class_name = 'Customify_Customizer_Control_';
                    $tpl_type = str_replace('_', ' ', $args['setting_type']);
                    $tpl_type = str_replace(' ', '_', ucfirst($tpl_type));
                    $control_class_name .= $tpl_type;

                    if ($settings_args['type'] != 'js_raw') {
                        if (class_exists($control_class_name)) {
                            $wp_customize->add_control(new $control_class_name($wp_customize, $name, $args));
                        } else {
                            $wp_customize->add_control(new Customify_Customizer_Control_Base($wp_customize, $name, $args));
                        }
                    }

                    if ($selective_refresh) {
                        $s_id = $selective_refresh['render_callback'];
                        if (is_array($s_id)) {
                            if ( is_string( $s_id[0]) )  {
                                $__id = $s_id[0] . '__' . $s_id[1];
                            } else {
                                $__id = get_class($s_id[0]) . '__' . $s_id[1];
                            }

                        } else {
                            $__id = $s_id;
                        }
                        if (!isset($this->selective_settings[$__id])) {
                            $this->selective_settings[$__id] = array(
                                'settings'            => array(),
                                'selector'            => $selective_refresh['selector'],
                                'container_inclusive' => (strpos($__id, 'Customify_Customizer_Auto_CSS') === false) ? true : false,
                                'render_callback'     => $s_id,
                            );

                        }

                        $this->selective_settings[$__id]['settings'][] = $name;
                    }

                    break;
            }// end switch

        } // End loop config

        // Remove partial for default custom logo and add this by theme custom
        $wp_customize->selective_refresh->remove_partial('custom_logo');

        $wp_customize->get_section('title_tagline')->panel = 'header_settings';
        $wp_customize->get_section('title_tagline')->title = __('Logo & Site Identity', 'customify');
        $wp_customize->get_setting('header_textcolor')->transport = 'postMessage';
        // add selective refresh
        $wp_customize->get_setting('custom_logo')->transport = 'postMessage';
        $wp_customize->get_setting('blogname')->transport = 'postMessage';
        $wp_customize->get_setting('blogdescription')->transport = 'postMessage';

        foreach ($this->selective_settings as $cb => $settings) {
            reset($settings['settings']);
            if ($cb == 'Customify_Builder_Item_Logo__render') {
                $settings['settings'][] = 'custom_logo';
                $settings['settings'][] = 'blogname';
                $settings['settings'][] = 'blogdescription';
            }
            $settings = apply_filters($cb, $settings);
            $wp_customize->selective_refresh->add_partial($cb, $settings);
        }

        // For live CSS
        $wp_customize->add_setting('customify__css', array(
            'default'           => '',
            'transport'         => 'postMessage',
            'sanitize_callback' => array('Customify_Sanitize_Input', 'sanitize_css_code'),
        ));

        do_action('customify/customize/register_completed', $this);
    }

}
