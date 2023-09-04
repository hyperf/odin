<?php

namespace Hyperf\Odin\Action;


use GuzzleHttp\Client;
use Hyperf\Odin\Message\UserMessage;

class WeatherAction extends AbstractAction
{

    public string $name = 'Weather';
    public string $desc = '如果需要查询天气可以使用，格式: {"name": "Weather", "args": {"location": "string", "date": "string"}}，如果用户没有指定某一天，则代表为今天，location 必须为明确的真实存在的城市名称，不能是不具体的名称';

    public function handle(string $location, string $date = 'now'): string
    {
        // 根据 $data 转换为对应的日期
        $date = match ($date) {
            'now' => date('Y-m-d'),
            'tomorrow' => date('Y-m-d', strtotime('+1 day')),
            'yesterday' => date('Y-m-d', strtotime('-1 day')),
            default => date('Y-m-d', strtotime($date)),
        };

        $key = \Hyperf\Support\env('QWEATHER_API_KEY');
        // 根据 Location 转为 LocationID
        $path = 'https://geoapi.qweather.com/v2/city/lookup?key=' . $key . '&location=' . $location;
        $client = new Client();
        $response = $client->get($path);
        $content = json_decode($response->getBody()->getContents(), true);
        $locationId = $content['location'][0]['id'] ?? 0;
        $cityName = $content['location'][0]['name'] ?? '';
        if (! $locationId) {
            return false;
        }
        // 根据 LocationID 查询天气
        $path = 'https://devapi.qweather.com/v7/weather/3d?key=' . $key . '&location=' . $locationId;
        // 通过 Guzzle 发起 Get 请求
        $response = $client->get($path);
        $content = json_decode($response->getBody()->getContents(), true);
        $weatherForecast = [];
        foreach ($content['daily'] ?? [] as $item) {
            if ($item['fxDate'] === $date) {
                $newItem = [
                    '日期' => $item['fxDate'],
                    '日出' => $item['sunrise'],
                    '日落' => $item['sunset'],
                    '月升' => $item['moonrise'],
                    '月落' => $item['moonset'],
                    '月相' => $item['moonPhase'],
                    '最高温度' => $item['tempMax'] . '℃',
                    '最低温度' => $item['tempMin'] . '℃',
                    '白天天气' => $item['textDay'],
                    '晚上天气' => $item['textNight'],
                    '白天风向' => $item['windDirDay'],
                    '晚上风向' => $item['windDirNight'],
                    '白天风力' => $item['windScaleDay'],
                    '晚上风力' => $item['windScaleNight'],
                    '白天风速' => $item['windSpeedDay'],
                    '晚上风速' => $item['windSpeedNight'],
                    '相对湿度' => $item['humidity'],
                    '降水量' => $item['precip'],
                    '紫外线指数' => $item['uvIndex'],
                    '能见度' => $item['vis'],
                ];
                $itemLine = [];
                foreach ($newItem as $key => $value) {
                    $itemLine[] = $key . ': ' . $value;
                }
                $weatherForecast = implode(', ', $itemLine);
            }
        }
        $result = $weatherForecast . '，以上是' . $cityName . '的天气预报，今天是' . date('Y-m-d') . '。';
        return $this->getClient()->chat([
            'user' => new UserMessage(sprintf("你需要根据下面的信息简洁的返回对应的天气预报情况，直接简洁的返回核心指标即可，不需要返回所有数据，返回内容为一行一句话，%s", $result))
        ], 'gpt-3.5-turbo');
    }

}