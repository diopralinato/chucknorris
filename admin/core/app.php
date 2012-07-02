<?php
//error_reporting(0);
define ('ADMIN', 1 );
define ('LOCAL_PATH', realpath(dirname(__FILE__).'/../..') . '/' );
require_once LOCAL_PATH. 'includes/redbean/rb.php';
require_once LOCAL_PATH. 'includes/Twig/Autoloader.php';
require_once LOCAL_PATH. 'includes/common/dbconnector.php';
require_once LOCAL_PATH. 'includes/common/twigloader.php';
require_once LOCAL_PATH. 'includes/common/functions.php';
require_once LOCAL_PATH. 'includes/common/allowed_ips.php';

$GLOBALS['bufferederrors'] = array();

class App {

  function __construct() {

    $this->coreFunctions(); // Run core functions
    
    if (isset($_POST['save'])) { 
      $this->save($_POST);
    }

    if (isset($_POST['update']) || isset($_POST['apply'])) { 
      $this->update($_POST);
    }
  }

  /**
   * @return mixed
   */
  public function coreFunctions() {
    $this->parseErrors();
    $this->log();
    return;
  }

  /**
   * @static
   * @return array
   */
  public static function globalSettings() {
    require_once realpath(dirname(__FILE__).'/..'). '/models/settings.php';
    $class = new Model_Settings;

    return App::buildEditForm($class->fields(), 'settings', 1);
  }

  /**
   * @return null
   */
  public function checkSession() {
    $this->loadSession();
    $session = isset($_SESSION['user']) ? $_SESSION['user'] : null; // Check session exists

    return $session;
  }

  /**
   * @return array
   */
  public function loadSession() {
    $s = session_id();
    if(empty($s)) session_start();
    return $_SESSION;
  }

  /**
   * @param $items
   *
   * @return array
   */
  public function loadMenu($items) {
    $dict = array();

    foreach ($items as $key => $value) {
      $dict[$key] = array();
      foreach ($value as $key2 => $value2) {
          $dict[$key][$key2] = $value2;
      }
    }

    return $dict;
  }

  /**
   * @static
   *
   * @param $template
   * @param $dict
   */
  public static function renderTwig($template, $dict) {
    global $twig;
    if (empty($GLOBALS['bufferederrors'])) {
      echo $twig->render($template, $dict);
    }
  }

  /**
   * @static
   *
   * @param      $view
   * @param bool $admin
   */
  public static function includeView($view, $admin = false) {
    global $dict, $module, $twig;

    if (!file_exists($view)) {
      App::createView($view, $module, $admin);
    } else {
      include_once($view);
    }
  }

  /**
   * @static
   *
   * @param $view
   * @param $module
   * @param $admin
   */
  public static function createView($view, $module, $admin) {
    // Strip all but letters and numbers and make lower case then upper case first letter in module name
    $module_lower = strtolower(preg_replace('/[^a-z0-9]/i','', $module));
    $module_upper = ucfirst($module_lower);

    if ($admin) {

      // Basic admin view file
      $file  = '<?php' . "\n";
      $file .= 'App::requireModel(\'models/\' . $module . \'.php\', true);' . "\n";
      $file .= '$model  = new Model_' . $module_upper . '();' . "\n\n";
      $file .= 'include_once \'common.php\';';

    } else {

      // Basic frontend view file
      $file  = '<?php' . "\n";
      $file .= 'App::requireModel(\'models/\' . $module . \'.php\', false);' . "\n";
      $file .= '$model  = new Model_' . $module_upper . '();' . "\n\n";
      $file .= '$dict[$module] = $model->' . $module_lower . '();' . "\n\n";
      $file .= 'echo $twig->render(\'' . $module_lower . '.html\', $dict);';

    }

    $fp = fopen($view, 'w');
    fwrite($fp, $file);
    fclose($fp);

    App::includeView($view, $admin);
  }

  public static function requireModel($model, $admin = false) {
    global $module;

    if (!file_exists($model)) {
      App::createModel($model, $module, $admin);
    } else {
      require_once($model);
    }
  }

