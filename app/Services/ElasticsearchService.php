<?php

namespace App\Services;

use Elastic\Elasticsearch\ClientBuilder;

use App\Models\Configuration;


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
                ->setHosts(["https://" . $host->value])
                ->setSSLVerification(false)
                ->setBasicAuthentication($user->value, $password->value)
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
