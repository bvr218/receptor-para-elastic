<?php

namespace App\Services;

use Elastic\Elasticsearch\ClientBuilder;

use App\Models\RemoteDevice;
use Illuminate\Support\Collection;

class DatabaseService
{
    protected static array $sensorHandlers = [
        'TER21' => 'handleTER21',
        'TER12' => 'handleTER12andTER11',
        'TER11' => 'handleTER12andTER11',
        'TR315H' => 'handleTr315',
        'TR310H' => 'handleTr315',
        'TR305N' => 'handleTr315',
        'EP100G-08'  => 'handleEp100',
        'EP100GL-08' => 'handleEp100',

        // ES2: versión potencial
        'ES2'    => 'handleEs2',
        'ES-2'   => 'handleEs2',

        // ES2: versión conductividad
        'ES2-C'  => 'handleEs2Conductivity',
        'ES-2-C' => 'handleEs2Conductivity',

        'WES-2'  => 'handleWes2',
        'WES-02' => 'handleWes2',
        'WES-03' => 'handleWes2',

        'MPS2'   => 'handleMps2',
        'MPS-2'  => 'handleMps2',
        'MPS-6'  => 'handleMps2',

        '5TE'    => 'handle5te',
        '5T-E'   => 'handle5te',

        'VP4'    => 'handleVp4',
        'VP-4'   => 'handleVp4',
        'ATM14'  => 'handleVp4',

        'ATM22' => 'handleAtm22',

        'SR05-D2A2' => 'handleSr05',

        'WSR-SDI' => 'handleWsrSdi',
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

    public function getSensorsKeys($attributes){

        $list = searchInListOfDicts($attributes, 'config_server_digital');
        $config = $list[0]['dict']['value'] ?? [];

        $config=parse_device_attributes($config);

        $keys = [];
        foreach ($config as $code => $data) {
            if (isset($data['name'])) {
                $keys[$code] = array_values($data['name']);
            }
        }

        ksort($keys);
        return $keys;
    }

    public function dataParsing($token, $data){
        try {
            $device = $this->getDeviceByToken($token);
            if (!$device) return [];


            $attributes = $device->attributes;
            $attributes = parse_device_attributes($attributes);
            $sensorTypes      = $this->getSensorsTypes($attributes);
            $sensorKeys       = $this->getSensorsKeys($attributes);

            $calculated = collect($sensorTypes)
            ->reject(fn($type) => !isset(self::$sensorHandlers[$type]))
            ->map(fn($type, $key) => $this->{self::$sensorHandlers[$type]}($key, $data, $sensorKeys))
            ->collapse()
            ->groupBy('name')
            ->map(fn($items) => $items->map(fn($i) => [
                'ts'    => $i['ts'],
                'value' => $i['value'],
            ])->all())
            ->all();

            $final_data = $this->mergeWithOriginalData($data, $calculated);

            return $final_data;

        } catch (\Exception $e) {
            \Log::error("Error en dataParsing para token {$token}: " . $e->getMessage());
            dd($e->getMessage());
            return [];
        }
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
        $base = collect($original['values'] ?? [])
            ->map(fn($value, $key) => [
                'ts'    => $ts,
                'value' => $value,
            ])
            ->groupBy(fn($item, $key) => $key)
            ->toArray();

        foreach ($calculated as $sensorName => $records) {
            if (!isset($base[$sensorName])) {
                $base[$sensorName] = [];
            }
            $base[$sensorName] = array_merge($base[$sensorName], $records);
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
            $values = $data['values'];
            $names = $sensorKeys[$key] ?? null;

            if (!$names || count($names) < 2) return collect();

            return collect([
                [$names[0], $values[$key . '1'] ?? null],
                [$names[1], $values[$key . '2'] ?? null],
            ])
            ->filter(fn($v) => $v[1] !== null)
            ->map(fn($item) => [
                'name'  => $item[0],
                'ts'    => $ts,
                'value' => $item[1],
            ]);

         } catch (\Throwable $e) {
            \Log::error("Sensor TER11/12 error at key {$key}: " . $e->getMessage());
            return collect();
        }

    }

    private function handleTER12andTER11(string $key, array $data, array $sensorKeys): Collection {
        try{
            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? null;

            $result = collect();

            $ch1 = $key . '1'; // raw
            $ch2 = $key . '2'; // temp
            $ch3 = $key . '3'; // raw para conductividad

            // Y1: Contenido volumétrico
            if (isset($v[$ch1]) && isset($names[0])) {
                $y1 = ((3.879E-4 * $v[$ch1]) - 0.6956) * 100;
                $result->push(['name' => $names[0], 'ts' => $ts, 'value' => round($y1, 3)]);
            }

            // Y2: Temperatura
            if (isset($v[$ch2]) && isset($names[1])) {
                $result->push(['name' => $names[1], 'ts' => $ts, 'value' => $v[$ch2]]);
            }

            // Y3: Conductividad (fórmula pesada)
            if (isset($v[$ch1], $v[$ch2], $v[$ch3]) && isset($names[2])) {
                $tempOffset = $v[$ch2] - 20.0;
                $numerator = (80.3 - (0.37 * $tempOffset)) * $v[$ch3];
                $denominator = 1.112E-18 * pow($v[$ch1], 5.607);
                $y3 = ($denominator != 0) ? ($numerator / $denominator) - 4.1 : 0;

                $result->push(['name' => $names[2], 'ts' => $ts, 'value' => round($y3, 2)]);
            }

            return $result;
        } catch (\Throwable $e) {
            \Log::error("Sensor handleTER12andTER11 error at key {$key}: " . $e->getMessage());
            return collect();
        }

    }

    private function handleTr315(string $key, array $data, array $sensorKeys): Collection {
        try{
            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? null;

            if (!$names) return collect();

            $result = collect();

            $ch1 = $key . '1';   // X1 - Volumétrico
            $ch2 = $key . '2';   // X2 - Temperatura
            $ch3 = $key . '3';   // X3 - Permitividad
            $ch4 = $key . '4';   // X4 - Conductividad

            // Y1: Contenido volumétrico (%)
            if (isset($v[$ch1]) && isset($names[0])) {
                $result->push([
                    'name'  => $names[0],
                    'ts'    => $ts,
                    'value' => $v[$ch1]   // Y1 = X1
                ]);
            }

            // Y2: Temperatura (°C)
            if (isset($v[$ch2]) && isset($names[1])) {
                $result->push([
                    'name'  => $names[1],
                    'ts'    => $ts,
                    'value' => $v[$ch2]   // Y2 = X2
                ]);
            }

            // Y3: Permitividad relativa
            if (isset($v[$ch3]) && isset($names[2]) ) {
                $result->push([
                    'name'  => $names[2],
                    'ts'    => $ts,
                    'value' => $v[$ch3]   // Y3 = X3
                ]);
            }

            // Y4: Conductividad (uS/cm)
            if (isset($v[$ch4]) && isset($names[3])) {
                $result->push([
                    'name'  => $names[3],
                    'ts'    => $ts,
                    'value' => $v[$ch4]   // Y4 = X4
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            \Log::error("Sensor handleTr315 error at key {$key}: " . $e->getMessage());
            return collect();
        }

    }

    private function handleEp100(string $key, array $data, array $sensorKeys): Collection{
        try{
            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? null;

            // EP100G-08 → 8 variables (X1..X8)
            if (!$names || count($names) < 8) return collect();

            $result = collect();

            for ($i = 1; $i <= 8; $i++) {
                $channel = $key . $i;
                $name    = $names[$i - 1];

                if (isset($v[$channel])) {
                    $result->push([
                        'name'  => $name,
                        'ts'    => $ts,
                        'value' => $v[$channel]
                    ]);
                }
            }

            return $result;
        } catch (\Throwable $e) {
            \Log::error("Sensor handleEp100 error at key {$key}: " . $e->getMessage());
            return collect();
        }

    }

    private function handleEs2(string $key, array $data, array $sensorKeys): Collection{

        try{
            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? null;

            if (!$names || count($names) < 2) return collect();

            $result = collect();

            $ch1 = $key . '1';   // X1: Potencial
            $ch2 = $key . '2';   // X2: Temperatura

            if (isset($v[$ch1]) && isset($names[0])) {
                $result->push(['name' => $names[0], 'ts' => $ts, 'value' => $v[$ch1]]);
            }

            if (isset($v[$ch2]) && isset($names[1])) {
                $result->push(['name' => $names[1], 'ts' => $ts, 'value' => $v[$ch2]]);
            }

            return $result;

        } catch (\Throwable $e) {
        \Log::error("Sensor handleEs2 error at key {$key}: " . $e->getMessage());
        return collect();
        }

    }

    private function handleWes2(string $key, array $data, array $sensorKeys): Collection{
        return $this->handleEs2($key, $data, $sensorKeys);
    }

    private function handleEs2Conductivity(string $key, array $data, array $sensorKeys): Collection{
        try{
            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? null;

            if (!$names || count($names) < 2) return collect();

            $result = collect();

            $ch1 = $key . '1';   // Conductividad
            $ch2 = $key . '2';   // Temperatura

            if (isset($v[$ch1]) && isset($names[0])) {
                $result->push(['name' => $names[0], 'ts' => $ts, 'value' => $v[$ch1]]);
            }

            if (isset($v[$ch2]) && isset($names[1])) {
                $result->push(['name' => $names[1], 'ts' => $ts, 'value' => $v[$ch2]]);
            }

            return $result;
        } catch (\Throwable $e) {
            \Log::error("Sensor TER11/12 error at key {$key}: " . $e->getMessage());
            return collect();
        }

    }

    private function handle5te(string $key, array $data, array $sensorKeys): Collection{
        try{
            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? null;

            if (!$names) return collect();

            $result = collect();

            $ch1 = $key . '1';   // X1: raw del volumétrico
            $ch2 = $key . '2';   // X2: EC
            $ch3 = $key . '3';   // X3: Temperatura

            // Y1: Contenido volumétrico %
            if (isset($v[$ch1]) && isset($names[0])) {
                $x = $v[$ch1];
                $y1 = (-0.053 + (0.0292 * $x) - (0.00055 * pow($x, 2)) + (0.0000043 * pow($x, 3))) * 100;
                $result->push(['name' => $names[0], 'ts' => $ts, 'value' => round($y1, 3)]);
            }

            // Y2: Conductividad eléctrica dS/m
            if (isset($v[$ch2]) && isset($names[1])) {
                $result->push(['name' => $names[1], 'ts' => $ts, 'value' => $v[$ch2] * 100]);
            }

            // Y3: Temperatura
            if (isset($v[$ch3]) && isset($names[2])) {
                $result->push(['name' => $names[2], 'ts' => $ts, 'value' => $v[$ch3]]);
            }

            return $result;
        } catch (\Throwable $e) {
            \Log::error("Sensor TER11/12 error at key {$key}: " . $e->getMessage());
            return collect();
        }

    }

    private function handleVp4(string $key, array $data, array $sensorKeys): Collection{
        try{
            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? null;

            $result = collect();

            $ch1 = $key . '1';
            $ch2 = $key . '2';
            $ch3 = $key . '3';
            $ch4 = $key . '4';

            // Y1: Presión vapor
            if (isset($v[$ch1]) && isset($names[0])) {
                $y1 = ((3.879E-4 * $v[$ch1]) - 0.6956) * 100.0;
                $result->push(['name' => $names[0], 'ts' => $ts, 'value' => round($y1, 3)]);
            }

            // Y2: Temperatura
            if (isset($v[$ch2]) && isset($names[1])) {
                $result->push(['name' => $names[1], 'ts' => $ts, 'value' => $v[$ch2]]);
            }

            // Y3: Humedad relativa %
            if (isset($v[$ch3]) && isset($names[2])) {
                $result->push(['name' => $names[2], 'ts' => $ts, 'value' => $v[$ch3] * 100]);
            }

            // Y4: Presión atmosférica kPa
            if (isset($v[$ch4]) && isset($names[3])) {
                $result->push(['name' => $names[3], 'ts' => $ts, 'value' => $v[$ch4] * 100]);
            }

            // Y5: DPV
            if (isset($v[$ch1], $v[$ch2])) {
                $temp = $v[$ch2];

                $pvsat = (1 + sqrt(2) * sin(($temp * 3.1416) / (180 * 3)));
                $dpv   = (pow($pvsat, 8.827) * 0.6107) - $v[$ch1];

                $result->push(['name' => $names[4], 'ts' => $ts, 'value' => round($dpv, 3)]);
            }

            return $result;
        } catch (\Throwable $e) {
            \Log::error("Sensor TER11/12 error at key {$key}: " . $e->getMessage());
            return collect();
        }

    }

    private function handleAtm22(string $key, array $data, array $sensorKeys): Collection {
        try{
            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? null;

            $result = collect();

            $ch1 = $key . '1';
            $ch2 = $key . '2';
            $ch3 = $key . '3';
            $ch4 = $key . '4';

            // Y1: Velocidad viento
            if (isset($v[$ch1]) && isset($names[0])) {
                $y1 = ($v[$ch1] > 0) ? $v[$ch1] : 0;
                $result->push(['name' => $names[0], 'ts' => $ts, 'value' => $y1]);
            }

            // Y2: Dirección viento
            if (isset($v[$ch2]) && isset($names[1])) {
                $result->push(['name' => $names[1], 'ts' => $ts, 'value' => $v[$ch2]]);
            }

            // Y3: Ráfagas
            if (isset($v[$ch3]) && isset($names[2])) {
                $y3 = ($v[$ch3] > 0) ? $v[$ch3] : 0;
                $result->push(['name' => $names[2], 'ts' => $ts, 'value' => $y3]);
            }

            // Y4: Temp aire
            if (isset($v[$ch4]) && isset($names[3])) {
                $result->push(['name' => $names[3], 'ts' => $ts, 'value' => $v[$ch4]]);
            }

            return $result;
        } catch (\Throwable $e) {
            \Log::error("Sensor TER11/12 error at key {$key}: " . $e->getMessage());
            return collect();
        }


    }

    private function handleSr05(string $key, array $data, array $sensorKeys): Collection{
        try{
            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? null;

            $result = collect();
            $ch1 = $key . '1';

            if (isset($v[$ch1])) {
                $x = $v[$ch1];
                $y1 = ($x > 0) ? ((0.5 * $x) - 400.0) : 0;
                $result->push(['name' => $names[0], 'ts' => $ts, 'value' => round($y1, 3)]);
            }

            return $result;

        } catch (\Throwable $e) {
            \Log::error("Sensor TER11/12 error at key {$key}: " . $e->getMessage());
            return collect();
        }
    }

    private function handleWsrSdi(string $key, array $data, array $sensorKeys): Collection{
        try{
            $ts = $data['ts'];
            $v  = $data['values'];
            $names = $sensorKeys[$key] ?? null;

            if (!$names) return collect();

            $result = collect();

            for ($i = 1; $i <= 4; $i++) {
                $ch = $key . $i;
                $name = $names[$i - 1];

                if (!isset($v[$ch])) continue;

                $raw = $v[$ch];

                if ($raw === 'OPEN') {
                    $result->push(['name' => $name, 'ts' => $ts, 'value' => -100]);
                    continue;
                }

                // Temperatura (X1)
                if ($i === 1) {
                    $value = floatval(str_replace('F', '', $raw));
                    $tempC = ($value - 32) * 5 / 9;
                    $result->push(['name' => $name, 'ts' => $ts, 'value' => round($tempC, 2)]);
                    continue;
                }

                // Potencial matricial (X2..X4)
                $value = str_replace(['C', 'B'], '', $raw);
                $value = floatval($value) * -1; // siempre negativo

                $result->push(['name' => $name, 'ts' => $ts, 'value' => $value]);
            }

            return $result;
        } catch (\Throwable $e) {
            \Log::error("Sensor TER11/12 error at key {$key}: " . $e->getMessage());
            return collect();
        }

    }

}