  public static function createModel($view, $module, $admin) {
    // Strip all but letters and numbers and make lower case then upper case first letter in module name
    $module_lower = strtolower(preg_replace('/[^a-z0-9]/i','', $module));
    $module_upper = ucfirst($module_lower);

    if ($admin) {

      // Basic admin view file
      $file  = '<?php' ."\n\n";
      $file .= 'class Model_' . $module_upper . ' extends RedBean_SimpleModel {' . "\n\n";
      $file .= "\t" . 'function fields() {' . "\n";
      $file .= "\t\t" . '// Add fields here' . "\n";
      $file .= "\t\t" . '$fields[\'title\']       = array(\'type\'=>\'text\', \'label\'=>\'title\', \'help\'=>\'This is optional help text\');' . "\n\n";
      $file .= "\t\t" . '// Settings' . "\n";
      $file .= "\t\t" . '$fields[\'add\']        = true;' . "\n";
      $file .= "\t\t" . '$fields[\'edit\']       = true;' . "\n";
      $file .= "\t\t" . '$fields[\'delete\']     = true;' . "\n";
      $file .= "\t\t" . 'return $fields;' . "\n";
      $file .= "\t" .'}' . "\n\n";
      $file .= "\t" .'function settings() {' . "\n";
      $file .= "\t\t" . '$dict = App::getSettings($this->fields());' . "\n";
      $file .= "\t\t" . 'return $dict;' . "\n";
      $file .= "\t" .'}' . "\n\n";
      $file .= "\t" .'function view() {' . "\n";
      $file .= "\t\t" . 'global $module;' . "\n";
      $file .= "\t\t" . '$dict = App::view($module, __CLASS__); // Region optional' . "\n";
      $file .= "\t\t" . 'return $dict;' . "\n";
      $file .= "\t" .'}' . "\n\n";
      $file .= "\t" .'function count() {' . "\n";
      $file .= "\t\t" . 'global $module;' . "\n";
      $file .= "\t\t" . '$dict = App::count($module); // Region optional' . "\n";
      $file .= "\t\t" . 'return $dict;' . "\n";
      $file .= "\t" .'}' . "\n\n";
      $file .= "\t" .'function add() {' . "\n";
      $file .= "\t\t" . 'return App::buildForm($this->fields());' . "\n";
      $file .= "\t" .'}' . "\n\n";
      $file .= "\t" .'function edit($id) {' . "\n";
      $file .= "\t\t" . 'global $module;' . "\n";
      $file .= "\t\t" . 'sanitize($id);' . "\n";
      $file .= "\t\t" . 'return App::buildEditform($this->fields(), $module, $id);' . "\n";
      $file .= "\t" .'}' . "\n\n";
      $file .= 'function trash($id) {' . "\n";
      $file .= "\t\t" . 'global $module;' . "\n";
      $file .= "\t\t" . 'sanitize($id);' . "\n";
      $file .= "\t\t" . 'return App::trash($id, $module);' . "\n";
      $file .= "\t" . '}' . "\n";
      $file .= '}';

    } else {

      // Basic frontend view file
      $file  = '<?php' . "\n";
      $file .= 'class Model_' . $module_upper . ' extends RedBean_SimpleModel {' . "\n\n";
      $file .= "\t" . 'function ' . $module_lower . '() {' . "\n";
      $file .= "\t\t" . '$dict = array();' . "\n";
      $file .= "\t\t" . '// Add database calls here' . "\n";
      $file .= "\t\t" . 'return $dict;' . "\n";
      $file .= "\t" . '}' . "\n";
      $file .= '}';

    }

    $fp = fopen($view, 'w');
    fwrite($fp, $file);
    fclose($fp);

    App::includeView($view, $admin);
  }

  /**
   * @static
   *
   * @param      $module
   * @param      $class
   * @param null $region
   *
   * @return mixed
   */
  public static function view($module, $class, $region = null) {
    require_once realpath(dirname(__FILE__).'/..'). '/models/'.$module.'.php';
    $class = new $class;
    $settings = App::getSettings($class->fields());

    $orderby = $settings['orderby'];
    $order   = $settings['order'];

    $start = (isset($_GET['start'])) ? (int)$_GET['start'] : 0;
    $limit = R::getCell('SELECT pagination FROM settings LIMIT 1');

    $limit = ($limit) ? (int)$limit : 999999;

    if ($region) {
      // Region specific
      $data  = R::getAll('SELECT SQL_CALC_FOUND_ROWS * FROM ' . $module . ' WHERE region = "' . $region . '" ORDER BY ' . $orderby . ' ' . $order . ' LIMIT ' . $start . ',' .$limit);
      $count = R::getCell('SELECT FOUND_ROWS()');
    } else {
      // Site specific
      $data  = R::getAll('SELECT SQL_CALC_FOUND_ROWS * FROM ' . $module . ' WHERE 1 ORDER BY ' . $orderby . ' ' . $order . ' LIMIT ' . $start . ',' .$limit);
      $count = R::getCell('SELECT FOUND_ROWS()');
    }
    $dict = App::removeForeignkeys($data);
    $dict = App::removeHidden($dict, $module, $class);
    return $dict;
  }

  /**
   * @static
   *
   * @param      $module
   * @param null $region
   *
   * @return array
   */
  public static function count($module, $region = null) {
    $pagination = array();
    $limit = R::getCell('SELECT pagination FROM settings LIMIT 1');
    $limit = ($limit) ? (int)$limit : 999999;

    $pagination['start'] = (isset($_GET['start'])) ? (int)$_GET['start'] : 0;

    if ($region) {
      // Region specific
      $pagination['total'] = count(R::find($module,' region = ?', array( $region )));
    } else {
      // Site specific
      $pagination['total'] = count(R::find($module));
    }
      $pagination['tabs'] = ceil($pagination['total'] / $limit);
      $pagination['limit'] = $limit;
    return $pagination;
  }

  /**
   * @static
   *
   * @param $dict
   *
   * @return mixed
   */
  public static function removeForeignkeys($dict) {
    $i = 0;
    foreach($dict as $key => $value) {
      foreach($value as $key => $value) {
        if (is_array($value)) { unset($dict[$i][$key]); }
        if ($key == 'region') { unset($dict[$i][$key]); }
      }
    $i++;
    }
    return $dict;
  }

  /**
   * @static
   *
   * @param $dict
   * @param $model
   * @param $class
   *
   * @return mixed
   */
  public static function removeHidden($dict, $model, $class) {
    $fields = App::getFields($model, $class);

    $i = 0;
    foreach($dict as $key => $value) {
      foreach($value as $key2 => $value2) {
        if ($key2 != 'id' && array_key_exists($key2, $fields)) {
          if ($fields[$key2]['hide'] === true) { unset($dict[$i][$key2]); }
        }
      }
    $i++;
    }
    return $dict;
  }

