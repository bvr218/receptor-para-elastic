<?php

namespace App\Services;

use Elasticsearch\ClientBuilder;

class ElasticsearchService
{
    protected $client;

    public function __construct()
    {
        $host = Configuration::where("name", "elastic_host")->first();
        $user = Configuration::where("name", "elastic_user")->first();
        $password = Configuration::where("name", "elastic_password")->first();

        if (is_null($host) || is_null($user) || is_null($password)) {
            throw new \Exception('Configure elastic primero');
            return;
        }

        $this->client = ClientBuilder::create()
            ->setHosts([$host])
            ->setBasicAuthentication($user, $password)
            ->build();
    }

    public function indexRequest($data)
    {
        $params = [
            'index' => 'api_requests',
            'body' => [
                'timestamp' => now(),
                'request' => $data
            ]
        ];

        return $this->client->index($params);
    }
}
