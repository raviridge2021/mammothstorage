<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use GuzzleHttp\Client;

class GuzzleHttpClient {
    protected $client;

    public function __construct() {

        $this->client = new Client();
    }

    public function request($method, $url, $options = []) {
        return $this->client->request($method, $url, $options);
    }
}