<?php

if (!defined('_PS_VERSION_')) { exit(); }

class WirecardBase extends PaymentModule
{
    private $config = array();
    
    protected $_logging_level = 1; //Which kind of messages to log? 0 = none, 1 = errors, 2 = errors, info
    protected $_logging_backtrace = true; //Include script/line number in logs?
    
    public $cookie;
    public $link;
    public $smarty;
    
    public $root_path;
    public $root_uri;
    
    public function __construct() {
        
        parent::__construct();

        $this->author = 'Induxive';
        
        //Load config
        $this->config_init();
        
        //Define class globals
        global $cookie;
        global $smarty;
        $this->cookie = $cookie;
        $this->link = new Link();
        $this->smarty = $smarty;
        
        //Define paths
        $this->root_path = dirname(__FILE__).'/../';
        $this->presta_root = Tools::getShopDomainSsl(true, true).__PS_BASE_URI__;
        $this->root_uri = $this->presta_root.'modules/'.$this->name.'/';
    }
    
    public function install()
    {
        return parent::install();
    }

    public function uninstall()
    {
        return parent::uninstall();
    }
    
    protected function install_hooks($hooks=array()) {
        $results = array();
        foreach($hooks as $hook) {
            $results[] = $this->registerHook($hook);
        }
        if(in_array(false, $results)) {
            return false;
        }
        return true;
    }
    
    private function config_init()
    {
        $config = json_decode(Configuration::get('config_'.$this->name), true);
        
        if(is_array($config)) {
            $this->config = $config;
        }
    }
    
    public function config($key=null, $value=null)
    {
        //GET
        if($value === null) {
            if($key === null) {
                return $this->config;
            }
            if(isset($this->config[$key])) {
                return $this->config[$key];
            }
            return null;
        }
        
        //SET
        $value = array(
            $key => $value
        );
        $this->config = array_merge($this->config, $value);
        Configuration::updateValue('config_'.$this->name, json_encode($this->config));
    }
    
    public function display_view($view_file, $data, $return=false)
    {
        //Global vars
        $data['root_uri'] = $this->root_uri;

        //Create path to file
        $ext = explode('.', $view_file);
        if (end($ext) != 'php') {
            $view_file = $view_file.'.php';
        }
        $view_file = $this->root_path.'views/'.$view_file;

        //Include vars
        extract($data);

        //Buffer output
        ob_start();

        //Include view
        include($view_file);

        //Return or output?
        if ($return == true) {
            $buffer = ob_get_contents();
            @ob_end_clean();
            return $buffer;
        }
        ob_end_flush();
        @ob_end_clean();
    }
    
    public function backend_uri_add($queries=array())
    {
        //Check delete is not in the query
        if(isset($queries['delete'])) {
            exit('Using the GET var delete will delete the whole module!');
        }
        parse_str($_SERVER['QUERY_STRING'], $query_string);
        foreach($queries as $_key => $_value) {
            $query_string[$_key] = $_value;
        }
        return strtok($_SERVER['REQUEST_URI'],'?').'?'.http_build_query($query_string);
    }
    
    public function backend_uri_remove($queries=array())
    {
        parse_str($_SERVER['QUERY_STRING'], $query_string);
        foreach($queries as $_value) {
            if(isset($query_string[$_value])) {
                unset($query_string[$_value]);
            }
        }
        return strtok($_SERVER['REQUEST_URI'],'?').'?'.http_build_query($query_string);
    }
    
    public function redirect($uri)
    {
        header('Location: '.$uri);
        exit();
    }
    
    public function curl($url, $post)
    {
        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$post);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); //Needs to be included if no *.crt is available to verify SSL certificates
        $result = curl_exec ($ch); 
        curl_close ($ch);
        return $result;
    }
    
    protected function get_all_order_status()
    {
        return OrderState::getOrderStates($this->cookie->id_lang);
    }
    
    protected function create_order_status($statuses)
    {
        foreach($statuses as $status) {
            
            //Set defaults
            if (!isset($status['identify']) || !isset($status['name'])) {
                return false;
            }
            if(!isset($status['invoice'])) {
                $status['invoice'] = false;
            }
            if(!isset($status['send_email'])) {
                $status['send_email'] = false;
            }
            
            //Check if the order status is already set
            if(!is_numeric( $this->config('order_status_'.$status['identify']) )) {
                
                //Create
                $orderState = new OrderState();
                $orderState->name[$this->cookie->id_lang] = $status['name'];
                $orderState->invoice = $status['invoice'];
                $orderState->send_email = $status['send_email'];
                $orderState->logable = false;
                $orderState->color = $status['color'];
                $orderState->save();
                
                //Save config
                $this->config('order_status_'.$status['identify'], $orderState->id);
                
            }
            
        }
        
        return true;
    }
    
    public function log($string, $level='error')
    {
        $level = strtolower($level);

        //Check whether to log based on level
        if($this->_logging_level <= 0) {
            return false;
        }
        if($this->_logging_level == 1 && $level != 'error') {
            return false;
        }

        $backtrace = '';
        if($this->_logging_backtrace) { //Only if logging backtracing is enabled - which gives line numbers etc.
            $bt = debug_backtrace();
            $caller = array_shift($bt);
            $backtrace = '('.$caller['file'].' '.$caller['line'].'])';
        }

        $date = time();

        //Log file name
        $filename = $this->root_path.'logs/'.date('Y', $date).'/'.date('m', $date).'/'.date('d', $date).'.txt';

        //Create folder structure if required
        if(!is_dir($this->root_path.'logs')) {
            mkdir($this->root_path.'logs');
        }
        if(!is_dir($this->root_path.'logs/'.date('Y', $date))) {
            mkdir($this->root_path.'logs/'.date('Y', $date));
        }
        if(!is_dir($this->root_path.'logs/'.date('Y', $date).'/'.date('m', $date))) {
            mkdir($this->root_path.'logs/'.date('Y', $date).'/'.date('m', $date));
        }

        //Log contents to file
        file_put_contents($filename, $level.'   '.date('Y-m-d H:i:s', $date).' --> '.$string.' '.$backtrace.PHP_EOL.PHP_EOL, FILE_APPEND | LOCK_EX);

        return true;
    }
}