  /**
   * @static
   *
   * @param $fields
   *
   * @return array
   */
  public static function buildForm($fields) {
    $form = array();

    foreach($fields as $key => $field) {

      if ($key != 'add' && $key != 'edit' && $key != 'delete' && $key != 'run') {

        if ($field['type'] == 'foreignkey') {

            if ($field['relation'] == 'own') { // One to many

              $name = $field['relation'].ucfirst($field['model']);

              $form[$name]              = array();
              $form[$name]['type']      = $field['type'];
              $form[$name]['label']     = $field['label'];
              $form[$name]['relation']  = $field['relation'];
              $form[$name]['fields']    = array();
              $form[$name]['fields']    = App::getFields($field['model'], $field['class']);

            } elseif ($field['relation'] == 'shared') { // Many to many

              $name = $field['relation'].ucfirst($field['model']);

              $form[$name]              = array();
              $form[$name]['type']      = $field['type'];
              $form[$name]['label']     = $field['label'];
              $form[$name]['relation']  = $field['relation'];
              $form[$name]['fields']    = array();
              $form[$name]['fields']    = App::getShared($field['model'], $field['selecttitle']);
              $form[$name]['help']      = (isset($field['help'])) ? $field['help'] : null;

            }
        
        } elseif ($field['type'] == 'file') { // File fields

          $form[$key] = array();
          $form[$key]['type']       = $field['type'];
          $form[$key]['label']      = $field['label'];
          $form[$key]['path']       = $field['path'];
          $form[$key]['accept']     = (isset($field['accept'])) ? $field['accept'] : 'gif, jpg, jpeg, png';
          $form[$key]['required']   = (isset($field['required']) && $field['required'] === true) ? true : false;
          $form[$key]['validate']   = (isset($field['accept'])) ? $field['accept'] : 'gif,jpg,jpeg,png';
          $form[$key]['hide']       = (isset($field['table_hide']) && $field['table_hide'] === true) ? true : false;
          $form[$key]['help']       = (isset($field['help'])) ? $field['help'] : null;

        } elseif ($field['type'] == 'select') { // File fields

          $form[$key] = array();
          $form[$key]['type']       = $field['type'];
          $form[$key]['label']      = $field['label'];
          $form[$key]['values']     = (is_array($field['values'])) ? $field['values'] : '';
          $form[$key]['required']   = (isset($field['required']) && $field['required'] === true) ? true : false;
          $form[$key]['hide']       = (isset($field['table_hide']) && $field['table_hide'] === true) ? true : false;
          $form[$key]['help']       = (isset($field['help'])) ? $field['help'] : null;

        } elseif ($field['type'] == 'radio') { // Radio fields

          $form[$key] = array();
          $form[$key]['type']       = $field['type'];
          $form[$key]['label']      = $field['label'];
          $form[$key]['values']     = (is_array($field['values'])) ? $field['values'] : '';
          $form[$key]['required']   = (isset($field['required']) && $field['required'] === true) ? true : false;
          $form[$key]['hide']       = (isset($field['table_hide']) && $field['table_hide'] === true) ? true : false;
          $form[$key]['help']       = (isset($field['help'])) ? $field['help'] : null;

        } else { // Own fields
        
          $form[$key] = array();
          $form[$key]['type']       = $field['type'];
          $form[$key]['label']      = $field['label'];
          $form[$key]['max_length'] = (isset($field['max_length'])) ? $field['max_length'] : null;
          $form[$key]['readonly']   = (isset($field['readonly']) && $field['readonly'] === true) ? true : false;
          $form[$key]['required']   = (isset($field['required']) && $field['required'] === true) ? true : false;
          $form[$key]['validate']   = (isset($field['validate'])) ? $field['validate'] : false;
          $form[$key]['equalto']    = (isset($field['equalto'])) ? $field['equalto'] : false;
          $form[$key]['hide']       = (isset($field['table_hide']) && $field['table_hide'] === true) ? true : false;
          $form[$key]['help']       = (isset($field['help'])) ? $field['help'] : null;
        
        }
      }
    }
    return $form;
  }

  /**
   * @static
   *
   * @param $model
   * @param $class
   *
   * @return array
   */
  public static function getFields($model, $class) {
    require_once realpath(dirname(__FILE__).'/..'). '/models/'.$model.'.php';
    $class = new $class;

    return App::buildForm($class->fields());
  }

  /**
   * @static
   *
   * @param $model
   * @param $select
   *
   * @return array|null
   */
  public static function getShared($model, $select) {

    $data = null;

    $tables = R::$writer->getTables();
    if (in_array($model, $tables)) {
      $columns = R::$writer->getColumns($model);
      if(array_key_exists($select, $columns)) {
        $data = R::getAll( 'SELECT id, '. $select .' AS selecttitle  FROM ' . $model );
      }
    }

    return $data;
  }

