# Piwik counter plugin

## Description

Display Hits/Visits on image

## FAQ

See http://xn--80aeqbhthr9b.com/en/others/piwik/10-piwik-graphical-counter.html

## Changelog
2.0.3
* Added custom template for 'visitors by countries'. NB! 'Live visitors counter' works only if custom template field for 'Visitors by countries' is empty.
* Fixed an error w/ undefined method Access::isSuperUser
* Fix for double slash in ajax url
* Added workaround for getallheaders() method if PHP running as CGI.
* Remove PIWIK_ENABLE_DISPATCH due to triggering an error while generating counter image.

2.0.2
* Fixed a bug where the URL with the image displayed via http, if you are using https(bug only in counters list).

2.0.1
* Fix for CORS (thanks for aureq for patch)
* Changing versioning according to requirements

2.0 Initial release

## Support

http://xn--80aeqbhthr9b.com/en/contact-form.html