<?php
/**
 * This file is part of the Imbo package
 *
 * (c) Christer Edvartsen <cogo@starzinger.net>
 *
 * For the full copyright and license information, please view the LICENSE file that was
 * distributed with this source code.
 */

namespace Imbo\EventListener\ImageVariations\Storage;

use Imbo\Exception\StorageException,
    MongoClient,
    MongoGridFS,
    MongoException;

/**
 * GridFS (MongoDB) database driver for the image variations
 *
 * Valid parameters for this driver:
 *
 * - (string) databaseName Name of the database. Defaults to 'imbo_imagevariation_storage'
 * - (string) server The server string to use when connecting to MongoDB. Defaults to
 *                   'mongodb://localhost:27017'
 * - (array) options Options to use when creating the Mongo client instance. Defaults to
 *                   array('connect' => true, 'connectTimeoutMS' => 1000).
 *
 * @author Christer Edvartsen <cogo@starzinger.net>
 * @package Storage
 */
class GridFS implements StorageInterface {
    /**
     * Mongo client instance
     *
     * @var MongoClient
     */
    private $mongoClient;

    /**
     * The grid instance
     *
     * @var MongoGridFS
     */
    private $grid;

    /**
     * Parameters for the driver
     *
     * @var array
     */
    private $params = array(
        // Database name
        'databaseName' => 'imbo_imagevariation_storage',

        // Server string and ctor options
        'server'  => 'mongodb://localhost:27017',
        'options' => array('connect' => true, 'connectTimeoutMS' => 1000),
    );

    /**
     * Class constructor
     *
     * @param array $params Parameters for the driver
     * @param MongoClient $client Mongo client instance
     * @param MongoGridFS $grid MongoGridFS instance
     */
    public function __construct(array $params = null, MongoClient $client = null, MongoGridFS $grid = null) {
        if ($params !== null) {
            $this->params = array_replace_recursive($this->params, $params);
        }

        if ($client !== null) {
            $this->mongoClient = $client;
        }

        if ($grid !== null) {
            $this->grid = $grid;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function storeImageVariation($publicKey, $imageIdentifier, $blob, $width) {
        $this->getGrid()->storeBytes($blob, array(
            'added' => time(),
            'publicKey' => $publicKey,
            'imageIdentifier' => $imageIdentifier,
            'width' => (int) $width,
        ));

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getImageVariation($publicKey, $imageIdentifier, $width) {
        $file = $this->getGrid()->findOne(array(
            'publicKey' => $publicKey,
            'imageIdentifier' => $imageIdentifier,
            'width' => (int) $width,
        ));

        return $file ? $file->getBytes() : null;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteImageVariations($publicKey, $imageIdentifier, $width = null) {
        $query = array(
            'publicKey' => $publicKey,
            'imageIdentifier' => $imageIdentifier
        );

        if ($width !== null) {
            $query['width'] = $width;
        }

        $this->getGrid()->remove($query);

        return true;
    }

    /**
     * Get the grid instance
     *
     * @return MongoGridFS
     */
    protected function getGrid() {
        if ($this->grid === null) {
            try {
                $database = $this->getMongoClient()->selectDB($this->params['databaseName']);
                $this->grid = $database->getGridFS();
            } catch (MongoException $e) {
                throw new StorageException('Could not connect to database', 500, $e);
            }
        }

        return $this->grid;
    }

    /**
     * Get the mongo client instance
     *
     * @return MongoClient
     */
    protected function getMongoClient() {
        if ($this->mongoClient === null) {
            try {
                $this->mongoClient = new MongoClient($this->params['server'], $this->params['options']);
            } catch (MongoException $e) {
                throw new StorageException('Could not connect to database', 500, $e);
            }
        }

        return $this->mongoClient;
    }
}
