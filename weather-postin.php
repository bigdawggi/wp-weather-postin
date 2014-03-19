<?php
/*
Plugin Name: Weather Postin'
Plugin URI: 
Description: Saves the current temperature when you post, then displays it after each post.
Version: 1.0.01
Author: Matthew Richmond
Author URI: http://matthewgrichmond.com
*/

// Do this when publishing the post
add_action('publish_post','post_weather_save_weather');

// Do this when user activates the plugin (Install Script)
register_activation_hook(__FILE__, 'post_weather_install');

// Do this to display the options for Weather Postin'
add_action('admin_menu', 'post_weather_config_page');

// Do this to make weather content appear at the end of the post
add_filter('the_content', 'post_weather_append_weather_info');

// Define Class (Heads up, approx ~100 Lines!)
class post_weather_Weather {
  var $feed_contents;
  var $temperature;
  var $humidity;
  var $heat_index;
  var $wind_chill;
  var $pressure;
  var $location;  
  
  // Section of functions to retrieve and parse weather data to get current weather details
    function post_weather_get_feed($zip_code){
      if ($zip_code == "local"){
        $this->feed_contents = get_option('post_weather_latest_feed');
      } else {
        $url = 'http://www.rssweather.com/zipcode/' . $zip_code . '/rss.php';
        $this->feed_contents = file_get_contents($url);     
      }
      return $this->post_weather_clean_feed($this->feed_contents);
    }
      
    // Clean out the feed's results of HTML/XML tags, to just get text
    function post_weather_clean_feed($feed_contents){  
      return strip_tags($feed_contents);
    }
    
    // Strip out numbers from preliminarily returned string
    function post_weather_get_digits($string){
      $pos = 1;
      while(is_numeric(substr($string,0,$pos))) {
        $num_val = substr($string,0,$pos);
      	$pos++;  	
      }   
      return $num_val;
    }
  
  
  // Temperature Function
  function post_weather_get_temperature(){
    // Find the unique string that will help to reference the current temperature, and grab the temp plus a char or two, to be safe
    $temperature_string = substr($this->feed_contents,strpos($this->feed_contents,"Weather :: ")+11,3);
    // Now that we have the digits plus a little extra, we need to strip it down to just the digits...
    return $this->post_weather_get_digits($temperature_string);
  }
  
  function post_weather_get_humidity(){
    // Find the unique string that will help to reference the humidity and grab it plus a char or two, to be safe
    $humidity_string = substr($this->feed_contents,strpos($this->feed_contents,"Humidity:")+9,3);
    // Now that we have the digits plus a little extra, we need to strip it down to just the digits...
    return $this->post_weather_get_digits($humidity_string);
  }
  
  function post_weather_get_heat_index(){
    // Find the unique string that will help to reference the heat index, and grab it plus a char or two, to be safe
    $heat_index_string = substr($this->feed_contents,strpos($this->feed_contents,"Heat Index:")+11,3);
    // Now that we have the digits plus a little extra, we need to strip it down to just the digits...
    return $this->post_weather_get_digits($heat_index_string);
  }
  
  function post_weather_get_wind_chill(){
    // Find the unique string that will help to reference the wind chill, and grab it plus a char or two, to be safe
    $wind_chill_string = substr($this->feed_contents,strpos($this->feed_contents,"Wind Chill:")+11,3);
    // Now that we have the digits plus a little extra, we need to strip it down to just the digits...
    return $this->post_weather_get_digits($wind_chill_string);
  }
  
  function post_weather_get_pressure(){
    // Find the unique string that will help to reference the pressure (barometer), and grab it plus a char or two, to be safe
    $pressure_string = substr($this->feed_contents,strpos($this->feed_contents,"Barometer: ")+11,6);
    // Now that we have the digits plus a little extra, we need to strip it down to just the digits...
    return $this->post_weather_get_digits($pressure_string);
  }
  
  function post_weather_get_location(){
    // Find the unique string that will help to reference the location the zip code refers to
    return substr($this->feed_contents,0,strpos($this->feed_contents,"Weather"));
  }
  
  function post_weather_save_feed($feed_contents){
    update_option('post_weather_latest_feed',$feed_contents);
  }
  
  // Constructor Function  
  function post_weather_Weather($zip_code){
    // When object is initialized, get the feed and save the weather's values
    $this->feed_contents = $this->post_weather_get_feed($zip_code);
    if (is_numeric($zip_code)){
      $this->post_weather_save_feed($this->feed_contents);
    } 
    $this->location = $this->post_weather_get_location();
    $this->temperature = $this->post_weather_get_temperature();
    $this->humidity = $this->post_weather_get_humidity();
    $this->heat_index = $this->post_weather_get_heat_index();
    $this->wind_chill = $this->post_weather_get_wind_chill();
    $this->pressure = $this->post_weather_get_pressure();
  }

}
///////////////////////////////////////////////////////////////
// All Done Creating post_weather_Weather Class!  -- Finally //
///////////////////////////////////////////////////////////////


