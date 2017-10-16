<?php

class _Beacon_Customizer_Control extends WP_Customize_Control {
    /**
     * The control type.
     *
     * @access public
     * @var string
     */
    public $type = '_beacon';
    /**
     * Data type
     *
     * @access public
     * @var string
     */
    public $option_type = 'theme_mod';



    public $setting_type = 'group';
    public $fields = array();
    public $choices = array();
    public $default = array();
    public $device = '';
    public $checkbox_label = '';

    public $live_title_field; // for repeater

    public $_settings;
    public $_selective_refresh;

    /**
     * Provide the parent, comparison operator, and value which affects the field’s visibility
     *
     * @var
     */
    public $required;

    static $_js_template_added;
    function __construct($manager, $id, $args = array())
    {
        parent::__construct($manager, $id, $args);

        add_action( 'customize_controls_print_footer_scripts', array( $this, 'content_js_template' ) );
    }

    /**
     * Enqueue control related scripts/styles.
     *
     * @access public
     */
    public function enqueue() {
        wp_enqueue_media();
        if( $this->setting_type == 'repeater' ) {
            wp_enqueue_script('jquery-ui-sortable');
        }
        wp_enqueue_style('_beacon-customizer-control', get_template_directory_uri().'/assets/css/admin/customizer/customizer.css');
        wp_enqueue_script( '_beacon-customizer-control',  get_template_directory_uri().'/assets/js/customizer/control.js', array( 'jquery', 'customize-base', 'jquery-ui-core', 'jquery-ui-sortable' ), false, true );
        wp_localize_script( '_beacon-customizer-control', '_Beacon_Control_Args', array(
                'home_url' => home_url('')
        ) );
    }



    /**
     * Refresh the parameters passed to the JavaScript via JSON.
     *
     * @access public
     */
    public function to_json() {
        parent::to_json();
        // Add something here
        $value = $this->value();
        if ( $this->setting_type == 'group' ) {
            if ( ! is_array( $value ) ) {
                $value = array();
            }
            foreach ( $this->fields as $k => $f ) {
                if ( isset( $value[ $f['name'] ] ) ) {
                    $this->fields[ $k ]['value'] = $value[ $f['name'] ];
                }
            }

            if ( ! is_array( $this->default ) ) {
                $this->default = array();
            }

        } elseif (  $this->setting_type == 'repeater' ) {
            if ( ! is_array( $value ) ) {
                $value = array();
            }
            if ( ! is_array( $this->default ) ) {
                $this->default = array();
            }
        }

        $this->json['value']        = $value;
        $this->json['default']      = $this->default;
        $this->json['fields']       = $this->fields;
        $this->json['setting_type'] = $this->setting_type;
        if ( $this->setting_type == 'repeater' ) {
            $this->json['l10n'] = array(
                'untitled' => __( 'Untitled', '_beacon' )
            );
            $this->json['live_title_field'] = $this->live_title_field;
        }

        if ( $this->setting_type == 'select' || $this->setting_type == 'radio' ) {
            $this->json['choices'] = $this->choices;
        }
        if ( $this->setting_type == 'checkbox' ) {
            $this->json['checkbox_label'] = $this->checkbox_label;
        }
        //$this->json['link']       = $this->get_link();
    }

    function compare( $value1, $condition, $value2 ){
        $equal = false;
        switch ( $condition ) {
            case '===':
                $equal = $value1 === $value2 ? true : false;
                break;
            case '>':
                $equal = $value1 > $value2 ? true : false;
                break;
            case '<':
                $equal = $value1 < $value2 ? true : false;
                break;
            case '!=':
                $equal = $value1 != $value2 ? true : false;
                break;
            default:
                  $equal = $value1 == $value2 ? true : false;

        }

        return $equal;
    }

    function active_callback() {

        if ( $this->required && is_array( $this->required ) ) {
            $test_field = current( $this->required );
            reset( $this->required );

            if ( is_string( $test_field ) ) {
                $condition = $this->required[1];
                if ( ! $condition ) {
                    $condition = '=';
                }
                $condition_value = $this->required[2];
                if ( isset( $this->required[3] ) && $this->required[3] == 'option' ) {
                    $value = get_option( $test_field );
                } else {
                    $_settings = $this->manager->get_setting( $test_field );
                    $value = get_theme_mod( $test_field, ( $_settings ) ? $_settings->default : null );
                }
                return $this->compare( $value, $condition, $condition_value );
            } else {

                $active = true;
                foreach (  $this->required as $cond ) {
                    $field_name = $cond[0];
                    $field_cond = $cond[1];
                    $field_cond_value = $cond[2];
                    if ( isset( $cond[3] ) && $cond[3] == 'option' ) {
                        $value = get_option( $field_name );
                    } else {
                        $_settings = $this->manager->get_setting( $field_name );
                        $value = get_theme_mod( $field_name, ( $_settings ) ? $_settings->default : null );
                    }
                    if ( ! $this->compare( $value, $field_cond, $field_cond_value ) ) {
                        $active = false;
                    }
                }

                return $active;
            }


        }

       return true;
    }


