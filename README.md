# Roxy - Local Development Proxy

Roxy is our stand-alone proxy server. This tool allows you to use ReSRC when developing: locally (localhost),
on intranets or under any other scenario where your images are not publicly available online.

## Requirements

* PHP >= 5.4.0  
* [resrc.js](http://use.resrc.it/0.7) >= 0.7 (If using our responsive image JavaScript library)

## Getting Started

Roxy is designed to be run using PHP's
[built-in web server](http://php.net/manual/en/features.commandline.webserver.php).

Your local website itself is not dependent on php, however [php](http://php.net) is required to run Roxy on your [mac](http://www.mamp.info) or [pc](http://www.wampserver.com).

The quickest possible start is to clone the repository and then simply run:

`./start "your-api-key"` 

from within the root of the project directory. By default this will listen on all interfaces on port 8001,
but these can be overridden individually:

`HOST=localhost PORT=1234 ./start "your-api-key"`

Your api token **must** be provided and must be valid. This is available from your domain dashboard at: [https://my.resrc.it](https://my.resrc.it).

## Examples:

* [Basic](https://github.com/resrcit/roxy/blob/master/examples/example.html)

## History

For a full list of releases and changes please see the [CHANGELOG](https://github.com/resrcit/roxy/blob/master/CHANGELOG.md).

## Contributing

Please see the [CONTRIBUTING](https://github.com/resrcit/roxy/blob/master/CONTRIBUTING.md) file for guidelines.

## Contact

Please get in touch via: [EMAIL](mailto:support@resrc.it).

## Team

Roxy was built for **free** by the incredibly talented [Nick Payne](https://github.com/makeusabrew). 
We thank him dearly for all his hard work and commitment :)

## License

Copyright (C) 2014 by [ReSRC LTD](http://www.resrc.it) - The MIT License (MIT)  
Please see [LICENSE](https://github.com/resrcit/roxy/blob/master/LICENSE).