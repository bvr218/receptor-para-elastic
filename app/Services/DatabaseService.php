<?php

namespace App\Services;

use Elastic\Elasticsearch\ClientBuilder;

use App\Models\RemoteDevice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

class DatabaseService
{
    protected static array $sensorHandlers = [
        'TER21' => 'handleTER21',
        'TER12' => 'handleTER12andTER11',
        'TER11' => 'handleTER12andTER11',
        'TR315H' => 'handleTr315',
        'TR310H' => 'handleTr315',
        'TR305N' => 'handleTr315',


        'EP100G-08'  => 'handleEp100G',
        'EP100GL-08' => 'handleEp100GL',
        'EP100GL-04' => 'handleEs2',
        'EP100G-04' => 'handleEs2',

        'ES2-C'  => 'handleEs2Conductivity',
        'ES-2-C' => 'handleEs2Conductivity',

        'WES-2'  => 'handleWes2',
        'WES-02' => 'handleWes2',
        'WES-03' => 'handleWes2',

        'MPS2'   => 'handleTER21',
        'MPS-2'  => 'handleTER21',
        'MPS-6'  => 'handleTER21',

        '5TE'    => 'handle5te',
        '5T-E'   => 'handle5te',

        'VP4'    => 'handleVp4',
        'VP-4'   => 'handleVp4',

        'ATM14'  => 'handleVp4',
        'ATM22' => 'handleAtm22',

        'SR05-D2A2' => 'handleSr05',
        'SR05-D2A2O' => 'handleSr05O',

        'WSR-SDI' => 'handleWsrSdi',

        'CNT' => 'handleCNT',
        'DD' => 'handleDD',

        'VP-3'   => 'handleVp3',
        'SI-411'   => 'handleEs2', #termoradiometros
        'WMARK1'   => 'handleWMARK1',

        'GL20M' => 'handleGl20m',
        'GL120M' => 'handleGl120m',

        'HAOSHI_PH' => 'handleHaoshiPh',

        #iones
        'CAS40D-PH' => 'handleCas40d',
        'CAS40D-K' => 'handleCas40d',
        'CAS40D-CL' => 'handleCas40d',
        'CAS40D-PH' => 'handleCas40d',

        'TYP8.3' => 'handleTyp83',

        'GS-3' => 'handleGs3',
        'GS3' => 'handleGs3',

        'presostato_0_1MP' => 'handlePresostato01mp',
        'presostato_0_1.6MP' => 'handlePresostato016mp',

        #config server pulse
        'caudal_agri' => 'handlePulseSensor',
        'drain_agri' => 'handlePulseSensor',
        'rain_meter' => 'handlePulseSensor',
    ];

    private function output($name, $ts, $value){
        return [
            $name => [
                (object)[
                    "ts"    => $ts,
                    "value" => $value
                ]
            ]
        ];
    }

    public function getDeviceByToken(string $token){
        return RemoteDevice::where('token', $token)->first();
    }

    public function getSensorsTypes($attributes){

        $list = searchInListOfDicts($attributes, 'cl_digital_list');
        $types = [];

        foreach ($list as $item) {
            foreach ($item['dict']['value']['names'] ?? [] as $code => $name) {
                $types[$code] = $name;
            }
        }

        ksort($types);
        return $types;
    }

    public function getSensorsTypesShSensor($attributes){

        $types=[];
        $shSensors = searchInListOfDicts($attributes, 'sh_sensor');
        if (!is_array($shSensors)) $shSensors = [$shSensors];

        foreach ($shSensors as $item) {
            if (!is_array($item)) continue;
            foreach ($item['dict']['value'] ?? [] as $code => $sensor) {
                if (!empty($sensor['sname'])) {
                    $types[$code] = $sensor['sname'];
                }
            }
        }

        ksort($types);
        return $types;
    }

