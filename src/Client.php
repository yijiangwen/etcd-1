<?php

namespace ActiveCollab\Etcd;

use ActiveCollab\Etcd\Exception\EtcdException;
use ActiveCollab\Etcd\Exception\KeyExistsException;
use ActiveCollab\Etcd\Exception\KeyNotFoundException;
use RecursiveArrayIterator;
use stdClass;

/**
 * @package ActiveCollab\Etcd
 */
class Client
{
    /**
     * @var string
     */
    private $server;

    /**
     * @var bool
     */
    private $is_https = false;

    /**
     * @var string
     */
    private $api_version;

    /**
     * @var string
     */
    private $root = '/';

    /**
     * @var boolean
     */
    private $verify_ssl_peer = true;

    /**
     * @param string $server
     * @param string $api_version
     */
    public function __construct($server = 'http://127.0.0.1:4001', $api_version = 'v2')
    {
        $this->setServer($server);
        $this->setApiVersion($api_version);
    }

    /**
     * @return string
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * @param  string $server
     * @return $this
     */
    public function &setServer($server)
    {
        if (filter_var($server, FILTER_VALIDATE_URL)) {
            $server = rtrim($server, '/');

            if ($server) {
                $this->server = $server;
                $this->is_https = strtolower(parse_url($this->server)['scheme']) == 'https';
            }

            return $this;
        } else {
            throw new \InvalidArgumentException("Value '$server' is not a valid server URL");
        }
    }

    /**
     * @return string
     */
    public function getApiVersion()
    {
        return $this->api_version;
    }

    /**
     * @param  string $version
     * @return $this
     */
    public function &setApiVersion($version)
    {
        $this->api_version = $version;

        return $this;
    }

    /**
     * @return string
     */
    public function getRoot()
    {
        return $this->root;
    }

    /**
     * Set the default root directory. the default is `/`
     * If the root is others e.g. /linkorb when you set new key,
     * or set dir, all of the key is under the root
     * e.g.
     * <code>
     *    $client->setRoot('/linkorb');
     *    $client->set('key1, 'value1');
     *    // the new key is /linkorb/key1
     * </code>
     *
     * @param string $root
     * @return Client
     */
    public function &setRoot($root)
    {
        if (substr($root, 0, 1) !== '/') {
            $root = '/' . $root;
        }

        $this->root = rtrim($root, '/');

        return $this;
    }

    /**
     * Build key space operations
     *
     * @param  string $key
     * @return string
     */
    public function getKeyPath($key)
    {
        if (substr($key, 0, 1) !== '/') {
            $key = '/' . $key;
        }

        return rtrim('/' . $this->api_version . '/keys' . $this->root, '/') . $key;
    }

    /**
     * Return full key URI
     *
     * @param  string $key
     * @return string
     */
    public function getKeyUrl($key)
    {
        return $this->server . $this->getKeyPath($key);
    }

    /**
     * @return array
     */
    public function geVersion()
    {
        return $this->httpGet($this->server . '/version');
    }