  /**
   * @static
   *
   * @param $fields
   * @param $module
   * @param $id
   *
   * @return array
   */
  public static function buildEditform($fields, $module, $id) {
    $data = R::load($module, $id);
    $data = R::exportAll($data, true);

    $form = array();
    $form['id'] = $id;
    $form['start'] = (isset($_GET['start'])) ? $_GET['start'] : 0;

    //echo '<pre>' . print_r ($fields, true ) . '</pre.'; exit;

    foreach($fields as $key => $field) {

      if ($key != 'add' && $key != 'edit' && $key != 'delete' && $key != 'run') {

        if ($field['type'] == 'foreignkey') {

            if ($field['relation'] == 'own') { // One to many

              $name = $field['relation'].ucfirst($field['model']);

              $form[$name]              = array();
              $form[$name]['type']      = $field['type'];
              $form[$name]['label']     = $field['label'];
              $form[$name]['relation']  = $field['relation'];
              $form[$name]['fields']    = array();
              $form[$name]['fields']    = App::getEditfields($module, $field['model'], $field['class'], $id);

            } elseif ($field['relation'] == 'shared') { // Many to many

              $name = $field['relation'].ucfirst($field['model']);

              $form[$name]              = array();
              $form[$name]['type']      = $field['type'];
              $form[$name]['label']     = $field['label'];
              $form[$name]['relation']  = $field['relation'];
              $form[$name]['fields']    = array();
              $form[$name]['fields']    = App::getEditshared($module, $field['model'], $field['selecttitle'], $id);
              $form[$name]['help']      = (isset($field['help'])) ? $field['help'] : null;

            }
        
        } elseif ($field['type'] == 'file') { // File fields

          $form[$key] = array();
          $form[$key]['type']       = $field['type'];
          $form[$key]['label']      = $field['label'];
          $form[$key]['path']       = (isset($field['path'])) ? $field['path'] : false;
          $form[$key]['accept']     = (isset($field['accept'])) ? $field['accept'] : 'gif, jpg, jpeg, png';
          $form[$key]['required']   = (isset($field['required']) && $field['required'] === true) ? true : false;
          $form[$key]['validate']   = (isset($field['accept'])) ? $field['accept'] : 'gif,jpg,jpeg,png';
          $form[$key]['value']      = $data[0][$key];
          $form[$key]['help']       = (isset($field['help'])) ? $field['help'] : null;

        } elseif ($field['type'] == 'select') { // File fields

          $form[$key] = array();
          $form[$key]['type']       = $field['type'];
          $form[$key]['label']      = $field['label'];
          $form[$key]['values']     = (is_array($field['values'])) ? $field['values'] : '';
          $form[$key]['required']   = (isset($field['required']) && $field['required'] === true) ? true : false;
          $form[$key]['value']      = $data[0][$key];
          $form[$key]['help']       = (isset($field['help'])) ? $field['help'] : null;

        } elseif ($field['type'] == 'radio') { // Radio fields

          $form[$key] = array();
          $form[$key]['type']       = $field['type'];
          $form[$key]['label']      = $field['label'];
          $form[$key]['values']     = (is_array($field['values'])) ? $field['values'] : '';
          $form[$key]['required']   = (isset($field['required']) && $field['required'] === true) ? true : false;
          $form[$key]['value']      = $data[0][$key];
          $form[$key]['help']       = (isset($field['help'])) ? $field['help'] : null;

        } else { // Own fields
        
          $form[$key] = array();
          $form[$key]['type']       = $field['type'];
          $form[$key]['label']      = $field['label'];
          $form[$key]['max_length'] = (isset($field['max_length'])) ? $field['max_length'] : null;
          $form[$key]['readonly']   = (isset($field['readonly']) && $field['readonly'] == true) ? true : false;
          $form[$key]['required']   = (isset($field['required']) && $field['required'] === true) ? true : false;
          $form[$key]['validate']   = (isset($field['validate'])) ? $field['validate'] : false;
          $form[$key]['equalto']    = (isset($field['equalto'])) ? $field['equalto'] : false;
          $form[$key]['value']      = $data[0][$key];
          $form[$key]['help']       = (isset($field['help'])) ? $field['help'] : null;
        
        }
      }
    }
    return $form;
  }

  /**
   * @param $fields
   *
   * @return array
   */
  public static function buildEditformownfields($fields) {
    $form = array();

    foreach($fields as $key => $field) {

      if ($key != 'add' && $key != 'edit' && $key != 'delete' && $key != 'run') {

        if ($field['type'] == 'foreignkey') {

            if ($field['relation'] == 'own') { // One to many

              $name = $field['relation'].ucfirst($field['model']);

              $form[$name]              = array();
              $form[$name]['type']      = $field['type'];
              $form[$name]['label']     = $field['label'];
              $form[$name]['relation']  = $field['relation'];
              $form[$name]['fields']    = array();
              $form[$name]['fields']    = App::getFields($field['model'], $field['class']);

            }
        
        }
      }
    }
    return $form;
  }