    public function getSensorsTypesAnalogaList($attributes){

        $types = [];

        $analogList = searchInListOfDicts($attributes, 'analog_list');
        if (!is_array($analogList)) $analogList = [$analogList];

        foreach ($analogList as $item) {
            if (!is_array($item)) continue;
            $value = $item['dict']['value'] ?? [];

            if (is_string($value)) {
                $value = json_decode($value, true) ?? [];
            }
            foreach ($value as $code => $sensor) {
                if (is_string($sensor) && !empty($sensor)) {
                    $types[$code] = $sensor;
                }
                elseif (is_array($sensor) && !empty($sensor['sname'])) {
                    $types[$code] = $sensor['sname'];
                }
            }
        }

        ksort($types);
        return $types;
    }

    public function getSensorsKeysShSensorKey($attributes){

        $list = searchInListOfDicts($attributes, 's_sensorkey');
        $config = $list[0]['dict']['value'] ?? [];

        $config=parse_device_attributes($config);

        $keys = [];
        foreach ($config as $code => $data) {
            $keys[$code] = $data['name'];
        }

        ksort($keys);
        return $keys;
    }

    public function getSensorsKeysConfigServerAnalog($attributes){

       $keys = [];

        $list = searchInListOfDicts($attributes, 'config_server_analog');

        if (!is_array($list) || empty($list)) {
            return $keys;
        }

        $config = $list[0]['dict']['value'] ?? [];
        $config = parse_device_attributes($config);

        foreach ($config as $code => $data) {
            if (!empty($data['name']) && is_array($data['name'])) {
                $keys[$code] = $data['name'];
            }
        }

        ksort($keys);
        return $keys;
    }

    public function getSensorsKeys($attributes){

        $list = searchInListOfDicts($attributes, 'config_server_digital');
        $config = $list[0]['dict']['value'] ?? [];

        $config=parse_device_attributes($config);

        $keys = [];
        foreach ($config as $code => $data) {
            if (isset($data['name'])) {
                $keys[$code] = $data['name'];
            }
        }

        ksort($keys);
        return $keys;
    }

    private function getSensorsTypesPulse(array $attributes): array
    {
        $config = searchInListOfDicts($attributes, 'config_server_pulse');

        $config = $config[0]['dict']['value'] ?? [];

        return collect($config)
            ->mapWithKeys(function ($item, $key) {
                return [$key => $item['type'] ?? null];
            })
            ->filter()
            ->all();
    }

    private function getSensorsKeysConfigServerPulse(array $attributes): array
    {
        $config = searchInListOfDicts($attributes, 'config_server_pulse');

        $config = $config[0]['dict']['value'] ?? [];

        return collect($config)
            ->mapWithKeys(function ($item, $key) {
                return [$key => [$item['name'] ?? $key]];
            })
            ->all();
    }

    private function handlePulseSensor(string $key, array $data, array $sensorKeys, array $sensorTypes): array
    {
        try {
            $ts = $data['ts'] ?? null;
            $v  = $data['values'] ?? [];

            $names = $sensorKeys[$key] ?? null;
            $sensorType = $sensorTypes[$key] ?? null;

            if (!isset($v[$key]) || !$names || !isset($names[0]) || !$sensorType) {
                return [];
            }

            $x = $v[$key];
            $y = null;

            switch ($sensorType) {
                case 'caudal_agri':
                    $rp  = $v[$key . '_rp']  ?? 1;
                    $ltr = $v[$key . '_ltr'] ?? 1;
                    $nge = $v[$key . '_nge'] ?? 1;
                    $y = (60 / $rp) * $ltr * $x / $nge;
                    break;

                case 'drain_agri':
                    $ml = $v[$key . '_ml'] ?? 1;
                    $y = $ml * $x;
                    break;

                case 'rain_meter':
                    $ml = $v[$key . '_ml'] ?? 1;
                    $y = $x * $ml * 0.001 * 50;
                    break;
            }

            if ($y !== null) {
                $name = $names[0];
                return [
                    $name => [
                        [
                            'ts'    => $ts,
                            'value' => $y
                        ]
                    ]
                ];
            }

            return [];

        } catch (\Throwable $e) {
            Log::error("Sensor pulse error at key {$key}: " . $e->getMessage());
            return [];
        }
    }

