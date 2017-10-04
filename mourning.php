<?php
/*
Plugin Name: Mourning
Plugin URI: https://github.com/woodpeckerr/mourning
Description: Plugin for mourning, add black ribbon and grey out the website
Version: 1.0.1
Author: Woodpeckerr
Author URI: https://github.com/woodpeckerr
Text Domain: mrn
License: GPL2+
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

define( 'MRN_BASE_FILE', plugin_basename( __FILE__ ) );
define( 'MRN_PLUGIN_NAME', 'Mourning' );

// @todo refactor
class MrnMourningFieldKey {
  public function __construct() {
    $this->field_grey_percentage = 'field_grey_percentage';
    $this->field_ribbon_position = 'field_ribbon_position';
  }
}

class MrnMourning {

  public function __construct() {
    $this->is_debug           = false;
    $this->menu_page          = 'mrn-mourning';
    $this->option_group_name  = 'mrn_option_group';
    $this->option_field_name  = 'mrn_option_field';
    $this->setting_section_id = 'mrn_setting_section_id';

    $this->field_key = new MrnMourningFieldKey();
    $this->options   = get_option( $this->option_field_name );

    // set default prop
    // for only
    // - first time
    // - no submitting form
    $this->set_default_prop();

    // backend: menu
    add_action( 'admin_menu', array( $this, 'admin_menu' ) );
    add_action( 'admin_init', array( $this, 'admin_init' ) );

    // backend: plugin
    add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 4 );

    // frontend
    add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    add_action( 'wp_footer', array( $this, 'foot' ) );
  }

  /** ================================================================ backend: menu
   */

  public function admin_menu() {
    add_options_page(
      MRN_PLUGIN_NAME,
      MRN_PLUGIN_NAME,
      'manage_options',
      $this->menu_page,
      array( $this, 'admin_page' )
    );
  }

  public function admin_page() {
    $this->dump(); ?>
    <div class="wrap">
      <h1><?= MRN_PLUGIN_NAME; ?></h1>
      <form method="post" action="options.php">
        <?php
        settings_fields( $this->option_group_name );
        do_settings_sections( $this->menu_page );
        submit_button();
        ?>
      </form>
    </div>
    <style>
    <?php
  }

  public function admin_init() {
    register_setting(
      $this->option_group_name,
      $this->option_field_name,
      array( $this, 'sanitize' )
    );

    // section
    add_settings_section(
      $this->setting_section_id,
      'Settings',
      array( $this, 'print_section_info' ),
      $this->menu_page
    );

    // option field(s)
    // - field_grey_percentage
    // - field_ribbon_position
    add_settings_field(
      $this->field_key->field_grey_percentage,
      'Grayscale percentage',
      array( $this, $this->field_key->field_grey_percentage . '_callback' ),
      $this->menu_page,
      $this->setting_section_id
    );

    add_settings_field(
      $this->field_key->field_ribbon_position,
      'Ribbon position',
      array( $this, $this->field_key->field_ribbon_position . '_callback' ),
      $this->menu_page,
      $this->setting_section_id
    );
  }

  /** ================================================================ backend: field
   */

  public function set_default_prop() {
    // default
    // [
    //   'field_grey_percentage'    => ''
    //   'field_ribbon_position'    => ''
    // ]
    $options = $this->options;

    if ( ! isset( $options[ $this->field_key->field_grey_percentage ] ) ) {
      $options[ $this->field_key->field_grey_percentage ] = '';
    }

    if ( ! isset( $options[ $this->field_key->field_ribbon_position ] ) ) {
      $options[ $this->field_key->field_ribbon_position ] = '';
    }

    $this->options = $options;
  }

  /**
   * Sanitize each setting field as needed
   *
   * @param array $input Contains all settings fields as array keys
   *
   * @return array[]
   */
  public function sanitize( $input ) {
    $result = array();

    // text
    $text_input_ids = array(
      $this->field_key->field_grey_percentage,
      $this->field_key->field_ribbon_position
    );
    foreach ( $text_input_ids as $text_input_id ) {
      $result[ $text_input_id ] = isset( $input[ $text_input_id ] )
        ? sanitize_text_field( $input[ $text_input_id ] )
        : '';
    }

    return $result;
  }

  public function print_section_info() {
    printf( '%s', 'Enter your settings below' );
  }

  /** ================================================================ backend: field
   */

  public function field_grey_percentage_callback() {
    $field_id    = $this->field_key->field_grey_percentage;
    $field_name  = $this->option_field_name . "[$field_id]";
    $field_value = $this->options[ $field_id ];

    printf( '<input type="text" id="%s" placeholder="e.g. 40" name="%s" value="%s" />',
      $field_id,
      $field_name,
      $field_value
    );
  }

  public function field_ribbon_position_callback() {
    $field_id   = $this->field_key->field_ribbon_position;
    $field_name = $this->option_field_name . "[$field_id]";
    $positions  = array(
      array(
        'value' => '',
        'name'  => '-'
      ),
      array(
        'value' => 'top-left',
        'name'  => 'Top left'
      ),
      array(
        'value' => 'top-right',
        'name'  => 'Top right'
      ),
      array(
        'value' => 'bottom-left',
        'name'  => 'Bottom left'
      ),
      array(
        'value' => 'bottom-right',
        'name'  => 'Bottom right'
      )
    );

    printf( '<select id="%s" name="%s">', $field_id, $field_name );
    foreach ( $positions as $position ) {
      $value       = $position['value'];
      $name        = $position['name'];
      $select_attr = selected( $this->options[ $field_id ], $value, false );

      printf( '<option value="%s" %s>%s</option>',
        $value,
        $select_attr,
        $name
      );
    }
    echo '</select>';
  }

  /** ================================================================ backend: plugin
   */

  /**
   * @param string[] $links
   * @param string $plugin_file
   *
   * @return array
   */
  public function plugin_action_links( $links = [], $plugin_file = '' ) {
    $plugin_link = array();
    if ( $plugin_file === MRN_BASE_FILE ) {
      $plugin_link[] = sprintf( '<a href="%s">%s</a>',
        admin_url( 'options-general.php?page=' . $this->menu_page ),
        'Settings'
      );
    }

    return array_merge( $links, $plugin_link );
  }

  /** ================================================================ debug
   */

  /**
   * @param null $var
   * @param bool $is_die
   *
   * @return bool
   */
  private function dd( $var = null, $is_die = true ) {
    if ( ! $this->is_debug ) {
      return false;
    } else {
      echo '<pre>';
      print_r( $var );
      echo '</pre>';

      if ( $is_die ) {
        die();
      }
    }
  }

  private function da( $var = null, $is_die = false ) {
    $this->dd( $var, $is_die );
  }

  private function dump( $is_die = false ) {
    $this->da( $this->options, $is_die );
  }

  private function reset() {
    update_option( $this->option_field_name, array() );
  }

  /** ================================================================ frontend
   */

  /**
   * @todo "wp_enqueue_style" only what we need
   */
  public function enqueue_scripts() {
    $options         = $this->options;
    $grey_percentage = $options[ $this->field_key->field_grey_percentage ];
    wp_enqueue_style( 'mrn-main-style', plugins_url( 'css/main.css', __FILE__ ) );

    if ( is_numeric( $grey_percentage ) ) {
      $custom_css   = sprintf( 'html {
        filter: progidXImageTransform.Microsoft.BasicImage(grayscale=%s);
        -webkit-filter: grayscale(%s%%);
        -moz-filter: grayscale(%s%%);
        -ms-filter: grayscale(%s%%);
        -o-filter: grayscale(%s%%);
        filter: grayscale(%s%%);
        filter: gray;
        }',
        $grey_percentage / 100,
        $grey_percentage,
        $grey_percentage,
        $grey_percentage,
        $grey_percentage,
        $grey_percentage
      );
      wp_add_inline_style( 'mrn-main-style', $custom_css );
    }
  }

  public function foot() {
    $options         = $this->options;
    $ribbon_position = $options[ $this->field_key->field_ribbon_position ];
    if ( ! empty( $ribbon_position ) ) {
      $custom_css = 'mrn-pos-' . $ribbon_position;
      printf( '<div class="mrn-ribbon %s">&nbsp;</div>',
        $custom_css
      );
    }
  }
}

$mrn_mourning = new MrnMourning();