  /**
   * @static
   *
   * @param $parent
   * @param $model
   * @param $class
   * @param $id
   *
   * @return array
   */
  public static function getEditfields($parent, $model, $class, $id) {
    require_once realpath(dirname(__FILE__).'/..'). '/models/'.$model.'.php';
    $class = new $class;
    $fields = $class->fields();

    $parent = R::load($parent, $id);
    $own    = 'own'.ucfirst($model);

    $data = R::exportAll($parent->$own);

    $array = array();
    $i = 0;
    foreach ($data as $key => $value) {
      foreach($fields as $key => $field) {

        if ($key != 'add' && $key != 'edit' && $key != 'delete' && $key != 'run') {
          if ($field['type'] != 'foreignkey' && $field['type'] != 'file' && $field['type'] != 'select' && $field['type'] != 'radio') {

            $array[$i][$key] = array();
            $array[$i][$key]['type']       = $field['type'];
            $array[$i][$key]['label']      = $field['label'];
            $array[$i][$key]['max_length'] = (isset($field['max_length'])) ? $field['max_length'] : null;
            $array[$i][$key]['id']         = $data[$i]['id'];
            $array[$i][$key]['value']      = $data[$i][$key];
            $array[$i][$key]['required']   = (isset($field['required']) && $field['required'] === true) ? true : false;
            $array[$i][$key]['validate']   = (isset($field['validate'])) ? $field['validate'] : false;
            $array[$i][$key]['help']       = (isset($field['help'])) ? $field['help'] : null;

          } elseif ($field['type'] == 'file') {

            $array[$i][$key] = array();
            $array[$i][$key]['type']       = $field['type'];
            $array[$i][$key]['label']      = $field['label'];
            $array[$i][$key]['max_length'] = (isset($field['max_length'])) ? $field['max_length'] : null;
            $array[$i][$key]['id']         = $data[$i]['id'];
            $array[$i][$key]['value']      = $data[$i][$key];
            $array[$i][$key]['path']       = (isset($field['path'])) ? $field['path'] : false;
            $array[$i][$key]['accept']     = (isset($field['accept'])) ? $field['accept'] : 'gif, jpg, jpeg, png';
            $array[$i][$key]['required']   = (isset($field['required']) && $field['required'] === true) ? true : false;
            $array[$i][$key]['validate']   = (isset($field['accept'])) ? $field['accept'] : 'gif,jpg,jpeg,png';
            $array[$i][$key]['help']       = (isset($field['help'])) ? $field['help'] : null;

          } elseif ($field['type'] == 'select') { // File fields

            $array[$i][$key] = array();
            $array[$i][$key]['type']       = $field['type'];
            $array[$i][$key]['label']      = $field['label'];
            $array[$i][$key]['id']         = $data[$i]['id'];
            $array[$i][$key]['value']      = $data[$i][$key];
            $array[$i][$key]['values']     = (is_array($field['values'])) ? $field['values'] : '';
            $array[$i][$key]['required']   = (isset($field['required']) && $field['required'] === true) ? true : false;
            $array[$i][$key]['help']       = (isset($field['help'])) ? $field['help'] : null;

          } elseif ($field['type'] == 'radio') { // Radio fields

            $array[$i][$key] = array();
            $array[$i][$key]['type']       = $field['type'];
            $array[$i][$key]['label']      = $field['label'];
            $array[$i][$key]['id']         = $data[$i]['id'];
            $array[$i][$key]['value']      = $data[$i][$key];
            $array[$i][$key]['values']     = (is_array($field['values'])) ? $field['values'] : '';
            $array[$i][$key]['required']   = (isset($field['required']) && $field['required'] === true) ? true : false;
            $array[$i][$key]['help']       = (isset($field['help'])) ? $field['help'] : null;

          }
        }
      }
    $i++;
    }

    return $array;
  }

  /**
   * @static
   *
   * @param $parent
   * @param $model
   * @param $select
   * @param $id
   *
   * @return array|null
   */
  public static function getEditshared($parent, $model, $select, $id) {

    $data = null;

    $parent = R::load($parent, $id);
    $shared = 'shared'.ucfirst($model);

    $data = R::exportAll($parent->$shared);

    $selected = array();
    foreach ($data as $shared) {
      $selected[] = $shared['id'];
    }

    $tables = R::$writer->getTables();
    if (in_array($model, $tables)) {
      $columns = R::$writer->getColumns($model);
      if(array_key_exists($select, $columns)) {
        $data = R::getAll( 'SELECT id, '. $select .' AS selecttitle  FROM ' . $model );
      }
    }

    foreach ($data as $key => $value) {
      if (in_array($data[$key]['id'], $selected)) {
        $data[$key]['id']           = $value['id'];
        $data[$key]['selecttitle']  = $value['selecttitle'];
        $data[$key]['selected']     = true;
      } else {
        $data[$key]['id']           = $value['id'];
        $data[$key]['selecttitle']  = $value['selecttitle'];
        $data[$key]['selected']     = false;
      }
    }

    return $data;
  }

  /**
   * @static
   *
   * @param $fields
   *
   * @return array
   */
  public static function getSettings($fields) {
      $dict = array();

      $dict['add']     = false;
      $dict['edit']    = false;
      $dict['delete']  = false;

      $dict['orderby'] = false;
      $dict['order']   = false;

      $dict['run']     = false;

      if (isset($fields['add']) && $fields['add'] === true) {
        $dict['add']   = 'true';
      } elseif (is_numeric($fields['add'])) {
        $dict['add']   = $fields['add'];
      }

      $dict['edit']    = (isset($fields['edit']) && $fields['edit'] == true) ? true : false;
      $dict['delete']  = (isset($fields['delete']) && $fields['delete'] == true) ? true : false;
      $dict['orderby'] = (isset($fields['orderby'])) ?  $fields['orderby'] : 'id';
      $dict['order']   = (isset($fields['order'])) ?  $fields['order'] : 'ASC';
      $dict['run']['path'] = (isset($fields['run']['path'])) ?  $fields['run']['path'] : false;
      if ($dict['run']['path']) {
        $dict['run']['button']         = (isset($fields['run']['button'])) ?  $fields['run']['button'] : 'Run';
        $dict['run']['button_running'] = (isset($fields['run']['button_running'])) ?  $fields['run']['button_running'] : 'Running...';
      }
    return $dict;
  }