    public function dataParsing(string $token, array $data): array{
        try {

            $device = $this->getDeviceByToken($token);
            if (!$device) {
                return [];
            }

            $attributes = parse_device_attributes($device->attributes);

            $processingStages = [
                [
                    'name'       => 'digital_classic',
                    'typesMethod' => 'getSensorsTypes',
                    'keysMethod'  => 'getSensorsKeys',
                    'priority'   => 1,
                ],
                [
                    'name'       => 'sh_sensor',
                    'typesMethod' => 'getSensorsTypesShSensor',
                    'keysMethod'  => 'getSensorsKeysShSensorKey',
                    'priority'   => 2,
                ],
                [
                    'name'       => 'analog',
                    'typesMethod' => 'getSensorsTypesAnalogaList',
                    'keysMethod'  => 'getSensorsKeysConfigServerAnalog',
                    'priority'   => 3,
                ],

            ];

            $allCalculated = [];

            foreach ($processingStages as $stage) {
                $types = $this->{$stage['typesMethod']}($attributes);
                $keys  = $this->{$stage['keysMethod']}($attributes);


                if (empty($types)) {
                    continue;
                }

                $stageCalculated = $this->processSensorStage($types, $keys, $data, $stage['name']);

                if (!empty($stageCalculated)) {
                    $allCalculated = array_merge_recursive($allCalculated, $stageCalculated);
                }
            }

            $pulseTypes = $this->getSensorsTypesPulse($attributes);
            $pulseKeys  = $this->getSensorsKeysConfigServerPulse($attributes);

            foreach ($pulseKeys as $key => $names) {
                $pulseData= $this->handlePulseSensor($key, $data, $pulseKeys, $pulseTypes);
                $allCalculated = array_merge_recursive($allCalculated, $pulseData);
            }

           return $this->mergeWithOriginalData($data, $allCalculated);

        } catch (\Throwable $e) {
            dd(str($e));
            return [];
        }
    }

    private function processSensorStage(array $types, array $keys, array $data, string $stageName): array{
        return collect($types)
            ->reject(function ($type) {
                return !isset(self::$sensorHandlers[$type]);
            })
            ->flatMap(function ($type, $sensorCode) use ($data, $keys, $stageName) {
                $handler = self::$sensorHandlers[$type];
                $results = $this->$handler($sensorCode, $data, $keys);

                return $results;
            })
            ->groupBy('name')
            ->map(function ($group) {
                return $group->map(fn($item) => [
                    'ts'    => $item['ts'],
                    'value' => $item['value'],
                ])->values()->all();
            })
            ->all();
    }

    public function mergeSensorData(array $originalData, array $calculated): array {
        $ts = $originalData['ts'] ?? null;
        $values = $originalData['values'] ?? [];

        $final = [];

        foreach ($values as $key => $val) {
            $out = $this->output($key, $ts, $val);
            $final = $this->mergeArrays($final, $out);
        }

        foreach ($calculated as $key => $records) {
            foreach ($records as $r) {
                $val = is_object($r) ? $r->value : $r['value'];
                $tsValue = is_object($r) ? $r->ts : $r['ts'];
                $out = $this->output($key, $tsValue, $val);
                $final = $this->mergeArrays($final, $out);
            }
        }

        return $final;
    }

    private function mergeWithOriginalData(array $original, array $calculated): array{
        $ts = $original['ts'] ?? null;

        $base = [];
        foreach ($original['values'] ?? [] as $sensorName => $value) {
            $base[$sensorName][$ts] = [
                'ts'    => $ts,
                'value' => $value,
            ];
        }

        foreach ($calculated as $sensorName => $records) {
            if (!isset($base[$sensorName])) {
                $base[$sensorName] = [];
            }
            foreach ($records as $record) {
                $recordTs = $record['ts'] ?? null;
                if ($recordTs !== null) {
                    $base[$sensorName][$recordTs] = $record;
                }
            }
        }

        foreach ($base as $sensorName => $recordsByTs) {
            $base[$sensorName] = array_values($recordsByTs);
        }

        return $base;
    }

    private function mergeArrays(array $a, array $b): array
    {
        foreach ($b as $key => $values) {
            if (!isset($a[$key])) $a[$key] = [];
            $a[$key] = array_merge($a[$key], $values);
        }
        return $a;
    }

