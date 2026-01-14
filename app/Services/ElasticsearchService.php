<?php

namespace App\Services;

use Elastic\Elasticsearch\ClientBuilder;

use App\Models\Configuration;
use App\Models\RemoteDevice;

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


    public function checkDevicesDataAndExport()
    {
        try{
            $devices = RemoteDevice::all();
            $results = [];

            foreach ($devices as $device) {
                $token = $device->token;
                $deviceKey = $device->key;

                $params = [
                    'index' => 'api_requests',
                    'body' => [
                        'size' => 1000,
                        '_source' => ['timestamp','request.token','request.body','request.body_calculated'],
                        'query' => [
                            'term' => [
                                'request.token.keyword' => $token
                            ]
                        ],
                        'sort' => [
                            ['timestamp' => ['order' => 'desc']]
                        ]
                    ]
                ];

                try {
                    $response = $this->client->search($params);

                    $hasData = isset($response['hits']['hits']) && count($response['hits']['hits']) > 0;

                    $results[] = [
                        'device_key' => $deviceKey,
                        'token' => $token,
                        'has_data' => $hasData ? 'yes' : 'no',
                        'error' => null
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'device_key' => $deviceKey,
                        'token' => $token,
                        'has_data' => 'no',
                        'error' => $e->getMessage()
                    ];
                }
            }

            $fileName = storage_path('app/devices_data_report.csv');
            $file = fopen($fileName, 'w');

            fputcsv($file, ['device_key', 'token', 'has_data', 'error']);

            foreach ($results as $row) {
                fputcsv($file, $row);
            }

            fclose($file);

            return $fileName;


    } catch (\Exception $e) {
        $results[] = [
            'device_key' => $deviceKey,
            'token' => $token,
            'has_data' => 'no',
            'error' => $e->getMessage()
        ];
    }
    }

}