  /**
   * @param $_POST
   */
  public function save($_POST) {
    require_once realpath(dirname(__FILE__).'/../..'). '/includes/common/imageupload.php';
    $_POST = sanitize($_POST);
    $_FILES = sanitize($_FILES);
    $module = $_POST['modulename'];
    $ownfields = null;
    // echo '<pre>' . print_r($_FILES, true) . '</pre>'; 
    // echo '<pre>' . print_r($_POST, true) . '</pre>';exit;

    require_once 'models/' . $module . '.php';

    if ($_FILES) {
      $_FILES = App::organiseFiles($_FILES, $module);
      $i = 0;
      foreach ($_FILES[$module] as $key => $value) {
        if ($_FILES[$module][$key]['tmp_name']) {

          $info = explode('|', $key); // First field is the key, second is the path, third is the accepted file types

          if (count($info) == 1 && substr($info[0], 0, 3) === 'own') {
            $owninfo[$i] = $info[0];
            $ownfields[$i] = $_FILES[$module][$info[0]]['tmp_name'];
          }

          if (!$ownfields[$i]) { // Continue if not foreign key (own) file
            $path = $info[1];
            $path = rtrim($path, '/');
            $path = ltrim($path, '/');
            $ext = explode(',', $info[2]);
            foreach ($ext as $ext) {
              $ext = str_replace("'", '', $ext);
              $ext = str_replace(" ", '', $ext);
              $accept[] = $ext;
            }

            $upload = new Upload();
            $upload->SetFileName($_FILES[$module][$key]['name']);
            $upload->SetTempName($_FILES[$module][$key]['tmp_name']);
            $upload->SetUploadDirectory( realpath(dirname(__FILE__).'/../..') . '/' . $path . '/');
            $upload->SetValidExtensions($accept);
            //$upload->SetMaximumFileSize(300000); // Maximum file size in bytes, if this is not set, the value in your php.ini file will be the maximum value
            $file = $upload->UploadFile();

            $_POST[$module][$info[0]] = $path . '/' . $file;
          }
        }
      $i++;
      }
    }
      if (!is_null($ownfields)) {
        foreach ($ownfields as $key1 => $value1) {
          foreach ($value1 as $key => $value) {
            if ($_FILES[$module][$owninfo[$key1]]['tmp_name']) {

              foreach ($_FILES[$module][$owninfo[$key1]]['tmp_name'] as $beanid => $images) {

                foreach ($images as $key => $image) {

                  if ($_FILES[$module][$owninfo[$key1]]['name'][$beanid][$key]) {

                    $info = explode('|', $key); // First field is the key, second is the path, third is the accepted file type, 4th is the field name for own fields
                    $path = $info[1];
                    $path = rtrim($path, '/');
                    $path = ltrim($path, '/');
                    $ext = explode(',', $info[2]);
                    foreach ($ext as $ext) {
                      $ext = str_replace("'", '', $ext);
                      $ext = str_replace(" ", '', $ext);
                      $accept[] = $ext;
                    }

                    $upload = new Upload();
                    $upload->SetFileName($_FILES[$module][$owninfo[$key1]]['name'][$beanid][$key]);
                    $upload->SetTempName($_FILES[$module][$owninfo[$key1]]['tmp_name'][$beanid][$key]);
                    $upload->SetUploadDirectory( realpath(dirname(__FILE__).'/../..') . '/' . $path . '/');
                    $upload->SetValidExtensions($accept);
                    //$upload->SetMaximumFileSize(300000); // Maximum file size in bytes, if this is not set, the value in your php.ini file will be the maximum value
                    $file = $upload->UploadFile();

                    if (!$_POST[$module][$owninfo[$key1]][$beanid][$info[3]]) {
                      $_POST[$module][$owninfo[$key1]][$beanid][$info[3]] = $path . '/' . $file;
                    }
                  }
                }
              }
            }
          }
        }
      }

    $_POST = array_remove_empty($_POST);

    // echo '<pre>' . print_r($_POST, true) . '</pre>'; exit;

    foreach ($_POST as $key => $value) {
      if (strlen(strstr($key,'shared'))>0) {
        unset($_POST[$key]);
        $key = strtolower(str_replace('shared', '', $key));
        $shared[$key] = $value;
      }
    }
    
    try {
      $data = R::graph($_POST[$module], true);
      if ($shared) {
        foreach ($shared as $model => $items) {
          foreach ($items as $id) {
            $item = R::load($model, $id);
            R::associate( $data, $item );
          }
        }
      }
      R::store($data);
    } catch (RedBean_Exception_Security $e) { echo '<pre>' . $e . '</pre>'; }
    header('Location: /admin/' . $module ); exit;
  }