    //Calculo de los sensores
    private function handleTER21(string $key, array $data, array $sensorKeys): Collection{
        try{
            $ts = $data['ts'];
            $v= $data['values'];
            $names = $sensorKeys[$key] ?? null;

            $result = collect();

            foreach ($names as $idx => $name) {
                $channel = $key . ($idx);

                if (!isset($v[$channel])) {
                    continue;
                }

                $result->push([
                    'name'  => $name,
                    'ts'    => $ts,
                    'value' => $v[$channel],
                ]);
            }

            return $result;

         } catch (\Throwable $e) {
            Log::error("Sensor TER11/12 error at key {$key}: " . $e->getMessage());
            return collect();
        }

    }

    private function handleTER12andTER11(string $key, array $data, array $sensorKeys): Collection {
        try {

        $ts = $data['ts'];
        $v  = $data['values'];
        $names = $sensorKeys[$key] ?? [];

        if (empty($names)) return collect();

        $result = collect();

        foreach ($names as $idx => $name) {

            $channel = $key . $idx;

            if (!isset($v[$channel])) {
                continue;
            }

            $value = null;

            if ($idx == 1) {
                $value = ((3.879E-4 * $v[$channel]) - 0.6956) * 100;
                $value = round($value, 3);
            }

            elseif ($idx == 2) {
                $value = $v[$channel];
            }

            elseif ($idx == 3) {

                $ch1 = $key . '1';
                $ch2 = $key . '2';
                $ch3 = $key . '3';

                if (isset($v[$ch1], $v[$ch2], $v[$ch3])) {

                    $tempOffset = $v[$ch2] - 20.0;
                    $numerator = (80.3 - (0.37 * $tempOffset)) * $v[$ch3];
                    $denominator = 1.112E-18 * pow($v[$ch1], 5.607) - 4.1;

                    $value = ($denominator != 0)
                        ? ($numerator / $denominator)
                        : 0;

                    $value = round($value, 2);
                }
            }

            if ($value !== null) {
                $result->push([
                    'name'  => $name,
                    'ts'    => $ts,
                    'value' => $value,
                ]);
            }
        }

        return $result;


        } catch (\Throwable $e) {
            dd(str($e));
            Log::error("Sensor handleTER12andTER11 error at key {$key}: " . $e->getMessage());
            return collect();
        }

    }

    private function handleTr315(string $key, array $data, array $sensorKeys): Collection {
        try {

            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? [];

            if (empty($names)) return collect();

            $result = collect();

            foreach ($names as $idx => $name) {

                $channel = $key . $idx;

                if (!isset($v[$channel])) {
                    continue;
                }

                $value = null;

                switch ($idx) {
                    // Y1 = X1
                    case 1:
                        $value = $v[$channel];
                        break;
                    // Y2 = X2
                    case 2:
                        $value = $v[$channel];
                        break;
                    // Y3 = X3
                    case 3:
                        $value = $v[$channel];
                        break;
                    // Y4 = X4 conductividad la division entre 1000 se maneja en 4egrowth
                    case 4:
                        $value = $v[$channel];
                        break;
                }

                if ($value !== null) {
                    $result->push([
                        'name'  => $name,
                        'ts'    => $ts,
                        'value' => $value
                    ]);
                }
            }

        return $result;
        } catch (\Throwable $e) {
            Log::error("Sensor handleTr315 error at key {$key}: " . $e->getMessage());
            return collect();
        }

    }

