forecast-io
===========

Forecast IO APIv2 Wrapper implementation: http://forecast.io/

# Composer Install
```json

{
  "require" : {
    "jpirkey/forecast-io-php": "1.0"
  }
}

```

# Usage
```php

$WeatherService = new \Forecast\Service('<api-key>');
var_dump($WeatherService->fetch(25.79036, -80.13623));


```


For response specifics, see [API Documentation](https://developer.forecast.io/docs/v2)