    /**
     * Renders the control wrapper and calls $this->render_content() for the internals.
     *
     * @since 3.4.0
     */
    protected function render() {
        $id    = 'customize-control-' . str_replace( array( '[', ']' ), array( '-', '' ), $this->id );
        $class = 'customize-control customize-control-' . $this->type.'-'.$this->setting_type;

        ?><li id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $class ); ?><?php echo ( $this->device ) ? '  _beacon--device-show _beacon--device-'.esc_attr( $this->device ) : ''; ?>">
        <?php $this->render_content(); ?>
        </li><?php
    }


    /**
     * Render the control's content.
     * Allows the content to be overriden without having to rewrite the wrapper in $this->render().
     *
     * @access protected
     */
    protected function render_content() {

        if ( $this->setting_type == 'device_select' ) {
            ?>
            <div class="_beacon--device-select">
                <a href="#" class="_beacon--active _beacon--tab-device-general"><?php _e( 'General', '_beacon' ); ?></a>
                <a href="#" class="_beacon--tab-device-mobile"><?php _e( 'Mobile', '_beacon' ); ?></a>
            </div>
            <?php

        } else {
            ?>
            <div class="_beacon--settings-wrapper">
                <label>
                    <?php if (!empty($this->label)) : ?>
                        <span class="customize-control-title"><?php echo esc_html($this->label); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($this->description)) : ?>
                        <span class="description customize-control-description"><?php echo wp_kses_post($this->description); ?></span>
                    <?php endif; ?>
                </label>
                <div class="_beacon--settings-fields<?php echo ( $this->setting_type == 'repeater' ) ? ' _beacon--repeater-items' : ''; ?>"></div>
                <?php if ( $this->setting_type == 'repeater' ) { ?>
                    <a href="#" class="_beacon--repeater-add-new"><?php _e( 'Add item', '_beacon' ); ?></a>
                <?php } ?>
            </div>
            <?php
        }

    }

    function content_js_template() {
        if ( is_null( self::$_js_template_added ) ) {
            self::$_js_template_added  = true;
        } else {
            return ;
        }

        ?>
        <script type="text/html" id="tmpl-customize-control-<?php echo esc_attr( $this->type ); ?>-fields">
            <# _.each( data, function( field ){
                    switch( field.type ) { case 'select':
                    #>
                    <?php $this->field_select(); ?>
                <# break; #>
                <# case 'textarea': #>
                    <?php $this->field_textarea(); ?>
                    <# break; #>
                <# case 'checkbox': #>
                    <?php $this->field_checkbox(); ?>
                    <# break; #>
                <# case 'radio': #>
                    <?php $this->field_radio(); ?>
                    <# break; #>
                <# case 'image': case 'media': #>
                    <?php $this->field_media(); ?>
                    <# break; #>
                <# break;
                    default: #>
                    <?php $this->field_text(); ?>
                <# break;
                }
            }); #>
        </script>
        <script type="text/html" id="tmpl-customize-control-<?php echo esc_attr( $this->type ); ?>-repeater">
            <div class="_beacon--repeater-item">
                <div class="_beacon--repeater-item-heading">
                    <span class="_beacon--repeater-live-title"></span>
                    <a href="#" class="_beacon--repeater-item-toggle"><span class="screen-reader-text"><?php _e( 'Close', '_beacon' ) ?></span></a>
                </div>
                <div class="_beacon--repeater-item-settings">
                    <div class="_beacon--repeater-item-inside">
                        <div class="_beacon--repeater-item-inner">{{{ data }}}</div>
                        <a href="#" class="_beacon--remove"><?php _e( 'Remove', '_beacon' ); ?></a>
                    </div>

                </div>
            </div>
        </script>
        <?php
    }

    function before_field(){
        ?>
        <div class="_beacon--field _beacon--field-{{ field.type }}" data-field-name="{{ field.name }}">
        <?php
    }

     function after_field(){
        ?>
        </div>
        <?php
    }

    function field_text(){
        $this->before_field();
        ?>
        <# if ( field.label ) { #>
            <label>{{{ field.label }}}</label>
        <# } #>
        <# if ( field.description ) { #>
            <p class="description">{{{ field.description }}}</p>
        <# } #>
        <input type="text" class="_beacon-input" data-name="{{ field.name }}" value="{{ field.value }}">
        <?php
        $this->after_field();
    }

    function field_radio(){
        $this->before_field();
        ?>
        <#
        var uniqueID = field.name + ( new Date().getTime() );

        if ( field.label ) { #>
            <label>{{{ field.label }}}</label>
        <# } #>
        <# if ( field.description ) { #>
            <p class="description">{{{ field.description }}}</p>
        <# } #>
        <div class="_beacon-radio-list">
            <# _.each( field.choices, function( label, key ){  #>
                <p>
                <label><input type="radio" data-name="{{ field.name }}" value="{{ key }}" <# if ( field.value == key ){ #> checked="checked" <# } #> name="{{ uniqueID }}"> {{ label }}</label>
                </p>
            <# } ); #>
        </div>
        <?php
        $this->after_field();
    }

    function field_checkbox(){
        $this->before_field();
        ?>
        <label>
            <input type="checkbox" class="_beacon-input" <# if ( field.value == 1 ){ #> checked="checked" <# } #> data-name="{{ field.name }}" value="1"> {{{ field.label }}}
        </label>
        <# if ( field.description ) { #>
            <p class="description">{{{ field.description }}}</p>
        <# } #>
        <?php
        $this->after_field();
    }

    function field_textarea(){
        $this->before_field();
        ?>
        <# if ( field.label ) { #>
            <label>{{{ field.label }}}</label>
        <# } #>
        <# if ( field.description ) { #>
            <p class="description">{{{ field.description }}}</p>
        <# } #>
        <textarea rows="10" class="_beacon-input" data-name="{{ field.name }}">{{ field.value }}</textarea>
        <?php
        $this->after_field();
    }

    function field_select(){
        $this->before_field();
        ?>
        <# if ( field.label ) { #>
            <label>{{{ field.label }}}</label>
        <# } #>
        <# if ( field.description ) { #>
            <p class="description">{{{ field.description }}}</p>
        <# } #>
        <select class="_beacon-input" data-name="{{ field.name }}">
            <# _.each( field.choices, function( label, key ){  #>
            <option <# if ( field.value == key ){ #> selected="selected" <# } #> value="{{ key }}">{{ label }}</option>
            <# } ); #>
        </select>
        <?php
        $this->after_field();
    }

    function field_media(){
        $this->before_field();
        ?>
        <#

        if ( ! _.isObject(field.value) ) {
            field.value = {};
        }
        if ( field.label ) { #>
            <label>{{{ field.label }}}</label>
        <# } #>
        <# if ( field.description ) { #>
            <p class="description">{{{ field.description }}}</p>
        <# } #>
        <div class="_beacon--media">
            <input type="hidden" class="attachment-id" value="{{ field.value.id }}" data-name="{{ field.name }}">
            <input type="hidden" class="attachment-url"  value="{{ field.value.url }}" data-name="{{ field.name }}-url">
            <input type="hidden" class="attachment-mime"  value="{{ field.value.mime }}" data-name="{{ field.name }}-mime">
            <div class="_beacon-image-preview">
                <#
                var url = field.value.url;
                if ( url ) {
                    if ( url.indexOf('http://') > -1 || url.indexOf('https://') ){

                    } else {
                        url = _Beacon_Control_Args.home_url + url;
                    }

                    if ( ! field.value.mime || field.value.mime.indexOf('image/') > -1 ) {
                        #>
                        <img src="{{ url }}" alt="">
                    <# } else if ( field.value.mime.indexOf('video/' ) > -1 ) { #>
                        <video width="100%" height="" controls><source src="{{ url }}" type="{{ field.value.mime }}">Your browser does not support the video tag.</video>
                    <# } else {
                    var basename = url.replace(/^.*[\\\/]/, '');
                    #>
                        <a href="{{ url }}" class="attachment-file" target="_blank">{{ basename }}</a>
                    <# }
                }
                #>
            </div>
            <button type="button" class="button _beacon--add"><?php _e( 'Add', '_beacon' ); ?></button>
            <button type="button" class="button _beacon--change _beacon--hide"><?php _e( 'Change', '_beacon' ); ?></button>
            <button type="button" class="button _beacon--remove"><?php _e( 'Remove', '_beacon' ); ?></button>
        </div>

        <?php
        $this->after_field();
    }







}