// Goes out to get the weather and save it to the post_weather table.
// If less than an hour since last weather retrieval, it uses cached weather. 
function post_weather_save_weather($post_ID){
  global $wpdb;
  
  if (get_option("post_weather_last_checked")+3600 <= time() || get_option("post_weather_last_checked") == "initial" ){
    $myWeather = new post_weather_Weather(get_option("post_weather_zip_code"));  
    update_option('post_weather_last_checked',time());
  } else {
    $dummy_zip = "local";
    $myWeather = new post_weather_Weather($dummy_zip);
  }

  $sql = "INSERT INTO " . $wpdb->prefix . "post_weather VALUES (
    $post_ID,
    '" . $myWeather->location . "',
    $myWeather->temperature,
    $myWeather->humidity,
    $myWeather->heat_index,
    $myWeather->wind_chill,
    $myWeather->pressure
    ) ON DUPLICATE KEY UPDATE 
    location='" . $myWeather->location . "',
    temperature=$myWeather->temperature,
    humidity=$myWeather->humidity,
    heat_index=$myWeather->heat_index,
    wind_chill=$myWeather->wind_chill,
    pressure=$myWeather->pressure;";
  $post_weather_results = $wpdb->query($sql);
}

// Installation Function
// Create table, set options
function post_weather_install(){
  global $wpdb;
  
  $table_name = $wpdb->prefix . "post_weather";

  if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
    $sql = "CREATE TABLE " . $table_name . " (
      post_ID BIGINT(20) UNSIGNED NOT NULL,
      location VARCHAR(30) NOT NULL,
      temperature TINYINT NOT NULL,
      humidity TINYINT NOT NULL,
      heat_index TINYINT NOT NULL,
      wind_chill TINYINT NOT NULL,
      pressure DOUBLE NOT NULL,
      PRIMARY KEY  id (post_ID)
      );";
      
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    $post_weather_db_version="1.0";
    add_option('post_weather_db_version',$post_weather_db_version);
  }
  
  $post_weather_zip_code="43515";
  add_option('post_weather_zip_code',$post_weather_zip_code);
  add_option('post_weather_last_checked','initial');
  add_option('post_weather_text_color','#999999');
  add_option('post_weather_latest_feed',''); 
}

// Set options menu item
function post_weather_config_page(){
  add_submenu_page('plugins.php',__("Weather Postin' Configuration"),__("Weather Postin' Configuration"),'manage_options',__FILE__,'post_weather_conf');
}

// Display the options/configurations for Weather Postin'
function post_weather_conf(){
?>
  <form method="post" action="options.php">
    <p class="submit">
    <input type="submit" name="Submit" value="<?php _e('Save Changes') ?>" />
    </p>
    <?php wp_nonce_field('update-options'); ?>
      <table style="margin: 25px;">
        <tr>
          <td>Zip Code:</td><td><input type="text" name="post_weather_zip_code" value="<?php echo get_option('post_weather_zip_code'); ?>" /></td>
        </tr>
        <tr>
          <td>Text Color:</td><td><input type="text" name="post_weather_text_color" value="<?php if(get_option('post_weather_text_color')){echo get_option('post_weather_text_color');}else{echo '#999999';} ?>" /> (Hex or English Color)</td>
        </tr>
      </table>
    <input type="hidden" name="post_weather_last_checked" value="0" />
    <input type="hidden" name="action" value="update" />   
    <input type="hidden" name="page_options" value="post_weather_zip_code,post_weather_last_checked,post_weather_text_color" />
    <p class="submit">
    <input type="submit" name="Submit" value="<?php _e('Save Changes') ?>" />
    </p>
  </form>
<?php
}

// Append weather information to end of post. -- No Changes to Post's original text.
function post_weather_append_weather_info($content = ''){
  global $post;
  if ($content .= post_weather_get_post_weather($post->ID)){
    return $content;
  }
}

// Retrieve weather based on the current post's id.
function post_weather_get_post_weather($postID){
  global $wpdb;
  $sql = 'Select temperature,humidity,heat_index,wind_chill,pressure from ' . $wpdb->prefix . 'post_weather where post_ID=' . $postID .';';
  if($results = $wpdb->get_row($sql)){
    // build results string
    $post_weather_results_string = '<div style="color:' . get_option('post_weather_text_color') . ';margin-bottom:5px;font-size:10px;">';
    $post_weather_results_string .= '<p style="margin-bottom: 2px;">-- Weather When Posted --';
    $post_weather_results_string .= '<ul style="display:inline;">';
    foreach ($results as $key=>$value){
      if ($key == 'temperature' || $key == 'heat_index' || $key == 'wind_chill'){
        $post_weather_sign = '&deg;F';
      } elseif ($key == 'humidity'){
        $post_weather_sign = '&#37;';
      } else {
        $post_weather_sign = ' in.';
      }
      $post_weather_results_string .= '<li style="padding:0px 3px;display:inline;">' . ucwords(str_replace('_',' ',$key)) . ': ' . $value . "$post_weather_sign;</li>";
    }
    $post_weather_results_string .= '</ul>';
    $post_weather_results_string .= '</p></div>';
    return $post_weather_results_string;
  } else { 
    return false;
  }
   
}
?>
