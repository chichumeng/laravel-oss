<?php
/**
 * Created by PhpStorm.
 * User: ccm
 * Date: 2019/11/13
 * Time: 14:42
 */
namespace Chichumeng;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use OSS\OssClient;
use OSS\Core\OssException;

class AliyunAdapter extends AbstractAdapter
{

    protected $client;
    protected $bucket;
    protected $ssl = true;
    protected $isCname;
    protected $cdnDomain;
    protected $endPoint;

    /**
     * @var array
     */
    protected static $resultMap = [
        'Body'           => 'raw_contents',
        'Content-Length' => 'size',
        'ContentType'    => 'mimetype',
        'Size'           => 'size',
        'StorageClass'   => 'storage_class',
    ];
    /**
     * @var array
     */
    protected static $metaOptions = [
        'CacheControl',
        'Expires',
        'ServerSideEncryption',
        'Metadata',
        'ACL',
        'ContentType',
        'ContentDisposition',
        'ContentLanguage',
        'ContentEncoding',
    ];
    /**
     * @var array
     */
    protected $options = [];

    public function __construct($app,Config $config)
    {
        $this->client = new OssClient($config->get('access_key_id'), $config->get('access_key_secret'), $config->get('endpoint'));
        $this->bucket = $config->get('bucket');
        $this->ssl = $config->get('ssl');
        $this->isCname = $config->get('is_cname');
        $this->cdnDomain = $config->get('cdn_domain');
        $this->endPoint = $config->get('endpoint');
        //设置前缀
        $this->setPathPrefix($config->get('prefix'));
    }


    /**
     * Get options from the config.
     *
     * @param Config $config
     *
     * @return array
     */
    protected function getOptionsFromConfig(Config $config)
    {
        $options = $this->options;
        if ($visibility = $config->get('visibility')) {
            // For local reference
            $options['visibility'] = $visibility;
            // For external reference
            $options['ACL'] = $visibility === AdapterInterface::VISIBILITY_PUBLIC ? 'public-read' : 'private';
        }
        if ($mimetype = $config->get('mimetype')) {
            // For local reference
            $options['mimetype'] = $mimetype;
            // For external reference
            $options['ContentType'] = $mimetype;
        }
        foreach (static::$metaOptions as $option) {
            if ( ! $config->has($option)) {
                continue;
            }
            $options[$option] = $config->get($option);
        }
        return $options;
    }
    /**
     * Write a new file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function write($path, $contents, Config $config)
    {
        $object = $this->applyPathPrefix($path);
        $options = $this->getOptionsFromConfig($config);

        return $this->client->putObject($this->bucket,$object,$contents,$options);
    }

    /**
     * Write a new file using a stream.
     *
     * @param string $path
     * @param resource $resource
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function writeStream($path, $resource, Config $config)
    {
        return $this->write($path,stream_get_contents($resource),$config);
    }

    /**
     * Update a file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function update($path, $contents, Config $config)
    {
        return $this->write($path,$contents,$config);
    }

    /**
     * Update a file using a stream.
     *
     * @param string $path
     * @param resource $resource
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function updateStream($path, $resource, Config $config)
    {
        return $this->write($path,stream_get_contents($resource),$config);
    }

    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function rename($path, $newpath)
    {
        return $this->copy($path, $newpath) && $this->delete($path);
    }

    /**
     * Copy a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function copy($path, $newpath)
    {
        $object = $this->applyPathPrefix($path);
        $newObject = $this->applyPathPrefix($newpath);

        return $this->client->copyObject($this->bucket,$object,$this->bucket,$newObject);
    }

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @return bool
     */
    public function delete($path)
    {
        $object = $this->applyPathPrefix($path);

        return $this->client->deleteObject($this->bucket,$object);
    }

    /**
     * Delete a directory.
     *
     * @param string $dirname
     *
     * @return bool
     */
    public function deleteDir($dirname)
    {
    }

    /**
     * Create a directory.
     *
     * @param string $dirname directory name
     * @param Config $config
     *
     * @return array|false
     */
    public function createDir($dirname, Config $config)
    {
        $object = $this->applyPathPrefix($dirname);

        return $this->client->createObjectDir($this->bucket,$object);
    }

    /**
     * Set the visibility for a file.
     *
     * @param string $path
     * @param string $visibility
     *
     * @return array|false file meta data
     */
    public function setVisibility($path, $visibility)
    {
    }

    /**
     * Check whether a file exists.
     *
     * @param string $path
     *
     * @return array|bool|null
     */
    public function has($path)
    {
        $object = $this->applyPathPrefix($path);
        return $this->client->doesObjectExist($this->bucket, $object);
    }

    /**
     * Read a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function read($path)
    {
    }

    /**
     * Read a file as a stream.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function readStream($path)
    {
    }

    /**
     * List contents of a directory.
     *
     * @param string $directory
     * @param bool $recursive
     *
     * @return array
     */
    public function listContents($directory = '', $recursive = false)
    {
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getMetadata($path)
    {
        $object = $this->applyPathPrefix($path);
        try {
            $objectMeta = $this->client->getObjectMeta($this->bucket, $object);
        } catch (OssException $e) {
            return false;
        }
        return $objectMeta;
    }

    /**
     * Get the size of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getSize($path)
    {
        if($object = $this->getMetadata($path)){
            $object['size'] = $object['content-length'];
        }

        return $object;
    }

    /**
     * Get the mimetype of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getMimetype($path)
    {
        if($object = $this->getMetadata($path)){
            $object['mimetype'] = $object['content-type'];
        }

        return $object;
    }

    /**
     * Get the last modified time of a file as a timestamp.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getTimestamp($path)
    {
        if($object = $this->getMetadata($path)){
            $object['timestamp'] = strtotime( $object['last-modified'] );
        }

        return $object;
    }

    /**
     * Get the visibility of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getVisibility($path)
    {
        $object = $this->applyPathPrefix($path);
        try {
            $acl = $this->client->getObjectAcl($this->bucket, $object);
        } catch (OssException $e) {
            return false;
        }

        return [
            'visibility'=>$acl == OssClient::OSS_ACL_TYPE_PUBLIC_READ ? AdapterInterface::VISIBILITY_PUBLIC : AdapterInterface::VISIBILITY_PRIVATE
        ];
    }

    public function getUrl($path){
        $scheme = $this->ssl ? 'https://' : 'http://';
        $domain = $this->isCname ? ($this->cdnDomain ?: $this->endPoint) : ($this->bucket . '.' . $this->endPoint);

        return sprintf('%s%s/%s',$scheme,$domain,trim($path, '/ '));
    }
}