    private function handleEp100GL(string $key, array $data, array $sensorKeys): Collection{
        try {

            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? [];

            if (empty($names)) return collect();

            $result = collect();

            foreach ($names as $idx => $name) {

                $channel = $key . $idx;

                if (!isset($v[$channel])) {
                    continue;
                }

                $result->push([
                    'name'  => $name,
                    'ts'    => $ts,
                    'value' => $v[$channel]
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error("Sensor handleEp100 error at key {$key}: " . $e->getMessage());
            return collect();
        }

    }


    private function handleEp100G(string $key, array $data, array $sensorKeys): Collection{
       try {

            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? [];

            if (empty($names)) return collect();

            $result = collect();

            foreach ($names as $idx => $name) {

                $channel = $key . $idx;

                if (!isset($v[$channel])) {
                    continue;
                }

                $result->push([
                    'name'  => $name,
                    'ts'    => $ts,
                    'value' => $v[$channel]
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error("Sensor handleEp100 error at key {$key}: " . $e->getMessage());
            return collect();
        }

    }


    #mantiene los mismos valores
    private function handleEs2(string $key, array $data, array $sensorKeys): Collection{

        try {

            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? [];

            if (empty($names)) {
                return collect();
            }

            $result = collect();

            foreach ($names as $idx => $name) {

                $channel = $key . $idx;

                if (!isset($v[$channel])) {
                    continue;
                }

                $result->push([
                    'name'  => $name,
                    'ts'    => $ts,
                    'value' => $v[$channel],
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
        Log::error("Sensor handleEs2 error at key {$key}: " . $e->getMessage());
        return collect();
        }

    }

    private function handleWes2(string $key, array $data, array $sensorKeys): Collection{
        return $this->handleEs2($key, $data, $sensorKeys);
    }

    private function handleEs2Conductivity(string $key, array $data, array $sensorKeys): Collection {

        try {

            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? [];

            if (empty($names)) return collect();

            $result = collect();

            foreach ($names as $idx => $name) {

                $channel = $key . $idx;

                if (!isset($v[$channel])) {
                    continue;
                }

                $result->push([
                    'name'  => $name,
                    'ts'    => $ts,
                    'value' => $v[$channel]
                ]);
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error("Sensor TER11/12 error at key {$key}: " . $e->getMessage());
            return collect();
        }
    }

    private function handle5te(string $key, array $data, array $sensorKeys): Collection {

        try {

            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? [];

            if (empty($names)) return collect();

            $result = collect();

            foreach ($names as $idx => $name) {

                $channel = $key . $idx;

                if (!isset($v[$channel])) {
                    continue;
                }

                $value = null;

                switch ($idx) {

                    // Y1: Contenido volumétrico
                    case 1:
                        $x = $v[$channel];
                        $value = (-0.053 + (0.0292 * $x) - (0.00055 * pow($x, 2)) + (0.0000043 * pow($x, 3))) * 100;
                        $value = round($value, 3);
                        break;

                    // Y2: Conductividad eléctrica
                    case 2:
                        $value = $v[$channel] * 100;
                        break;

                    // Y3: Temperatura
                    case 3:
                        $value = $v[$channel];
                        break;
                }

                if ($value !== null) {
                    $result->push([
                        'name'  => $name,
                        'ts'    => $ts,
                        'value' => $value
                    ]);
                }
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error("Sensor 5TE error at key {$key}: " . $e->getMessage());
            return collect();
        }
    }

   private function handleVp4(string $key, array $data, array $sensorKeys): Collection {

        try {

            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? [];

            if (empty($names)) return collect();

            $result = collect();

            foreach ($names as $idx => $name) {

                $channel = $key . $idx;

                $value = null;

                switch ($idx) {

                    // Y1: Presión de vapor
                    case 1:
                        if (isset($v[$channel])) {
                            $value = ((3.879E-4 * $v[$channel]) - 0.6956) * 100;
                            $value = round($value, 3);
                        }
                        break;

                    // Y2: Temperatura
                    case 2:
                        if (isset($v[$channel])) {
                            $value = $v[$channel];
                        }
                        break;

                    // Y3: Humedad relativa
                    case 3:
                        if (isset($v[$channel])) {
                            $value = $v[$channel] * 100;
                        }
                        break;

                    // Y4: Presión atmosférica
                    case 4:
                        if (isset($v[$channel])) {
                            $value = $v[$channel] * 100;
                        }
                        break;

                    // Y5: DPV (usa canal 1 y 2)
                    case 5:

                        $ch1 = $key.'1';
                        $ch2 = $key.'2';

                        if (isset($v[$ch1], $v[$ch2])) {

                            $temp = $v[$ch2];

                            $pvsat = (1 + sqrt(2) * sin(($temp * 3.1416) / (180 * 3)));
                            $dpv   = (pow($pvsat, 8.827) * 0.6107) - $v[$ch1];

                            $value = round($dpv, 3);
                        }

                        break;
                }

                if ($value !== null) {
                    $result->push([
                        'name'  => $name,
                        'ts'    => $ts,
                        'value' => $value
                    ]);
                }
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error("Sensor VP4 error at key {$key}: " . $e->getMessage());
            return collect();
        }
    }

    private function handleAtm22(string $key, array $data, array $sensorKeys): Collection {

        try {

            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? [];

            if (empty($names)) return collect();

            $result = collect();

            foreach ($names as $idx => $name) {

                $channel = $key . $idx;

                if (!isset($v[$channel])) {
                    continue;
                }

                $value = null;

                switch ($idx) {

                    // Y1: Velocidad viento
                    case 1:
                        $value = ($v[$channel] > 0) ? $v[$channel] : 0;
                        break;

                    // Y2: Dirección viento
                    case 2:
                        $value = $v[$channel];
                        break;

                    // Y3: Ráfagas
                    case 3:
                        $value = ($v[$channel] > 0) ? $v[$channel] : 0;
                        break;

                    // Y4: Temperatura aire
                    case 4:
                        $value = $v[$channel];
                        break;
                }

                if ($value !== null) {
                    $result->push([
                        'name'  => $name,
                        'ts'    => $ts,
                        'value' => $value
                    ]);
                }
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error("Sensor ATM22 error at key {$key}: " . $e->getMessage());
            return collect();
        }
    }

    private function handleSr05(string $key, array $data, array $sensorKeys): Collection {

        try {

            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? [];

            if (empty($names)) return collect();

            $result = collect();

            foreach ($names as $idx => $name) {

                $channel = $key.$idx;

                if (!isset($v[$channel])) {
                    continue;
                }

                $x = $v[$channel];
                $value = ($x > 0) ? ((0.5 * $x) - 430.0) : 0;

                $result->push([
                    'name'  => $name,
                    'ts'    => $ts,
                    'value' => round($value, 3)
                ]);
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error("Sensor SR05 error at key {$key}: " . $e->getMessage());
            return collect();
        }
    }

    private function handleSr05O(string $key, array $data, array $sensorKeys): Collection {

        try {

            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? [];

            if (empty($names)) return collect();

            $result = collect();

            foreach ($names as $idx => $name) {

                $channel = $key.$idx;

                if (!isset($v[$channel])) {
                    continue;
                }

                $x = $v[$channel];
                $value = ($x > 0) ? ($x - 430.0) : 0;

                $result->push([
                    'name'  => $name,
                    'ts'    => $ts,
                    'value' => round($value, 3)
                ]);
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error("Sensor SR05O error at key {$key}: " . $e->getMessage());
            return collect();
        }
    }



    private function handleWsrSdi(string $key, array $data, array $sensorKeys): Collection {

        try {

            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? [];

            if (empty($names)) return collect();

            $result = collect();

            foreach ($names as $idx => $name) {

                $channel = $key.$idx;

                if (!isset($v[$channel])) {
                    continue;
                }

                $raw = $v[$channel];

                if ($raw === 'OPEN') {
                    $result->push([
                        'name'  => $name,
                        'ts'    => $ts,
                        'value' => -100
                    ]);
                    continue;
                }

                // X1 → Temperatura
                if ($idx === 1) {

                    $value = floatval(str_replace('F', '', $raw));
                    $tempC = ($value - 32) * 5 / 9;

                    $result->push([
                        'name'  => $name,
                        'ts'    => $ts,
                        'value' => round($tempC, 2)
                    ]);

                    continue;
                }

                // X2..X4 → Potencial matricial
                $value = str_replace(['C', 'B'], '', $raw);
                $value = floatval($value) * -1;

                $result->push([
                    'name'  => $name,
                    'ts'    => $ts,
                    'value' => $value
                ]);
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error("Sensor WsrSdi error at key {$key}: " . $e->getMessage());
            return collect();
        }
    }

    private function handleCNT(string $key, array $data, array $sensorKeys): Collection{
        try{
            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? null;

            if (!$names || count($names) < 1) return collect();

            $result = collect();

            $ch1 = $key . '1';

            if (isset($v[$ch1]) && isset($names[0])) {
                $result->push(['name' => $names[0], 'ts' => $ts, 'value' => $v[$ch1]]);
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error("Sensor TER11/12 error at key {$key}: " . $e->getMessage());
            return collect();
        }

    }

    private function handleDD(string $key, array $data, array $sensorKeys): Collection {

        try {

            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? [];

            if (empty($names)) return collect();

            $result = collect();

            foreach ($names as $idx => $name) {

                $channel = $key.$idx;

                if (!isset($v[$channel])) {
                    continue;
                }

                $result->push([
                    'name'  => $name,
                    'ts'    => $ts,
                    'value' => $v[$channel],
                ]);
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error("Sensor handleDD error at key {$key}: " . $e->getMessage());
            return collect();
        }
    }

    private function handleVp3(string $key, array $data, array $sensorKeys): Collection {

        try {

            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? [];

            if (empty($names)) return collect();

            $result = collect();

            foreach ($names as $idx => $name) {

                $channel = $key.$idx;
                $value = null;

                switch ($idx) {

                    // Y1: Presión vapor
                    case 1:
                        if (isset($v[$channel])) {
                            $value = $v[$channel];
                        }
                        break;

                    // Y2: Temperatura
                    case 2:
                        if (isset($v[$channel])) {
                            $value = $v[$channel];
                        }
                        break;

                    // Y3: Humedad %
                    case 3:
                        if (isset($v[$channel])) {
                            $value = $v[$channel] * 100;
                        }
                        break;

                    // Y4: DPV
                    case 4:

                        $ch1 = $key.'1';
                        $ch2 = $key.'2';

                        if (isset($v[$ch1], $v[$ch2])) {

                            $temp = $v[$ch2];
                            $pvsat = (1 + sqrt(2) * sin(($temp * 3.1416) / (180 * 3)));
                            $dpv   = (pow($pvsat, 8.827) * 0.6107) - $v[$ch1];

                            $value = round($dpv, 3);
                        }

                        break;
                }

                if ($value !== null) {
                    $result->push([
                        'name'  => $name,
                        'ts'    => $ts,
                        'value' => $value
                    ]);
                }
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error("Sensor VP3 error at key {$key}: " . $e->getMessage());
            return collect();
        }
    }

    private function handleWMARK1(string $key, array $data, array $sensorKeys): Collection
    {
        try {

            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? null;

            if (!$names) return collect();

            $result = collect();

            foreach ($names as $idx => $name) {
                $channel = $key . $idx;

                if (!isset($v[$channel])) {
                    continue;
                }

                if (($idx == 6 || $idx == 7) && $v[$channel] <= 0) {
                    continue;
                }

                $result->push([
                    'name'  => $name,
                    'ts'    => $ts,
                    'value' => $v[$channel],
                ]);
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error("Sensor WMARK1 error at key {$key}: " . $e->getMessage());
            return collect();
        }
    }

    private function handleGl20m(string $key, array $data, array $sensorKeys): Collection
    {
        try {

            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? [];

            if (empty($names)) return collect();

            $result = collect();

            foreach ($names as $idx => $name) {

                $channel = $key.$idx;

                if (!isset($v[$channel])) continue;

                $x = $v[$channel];

                $value = ($x < 0) ? 0 : (0.0125 * $x - 5);

                $result->push([
                    'name'  => $name,
                    'ts'    => $ts,
                    'value' => $value
                ]);
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error("Sensor GL20M error at key {$key}: " . $e->getMessage());
            return collect();
        }
    }

   private function handleGl120m(string $key, array $data, array $sensorKeys): Collection
    {
        try {

            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? [];

            if (empty($names)) return collect();

            $result = collect();

            foreach ($names as $idx => $name) {

                $channel = $key.$idx;

                if (!isset($v[$channel])) continue;

                $x = $v[$channel];

                $value = ($x < 0) ? 0 : (0.075 * $x - 30);

                $result->push([
                    'name'  => $name,
                    'ts'    => $ts,
                    'value' => $value
                ]);
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error("Sensor GL120M error at key {$key}: " . $e->getMessage());
            return collect();
        }
    }

    private function handleHaoshiPh(string $key, array $data, array $sensorKeys): Collection
    {
        try {

            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? [];

            if (empty($names)) return collect();

            $result = collect();

            foreach ($names as $idx => $name) {

                $channel = $key.$idx;

                if (!isset($v[$channel])) continue;

                $x = $v[$channel];

                $value = ($x < 0) ? 0 : (-8.08 + (7.89 * $x / 1000.0));

                $result->push([
                    'name'  => $name,
                    'ts'    => $ts,
                    'value' => $value
                ]);
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error("Sensor HAOSHI_PH error at key {$key}: " . $e->getMessage());
            return collect();
        }
    }

   private function handleCas40d(string $key, array $data, array $sensorKeys): Collection
    {
        try {

            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? [];

            if (empty($names)) return collect();

            $result = collect();

            foreach ($names as $idx => $name) {

                $channel = $key.$idx;

                if (!isset($v[$channel])) {
                    continue;
                }

                $result->push([
                    'name'  => $name,
                    'ts'    => $ts,
                    'value' => $v[$channel]
                ]);
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error("Sensor CAS40D-PH error at key {$key}: " . $e->getMessage());
            return collect();
        }
    }


    private function handleTyp83(string $key, array $data, array $sensorKeys): Collection
    {
        try {

            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? [];

            if (empty($names)) return collect();

            $result = collect();

            foreach ($names as $idx => $name) {

                $channel = $key.$idx;

                if (!isset($v[$channel])) continue;

                $x = $v[$channel];
                $y = max($x * 0.2, 400);

                $result->push([
                    'name'  => $name,
                    'ts'    => $ts,
                    'value' => $y
                ]);
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error("Sensor TYP8.3 error at key {$key}: " . $e->getMessage());
            return collect();
        }
    }

   private function handleGs3(string $key, array $data, array $sensorKeys): Collection
    {
        try {

            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? [];

            if (empty($names)) return collect();

            $result = collect();

            foreach ($names as $idx => $name) {

                $channel = $key.$idx;

                if (!isset($v[$channel])) continue;

                $value = null;

                switch ($idx) {

                    // Y1 humedad volumétrica
                    case 1:
                        $x = $v[$channel];
                        $value = ((0.18 * sqrt($x)) - 0.117) * 100;
                        break;

                    // Y2 temperatura suelo
                    case 2:
                        $value = $v[$channel];
                        break;

                    // Y3 conductividad
                    case 3:
                        $value = $v[$channel];
                        break;
                }

                if ($value !== null) {
                    $result->push([
                        'name'  => $name,
                        'ts'    => $ts,
                        'value' => $value
                    ]);
                }
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error("Sensor GS-3 error at key {$key}: " . $e->getMessage());
            return collect();
        }
    }

    private function handlePresostato01mp(string $key, array $data, array $sensorKeys): Collection
    {
        try {

            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? [];

            if (empty($names)) return collect();

            $result = collect();

            foreach ($names as $idx => $name) {

                $channel = $key.$idx;

                if (!isset($v[$channel])) continue;

                $x = $v[$channel];
                $y = 0.625 * $x + 250;

                $result->push([
                    'name'  => $name,
                    'ts'    => $ts,
                    'value' => $y
                ]);
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error("Sensor presostato_0_1MP error at key {$key}: " . $e->getMessage());
            return collect();
        }
    }

    private function handlePresostato016mp(string $key, array $data, array $sensorKeys): Collection
    {
        try {

            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? [];

            if (empty($names)) return collect();

            $result = collect();

            foreach ($names as $idx => $name) {

                $channel = $key.$idx;

                if (!isset($v[$channel])) continue;

                $x = $v[$channel];
                $y = $x - 400;

                $result->push([
                    'name'  => $name,
                    'ts'    => $ts,
                    'value' => $y
                ]);
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error("Sensor presostato_0_1.6MP error at key {$key}: " . $e->getMessage());
            return collect();
        }
    }

}
