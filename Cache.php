<?php
/**
 * @package    Piwik.Counter
 * @copyright  Copyright (C) 2010 Libra.ms. All rights reserved.
 * @license    GNU General Public License version 3 or later
 * @url        http://xn--80aeqbhthr9b.com/en/others/piwik/10-piwik-graphical-counter.html
 * @url        http://киноархив.com/ru/разное/piwik/9-piwik-графический-счетчик.html
 */

namespace Piwik\Plugins\Counter;

/**
 * Plugin cache class.
 */
class Cache
{
    protected $options = array(
        'cacheDir'         => '',
        'cacheId'          => '',
        'fileLocking'      => true,
        'cache_file_umask' => 0600
    );

    public function __construct($options)
    {
        $this->options['cacheDir']    = array_key_exists('cacheDir', $options) ? $options['cacheDir'] : '';
        $this->options['cacheId']     = array_key_exists('cacheId', $options) ? $options['cacheId'] : '';
        $this->options['fileLocking'] = array_key_exists('fileLocking', $options) ? $options['fileLocking'] : $this->options['fileLocking'];
    }

    /**
     * Validate cache.
     *
     * @return  boolean
     */
    public function validate()
    {
        $metaFile = file_get_contents($this->options['cacheDir'] . $this->options['cacheId'] . '.meta');

        if ($metaFile)
        {
            $metaInfo = json_decode($metaFile);

            if (time() <= $metaInfo->lifetime)
            {
                return true;
            }
        }

        return false;
    }

	/**
     * Start the cache.
     *
     * @param   integer  $lifetime   Cache lifetime.
     *
     * @return  boolean
     */
    public function start($lifetime)
    {
        $metaFile = $this->options['cacheDir'] . $this->options['cacheId'] . '.meta';
        $metaInfo = json_encode(
            (object) array(
                'lifetime' => (time() + (int) $lifetime)
            )
        );

        file_put_contents($metaFile, $metaInfo);

        ob_start();
        ob_implicit_flush(false);

        return false;
    }

	/**
     * Stop the cache.
     *
     * @return  void
     */
    public function end()
    {
        $data = ob_get_clean();
        $this->save($data);

        echo $data;
    }

	/**
     * Load the cache.
     *
     * @return  boolean
     */
    public function load()
    {
        $result = false;
        if (!is_file($this->options['cacheDir'] . $this->options['cacheId'])) {
            return false;
        }
        $f = @fopen($this->options['cacheDir'] . $this->options['cacheId'], 'rb');

        if ($f)
        {
            if ($this->options['fileLocking']) @flock($f, LOCK_SH);
            $result = stream_get_contents($f);
            if ($this->options['fileLocking']) @flock($f, LOCK_UN);
            @fclose($f);
        }

        return $result;
    }

    /**
     * Save some string data into a cache record.
     *
     * @param   mixed  $data  Cache data.
     *
     * @return  boolean
     */
    public function save($data)
    {
        clearstatcache();
        $result = false;
        $f = @fopen($this->options['cacheDir'] . $this->options['cacheId'], 'ab+');

        if ($f)
        {
            if ($this->options['fileLocking']) @flock($f, LOCK_EX);
            fseek($f, 0);
            ftruncate($f, 0);
            $tmp = @fwrite($f, $data);

            if (!($tmp === FALSE))
            {
                $result = true;
            }

            @fclose($f);
        }

        @chmod($this->options['cacheDir'] . $this->options['cacheId'], $this->options['cache_file_umask']);

        return $result;
    }

    /**
     * Remove cache item(s).
     *
     * @param   string  $id  Cache ID.
     *
     * @return  boolean
     */
    public function remove($id)
    {
        clearstatcache();

        $cacheId = !empty($this->options['cacheId']) ? $this->options['cacheId'] : $id;

	    if (!is_file($this->options['cacheDir'] . $cacheId)) {
		    return false;
	    }

	    if (!@unlink($this->options['cacheDir'] . $cacheId)) {
		    return false;
	    }

	    // Remove meta file.
	    @unlink($this->options['cacheDir'] . $cacheId . '.meta');

        return true;
    }
}
