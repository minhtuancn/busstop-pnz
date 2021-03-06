<?php defined('SYSPATH') or die('No direct script access.');
/**
 *
 */
class Model_DidYouMean
{
    const MIN_WORD_LENGTH = 3;

    public $max_distance = 4;
    protected $lib;
    protected $name;
    protected static $instances;

    /**
     * @param string $name
     * @return Model_DidYouMean
     */
    public static function instance($name='default')
    {
        if(!isset(self::$instances[$name]))
        {
            $instance = new self();
            $instance->name = $name;
            $instance->lib = Cache::instance()->get('didyoumean-'.$name,array());
            self::$instances[$name] = $instance;
        }
        return self::$instances[$name];
    }

    public function save($lifetime){
        $this->lib = array_map(function($word){return trim(UTF8::strtolower($word));},$this->lib);
        $this->lib = array_unique($this->lib);
        Cache::instance()->set('didyoumean-'.$this->name,$this->lib,$lifetime);
    }
    public function clear(){
        $this->lib = array();
        return $this;
    }

    protected function __construct(){}

    public function learn($name)
    {
        $words = Text::split_words($name);
        $words = array_filter($words,function($word){
            return UTF8::strlen($word) >= Model_DidYouMean::MIN_WORD_LENGTH;
        });
        $this->lib = array_merge($words,$this->lib);
    }

    protected function find_replacement($word)
    {
        $closest_match = null;
        foreach($this->lib as $lib_word)
        {
            $distance = levenshtein($lib_word,UTF8::strtolower($word)); //TODO: what's with wrong keyboard layout ?
            if($closest_match === null || $closest_match['distance'] > $distance){
                $closest_match = array('distance' => $distance, 'word' => $lib_word);
            }
        }

        if($closest_match['distance'] <= $this->max_distance){
            return $closest_match['word'];
        }

        return $word;
    }

    /**
     * @param $query
     * @return string
     */
    public function fix($query)
    {
        if(count($this->lib) == 0){return null;}

        $fixed_query = $query;
        foreach(Text::split_words($query) as $word)
        {
            $replacement = $this->find_replacement($word);
            $fixed_query = str_replace($word,$replacement,$fixed_query);
        }
        return $fixed_query == $query ? null : $fixed_query;
    }
}