  /**
   * @param $_POST
   */
  public function update($_POST) {
//    Todo: Add in image clearing - but remove that info from the POST?
//    [removeimages][table][id][field] = remove
    require_once realpath(dirname(__FILE__).'/../..'). '/includes/common/imageupload.php';
    $_POST = sanitize($_POST);
    $_FILES = sanitize($_FILES);
    $module = $_POST['modulename'];
    $start = (isset($_POST['start'])) ? $_POST['start'] : 0;
    $ownfields = null;

    require_once 'models/' . $module . '.php';

    if (isset($_POST['removeimages'])) {
      App::removeImages($_POST['removeimages']);
      unset($_POST['removeimages']);
    }

    if ($_FILES) {
      $_FILES = App::organiseFiles($_FILES, $module);

      $i = 0;
      foreach ($_FILES[$module] as $key => $value) {
        if ($_FILES[$module][$key]['tmp_name']) {

          $info = explode('|', $key); // First field is the key, second is the path, third is the accepted file types

          if (count($info) == 1 && substr($info[0], 0, 3) === 'own') {
            $owninfo[$i] = $info[0];
            $ownfields[$i] = $_FILES[$module][$info[0]]['tmp_name'];
          } else { // Continue if not foreign key (own) file
            $path = $info[1];
            $path = rtrim($path, '/');
            $path = ltrim($path, '/');
            $ext = explode(',', $info[2]);
            foreach ($ext as $ext) {
              $ext = str_replace("'", '', $ext);
              $ext = str_replace(" ", '', $ext);
              $accept[] = $ext;
            }

            $upload = new Upload();
            $upload->SetFileName($_FILES[$module][$key]['name']);
            $upload->SetTempName($_FILES[$module][$key]['tmp_name']);
            $upload->SetUploadDirectory( realpath(dirname(__FILE__).'/../..') . '/' . $path . '/');
            $upload->SetValidExtensions($accept);
            //$upload->SetMaximumFileSize(300000); // Maximum file size in bytes, if this is not set, the value in your php.ini file will be the maximum value
            $file = $upload->UploadFile();

            $_POST[$module][$info[0]] = $path . '/' . $file;
          }
        }
      $i++;
      }
    }

    //echo '<pre>' . print_r($_FILES, true) . '</pre>';
      if (!is_null($ownfields)) {
        foreach ($ownfields as $key1 => $value1) {
          foreach ($value1 as $key => $value) {
            if ($_FILES[$module][$owninfo[$key1]]['tmp_name']) {

              foreach ($_FILES[$module][$owninfo[$key1]]['tmp_name'] as $beanid => $images) {

                foreach ($images as $key => $image) {

                  if ($image) {

                    $info = explode('|', $key); // First field is the key, second is the path, third is the accepted file type, 4th is the field name for own fields
                    $path = $info[1];
                    $path = rtrim($path, '/');
                    $path = ltrim($path, '/');
                    $ext = explode(',', $info[2]);
                    foreach ($ext as $ext) {
                      $ext = str_replace("'", '', $ext);
                      $ext = str_replace(" ", '', $ext);
                      $accept[] = $ext;
                    }

                    $upload = new Upload();
                    $upload->SetFileName($_FILES[$module][$owninfo[$key1]]['name'][$beanid][$key]);
                    $upload->SetTempName($_FILES[$module][$owninfo[$key1]]['tmp_name'][$beanid][$key]);
                    $upload->SetUploadDirectory( realpath(dirname(__FILE__).'/../..') . '/' . $path . '/');
                    $upload->SetValidExtensions($accept);
                    //$upload->SetMaximumFileSize(300000); // Maximum file size in bytes, if this is not set, the value in your php.ini file will be the maximum value
                    $file = $upload->UploadFile();

                    if (!$_POST[$module][$owninfo[$key1]][$beanid][$info[3]]) { // Make sure empty loops don't overwrite previously set images!
                      $_POST[$module][$owninfo[$key1]][$beanid][$info[3]] = $path . '/' . $file;
                    }
                  }
                }
              }
            }
          }
        }
      }

//    echo '<pre>' . print_r($_POST, true) . '</pre>'; exit;

    $_POST = array_remove_empty($_POST);

//    echo '<pre>' . print_r($_POST, true) . '</pre>';exit;

    $shared = null;

    foreach ($_POST as $key => $value) {
      if (strlen(strstr($key,'shared'))>0) {
          unset($_POST[$key]);
          $key = strtolower(str_replace('shared', '', $key));
          $shared[$key] = $value;
          $table = strtolower(str_replace('shared', '', $key));
      }
    }

    //echo '<pre>' . print_r($_POST, true) . '</pre>';exit;
    
    try {
      $data = R::graph($_POST[$module], true);
      $id   = R::store($data);
      if ($shared) {
        $data = R::load($module, $id);
        R::clearRelations( $data, $table );
        foreach ($shared as $model => $items) {
          if ($items) {
            foreach ($items as $id) {
              $item = R::load($model, $id);
              R::associate( $data, $item );
            }
          }
        }
      }

    R::store($data);
    } catch (RedBean_Exception_Security $e) { echo '<pre>' . $e . '</pre>'; }
    if (!isset($_POST['apply'])) {
      if ($start > 0) {
        header('Location: /admin/' . $module . '/?start=' . $start ); exit;
      } else {
        header('Location: /admin/' . $module ); exit;
      }
    }
  }

  /**
   * @static
   *
   * @param $array
   */
  private static function removeImages($array) {
    foreach ($array AS $type => $bean) {
      foreach ($bean AS $id => $fields) {
        /** @var $item Redbean object for the record containing the image to be removed */
        $item = R::load($type, $id);
        foreach ($fields AS $field => $value) {
          if (!is_null($item->$field) && !is_null($value)) {
            /** @var $value File name to be unlinked and unset from the database */
            if (file_exists(LOCAL_PATH . $item->$field)) {
              unlink(LOCAL_PATH . $item->$field);
            }
            $item->$field = null;
          }
        }
        R::store($item);
      }
    }
  }

  /**
   * @static
   *
   * @param $id
   * @param $module
   *
   * @return array
   */
  public static function trash($id, $module) {
    $data = R::load($module, $id);
    if (!$data->id) {
      $dict['title']    = 'Error!';
      $dict['message']  = 'Record not found...';
    } else {
      // Todo: add foreach to iterate through fields that could be files
      // Check if file exists
      // Unlink($file) if file_exists
      R::trash( $data );
      $dict['title']    = 'Success!';
      $dict['message']  = 'Record successfully deleted';
    }
    return $dict;
  }

  /**
   * @var array
   */
  static $IMGVARS = array('name' => 1, 'type' => 1, 'tmp_name' => 1, 'error' => 1, 'size' => 1);