    /**
     * Make a GET request
     *
     * @param  string $uri
     * @param  array  $query_arguments
     * @return array
     */
    public function httpGet($uri, $query_arguments = [])
    {
        if (!empty($query_arguments)) {
            $uri .= '?' . http_build_query($query_arguments);
        }

        if ($curl = curl_init($uri)) {
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);

            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($curl);

            if ($error_code = curl_errno($curl)) {
                $error = curl_error($curl);

                curl_close($curl);
                throw new \RuntimeException('GET request failed. Reason: ' . $error, $error_code);
            } else {
                curl_close($curl);

                return json_decode($response, true);
            }
        }
    }

    public function httpPut($uri, $payload = [], $query_arguments = [])
    {
        if (!empty($query_arguments)) {
            $uri .= '?' . http_build_query($query_arguments);
        }

        if ($curl = curl_init($uri)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ]);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));

            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);

            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($curl);

            if ($error_code = curl_errno($curl)) {
                $error = curl_error($curl);

                curl_close($curl);
                throw new \RuntimeException('PUT request failed. Reason: ' . $error, $error_code);
            } else {
                curl_close($curl);

                $response = json_decode($response, true);

                if (isset($response['errorCode']) && $response['errorCode']) {
                    $message = $response['message'];

                    if (isset($response['cause']) && $response['cause']) {
                        $message .= '. Cause: ' . $response['cause'];
                    }

                    throw new EtcdException($message);
                }

                return $response;
            }
        }
    }

    public function httpDelete($uri, $query_arguments = [])
    {
        if (!empty($query_arguments)) {
            $uri .= '?' . http_build_query($query_arguments);
        }

        if ($curl = curl_init($uri)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ]);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');

            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);

            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($curl);

            if ($error_code = curl_errno($curl)) {
                $error = curl_error($curl);

                curl_close($curl);
                throw new \RuntimeException('DELETE request failed. Reason: ' . $error, $error_code);
            } else {
                curl_close($curl);
                return json_decode($response, true);
            }
        }
    }

    /**
     * Set the value of a key
     *
     * @param string $key
     * @param string $value
     * @param int    $ttl
     * @param array  $condition
     * @return stdClass
     */
    public function set($key, $value, $ttl = null, $condition = [])
    {
        $data = ['value' => $value];

        if ($ttl) {
            $data['ttl'] = $ttl;
        }

        return $this->httpPut($this->getKeyUrl($key), $data, $condition);
    }

    /**
     * Retrieve the value of a key
     *
     * @param string $key
     * @param array  $flags the extra query params
     * @return array
     * @throws KeyNotFoundException
     */
    public function getNode($key, array $flags = null)
    {
        $query = [];
        if ($flags) {
            $query = [
            'query' => $flags,
            ];
        }

        $body = $this->httpGet($this->getKeyUrl($key), $query);

//        $request = $this->guzzleclient->get(
//        $this->buildKeyUri($key),
//        null,
//        $query
//        );
//        $response = $request->send();
//        $body = $response->json();
        if (isset($body['errorCode'])) {
            throw new KeyNotFoundException($body['message'], $body['errorCode']);
        }

        return $body['node'];
    }

    /**
     * Retrieve the value of a key
     *
     * @param string $key
     * @param array  $flags the extra query params
     * @return string the value of the key.
     * @throws KeyNotFoundException
     */
    public function get($key, array $flags = null)
    {
        try {
            $node = $this->getNode($key, $flags);

            return $node['value'];
        } catch (KeyNotFoundException $ex) {
            throw $ex;
        }
    }

    /**
     * make a new key with a given value
     *
     * @param string $key
     * @param string $value
     * @param int    $ttl
     * @return array $body
     * @throws KeyExistsException
     */
    public function mk($key, $value, $ttl = 0)
    {
        $body = $request = $this->set(
        $key,
        $value,
        $ttl,
        ['prevExist' => 'false']
        );

        if (isset($body['errorCode'])) {
            throw new KeyExistsException($body['message'], $body['errorCode']);
        }

        return $body;
    }

    /**
     * make a new directory
     *
     * @param string $key
     * @param int    $ttl
     * @return array $body
     * @throws KeyExistsException
     */
    public function mkdir($key, $ttl = 0)
    {
        $data = ['dir' => 'true'];

        if ($ttl) {
            $data['ttl'] = $ttl;
        }

        //var_dump($this->server . $this->buildKeyUri($key));

        $body = $this->httpPut($this->getKeyUrl($key), $data, ['prevExist' => 'false']);

//        $request = $this->guzzleclient->put(
//        $this->buildKeyUri($key),
//        null,
//        $data,
//        [
//        'query' => ['prevExist' => 'false'],
//        ]
//        );
//
//        $response = $request->send();
//        $body = $response->json();
        if (isset($body['errorCode'])) {
            throw new KeyExistsException($body['message'], $body['errorCode']);
        }

        return $body;
    }


    /**
     * Update an existing key with a given value.
     *
     * @param string $key
     * @param string $value
     * @param int    $ttl
     * @param array  $condition The extra condition for updating
     * @return array $body
     * @throws KeyNotFoundException
     */
    public function update($key, $value, $ttl = 0, $condition = [])
    {
        $extra = ['prevExist' => 'true'];

        if ($condition) {
            $extra = array_merge($extra, $condition);
        }
        $body = $this->set($key, $value, $ttl, $extra);
        if (isset($body['errorCode'])) {
            throw new KeyNotFoundException($body['message'], $body['errorCode']);
        }

        return $body;
    }

    /**
     * Update directory
     *
     * @param string $key
     * @param int    $ttl
     * @return array $body
     * @throws EtcdException
     */
    public function updateDir($key, $ttl)
    {
        if (!$ttl) {
            throw new EtcdException('TTL is required', 204);
        }

        $condition = [
        'dir' => 'true',
        'prevExist' => 'true',
        ];

        $request = $this->guzzleclient->put(
        $this->getKeyPath($key),
        null,
        [
        'ttl' => (int)$ttl,
        ],
        [
        'query' => $condition,
        ]
        );
        $response = $request->send();
        $body = $response->json();
        if (isset($body['errorCode'])) {
            throw new EtcdException($body['message'], $body['errorCode']);
        }

        return $body;
    }


    /**
     * remove a key
     *
     * @param string $key
     * @return array|stdClass
     * @throws EtcdException
     */
    public function rm($key)
    {
        $request = $this->guzzleclient->delete($this->getKeyPath($key));
        $response = $request->send();
        $body = $response->json();

        if (isset($body['errorCode'])) {
            throw new EtcdException($body['message'], $body['errorCode']);
        }

        return $body;
    }

    /**
     * Removes the key if it is directory
     *
     * @param string  $key
     * @param boolean $recursive
     * @return mixed
     * @throws EtcdException
     */
    public function rmdir($key, $recursive = false)
    {
        $query = ['dir' => 'true'];

        if ($recursive === true) {
            $query['recursive'] = 'true';
        }

        $body = $this->httpDelete($this->server . $this->getKeyPath($key), $query);

//        $request = $this->guzzleclient->delete(
//        $this->buildKeyUri($key),
//        null,
//        null,
//        [
//        'query' => $query,
//        ]
//        );
//        $response = $request->send();
//        $body = $response->json();
        if (isset($body['errorCode'])) {
            throw new EtcdException($body['message'], $body['errorCode']);
        }

        return $body;
    }

    /**
     * Retrieve a directory
     *
     * @param string  $key
     * @param boolean $recursive
     * @return mixed
     * @throws KeyNotFoundException
     */
    public function listDir($key = '/', $recursive = false)
    {
        $query = [];
        if ($recursive === true) {
            $query['recursive'] = 'true';
        }
        $request = $this->guzzleclient->get(
        $this->getKeyPath($key),
        null,
        [
        'query' => $query,
        ]
        );
        $response = $request->send();
        $body = $response->json();
        if (isset($body['errorCode'])) {
            throw new KeyNotFoundException($body['message'], $body['errorCode']);
        }

        return $body;
    }

    /**
     * Retrieve a directories key
     *
     * @param string  $key
     * @param boolean $recursive
     * @return array
     * @throws EtcdException
     */
    public function ls($key = '/', $recursive = false)
    {
        try {
            $data = $this->listDir($key, $recursive);
        } catch (EtcdException $e) {
            throw $e;
        }

        $iterator = new RecursiveArrayIterator($data);

        return $this->traversalDir($iterator);
    }

    private $dirs = [];

    private $values = [];


    /**
     * Traversal the directory to get the keys.
     *
     * @param RecursiveArrayIterator $iterator
     * @return array
     */
    private function traversalDir(RecursiveArrayIterator $iterator)
    {
        $key = '';
        while ($iterator->valid()) {
            if ($iterator->hasChildren()) {
                $this->traversalDir($iterator->getChildren());
            } else {
                if ($iterator->key() == 'key' && ($iterator->current() != '/')) {
                    $this->dirs[] = $key = $iterator->current();
                }

                if ($iterator->key() == 'value') {
                    $this->values[ $key ] = $iterator->current();
                }
            }
            $iterator->next();
        }

        return $this->dirs;
    }

    /**
     * Get all key-value pair that the key is not directory.
     *
     * @param string  $root
     * @param boolean $recursive
     * @param string  $key
     * @return array
     */
    public function getKeysValue($root = '/', $recursive = true, $key = null)
    {
        $this->ls($root, $recursive);
        if (isset($this->values[ $key ])) {
            return $this->values[ $key ];
        }

        return $this->values;
    }
}