  /**
   * @param $files
   * @param $module
   *
   * @return array
   */
    private function organiseFiles($files, $module) {
    foreach ($files[$module] as $key => $part) {
      $key = (string) $key;
      if (isset(App::$IMGVARS[$key]) && is_array($part)) { // Only deal with valid keys and multiple files
        foreach ($part as $position => $value) {
          $files[$position][$key] = $value;
        }
        unset($files[$module][$key]);
      }
    }
    unset($files[$module]);
    $array = array();
    $array[$module] = $files;
    return $array;
  }

  /**
   * @static
   * @return string
   */
  public static function backupDatabase() {

    if (!$this->checkSession()) { return; }

    // Get all of the tables
    $date   = date("Y-m-d-H-i-s");
    $date2  = date("Y-m-d H:i:s");
    $tables = R::$writer->getTables();

    $return = "# Export via Chuck Norris at {$date2}\n\n";

    $return .= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;";
    $return .= "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;";
    $return .= "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;";
    $return .= "/*!40101 SET NAMES utf8 */;";
    $return .= "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;";
    $return .= "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;";
    $return .= "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n\n";

    // Cycle through
    foreach($tables as $table) {
      $result = R::getAll('SELECT * FROM '.$table);
      $count = count($result);

      $return .= '# Dump of table ' .$table. "\n";
      $return .= "# ------------------------------------------------------------\n\n";

      $return .= 'DROP TABLE IF EXISTS `'.$table.'`;';
      $row2 = R::getRow('SHOW CREATE TABLE '.$table);
      $return .= "\n\n".$row2['Create Table'].";";
      $return .= "\n\n".'LOCK TABLES `'.$table.'` WRITE;'."\n";
      $return .= '/*!40000 ALTER TABLE `'.$table.'` DISABLE KEYS */;' . "\n\n";

      foreach ($result AS $row => $val) {
        $i = 0;
        $num_fields = count($val);
        $return.= 'INSERT INTO `'.$table.'` VALUES(';
        foreach ($val AS $key2 => $val2) {
          $val2 = addslashes($val2);
          $val2 = preg_replace("/\n/","\\n",$val2);
          if (isset($val2)) {
            $return.= '"'.$val2.'"';
          } else {
            $return.= '""';
          }
          if ($i<($num_fields-1)) {
            $return.= ', ';
          }
        $i++;
        }
        $return .= ");\n";
      }
      if ($count > 0) {
        $return .= "\n";
      }

      $return .= '/*!40000 ALTER TABLE `'.$table.'` ENABLE KEYS */;' . "\n";
      $return .= "UNLOCK TABLES;\n\n";

     }
    $return .= "/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;";
    $return .= "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;";
    $return .= "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;";
    $return .= "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;";
    $return .= "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;";
    $return .= "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;";
    $return .= "\n\n\n";

    if (!is_null($return)) {
      // Save file
      $filename = 'admin/backups/db-backup-'.$date.'.sql';
      $handle = fopen(LOCAL_PATH . $filename, 'w+');
      fwrite($handle, $return);
      fclose($handle);
      $backup = R::dispense('backup');

      $backup->file = $filename;
      $backup->date = $date2;

      R::store($backup);

      return '<blockquote><p>Successful backup run at ' . $date2 . '</p>
                <small>' . $filename . '</small>
              </blockquote>';
    }
  }

  /**
   * @return bool
   */
  public function log() {
    $log_file = LOCAL_PATH . 'admin/timeline/log.txt';
    if (!is_writable($log_file)) {
      chmod($log_file, 0755);
    }
    R::log($log_file);
    return true;
  }

  /**
   * Sets a custom error handler to override PHP's default
   */
  function parseErrors() {
    set_error_handler(array(&$this, "process_error_backtrace"));
  }

  /**
   * @param $errno
   * @param $errstr
   * @param $errfile
   * @param $errline
   * @param $errcontext
   *
   * @return bool
   */
  function process_error_backtrace($errno, $errstr, $errfile, $errline, $errcontext) {
    global $twig;
    $errorTypes = Array(
      E_ERROR => 'Fatal Error',
      E_WARNING => 'Warning',
      E_PARSE => 'Parse Error',
      E_NOTICE => 'Notice',
      E_CORE_ERROR => 'Fatal Core Error',
      E_CORE_WARNING => 'Core Warning',
      E_COMPILE_ERROR => 'Compilation Error',
      E_COMPILE_WARNING => 'Compilation Warning',
      E_USER_ERROR => 'Triggered Error',
      E_USER_WARNING => 'Triggered Warning',
      E_USER_NOTICE => 'Triggered Notice',
      E_STRICT => 'Deprecation Notice',
      E_RECOVERABLE_ERROR => 'Catchable Fatal Error'
    );

    $trace = array_reverse(debug_backtrace());
    array_pop($trace);

    $i = 0;
    foreach ($trace AS $traced){
      $file = $traced['file'];
      $lines = file($file); //file in to an array
      $trace[$i]['output'] = $lines[$traced['line']-1];
      $i++;
    }

    $ret = array(
      'number'  => $errno,
      'message' => $errstr,
      'file'    => $errfile,
      'line'    => $errline,
      'context' => $errcontext,
      'type'    => $errorTypes[$errno],
      'trace'   => $trace
    );

    $GLOBALS['bufferederrors'][] = $ret;
    if (error_get_last()) {
      $dict['backtrace'] = $GLOBALS['bufferederrors'];
      $dict['phpversion'] = phpversion();
      echo $twig->render('error-trace.html', $dict);
      //echo '<pre>' . print_r($dict, true) . '</pre>';
    } else {
      return false;
    }
  }

}